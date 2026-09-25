<?php

declare(strict_types=1);

namespace App\Infrastructure\WordPress\Admin;

use App\Domains\PartnerHub\Actions\UpdatePartnershipProspectAction;
use App\Domains\PartnerHub\Models\PartnershipProspect;
use App\Domains\PartnerHub\Support\PartnershipProspectFilters;
use App\Domains\PartnerHub\Support\PartnershipProspectOptions;
use Illuminate\Validation\ValidationException;

/**
 * Partners Hub → Prospects → one prospect: everything the list has no room for, and the team's
 * notes.
 *
 * The list shows a row per prospect and a trimmed message. This is where the rest lives — the
 * whole message, where they came from, the call if they booked one — and the one thing the list
 * cannot hold at all, the notes the team keeps on the conversation. It is the partnerships
 * counterpart of the referral admin's per-row handling, laid out as a page rather than packed
 * into table cells because a prospect is read, not scanned.
 *
 * ## Why it is registered and then taken out of the menu
 *
 * A screen for one record belongs to its list, not beside it in the menu, so it is added under
 * the Partners Hub like the other partnership screens and then removed from the submenu (see
 * hideFromMenu() for when). An `add_submenu_page()` with an empty parent would hide it too, but
 * it would also move the page to `admin.php?page=…`, which PartnershipAdminChrome::url() does not
 * build, and close the Partners Hub menu while it is open. Registered this way the menu stays
 * open and highlights Prospects, which is where the team is.
 *
 * ## What posts where
 *
 * The status and the notes are one form, saved here through UpdatePartnershipProspectAction,
 * because they are one act: moving a conversation on is usually a status change and a line
 * saying why. Delete is the list's own control (PartnershipProspectsAdmin::renderDeleteForm())
 * and its handler, since a deleted prospect's screen has nothing left to return to.
 *
 * The list's search, filters and page ride along in this screen's query string (see url() and
 * listArgs()), so "Back to prospects" and every redirect after a save return to the same place
 * in the list.
 *
 * Security is the referral admin's: `manage_options` to see or change anything, a nonce per
 * prospect checked with check_admin_referer(), wp_safe_redirect() and exit after every save, and
 * rejected input carried across the redirect by PartnershipAdminChrome::flash() rather than in
 * the query string. Everything printed is escaped at the point it is printed.
 */
class PartnershipProspectDetailAdmin
{
    public const SLUG = 'rl-partnership-prospect';

    /** The POST field that marks this screen's form, apart from the list's `rl_prospect_action`. */
    protected const ACTION_FIELD = 'rl_prospect_detail_action';

    protected const NONCE = 'rl_prospect_detail_save_';

    protected const FLASH_NOTICE = 'prospect_detail_notice';

    protected const FLASH_FORM = 'prospect_detail_form';

    /** The attribution columns, in the order a campaign URL is read, and what each is called here. */
    protected const ATTRIBUTION = [
        'landing_url' => 'Landing page',
        'referrer_url' => 'Referrer',
        'utm_source' => 'UTM source',
        'utm_medium' => 'UTM medium',
        'utm_campaign' => 'UTM campaign',
        'utm_term' => 'UTM term',
        'utm_content' => 'UTM content',
    ];

    /** The three answers, by the names the Overview gives them. */
    protected const ANSWERS = [
        'organization_type' => 'Organization type',
        'monthly_revenue' => 'Monthly revenue',
        'businesses_reached' => 'Businesses reached',
    ];

