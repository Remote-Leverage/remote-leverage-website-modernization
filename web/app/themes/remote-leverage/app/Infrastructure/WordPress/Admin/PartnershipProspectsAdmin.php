<?php

declare(strict_types=1);

namespace App\Infrastructure\WordPress\Admin;

use App\Domains\PartnerHub\Actions\RecordPartnershipProspectAction;
use App\Domains\PartnerHub\Export\PartnershipProspectCsv;
use App\Domains\PartnerHub\Models\PartnershipProspect;
use App\Domains\PartnerHub\Support\PartnershipProspectFilters;
use App\Domains\PartnerHub\Support\PartnershipProspectOptions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/**
 * Partners Hub → Prospects: every `/become-a-partner/` submission and every prospect the team
 * entered by hand, newest first.
 *
 * Under the `rl_partner` menu rather than under Leads or Referrers, because that is who these
 * people are about to become — the partnerships team opens the Partners Hub to work on partners,
 * and a prospect is the step before one. Filing them with leads is the mistake the whole domain
 * is built to avoid.
 *
 * What ReferralAdminDashboard's referrer screens do, adapted: search, the status tabs and a
 * filter per answer; a CSV export of what is on screen; an Add form for someone who emailed or
 * was met at an event; the status from each row; and delete behind a confirmation. Each row
 * opens the prospect's own screen, PartnershipProspectDetailAdmin, which holds everything the
 * list has no room for and the team's notes. Its delete control posts here through
 * renderDeleteForm(), so there is one delete handler; it saves status and notes together with
 * its own, since both belong to the one form the team edits there.
 *
 * Nothing reacts to a status change or a deletion — they are the team's record of a
 * conversation, not triggers. The only side effect anywhere on these screens is the one they
 * deliberately avoid: a hand-entered prospect is not announced (see
 * RecordPartnershipProspectAction).
 *
 * Every action follows the referral admin's idiom: `manage_options`, a nonce checked with
 * check_admin_referer(), then wp_safe_redirect() and exit, with form errors carried across the
 * redirect by PartnershipAdminChrome::flash(). The operations themselves are public methods that
 * take plain values, so they can be tested without superglobals.
 */
class PartnershipProspectsAdmin
{
    public const SLUG = 'rl-partnership-prospects';

    public const PARENT = PartnershipAdminChrome::PARENT;

    protected const PER_PAGE = 25;

    /** Characters of the message shown in the list; the detail screen has the rest. */
    protected const MESSAGE_PREVIEW = 180;

    /** The fields the Add form posts, in the order it asks for them. */
    protected const FORM_FIELDS = [
        'first_name', 'last_name', 'email', 'company', 'role',
        'organization_type', 'monthly_revenue', 'businesses_reached',
        'status', 'message', 'notes',
    ];

