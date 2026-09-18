<?php

declare(strict_types=1);

use App\Domains\Lead\Models\Lead;
use App\Infrastructure\WordPress\Admin\LeadsAdminDashboard;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/*
 * The leads list: what order it is in, and whether you can leave page one.
 *
 * Both of these were broken by something that looks right in isolation. Ordering by `id` reads
 * as "newest first" until the Gravity Forms import lands 3,900 historical rows with today's
 * auto-increment ids and 2023 timestamps. And Laravel's paginator defaults its cursor to
 * `page`, which inside wp-admin is the screen slug — so the screen read "rl-leads" as the page
 * number, fell back to 1 forever, and linked Next at `admin.php?page=2`, a menu slug that does
 * not exist.
 */

/** Reach a protected query helper on the dashboard, the way the screen calls it. */
function leadDashboardHelper(string $method): callable
{
    $dashboard = new LeadsAdminDashboard;
    $reflected = new ReflectionMethod($dashboard, $method);
    $reflected->setAccessible(true);

    return fn (...$args) => $reflected->invoke($dashboard, ...$args);
}

/** @return callable(Builder, int, string, array): LengthAwarePaginator */
function paginateLeadScreen(): callable
{
    $dashboard = new LeadsAdminDashboard;
    $method = new ReflectionMethod($dashboard, 'paginateScreen');
    $method->setAccessible(true);

    return fn ($query, int $perPage, string $screen, array $filters = []) => $method->invoke($dashboard, $query, $perPage, $screen, $filters);
}

function listingLead(string $email, string $createdAt): Lead
{
    $lead = Lead::create([
        'uuid' => 'listing-'.$email,
        'name' => 'Listing '.$email,
        'email' => $email,
        'status' => 'captured',
        'source_type' => 'organic',
    ]);

    // Written after the fact: `created_at` is filled by the model's own timestamps on insert,
    // which is exactly how the import ends up with an old date on a high id.
    Lead::query()->whereKey($lead->id)->update(['created_at' => $createdAt]);

    return $lead->refresh();
}

beforeEach(function () {
    Lead::truncate();
    $_GET = [];
});

afterEach(function () {
    $_GET = [];
});

test('the list is ordered by submission date, not by insertion id', function () {
    // Insertion order deliberately disagrees with submission order, the way an import leaves it.
    listingLead('backfill@example.com', '2023-04-02 09:00:00');
    listingLead('today@example.com', '2026-09-17 20:48:40');
    listingLead('older-backfill@example.com', '2022-11-30 12:00:00');

    $ordered = Lead::query()->latest('created_at')->latest('id')->pluck('email')->all();

    expect($ordered)->toBe([
        'today@example.com',
        'backfill@example.com',
        'older-backfill@example.com',
    ]);
});

test('the paginator reads its cursor from paged, not from the wp-admin screen slug', function () {
    foreach (range(1, 5) as $n) {
        listingLead("lead{$n}@example.com", sprintf('2026-09-%02d 10:00:00', $n));
    }

    $_GET = ['page' => 'rl-leads', 'paged' => '2'];

    $leads = paginateLeadScreen()(Lead::query()->latest('created_at')->latest('id'), 2, 'rl-leads');

    // Before the fix this was 1: "rl-leads" is not a number, so resolveCurrentPage() gave up.
    expect($leads->currentPage())->toBe(2)
        ->and($leads->lastPage())->toBe(3)
        ->and($leads->pluck('email')->all())->toBe(['lead3@example.com', 'lead2@example.com']);
});

test('page links keep the screen slug and the active filters', function () {
    foreach (range(1, 5) as $n) {
        listingLead("filtered{$n}@example.com", sprintf('2026-09-%02d 10:00:00', $n));
    }

    $_GET = ['page' => 'rl-leads', 'paged' => '2'];

    $leads = paginateLeadScreen()(
        Lead::query()->latest('created_at')->latest('id'),
        2,
        'rl-leads',
        ['s' => 'filtered', 'status' => 'captured', 'mrr' => '', 'date_range' => ''],
    );

    $next = $leads->nextPageUrl();

    expect($next)->toContain('page=rl-leads')
        ->and($next)->toContain('paged=3')
        ->and($next)->toContain('s=filtered')
        ->and($next)->toContain('status=captured')
        // Empty toolbar slots stay out of the URL rather than pinning a blank filter.
        ->and($next)->not->toContain('mrr=')
        ->and($next)->not->toContain('date_range=')
        // The old bug in one assertion: a link that replaces the menu slug with a page number.
        ->and($next)->not->toContain('page=3');
});

/*
 * The date window.
 *
 * "Today" was `whereDate('created_at', Carbon::today())` — a calendar day resolved in the app
 * timezone. PHP runs in UTC here and WordPress has no timezone set, so from 8pm Eastern onward
 * Carbon::today() was already tomorrow and the filter returned nothing while the table beside it
 * still printed today's date on 96 rows. These pin the rolling window that replaced it, which has
 * no midnight to fall off.
 */
test('the 24 hour window holds at the moment UTC rolls over to a new day', function () {
    // Six minutes past midnight UTC: exactly when the old calendar-day filter went empty.
    Carbon::setTestNow(Carbon::parse('2026-09-18 00:06:00'));

    listingLead('yesterday-evening@example.com', '2026-09-17 20:48:40');
    listingLead('this-morning@example.com', '2026-09-17 09:00:00');
    listingLead('two-days-ago@example.com', '2026-09-16 09:00:00');

    $query = Lead::query();
    leadDashboardHelper('applyDateRange')($query, '24h');

    expect($query->pluck('email')->all())->toBe([
        'yesterday-evening@example.com',
        'this-morning@example.com',
    ]);

    Carbon::setTestNow();
});

test('the slug the window used to ship under still selects it', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-18 00:06:00'));

    listingLead('recent@example.com', '2026-09-17 20:48:40');
    listingLead('stale@example.com', '2026-09-16 09:00:00');

    // An old bookmark carries date_range=today; it must not silently widen to the whole table.
    $query = Lead::query();
    leadDashboardHelper('applyDateRange')($query, 'today');

    expect($query->pluck('email')->all())->toBe(['recent@example.com']);

    Carbon::setTestNow();
});

test('the wider windows are rolling too, and an empty range filters nothing', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-18 00:06:00'));

    listingLead('day@example.com', '2026-09-17 20:00:00');
    listingLead('week@example.com', '2026-09-13 20:00:00');
    listingLead('month@example.com', '2026-08-30 20:00:00');
    listingLead('ancient@example.com', '2023-04-02 09:00:00');

    foreach ([['24h', 1], ['week', 2], ['month', 3], ['', 4], ['nonsense', 4]] as [$range, $expected]) {
        $query = Lead::query();
        leadDashboardHelper('applyDateRange')($query, $range);

        expect($query->count())->toBe($expected);
    }

    Carbon::setTestNow();
});