    public function register(): void
    {
        add_action('admin_menu', [$this, 'addMenuPage']);
        add_action('admin_enqueue_scripts', [$this, 'hideFromMenu'], 1);
        add_filter('submenu_file', [$this, 'highlightProspects']);
        add_action('admin_init', [$this, 'handleActions']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueStyles']);
    }

    /**
     * One prospect's screen.
     *
     * @param  array<string, string|int>  $listArgs  The list's search, filters and page, carried
     *                                               through so "Back to prospects" returns there.
     *                                               See listArgs().
     */
    public static function url(int $id, array $listArgs = []): string
    {
        unset($listArgs['page'], $listArgs['prospect']);

        return PartnershipAdminChrome::url(self::SLUG, ['prospect' => $id, ...$listArgs]);
    }

    /**
     * The list's position — search, status tab, answer filters and page — out of a query string.
     *
     * Read through PartnershipProspectFilters, so only values the list itself would accept
     * survive, and anything else in the query (this screen's own `page` and `prospect`) drops
     * out. Unslash the query first; WordPress adds slashes to $_GET.
     *
     * @param  array<string, mixed>  $query
     * @return array<string, string|int>
     */
    public static function listArgs(array $query): array
    {
        $paged = is_scalar($query['paged'] ?? null) ? absint($query['paged']) : 0;

        return [
            ...PartnershipProspectFilters::fromQuery($query)->toQueryArgs(),
            ...($paged > 1 ? ['paged' => $paged] : []),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Registration
    |--------------------------------------------------------------------------
    */

    public function addMenuPage(): void
    {
        add_submenu_page(
            parent_slug: PartnershipAdminChrome::PARENT,
            page_title: 'Partnership Prospect',
            menu_title: 'Prospect',
            capability: 'manage_options',
            menu_slug: self::SLUG,
            callback: [$this, 'render'],
        );
    }

    /**
     * Take the screen out of the submenu, first thing on `admin_enqueue_scripts`.
     *
     * That is the one point that is late enough and early enough. By then WordPress has resolved
     * the page, checked the capability and read its title out of the submenu, all of which need
     * the entry to be there. The entry has to be gone before the command palette serialises the
     * menu into its "Go to" list at priority 10 of the same hook — otherwise Cmd+K offers
     * "Partners Hub > Prospect", a link with no prospect in it — and before the sidebar is drawn.
     * `admin_head`, the usual place for this, runs after the palette has already read it.
     */
    public function hideFromMenu(): void
    {
        remove_submenu_page(PartnershipAdminChrome::PARENT, self::SLUG);
    }

    /**
     * Mark Prospects as the current submenu item while a prospect is open, since this screen has
     * no item of its own to mark.
     */
    public function highlightProspects(mixed $submenuFile): mixed
    {
        return ($GLOBALS['plugin_page'] ?? null) === self::SLUG
            ? PartnershipProspectsAdmin::SLUG
            : $submenuFile;
    }

    /**
     * Only on this screen. Matched on the end of the hook name rather than with str_contains(),
     * the way the other partnership screens match theirs, because this slug is the list's slug
     * minus its final "s" and would otherwise load a second copy of the styles there.
     */
    public function enqueueStyles(string $hook): void
    {
        if (! str_ends_with($hook, '_page_'.self::SLUG)) {
            return;
        }

        PartnershipAdminChrome::enqueue();

        wp_add_inline_style('wp-admin', self::css());
    }

    public static function css(): string
    {
        return <<<'CSS'
        .rl-partnerships .rl-subhead { font-size: 13px; font-weight: 600; margin: 18px 0 8px; color: #09090b; }
        .rl-partnerships .rl-header-meta { margin: 2px 0 0; color: #71717a; font-size: 12px; }
        .rl-partnerships .rl-kv .rl-mono { font-size: 12px; }
        .rl-partnerships .rl-json {
            margin: 0; white-space: pre-wrap; overflow-wrap: anywhere; font-size: 11px; line-height: 1.5;
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace; color: #3f3f46;
            background: #fafafa; border: 1px solid #f4f4f5; border-radius: 6px; padding: 8px 10px;
        }
        CSS;
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

        if (sanitize_key(wp_unslash($_POST[self::ACTION_FIELD] ?? '')) !== 'save') {
            return;
        }

        $id = absint($_POST['prospect_id'] ?? 0);

        check_admin_referer(self::nonceAction($id));

        // The form posts to this screen's own URL, so the list's position is in the query string.
        wp_safe_redirect($this->save($id, [
            'status' => wp_unslash($_POST['status'] ?? ''),
            'notes' => wp_unslash($_POST['notes'] ?? ''),
        ], self::listArgs((array) wp_unslash($_GET))));
        exit;
    }

    /**
     * Save the status and notes, and say where to go next: back to the same prospect, carrying
     * the list's position, with either a confirmation or the errors and the typed values waiting
     * in a flash.
     *
     * Public, taking plain values and returning the URL rather than redirecting, so the save
     * path can be tested without superglobals or an exit — the same split the referral admin
     * makes between handleAdminActions() and createReferrer().
     *
     * A prospect deleted while its screen was open lands on "Prospect not found", which says
     * what happened; there is nothing left to save the notes onto.
     *
     * @param  array<string, mixed>  $input  `status` and `notes`, unslashed.
     * @param  array<string, string|int>  $listArgs
     */
    public function save(int $id, array $input, array $listArgs = []): string
    {
        $url = self::url($id, $listArgs);
        $prospect = $id > 0 ? PartnershipProspect::query()->find($id) : null;

        if ($prospect === null) {
            return $url;
        }

        try {
            $changed = app(UpdatePartnershipProspectAction::class)->execute($prospect, $input);
        } catch (ValidationException $e) {
            PartnershipAdminChrome::flash(self::FLASH_FORM, [
                'id' => $id,
                'errors' => $e->errors(),
                'input' => array_map(
                    static fn ($value) => is_scalar($value) ? (string) $value : '',
                    array_intersect_key($input, array_flip(UpdatePartnershipProspectAction::FIELDS)),
                ),
            ]);

            return $url;
        }

        PartnershipAdminChrome::flash(self::FLASH_NOTICE, ['id' => $id, 'message' => $this->savedMessage($prospect, $changed)]);

        return $url;
    }

    /**
     * What the confirmation says, so the team can tell a save that changed the status from one
     * that only touched the notes — and from one that changed nothing at all.
     *
     * @param  array<int, string>  $changed
     */
    protected function savedMessage(PartnershipProspect $prospect, array $changed): string
    {
        if ($changed === []) {
            return 'Nothing to save: the status and notes were already as shown.';
        }

        $parts = [];

        if (in_array('status', $changed, true)) {
            $parts[] = 'Status changed to '.ucfirst((string) $prospect->status).'.';
        }

        if (in_array('notes', $changed, true)) {
            $parts[] = $prospect->notes === null ? 'Notes cleared.' : 'Notes saved.';
        }

        return implode(' ', $parts);
    }

    protected static function nonceAction(int $id): string
    {
        return self::NONCE.$id;
    }

    /*
    |--------------------------------------------------------------------------
    | Screen
    |--------------------------------------------------------------------------
    */

    public function render(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die('You do not have permission to view this page.');
        }

        $query = (array) wp_unslash($_GET);
        $listArgs = self::listArgs($query);
        $id = is_scalar($query['prospect'] ?? null) ? absint($query['prospect']) : 0;
        $prospect = $id > 0 ? PartnershipProspect::query()->find($id) : null;

        // Taken before anything else can, because reading a flash consumes it — and used only if
        // it is about this prospect, not one open in another tab.
        $notice = $this->flashFor(self::FLASH_NOTICE, $id);
        $form = $this->flashFor(self::FLASH_FORM, $id);

        echo '<div class="wrap rl-admin-wrap rl-partnerships">';

        if ($prospect === null) {
            $this->renderNotFound($listArgs);
        } else {
            $this->renderProspect($prospect, $listArgs, $notice, $form);
        }

        echo '</div>';
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function flashFor(string $key, int $id): ?array
    {
        $value = PartnershipAdminChrome::takeFlash($key);

        return is_array($value) && (int) ($value['id'] ?? 0) === $id && $id > 0 ? $value : null;
    }

    /**
     * A link to a prospect that has been deleted, or a hand-edited URL. Said plainly, with the
     * way back, rather than an empty page or a fatal on a null model.
     *
     * @param  array<string, string|int>  $listArgs
     */
    protected function renderNotFound(array $listArgs): void
    {
        echo '<div class="rl-admin-header"><h1 class="rl-admin-title">Prospect not found</h1>'
            .'<p class="rl-admin-subtitle">There is no prospect with that ID.</p></div>';

        PartnershipAdminChrome::nav(PartnershipProspectsAdmin::SLUG);

        printf(
            '<div class="rl-card"><p class="rl-card-sub">It may have been deleted, or the link may be incomplete.</p>'
            .'<div class="rl-form-actions"><a class="rl-btn rl-btn-outline" href="%s">Back to prospects</a></div></div>',
            esc_url(PartnershipAdminChrome::url(PartnershipProspectsAdmin::SLUG, $listArgs)),
        );
    }

    /**
     * @param  array<string, string|int>  $listArgs
     * @param  array<string, mixed>|null  $notice  {id, message}
     * @param  array<string, mixed>|null  $form  {id, errors, input} from a rejected save
     */
    protected function renderProspect(PartnershipProspect $prospect, array $listArgs, ?array $notice, ?array $form): void
    {
        printf(
            '<p class="rl-back"><a href="%s">%s Back to prospects</a></p>',
            esc_url(PartnershipAdminChrome::url(PartnershipProspectsAdmin::SLUG, $listArgs)),
            PartnershipAdminChrome::icon('arrow-left', ''),
        );

        $this->renderHeader($prospect, $listArgs);

        PartnershipAdminChrome::nav(PartnershipProspectsAdmin::SLUG);

        if ($notice !== null) {
            printf('<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html((string) $notice['message']));
        }

        $errors = is_array($form['errors'] ?? null) ? $form['errors'] : [];

        if ($errors !== []) {
            printf(
                '<div class="notice notice-error"><p>Nothing was saved. %s</p></div>',
                esc_html(implode(' ', array_filter(array_map(
                    static fn ($messages) => (string) (((array) $messages)[0] ?? ''),
                    $errors,
                )))),
            );
        }

        echo '<div class="rl-detail-grid"><div>';
        $this->renderAnswers($prospect);
        $this->renderAttribution($prospect);
        echo '</div><div>';
        $this->renderFollowUp($prospect, $listArgs, $errors, is_array($form['input'] ?? null) ? $form['input'] : null);
        $this->renderBooking($prospect);
        echo '</div></div>';
    }

    /**
     * Who they are and how to reach them, with the status beside the name and when and how the
     * row arrived underneath: the four things anyone opening a prospect looks for first.
     *
     * @param  array<string, string|int>  $listArgs
     */
    protected function renderHeader(PartnershipProspect $prospect, array $listArgs): void
    {
        $name = $prospect->fullName() !== '' ? $prospect->fullName() : (string) $prospect->email;
        $email = (string) $prospect->email;
        $position = implode(' at ', array_filter([trim((string) $prospect->role), trim((string) $prospect->company)]));

        echo '<div class="rl-admin-header rl-admin-header-split"><div class="rl-contact">';

        printf(
            '<div class="rl-avatar rl-avatar-lg" aria-hidden="true">%s</div><div>',
            esc_html(PartnershipAdminChrome::initials($prospect->fullName(), $email)),
        );

        printf(
            '<div class="rl-title-row"><h1 class="rl-admin-title">%s</h1>%s</div>',
            esc_html($name),
            PartnershipAdminChrome::statusBadge((string) $prospect->status),
        );

        printf(
            '<p class="rl-admin-subtitle">%s%s<a href="%s">%s</a></p>',
            esc_html($position),
            $position !== '' ? ' &middot; ' : '',
            esc_url('mailto:'.$email),
            esc_html($email),
        );

        printf(
            '<p class="rl-header-meta">%s %s &middot; Source: %s</p>',
            $prospect->isManual() ? 'Added' : 'Submitted',
            esc_html(PartnershipAdminChrome::when($prospect->created_at)),
            esc_html($prospect->sourceLabel()),
        );

        echo '</div></div><div class="rl-actions">';

        printf(
            '<a class="rl-btn rl-btn-outline" href="%s">Email %s</a>',
            esc_url('mailto:'.$email),
            esc_html($prospect->first_name ?: 'them'),
        );

        PartnershipProspectsAdmin::renderDeleteForm($prospect, http_build_query($listArgs), 'Delete', 'rl-btn rl-btn-destructive');

        echo '</div></div>';
    }

    protected function renderAnswers(PartnershipProspect $prospect): void
    {
        echo '<div class="rl-card"><p class="rl-card-title">Their answers</p><table class="rl-kv"><tbody>';

        foreach (self::ANSWERS as $field => $label) {
            $answer = PartnershipProspectOptions::label($field, $prospect->{$field});

            $this->kvRow($label, $answer !== '' ? esc_html($answer) : '<span class="rl-muted">&mdash;</span>');
        }

        echo '</tbody></table>';

        $message = trim((string) $prospect->message);

        echo '<p class="rl-subhead">Message</p>';
        echo $message !== ''
            ? '<p class="rl-prose">'.esc_html($message).'</p>'
            : '<p class="rl-muted">No message.</p>';

        echo '</div>';
    }

    /**
     * Where they came from: the columns first, then whatever else was captured with the
     * submission. Only what is there — a prospect who arrived untagged has no UTM rows rather
     * than five empty ones.
     */
    protected function renderAttribution(PartnershipProspect $prospect): void
    {
        $rows = [];

        foreach (self::ATTRIBUTION as $column => $label) {
            $value = trim((string) $prospect->{$column});

            if ($value !== '') {
                $rows[$label] = str_ends_with($column, '_url') ? $this->link($value) : esc_html($value);
            }
        }

        $context = is_array($prospect->context) ? array_filter(
            $prospect->context,
            static fn ($value) => $value !== null && $value !== '' && $value !== [],
        ) : [];

        echo '<div class="rl-card"><p class="rl-card-title">Attribution</p>';

        if ($rows === [] && $context === []) {
            printf(
                '<p class="rl-muted">%s</p></div>',
                $prospect->isManual()
                    ? 'Entered by hand, so there is no landing page or campaign to show.'
                    : 'Nothing was captured about where they came from.',
            );

            return;
        }

        if ($rows !== []) {
            echo '<table class="rl-kv"><tbody>';

            foreach ($rows as $label => $html) {
                $this->kvRow($label, $html);
            }

            echo '</tbody></table>';
        }

        if ($context !== []) {
            echo '<p class="rl-subhead">Context</p><table class="rl-kv"><tbody>';

            foreach ($context as $key => $value) {
                $this->kvRow('<span class="rl-mono">'.esc_html((string) $key).'</span>', $this->contextValue((string) $key, $value), false);
            }

            echo '</tbody></table>';
        }

        echo '</div>';
    }

    /**
     * The status and the notes, one form. Refilled from the flash after a rejected save, so what
     * was typed is not lost to a notes limit.
     *
     * @param  array<string, string|int>  $listArgs
     * @param  array<string, mixed>  $errors  field => messages
     * @param  array<string, string>|null  $input  what was typed, when a save was rejected
     */
    protected function renderFollowUp(PartnershipProspect $prospect, array $listArgs, array $errors, ?array $input): void
    {
        // A refused status that is not on the list would leave no option selected, and the select
        // would then show the first one — "New" — as if that were saved. Show the stored one.
        $status = in_array($input['status'] ?? null, PartnershipProspect::STATUSES, true)
            ? (string) $input['status']
            : (string) $prospect->status;
        $notes = (string) ($input['notes'] ?? $prospect->notes);

        echo '<div class="rl-card"><p class="rl-card-title">Follow-up</p>'
            .'<p class="rl-card-sub">For the team only. Changing either notifies no one and is never sent to the prospect.</p>';

        printf('<form method="post" action="%s">', esc_url(self::url((int) $prospect->id, $listArgs)));
        wp_nonce_field(self::nonceAction((int) $prospect->id));
        printf('<input type="hidden" name="%s" value="save">', esc_attr(self::ACTION_FIELD));
        printf('<input type="hidden" name="prospect_id" value="%d">', (int) $prospect->id);

        // The select carries its own aria-label, so the visible label is not read out twice.
        echo '<div class="rl-field"><span class="rl-label" aria-hidden="true">Status</span>';
        echo PartnershipAdminChrome::statusSelect($status, 'Status for '.($prospect->fullName() ?: 'this prospect'));
        $this->fieldError($errors, 'status');
        echo '</div>';

        printf(
            '<div class="rl-field"><label class="rl-label" for="rl-prospect-notes">Internal notes</label>'
            .'<textarea id="rl-prospect-notes" class="rl-textarea" name="notes" rows="8">%s</textarea>',
            esc_textarea($notes),
        );
        $this->fieldError($errors, 'notes');
        echo '<span class="rl-hint">Who spoke to them, what was agreed, what happens next.</span></div>';

        echo '<div class="rl-form-actions"><button type="submit" class="rl-btn rl-btn-primary">Save</button></div>';

        if ($prospect->updated_at !== null) {
            printf('<span class="rl-hint">Last updated %s.</span>', esc_html(PartnershipAdminChrome::when($prospect->updated_at)));
        }

        echo '</form></div>';
    }

    /**
     * The partnership call. `booked_at` is when they booked it, reported by the Calendly embed on
     * the form; the call's own time is in Calendly, which the event URI identifies. The URIs are
     * printed rather than linked because they are API addresses, which open to a 401.
     */
    protected function renderBooking(PartnershipProspect $prospect): void
    {
        echo '<div class="rl-card"><p class="rl-card-title">Partnership call</p>';

        if (! $prospect->hasBookedCall()) {
            echo '<p class="rl-muted">No call booked.</p></div>';

            return;
        }

        echo '<table class="rl-kv"><tbody>';
        $this->kvRow('Booked', esc_html(PartnershipAdminChrome::when($prospect->booked_at)));

        foreach (['calendly_event_uri' => 'Calendly event', 'calendly_invitee_uri' => 'Calendly invitee'] as $column => $label) {
            $uri = trim((string) $prospect->{$column});

            if ($uri !== '') {
                $this->kvRow($label, '<span class="rl-mono">'.esc_html($uri).'</span>');
            }
        }

        echo '</tbody></table></div>';
    }

    /*
    |--------------------------------------------------------------------------
    | Pieces
    |--------------------------------------------------------------------------
    */

    /**
     * One key-value row. `$html` is markup the caller has already escaped. `$label` is plain text
     * and escaped here, unless `$escapeLabel` is false because the caller built it as markup.
     */
    protected function kvRow(string $label, string $html, bool $escapeLabel = true): void
    {
        printf('<tr><th scope="row">%s</th><td>%s</td></tr>', $escapeLabel ? esc_html($label) : $label, $html);
    }

    /**
     * A captured URL, linked when it is a web address and printed as text when it is anything
     * else — `javascript:` included — so a crafted referrer can never become a live link.
     */
    protected function link(string $url): string
    {
        if (preg_match('#^https?://#i', $url) !== 1) {
            return esc_html($url);
        }

        return sprintf(
            '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
            esc_url($url),
            esc_html($url),
        );
    }

    /**
     * One `context` value, readably: text as text, a nested list (AttributionCollector's
     * `unmapped`, say) as indented JSON, and the admin who entered a manual row by name.
     */
    protected function contextValue(string $key, mixed $value): string
    {
        if ($key === 'created_by' && is_numeric($value)) {
            $user = function_exists('get_userdata') ? get_userdata((int) $value) : false;

            return esc_html($user ? $user->display_name.' (user #'.(int) $value.')' : 'User #'.(int) $value);
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_array($value) || is_object($value)) {
            $json = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

            return '<pre class="rl-json">'.esc_html((string) $json).'</pre>';
        }

        return esc_html((string) $value);
    }

    /**
     * @param  array<string, mixed>  $errors
     */
    protected function fieldError(array $errors, string $field): void
    {
        $message = ((array) ($errors[$field] ?? []))[0] ?? '';

        if ($message !== '') {
            printf('<span class="rl-field-error">%s</span>', esc_html((string) $message));
        }
    }
}