    public function register(): void
    {
        add_action('admin_menu', [$this, 'addMenuPage']);
        add_action('admin_init', [$this, 'handleActions']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueStyles']);
    }

    /**
     * The screen's URL. Also what the Slack card's button opens.
     *
     * @param  array<string, string|int>  $args
     */
    public static function url(array $args = []): string
    {
        return PartnershipAdminChrome::url(self::SLUG, $args);
    }

    /**
     * One prospect's own screen, or null while that screen is not installed.
     *
     * `$listArgs` is the list's position (search, filters, page), carried so the detail
     * screen's back link returns to the same rows rather than the top of the plain list.
     *
     * @param  array<string, int|string>  $listArgs
     */
    public static function detailUrl(int $id, array $listArgs = []): string
    {
        return PartnershipProspectDetailAdmin::url($id, $listArgs);
    }

    public function enqueueStyles(string $hook): void
    {
        if (str_contains($hook, self::SLUG)) {
            PartnershipAdminChrome::enqueue();
        }
    }

    public function addMenuPage(): void
    {
        add_submenu_page(
            parent_slug: PartnershipAdminChrome::PARENT,
            page_title: 'Partnership Prospects',
            menu_title: 'Prospects',
            capability: 'manage_options',
            menu_slug: self::SLUG,
            callback: [$this, 'render'],
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Requests
    |--------------------------------------------------------------------------
    */

    public function handleActions(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        // The export is a link, so it arrives as a GET; everything that changes a row is a POST.
        if (sanitize_key(wp_unslash($_GET['rl_prospect_action'] ?? '')) === 'export_csv') {
            check_admin_referer('rl_prospect_export');
            $this->streamCsv(PartnershipProspectFilters::fromQuery((array) wp_unslash($_GET)));
            exit;
        }

        match (sanitize_key(wp_unslash($_POST['rl_prospect_action'] ?? ''))) {
            'update_status' => $this->handleStatus(),
            'delete' => $this->handleDelete(),
            'create' => $this->handleCreate(),
            default => null,
        };
    }

    protected function handleStatus(): never
    {
        check_admin_referer('rl_prospect_status');

        $updated = $this->updateStatus(
            absint($_POST['prospect_id'] ?? 0),
            sanitize_key(wp_unslash($_POST['status'] ?? '')),
        );

        $this->redirect($this->returnUrl($updated ? ['updated' => 1] : []));
    }

    protected function handleDelete(): never
    {
        check_admin_referer('rl_prospect_delete');

        $deleted = $this->deleteProspect(absint($_POST['prospect_id'] ?? 0));

        // To the list even from the prospect's own screen: the row it showed has gone.
        $this->redirect($this->returnUrl(['deleted' => $deleted ? 1 : null]));
    }

    protected function handleCreate(): never
    {
        check_admin_referer('rl_prospect_create');

        $input = [];

        foreach (self::FORM_FIELDS as $field) {
            $input[$field] = (string) wp_unslash($_POST[$field] ?? '');
        }

        $result = $this->createProspect($input);

        if ($result['success']) {
            $this->redirect(self::url(['created' => (int) $result['prospect']->id]));
        }

        PartnershipAdminChrome::flash('prospect_form', ['errors' => $result['errors'], 'input' => $result['input']]);
        $this->redirect(self::url(['new' => 1]));
    }

    /**
     * Enter a prospect by hand. Same {success, errors, input} shape as
     * ReferralAdminDashboard::createReferrer(), with the errors keyed by field so the form can
     * put each one under the input it is about.
     *
     * @param  array<string, mixed>  $input
     * @return array{success: bool, errors: array<string, array<int, string>>, input: array<string, string>, prospect: ?PartnershipProspect}
     */
    public function createProspect(array $input): array
    {
        $clean = [];

        foreach (self::FORM_FIELDS as $field) {
            $value = $input[$field] ?? '';
            $clean[$field] = is_scalar($value) ? trim((string) $value) : '';
        }

        try {
            $prospect = app(RecordPartnershipProspectAction::class)->execute($clean, get_current_user_id() ?: null);
        } catch (ValidationException $e) {
            return ['success' => false, 'errors' => $e->errors(), 'input' => $clean, 'prospect' => null];
        }

        return ['success' => true, 'errors' => [], 'input' => $clean, 'prospect' => $prospect];
    }

    public function updateStatus(int $id, string $status): bool
    {
        $prospect = PartnershipProspect::query()->find($id);

        if (! $prospect || ! in_array($status, PartnershipProspect::STATUSES, true)) {
            return false;
        }

        $prospect->update(['status' => $status]);

        return true;
    }

    /**
     * Delete the row, which is all there is: a prospect has no children. Not the lead, referrer
     * or partner hub the same company may also have — those are separate records on purpose, and
     * a deleted prospect says nothing about them.
     */
    public function deleteProspect(int $id): bool
    {
        $prospect = PartnershipProspect::query()->find($id);

        return $prospect !== null && (bool) $prospect->delete();
    }

    /**
     * The list's query for a set of filters, newest first.
     *
     * @return Builder<PartnershipProspect>
     */
    public function query(PartnershipProspectFilters $filters): Builder
    {
        return $filters->apply(PartnershipProspect::query())->latest('id');
    }

    /**
     * Write the CSV for the filters on screen to a handle.
     *
     * The same filters as the list, so "Export" hands over what is being looked at — every row
     * when nothing is narrowed, which is ReferralAdminDashboard's export. The status tab counts
     * as a filter.
     *
     * @param  resource  $handle
     */
    public function exportCsv($handle, PartnershipProspectFilters $filters): int
    {
        return PartnershipProspectCsv::write($handle, $filters->apply(PartnershipProspect::query()));
    }

    protected function streamCsv(PartnershipProspectFilters $filters): void
    {
        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="'.PartnershipProspectCsv::filename().'"');
        header('X-Content-Type-Options: nosniff');

        $output = fopen('php://output', 'w');
        $this->exportCsv($output, $filters);
        fclose($output);
    }

    /**
     * Where a form posted from the list goes back to: the tab, filters and page it was on, so
     * working down a list does not jump to the top of "All" after every row.
     *
     * A status change made on the prospect's own screen goes back there instead: that screen
     * posts a `return_url`, honoured only when it is this admin's own detail URL for the same
     * prospect, so the field cannot be used to bounce anyone elsewhere.
     *
     * The page is carried as a query string in a hidden field and read back through
     * PartnershipProspectFilters, so only arguments the screen knows survive the round trip.
     *
     * @param  array<string, int|string|null>  $extra
     */
    protected function returnUrl(array $extra = []): string
    {
        parse_str((string) wp_unslash($_POST['return_query'] ?? ''), $query);

        $args = [
            ...PartnershipProspectFilters::fromQuery($query)->toQueryArgs(),
            'paged' => absint($query['paged'] ?? 0) ?: null,
            ...$extra,
        ];

        return self::url(array_filter($args, static fn ($value) => $value !== null && $value !== ''));
    }

    protected function redirect(string $url): never
    {
        wp_safe_redirect($url);
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Screens
    |--------------------------------------------------------------------------
    */

    public function render(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die('You do not have permission to view this page.');
        }

        $this->renderList();
    }

    /**
     * The one-line confirmations every action ends on, from the flag its redirect set.
     */
    protected function renderNotices(): void
    {
        if (! empty($_GET['updated'])) {
            echo '<div class="notice notice-success is-dismissible"><p>Status updated.</p></div>';
        }

        if (! empty($_GET['deleted'])) {
            echo '<div class="notice notice-success is-dismissible"><p>Prospect deleted.</p></div>';
        }

        $created = absint($_GET['created'] ?? 0);

        if ($created > 0) {
            $link = self::detailUrl($created);

            printf(
                '<div class="notice notice-success is-dismissible"><p>Prospect added as a manual entry. It was not announced in Slack.%s</p></div>',
                $link !== null ? ' <a href="'.esc_url($link).'">Open it</a>' : '',
            );
        }
    }

    protected function renderList(): void
    {
        $filters = PartnershipProspectFilters::fromQuery((array) wp_unslash($_GET));
        $page = max(1, absint($_GET['paged'] ?? 1));

        $query = $this->query($filters);
        $total = (clone $query)->count();
        $prospects = $query->forPage($page, self::PER_PAGE)->get();

        // Taken once, here, because reading a flash consumes it: the form needs the errors and
        // the "should I be open?" answer out of the same read.
        $formFlash = PartnershipAdminChrome::takeFlash('prospect_form');
        $showForm = ! empty($_GET['new']) || is_array($formFlash);

        $returnQuery = http_build_query(array_filter([...$filters->toQueryArgs(), 'paged' => $page > 1 ? $page : null]));

        echo '<div class="wrap rl-admin-wrap rl-partnerships">';
        echo '<div class="rl-admin-header rl-admin-header-split"><div>';
        echo '<h1 class="rl-admin-title">Partnership prospects</h1>';
        echo '<p class="rl-admin-subtitle">Companies that asked to become a partner on /become-a-partner/, and anyone the team '
            .'added by hand. Not leads: none of these reach HubSpot, the ad platforms or the sales channel.</p>';
        echo '</div><div class="rl-actions">';
        printf(
            '<a class="rl-btn rl-btn-outline" href="%s">%s Export CSV</a>',
            esc_url(wp_nonce_url(self::url([...$filters->toQueryArgs(), 'rl_prospect_action' => 'export_csv']), 'rl_prospect_export')),
            PartnershipAdminChrome::icon('download', ''),
        );
        printf(
            '<a class="rl-btn rl-btn-primary" href="%s">%s Add prospect</a>',
            esc_url(self::url(['new' => 1])),
            PartnershipAdminChrome::icon('plus', ''),
        );
        echo '</div></div>';

        PartnershipAdminChrome::nav(self::SLUG);
        $this->renderNotices();

        if ($showForm) {
            $this->renderCreateForm(
                is_array($formFlash) ? (array) ($formFlash['errors'] ?? []) : [],
                is_array($formFlash) ? (array) ($formFlash['input'] ?? []) : [],
            );
        }

        $this->renderTabs($filters);
        $this->renderFilterBar($filters, $prospects->count(), $total);

        if ($prospects->isEmpty()) {
            printf(
                '<div class="rl-table-container"><p class="rl-empty">%s</p></div></div>',
                esc_html($filters->toQueryArgs() !== [] ? 'No prospects match these filters.' : 'No prospects yet.'),
            );

            return;
        }

        echo '<div class="rl-table-container"><table class="rl-table"><thead><tr>';

        foreach (['Submitted', 'Prospect', 'Organization', 'Monthly revenue', 'Reach', 'Message', 'Call', 'Status', ''] as $heading) {
            printf(
                '<th scope="col">%s</th>',
                $heading === '' ? '<span class="screen-reader-text">Actions</span>' : esc_html($heading),
            );
        }

        echo '</tr></thead><tbody>';

        foreach ($prospects as $prospect) {
            $this->renderRow($prospect, $returnQuery);
        }

        echo '</tbody></table></div>';

        $this->renderPager($total, $page, $filters);

        echo '</div>';
    }

    /**
     * Status tabs, each counting against the other filters — the number a tab shows is the
     * number of rows clicking it would list.
     */
    protected function renderTabs(PartnershipProspectFilters $filters): void
    {
        $counts = $filters->without('status')->apply(PartnershipProspect::query())
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->all();

        $active = $filters->get('status');
        $tabs = ['' => 'All'] + array_combine(
            PartnershipProspect::STATUSES,
            array_map('ucfirst', PartnershipProspect::STATUSES),
        );

        echo '<nav class="rl-tabs" aria-label="Filter by status">';

        foreach ($tabs as $status => $label) {
            $count = $status === '' ? array_sum($counts) : (int) ($counts[$status] ?? 0);
            $target = $status === '' ? $filters->without('status') : $filters->with('status', $status);

            printf(
                '<a class="rl-tab%s" href="%s">%s<span class="rl-tab-count">%d</span></a>',
                $status === $active ? ' rl-tab-active' : '',
                esc_url(self::url($target->toQueryArgs())),
                esc_html($label),
                $count,
            );
        }

        echo '</nav>';
    }

    protected function renderFilterBar(PartnershipProspectFilters $filters, int $shown, int $total): void
    {
        echo '<div class="rl-filter-bar">';
        printf('<form method="get" action="%s" class="rl-filter-form" role="search">', esc_url(admin_url('edit.php')));
        echo '<input type="hidden" name="post_type" value="rl_partner">';
        printf('<input type="hidden" name="page" value="%s">', esc_attr(self::SLUG));

        if ($filters->get('status') !== '') {
            printf('<input type="hidden" name="status" value="%s">', esc_attr($filters->get('status')));
        }

        printf(
            '<label class="rl-search"><span class="screen-reader-text">Search prospects</span>%s'
            .'<input type="search" name="s" value="%s" placeholder="Search name, email or company" class="rl-input"></label>',
            PartnershipAdminChrome::icon('search', 'rl-search-icon'),
            esc_attr($filters->search),
        );

        $this->filterSelect($filters, 'organization_type', 'Any organization', PartnershipProspectOptions::ORGANIZATION_TYPES);
        $this->filterSelect($filters, 'monthly_revenue', 'Any revenue', PartnershipProspectOptions::MONTHLY_REVENUE);
        $this->filterSelect($filters, 'businesses_reached', 'Any reach', PartnershipProspectOptions::BUSINESSES_REACHED);
        $this->filterSelect($filters, 'source', 'Any source', PartnershipProspect::SOURCES);

        echo '<button type="submit" class="rl-btn rl-btn-primary">Filter</button>';

        if ($filters->isNarrowed()) {
            printf(
                '<a class="rl-btn rl-btn-outline" href="%s">Reset</a>',
                esc_url(self::url(array_filter(['status' => $filters->get('status')]))),
            );
        }

        echo '</form>';
        printf(
            '<span class="rl-count-badge">Showing <strong>%d</strong> of <strong>%d</strong></span>',
            $shown,
            $total,
        );
        echo '</div>';
    }

    /**
     * @param  array<string, string>  $options  slug => label
     */
    protected function filterSelect(PartnershipProspectFilters $filters, string $column, string $any, array $options): void
    {
        printf('<select name="%1$s" class="rl-select" aria-label="%2$s">', esc_attr($column), esc_attr($any));
        printf('<option value="">%s</option>', esc_html($any));

        foreach ($options as $slug => $label) {
            printf(
                '<option value="%s"%s>%s</option>',
                esc_attr($slug),
                $filters->get($column) === $slug ? ' selected' : '',
                esc_html($label),
            );
        }

        echo '</select>';
    }

    protected function renderRow(PartnershipProspect $prospect, string $returnQuery): void
    {
        parse_str($returnQuery, $listArgs);
        $detailUrl = self::detailUrl((int) $prospect->id, PartnershipProspectDetailAdmin::listArgs($listArgs));

        echo '<tr>';

        // Submitted, with where from underneath: the landing path and the campaign when tagged,
        // or the fact that nobody submitted anything.
        $origin = $prospect->isManual()
            ? '<span class="rl-badge">Manual entry</span>'
            : '<span class="rl-mono">'.esc_html(implode(' · ', array_filter([
                $prospect->landing_url ? (string) parse_url((string) $prospect->landing_url, PHP_URL_PATH) : '',
                trim(implode(' / ', array_filter([$prospect->utm_source, $prospect->utm_campaign]))),
            ]))).'</span>';

        printf('<td>%s<br>%s</td>', esc_html(PartnershipAdminChrome::when($prospect->created_at)), $origin);

        printf(
            '<td><div class="rl-contact"><div class="rl-avatar" aria-hidden="true">%s</div><div>'
            .'%s<br><span class="rl-muted">%s at %s</span><br>'
            .'<a class="rl-muted" href="%s">%s</a></div></div></td>',
            esc_html(PartnershipAdminChrome::initials($prospect->fullName(), (string) $prospect->email)),
            '<a class="rl-row-link" href="'.esc_url($detailUrl).'">'.esc_html($prospect->fullName()).'</a>',
            esc_html((string) $prospect->role),
            esc_html((string) $prospect->company),
            esc_url('mailto:'.$prospect->email),
            esc_html((string) $prospect->email),
        );

        foreach (['organization_type', 'monthly_revenue', 'businesses_reached'] as $field) {
            echo '<td>'.esc_html(PartnershipProspectOptions::label($field, $prospect->{$field})).'</td>';
        }

        $message = trim((string) $prospect->message);
        $preview = mb_strimwidth($message, 0, self::MESSAGE_PREVIEW, '…');

        echo '<td><div class="rl-message">'
            .($message !== '' ? esc_html($preview) : '<span class="rl-muted">&mdash;</span>')
            .'</div></td>';

        echo '<td>'.($prospect->hasBookedCall()
            ? '<span class="rl-badge rl-badge-ok">Booked</span><br><span class="rl-muted">'
                .esc_html(PartnershipAdminChrome::when($prospect->booked_at)).'</span>'
            : '<span class="rl-muted">&mdash;</span>').'</td>';

        echo '<td>';
        $this->renderStatusForm($prospect, $returnQuery, 'Save');
        echo '</td>';

        echo '<td><div class="rl-actions">';

        printf('<a class="rl-btn rl-btn-outline rl-btn-sm" href="%s">Open</a>', esc_url($detailUrl));

        self::renderDeleteForm($prospect, $returnQuery);
        echo '</div></td>';

        echo '</tr>';
    }

    /**
     * The status select and its button, posting to handleActions().
     */
    public static function renderStatusForm(PartnershipProspect $prospect, string $returnQuery = '', string $button = 'Save', string $buttonClass = 'rl-btn rl-btn-outline rl-btn-sm'): void
    {
        printf('<form method="post" action="%s" class="rl-row-form">', esc_url(self::url()));
        wp_nonce_field('rl_prospect_status');
        echo '<input type="hidden" name="rl_prospect_action" value="update_status">';
        printf('<input type="hidden" name="prospect_id" value="%d">', (int) $prospect->id);
        printf('<input type="hidden" name="return_query" value="%s">', esc_attr($returnQuery));

        echo PartnershipAdminChrome::statusSelect((string) $prospect->status, 'Status for '.$prospect->fullName());
        printf('<button type="submit" class="%s">%s</button></form>', esc_attr($buttonClass), esc_html($button));
    }

    /**
     * Delete, as a POST behind a confirmation that says what goes with the row.
     *
     * A form rather than ReferralAdminDashboard's nonce link, so the nonce stays out of the URL
     * and the access log and nothing that prefetches links can reach it. Public so the
     * prospect's own screen offers the same control; either way it lands back on the list.
     *
     * The question rides a data attribute, escaped once for HTML, and the handler reads it back
     * from the DOM. Interpolating a visitor-typed name into the handler's own JavaScript would
     * need it escaped for a JS string inside an HTML attribute, which is two escapings to get
     * right in the correct order for the one control that deletes things.
     */
    public static function renderDeleteForm(PartnershipProspect $prospect, string $returnQuery = '', string $label = 'Delete', string $buttonClass = 'rl-btn rl-btn-destructive rl-btn-sm'): void
    {
        $who = trim($prospect->fullName().($prospect->company ? ' at '.$prospect->company : ''));
        $confirm = 'Delete '.($who !== '' ? $who : 'this prospect').'? Their answers, attribution and the team\'s notes go with them, and this cannot be undone.';

        printf(
            '<form method="post" action="%s" class="rl-row-form" data-confirm="%s" onsubmit="return window.confirm(this.dataset.confirm);">',
            esc_url(self::url()),
            esc_attr($confirm),
        );
        wp_nonce_field('rl_prospect_delete');
        echo '<input type="hidden" name="rl_prospect_action" value="delete">';
        printf('<input type="hidden" name="prospect_id" value="%d">', (int) $prospect->id);
        printf('<input type="hidden" name="return_query" value="%s">', esc_attr($returnQuery));
        printf('<button type="submit" class="%s">%s</button></form>', esc_attr($buttonClass), esc_html($label));
    }

    protected function renderPager(int $total, int $page, PartnershipProspectFilters $filters): void
    {
        $pages = (int) ceil($total / self::PER_PAGE);

        if ($pages <= 1) {
            return;
        }

        echo '<nav class="rl-pager" aria-label="Pages">';

        for ($i = 1; $i <= $pages; $i++) {
            printf(
                '<a class="%s" href="%s"%s>%d</a>',
                $i === $page ? 'rl-btn rl-btn-primary rl-btn-sm' : 'rl-btn rl-btn-outline rl-btn-sm',
                esc_url(self::url([...$filters->toQueryArgs(), 'paged' => $i])),
                $i === $page ? ' aria-current="page"' : '',
                $i,
            );
        }

        echo '</nav>';
    }

    /**
     * The Add prospect form, shown above the list on ?new=1 and again after a rejected submit.
     *
     * Rendered inline rather than behind a toggle so it works without JavaScript, the way
     * ReferralAdminDashboard::renderReferrerForm() does: the header button is a plain link to
     * ?new=1, and a rejected submit comes back to the same URL with the typed values refilled.
     *
     * @param  array<string, array<int, string>>  $errors  field => messages
     * @param  array<string, string>  $values
     */
    protected function renderCreateForm(array $errors, array $values): void
    {
        echo '<div class="rl-card">';
        echo '<p class="rl-card-title">Add prospect</p>';
        echo '<p class="rl-card-sub">For a company that emailed the team, was met at an event or was introduced — anyone who did not come through the form.</p>';

        if ($errors !== []) {
            echo '<div class="notice notice-error inline"><p>The prospect was not added. Check the highlighted fields.</p></div>';
        }

        printf('<form method="post" action="%s">', esc_url(self::url()));
        wp_nonce_field('rl_prospect_create');
        echo '<input type="hidden" name="rl_prospect_action" value="create">';
        echo '<div class="rl-form-grid">';

        $this->textField('first_name', 'First name', $values, $errors);
        $this->textField('last_name', 'Last name', $values, $errors);
        $this->textField('email', 'Email', $values, $errors, 'email');
        $this->textField('company', 'Company', $values, $errors);
        $this->textField('role', 'Role', $values, $errors);

        $this->choiceField('organization_type', 'Organization type', PartnershipProspectOptions::ORGANIZATION_TYPES, $values, $errors);
        $this->choiceField('monthly_revenue', 'Monthly revenue', PartnershipProspectOptions::MONTHLY_REVENUE, $values, $errors);
        $this->choiceField('businesses_reached', 'Businesses reached', PartnershipProspectOptions::BUSINESSES_REACHED, $values, $errors);
        $this->choiceField(
            'status',
            'Status',
            array_combine(PartnershipProspect::STATUSES, array_map('ucfirst', PartnershipProspect::STATUSES)),
            [...$values, 'status' => ($values['status'] ?? '') ?: 'new'],
            $errors,
            required: false,
        );

        $this->textareaField('message', 'What they told us', 'Their own words, if you have them — the equivalent of the form\'s message.', $values, $errors);
        $this->textareaField('notes', 'Internal notes', 'Where you met them, who they spoke to, what happens next. Seen only on these screens and in the export.', $values, $errors);

        echo '</div>';
        echo '<p class="rl-hint" style="margin:0 0 14px">Saved as a <strong>manual entry</strong>. Nothing is posted to Slack, and the address '
            .'is not run through the form\'s email check, so make sure it is the right one. The required fields are the form\'s own.</p>';
        echo '<div class="rl-form-actions"><button type="submit" class="rl-btn rl-btn-primary">Add prospect</button>';
        printf('<a class="rl-btn rl-btn-outline" href="%s">Cancel</a></div>', esc_url(self::url()));
        echo '</form></div>';
    }

    /**
     * @param  array<string, string>  $values
     * @param  array<string, array<int, string>>  $errors
     */
    protected function textField(string $name, string $label, array $values, array $errors, string $type = 'text'): void
    {
        $this->fieldOpen($name, $label, true);
        printf(
            '<input id="rl-prospect-%1$s" class="rl-input" type="%2$s" name="%1$s" value="%3$s" required%4$s>',
            esc_attr($name),
            esc_attr($type),
            esc_attr((string) ($values[$name] ?? '')),
            isset($errors[$name]) ? ' aria-invalid="true"' : '',
        );
        $this->fieldClose($name, $errors);
    }

    /**
     * @param  array<string, string>  $options
     * @param  array<string, string>  $values
     * @param  array<string, array<int, string>>  $errors
     */
    protected function choiceField(string $name, string $label, array $options, array $values, array $errors, bool $required = true): void
    {
        $this->fieldOpen($name, $label, $required);
        printf(
            '<select id="rl-prospect-%1$s" class="rl-select" name="%1$s"%2$s%3$s>',
            esc_attr($name),
            $required ? ' required' : '',
            isset($errors[$name]) ? ' aria-invalid="true"' : '',
        );

        if ($required) {
            echo '<option value="">Select one</option>';
        }

        foreach ($options as $slug => $optionLabel) {
            printf(
                '<option value="%s"%s>%s</option>',
                esc_attr((string) $slug),
                ($values[$name] ?? '') === (string) $slug ? ' selected' : '',
                esc_html($optionLabel),
            );
        }

        echo '</select>';
        $this->fieldClose($name, $errors);
    }

    /**
     * @param  array<string, string>  $values
     * @param  array<string, array<int, string>>  $errors
     */
    protected function textareaField(string $name, string $label, string $hint, array $values, array $errors): void
    {
        echo '<div class="rl-field rl-field-wide">';
        printf('<label class="rl-label" for="rl-prospect-%s">%s</label>', esc_attr($name), esc_html($label));
        printf(
            '<textarea id="rl-prospect-%1$s" class="rl-textarea" name="%1$s" rows="4">%2$s</textarea>',
            esc_attr($name),
            esc_textarea((string) ($values[$name] ?? '')),
        );
        printf('<span class="rl-hint">%s</span>', esc_html($hint));
        $this->fieldClose($name, $errors);
    }

    protected function fieldOpen(string $name, string $label, bool $required): void
    {
        printf(
            '<div class="rl-field"><label class="rl-label" for="rl-prospect-%s">%s%s</label>',
            esc_attr($name),
            esc_html($label),
            $required ? ' <span class="rl-required" aria-hidden="true">*</span>' : '',
        );
    }

    /**
     * @param  array<string, array<int, string>>  $errors
     */
    protected function fieldClose(string $name, array $errors): void
    {
        foreach ((array) ($errors[$name] ?? []) as $message) {
            printf('<span class="rl-field-error">%s</span>', esc_html((string) $message));
        }

        echo '</div>';
    }
}
