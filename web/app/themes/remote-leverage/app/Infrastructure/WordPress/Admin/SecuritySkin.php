<?php

declare(strict_types=1);

namespace App\Infrastructure\WordPress\Admin;

/**
 * Styles for the Security screens, in two halves.
 *
 * `components()` are ours: the `.rl-sec-*` primitives the Security overview and the dashboard
 * widget are built from. They extend `AdminDesignSystem`'s vocabulary rather than replacing
 * it, and they are self-scoped — each class carries its own box — so the same markup renders
 * identically inside `.rl-admin-wrap` and inside a `.postbox` on the dashboard, which have
 * very different ambient styles.
 *
 * `wordfenceSkin()` is a reskin of somebody else's UI. WordFence's Firewall, Scan, Blocking,
 * Tools and Login Security screens are a Vue application over a Bootstrap-derived `wf-*` grid,
 * and they keep working — we are not reimplementing scan triage or rule toggling, because
 * owning those flows means re-testing them on every WordFence release. What the skin does is
 * remap the design tokens: WordFence's teal/orange/blue palette, 4px radii, uppercase buttons
 * and blue-tinted shadows become the same zinc surfaces, 6-12px radii and neutral type as the
 * rest of the console.
 *
 * It is scoped to `.rl-security-skin`, a body class `SecurityAdmin` adds only on those
 * screens. WordFence's own stylesheet is loaded on the same page and is specific, so nearly
 * everything here needs `!important` — that is a property of what is being overridden, not
 * carelessness.
 *
 * The skin is intentionally structural rather than surgical. A WordFence upgrade that renames
 * a component leaves that component looking like stock WordFence inside an otherwise themed
 * page — visibly odd, but never broken, and never a fatal.
 */
class SecuritySkin
{
    /**
     * The `.rl-sec-*` primitives shared by the overview screen and the dashboard widget.
     */
    public static function components(): string
    {
        return <<<'CSS'
        /* --- Section heading ------------------------------------------------ */
        .rl-sec-section { margin: 0 0 10px; }
        .rl-sec-section-title {
            font-size: 12px; font-weight: 600; letter-spacing: .04em; text-transform: uppercase;
            color: #71717a; margin: 0 0 10px; padding: 0;
        }

        /* --- Status tiles ---------------------------------------------------- */
        .rl-sec-grid { display: grid; gap: 12px; margin: 0 0 16px; }
        .rl-sec-grid-2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .rl-sec-grid-3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .rl-sec-grid-4 { grid-template-columns: repeat(4, minmax(0, 1fr)); }
        @media (max-width: 1100px) {
            .rl-sec-grid-4 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 782px) {
            .rl-sec-grid-2, .rl-sec-grid-3, .rl-sec-grid-4 { grid-template-columns: minmax(0, 1fr); }
        }

        .rl-sec-tile {
            background: #fff; border: 1px solid #e4e4e7; border-radius: 12px;
            padding: 14px 16px; display: flex; flex-direction: column; gap: 8px;
            box-shadow: 0 1px 2px rgba(0,0,0,.04); min-width: 0;
        }
        .rl-sec-tile-head {
            display: flex; align-items: center; justify-content: space-between; gap: 8px;
        }
        .rl-sec-tile-label {
            font-size: 11px; font-weight: 600; letter-spacing: .04em; text-transform: uppercase;
            color: #71717a; margin: 0; line-height: 1.4;
        }
        .rl-sec-tile-value {
            font-size: 24px; font-weight: 700; letter-spacing: -0.03em; color: #09090b;
            line-height: 1.1; margin: 0; font-variant-numeric: tabular-nums;
        }
        .rl-sec-tile-value-sm { font-size: 17px; letter-spacing: -0.02em; }
        .rl-sec-tile-meta {
            font-size: 12px; color: #71717a; line-height: 1.45; margin: 0;
            overflow: hidden; text-overflow: ellipsis;
        }

        /* --- Status dot ------------------------------------------------------ */
        .rl-sec-dot {
            width: 8px; height: 8px; border-radius: 9999px; display: inline-block;
            flex: 0 0 auto; background: #a1a1aa;
        }
        .rl-sec-dot-ok { background: #10b981; }
        .rl-sec-dot-warn { background: #f59e0b; }
        .rl-sec-dot-bad { background: #ef4444; }
        .rl-sec-dot-idle { background: #a1a1aa; }
        .rl-sec-dot-live { box-shadow: 0 0 0 3px rgba(16,185,129,.18); }

        .rl-sec-status { display: inline-flex; align-items: center; gap: 7px; font-size: 13px; font-weight: 500; }

        /* --- Meter ------------------------------------------------------------ */
        .rl-sec-meter {
            height: 6px; border-radius: 9999px; background: #f4f4f5; overflow: hidden;
            display: flex; width: 100%;
        }
        .rl-sec-meter-fill { height: 100%; border-radius: 9999px; background: #10b981; transition: width .3s ease; }
        .rl-sec-meter-fill-warn { background: #f59e0b; }
        .rl-sec-meter-fill-bad { background: #ef4444; }

        /* --- Segmented bar (block types) -------------------------------------- */
        .rl-sec-split { height: 8px; border-radius: 9999px; background: #f4f4f5; overflow: hidden; display: flex; }
        .rl-sec-split span { height: 100%; display: block; }
        .rl-sec-split-complex { background: #18181b; }
        .rl-sec-split-brute { background: #6366f1; }
        .rl-sec-split-blocklist { background: #a1a1aa; }
        .rl-sec-legend { display: flex; flex-wrap: wrap; gap: 4px 14px; margin: 8px 0 0; padding: 0; list-style: none; }
        .rl-sec-legend li {
            display: inline-flex; align-items: center; gap: 6px; font-size: 12px; color: #71717a; margin: 0;
        }
        .rl-sec-legend i {
            width: 8px; height: 8px; border-radius: 2px; display: inline-block; flex: 0 0 auto; font-style: normal;
        }
        .rl-sec-legend b { color: #09090b; font-weight: 600; font-variant-numeric: tabular-nums; }

        /* --- Compact data list -------------------------------------------------- */
        .rl-sec-list { list-style: none; margin: 0; padding: 0; }
        .rl-sec-list li {
            display: flex; align-items: center; gap: 10px; padding: 8px 0;
            border-bottom: 1px solid #f4f4f5; font-size: 13px; margin: 0;
        }
        .rl-sec-list li:last-child { border-bottom: 0; padding-bottom: 0; }
        .rl-sec-list li:first-child { padding-top: 0; }
        .rl-sec-list-main { flex: 1 1 auto; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .rl-sec-list-aside { color: #71717a; font-size: 12px; flex: 0 0 auto; }
        .rl-sec-list-count {
            font-variant-numeric: tabular-nums; font-weight: 600; color: #09090b;
            flex: 0 0 auto; font-size: 13px;
        }
        .rl-sec-mono {
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 12px; color: #09090b;
        }

        /* --- Key/value rows ------------------------------------------------------ */
        .rl-sec-kv { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; padding: 7px 0; font-size: 13px; }
        .rl-sec-kv + .rl-sec-kv { border-top: 1px solid #f4f4f5; }
        .rl-sec-kv dt { color: #71717a; margin: 0; }
        .rl-sec-kv dd { margin: 0; font-weight: 500; color: #09090b; text-align: right; }

        /* --- Empty state ----------------------------------------------------------- */
        .rl-sec-empty {
            display: flex; align-items: center; gap: 8px; color: #71717a; font-size: 13px;
            padding: 14px 0; line-height: 1.5;
        }

        /* --- Callout --------------------------------------------------------------- */
        .rl-sec-callout {
            display: flex; gap: 10px; align-items: flex-start; border-radius: 10px;
            padding: 11px 13px; font-size: 13px; line-height: 1.5; margin: 0 0 14px;
            border: 1px solid #e4e4e7; background: #fafafa; color: #3f3f46;
        }
        .rl-sec-callout-warn { border-color: #fde68a; background: #fffbeb; color: #92400e; }
        .rl-sec-callout-bad { border-color: #fecaca; background: #fef2f2; color: #991b1b; }
        .rl-sec-callout strong { font-weight: 600; }
        .rl-sec-callout p { margin: 0; }

        /* --- Toolbar ---------------------------------------------------------------- */
        .rl-sec-toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin: 0 0 18px; }
        .rl-sec-toolbar-spacer { flex: 1 1 auto; }

        /* --- Button text inside the dashboard ---------------------------------------------
           `WordPressAdminTheme`'s link reset paints `#wpbody-content a`, `#dashboard-widgets a`
           and `.postbox a` at `!important`, and one id outranks any single-class rule — a dark
           button renders as near-black text on a near-black fill until a selector with an id
           says otherwise. `AdminDesignSystem` already carries those rules for `.rl-btn`, but
           scoped to `.rl-admin-wrap`, and the dashboard widget is not inside one. These cover
           the widget's contexts only, so the button colours still have a single home. */
        #dashboard-widgets a.rl-btn-primary,
        .postbox a.rl-btn-primary { color: #fafafa !important; text-decoration: none !important; }
        #dashboard-widgets a.rl-btn-outline,
        .postbox a.rl-btn-outline { color: #09090b !important; text-decoration: none !important; }
        #dashboard-widgets a.rl-btn-destructive,
        .postbox a.rl-btn-destructive { color: #ef4444 !important; text-decoration: none !important; }

        /* The tile link is ours, so it needs the same treatment in both contexts. */
        #wpbody-content a.rl-sec-tile-link,
        #dashboard-widgets a.rl-sec-tile-link,
        .postbox a.rl-sec-tile-link {
            color: #71717a !important; text-decoration: none !important;
        }
        #wpbody-content a.rl-sec-tile-link:hover,
        #dashboard-widgets a.rl-sec-tile-link:hover,
        .postbox a.rl-sec-tile-link:hover { color: #09090b !important; }

        /* --- Card actions -------------------------------------------------------------
           Every card ends in the place you would go to act on what it just told you. The
           divider keeps the buttons from reading as part of the data above them. */
        .rl-sec-actions {
            display: flex; flex-wrap: wrap; align-items: center; gap: 8px;
            margin: 14px -20px -18px; padding: 12px 20px; border-top: 1px solid #f4f4f5;
            background: #fafafa; border-radius: 0 0 12px 12px;
        }
        .rl-sec-actions-note { font-size: 12px; color: #a1a1aa; margin: 0 0 0 auto; text-align: right; }
        .rl-sec-tile .rl-sec-actions {
            margin: 8px -16px -14px; padding: 9px 16px; background: transparent; border-top-color: #f4f4f5;
        }
        .rl-sec-tile-link {
            font-size: 12px; font-weight: 500; color: #71717a !important; text-decoration: none !important;
            display: inline-flex; align-items: center; gap: 4px;
        }
        .rl-sec-tile-link:hover { color: #09090b !important; }
        .rl-sec-tile-link:after { content: "\2192"; font-size: 12px; line-height: 1; }

        /* --- Injected section header ----------------------------------------------------
           Goes on WordFence's own screens, where we do not control the page markup. The
           width matches `.wf-container-fluid` (1130px box, 15px gutter) so the header lines
           up with the cards underneath rather than floating over them.

           The top spacing is PADDING, not margin, and that is not cosmetic. `in_admin_header`
           fires inside `#wpcontent` but before `#wpbody` — so this element is the first child
           of a container with no top padding, and a `margin-top` collapses straight out
           through `#wpcontent` and `#wpwrap`, pushing the entire admin layout (sidebar
           included) down by that amount. It shows up as an unexplained band under the admin
           bar on exactly the screens this header renders on. */
        .rl-security-header {
            max-width: 1130px; margin: 0 auto; padding: 22px 15px 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }
        .rl-security-header .rl-admin-title { margin: 0; }
        .rl-security-header .rl-admin-subtitle { margin: 4px 0 0; }
        .rl-security-header .rl-tabs { margin: 14px 0 0; }

        /* AdminDesignSystem colours `.rl-tab` only inside `.rl-admin-wrap`, and the injected
           header is not inside one — without these the console's `#wpbody-content a` reset
           paints the tabs as dark underlined links. */
        #wpbody-content .rl-security-header a.rl-tab {
            color: #71717a !important; text-decoration: none !important;
        }
        #wpbody-content .rl-security-header a.rl-tab:hover { color: #09090b !important; }
        #wpbody-content .rl-security-header a.rl-tab-active { color: #09090b !important; }

        /* --- Two-column body --------------------------------------------------------- */
        .rl-sec-cols { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px 20px; align-items: start; }
        @media (max-width: 1100px) { .rl-sec-cols { grid-template-columns: minmax(0, 1fr); } }
        .rl-sec-col { min-width: 0; }

        .rl-sec-footer {
            display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between;
            gap: 10px; margin: 16px 0 0; padding: 13px 0 0; border-top: 1px solid #f4f4f5;
        }
        .rl-sec-footer span { font-size: 12px; color: #a1a1aa; }

        /* --- Dashboard widget shell --------------------------------------------------
           The widget renders inside a .postbox, which brings its own padding and link
           colours. Reset those so the primitives above land on the same surface they do
           on the Security screen. */
        #rl_dashboard_security .inside { margin: 0 !important; padding: 16px 18px 18px !important; }
        #rl_dashboard_security .rl-sec-grid { margin-bottom: 14px; }
        /* WordPress may place this widget in either dashboard column, and the narrow one is
           about 400px. Sizing by content rather than by a viewport media query lets the tiles
           fall to 2x2 there and stay 4-across in the wide column. */
        #rl_dashboard_security .rl-sec-grid-4 {
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        }
        #rl_dashboard_security .rl-sec-tile { padding: 12px 13px; gap: 6px; }
        #rl_dashboard_security .rl-sec-tile-value { font-size: 20px; }
        #rl_dashboard_security a.rl-btn { box-shadow: none; }
        CSS;
    }

    /**
     * Remap WordFence's own screens onto the console's palette and geometry.
     */
    public static function wordfenceSkin(): string
    {
        return <<<'CSS'
        /* ==========================================================================
           WORDFENCE SCREEN RESKIN  —  scoped to body.rl-security-skin
           ========================================================================== */

        .rl-security-skin #wpbody-content { padding-bottom: 60px; }
        .rl-security-skin .wrap.wordfence {
            max-width: 1130px !important; margin: 0 auto !important; padding: 0 !important;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif !important;
            color: #09090b !important;
        }
        /* The 15px gutter stays. WordFence's grid pairs it with `.wf-row { margin: 0 -15px }`,
           so removing it pushes every row 30px wider than its container and clips the right
           edge of every card. 1130 - 30 = the same 1100px content column the rest of the
           console uses. */
        .rl-security-skin .wf-container-fluid {
            max-width: 1130px !important; margin: 0 auto !important;
            padding-left: 15px !important; padding-right: 15px !important;
            color: #09090b !important;
        }
        .rl-security-skin .wordfence,
        .rl-security-skin .wordfence * { font-family: inherit !important; }

        /* --- Page title ----------------------------------------------------------- */
        .rl-security-skin .wf-section-title { margin: 22px 0 18px !important; align-items: center !important; }
        .rl-security-skin .wf-section-title h2 {
            font-size: 24px !important; font-weight: 700 !important; letter-spacing: -0.025em !important;
            color: #09090b !important; margin: 0 !important; padding: 0 !important; line-height: 1.25 !important;
        }
        .rl-security-skin .wf-section-title-subtitle,
        .rl-security-skin .wf-section-title p { color: #71717a !important; font-size: 13px !important; }
        /* The Wordfence padlock beside every page title is branding, not information. */
        .rl-security-skin .wordfence-lock-icon,
        .rl-security-skin .wordfence-icon32,
        .rl-security-skin .wf-section-title .wordfence-logo,
        .rl-security-skin #wf-gdpr-wrapper { display: none !important; }
        .rl-security-skin .wf-section-title-help-link,
        .rl-security-skin .wf-section-title a { color: #71717a !important; font-size: 12px !important; font-weight: 500 !important; }
        .rl-security-skin .wf-section-title a:hover { color: #09090b !important; }
        .rl-security-skin .wf-options-icon { fill: #71717a !important; width: 14px !important; height: 14px !important; }

        /* --- Cards ----------------------------------------------------------------- */
        .rl-security-skin .wf-block:not(.wf-block-transparent),
        .rl-security-skin .wf-card,
        .rl-security-skin .wf-dashboard-item,
        .rl-security-skin .wf-scanner-progress {
            background: #fff !important;
            border: 1px solid #e4e4e7 !important;
            border-radius: 12px !important;
            box-shadow: 0 1px 2px rgba(0,0,0,.04) !important;
            margin: 0 0 14px !important;
            padding: 0 !important;
        }
        .rl-security-skin .wf-block-header,
        .rl-security-skin .wf-dashboard-item-title {
            padding: 14px 18px !important; border-bottom: 1px solid #f4f4f5 !important;
            background: #fff !important; margin: 0 !important;
        }
        .rl-security-skin .wf-block-header h2,
        .rl-security-skin .wf-block-header h3,
        .rl-security-skin .wf-block-title,
        .rl-security-skin .wf-block-header-content .wf-block-title {
            font-size: 15px !important; font-weight: 600 !important; letter-spacing: -0.01em !important;
            color: #09090b !important; margin: 0 !important; text-transform: none !important;
        }
        /* `.wf-block-content` carries `margin: 0 -1.5rem`, which exists to cancel the
           `padding: 0 1.5rem` WordFence puts on `.wf-block`. We replace that padding with our
           own on the content, so the negative margin has nothing left to cancel and instead
           hangs 24px past each edge of the card — visible as rules and dividers running wider
           than the white surface they belong to. */
        .rl-security-skin .wf-block-content,
        .rl-security-skin .wf-dashboard-item-content {
            padding: 16px 18px !important; margin-left: 0 !important; margin-right: 0 !important;
        }
        /* `.wf-block-transparent` is WordFence's unstyled container — it holds the
           Save/Cancel control row. Given the card treatment it renders as a white panel
           floating in the middle of the options list. */
        .rl-security-skin .wf-block.wf-block-transparent {
            background: transparent !important; border: 0 !important;
            box-shadow: none !important; padding: 0 !important;
        }
        .rl-security-skin .wf-block.wf-block-transparent > .wf-block-content {
            padding-left: 0 !important; padding-right: 0 !important;
        }
        .rl-security-skin .wf-block-footer { padding: 12px 18px !important; border-top: 1px solid #f4f4f5 !important; background: #fafafa !important; }
        .rl-security-skin .wf-block.wf-active > .wf-block-header { border-bottom: 1px solid #f4f4f5 !important; }

        .rl-security-skin .wf-block-labeled-value-label { color: #71717a !important; font-size: 12px !important; text-transform: uppercase !important; letter-spacing: .04em !important; font-weight: 600 !important; }
        .rl-security-skin .wf-block-labeled-value-value { color: #09090b !important; font-size: 15px !important; font-weight: 600 !important; }

        /* --- Buttons ---------------------------------------------------------------- */
        .rl-security-skin .wf-btn,
        .rl-security-skin a.wf-btn,
        .rl-security-skin button.wf-btn {
            text-transform: none !important; border-radius: 6px !important;
            font-size: 13px !important; font-weight: 500 !important; line-height: 1.4 !important;
            padding: 7px 14px !important; letter-spacing: 0 !important;
            border: 1px solid transparent !important; box-shadow: 0 1px 2px rgba(0,0,0,.04) !important;
            transition: background-color .15s ease, border-color .15s ease, color .15s ease !important;
            text-shadow: none !important; background-image: none !important; height: auto !important;
        }
        /* These selectors are deliberately heavy, because they have to win twice.
           WordFence's licence-tier stylesheet doubles the variant class to raise specificity
           — `a.wf-btn.wf-btn-primary.wf-btn-primary { background-color: #1b719e !important }`
           — and loads after our inline styles, so a class-only rule ties on !important and
           loses on source order. Meanwhile the console's own link reset in
           `WordPressAdminTheme` is `#wpbody-content a { color: #09090b !important }`, and one
           id outranks any number of classes, so a class-only rule cannot set button text
           either: the buttons came out dark-on-dark. Naming `#wpbody-content` plus the
           element and both classes clears both. */
        .rl-security-skin #wpbody-content a.wf-btn.wf-btn-default,
        .rl-security-skin #wpbody-content button.wf-btn.wf-btn-default,
        .rl-security-skin #wpbody-content a.wf-btn.wf-btn-callout-subtle,
        .rl-security-skin #wpbody-content a.wf-btn.wf-btn-light {
            background-color: #fff !important; color: #09090b !important; border-color: #e4e4e7 !important;
        }
        .rl-security-skin #wpbody-content a.wf-btn.wf-btn-default:hover,
        .rl-security-skin #wpbody-content button.wf-btn.wf-btn-default:hover,
        .rl-security-skin #wpbody-content a.wf-btn.wf-btn-light:hover {
            background-color: #f4f4f5 !important; border-color: #d4d4d8 !important;
        }

        .rl-security-skin #wpbody-content a.wf-btn.wf-btn-primary,
        .rl-security-skin #wpbody-content button.wf-btn.wf-btn-primary,
        .rl-security-skin #wpbody-content a.wf-btn.wf-btn-success,
        .rl-security-skin #wpbody-content button.wf-btn.wf-btn-success,
        .rl-security-skin #wpbody-content a.wf-btn.wf-btn-info,
        .rl-security-skin #wpbody-content button.wf-btn.wf-btn-info,
        .rl-security-skin #wpbody-content a.wf-btn.wf-btn-callout {
            background-color: #18181b !important; color: #fafafa !important; border-color: #18181b !important;
        }
        .rl-security-skin #wpbody-content a.wf-btn.wf-btn-primary:hover,
        .rl-security-skin #wpbody-content button.wf-btn.wf-btn-primary:hover,
        .rl-security-skin #wpbody-content a.wf-btn.wf-btn-success:hover,
        .rl-security-skin #wpbody-content button.wf-btn.wf-btn-success:hover,
        .rl-security-skin #wpbody-content a.wf-btn.wf-btn-info:hover,
        .rl-security-skin #wpbody-content button.wf-btn.wf-btn-info:hover,
        .rl-security-skin #wpbody-content a.wf-btn.wf-btn-callout:hover {
            background-color: #27272a !important; border-color: #27272a !important;
        }

        .rl-security-skin #wpbody-content a.wf-btn.wf-btn-danger,
        .rl-security-skin #wpbody-content button.wf-btn.wf-btn-danger {
            background-color: #fff !important; color: #ef4444 !important; border-color: #fecaca !important;
        }
        .rl-security-skin #wpbody-content a.wf-btn.wf-btn-danger:hover,
        .rl-security-skin #wpbody-content button.wf-btn.wf-btn-danger:hover {
            background-color: #fef2f2 !important; border-color: #f87171 !important; color: #dc2626 !important;
        }

        .rl-security-skin #wpbody-content a.wf-btn.wf-btn-warning,
        .rl-security-skin #wpbody-content button.wf-btn.wf-btn-warning {
            background-color: #fff !important; color: #a16207 !important; border-color: #fde68a !important;
        }
        .rl-security-skin #wpbody-content a.wf-btn.wf-btn-warning:hover,
        .rl-security-skin #wpbody-content button.wf-btn.wf-btn-warning:hover {
            background-color: #fffbeb !important; border-color: #fcd34d !important;
        }

        .rl-security-skin .wf-btn-link { background: none !important; border: 0 !important; box-shadow: none !important; color: #09090b !important; text-decoration: underline !important; }
        .rl-security-skin .wf-btn-sm, .rl-security-skin .wf-btn-xs { padding: 5px 10px !important; font-size: 12px !important; }
        .rl-security-skin .wf-btn.wf-disabled, .rl-security-skin .wf-btn[disabled] { opacity: .5 !important; box-shadow: none !important; }
        .rl-security-skin .wf-btn-group > .wf-btn { border-radius: 0 !important; }
        .rl-security-skin .wf-btn-group > .wf-btn:first-child { border-radius: 6px 0 0 6px !important; }
        .rl-security-skin .wf-btn-group > .wf-btn:last-child { border-radius: 0 6px 6px 0 !important; }

        /* --- Tables -------------------------------------------------------------------- */
        .rl-security-skin .wf-table,
        .rl-security-skin .wf-striped-table {
            border-collapse: collapse !important; font-size: 13px !important;
            border: 1px solid #e4e4e7 !important; border-radius: 10px !important; overflow: hidden !important;
        }
        .rl-security-skin .wf-table th,
        .rl-security-skin .wf-striped-table th {
            text-align: left !important; font-size: 11px !important; text-transform: uppercase !important;
            letter-spacing: .04em !important; color: #71717a !important; font-weight: 600 !important;
            padding: 10px 14px !important; background: #fafafa !important;
            border-bottom: 1px solid #e4e4e7 !important; border-top: 0 !important;
        }
        .rl-security-skin .wf-table td,
        .rl-security-skin .wf-striped-table td {
            padding: 11px 14px !important; border-bottom: 1px solid #f4f4f5 !important;
            border-top: 0 !important; color: #09090b !important; vertical-align: middle !important;
        }
        .rl-security-skin .wf-table tr:last-child td { border-bottom: 0 !important; }
        .rl-security-skin .wf-table-hover tbody tr:hover > td { background: #fafafa !important; }
        .rl-security-skin .wf-striped-table tbody tr:nth-of-type(odd) { background: #fafafa !important; }

        /* --- Forms --------------------------------------------------------------------- */
        .rl-security-skin .wf-form-control,
        .rl-security-skin .wf-block input[type="text"],
        .rl-security-skin .wf-block input[type="number"],
        .rl-security-skin .wf-block input[type="email"],
        .rl-security-skin .wf-block input[type="password"],
        .rl-security-skin .wf-block input[type="search"],
        .rl-security-skin .wf-block select,
        .rl-security-skin .wf-block textarea {
            border: 1px solid #e4e4e7 !important; border-radius: 6px !important;
            padding: 7px 11px !important; font-size: 13px !important; color: #09090b !important;
            background: #fff !important; box-shadow: none !important; min-height: 36px !important;
            line-height: 1.4 !important;
        }
        .rl-security-skin .wf-form-control:focus,
        .rl-security-skin .wf-block input:focus,
        .rl-security-skin .wf-block select:focus,
        .rl-security-skin .wf-block textarea:focus {
            border-color: #a1a1aa !important; outline: 2px solid rgba(24,24,27,.08) !important; box-shadow: none !important;
        }
        .rl-security-skin .wf-form-field-label,
        .rl-security-skin .wf-option-title {
            font-size: 13px !important; font-weight: 600 !important; color: #09090b !important;
        }
        .rl-security-skin .wf-option-subtitle,
        .rl-security-skin .wf-option-text,
        .rl-security-skin .wf-form-field-help { font-size: 12px !important; color: #71717a !important; line-height: 1.5 !important; }
        .rl-security-skin .wf-option {
            border-bottom: 1px solid #f4f4f5 !important; padding: 14px 0 !important; background: transparent !important;
        }
        .rl-security-skin .wf-option:last-child { border-bottom: 0 !important; }

        /* Toggles pick up the accent green used by the rest of the console. */
        .rl-security-skin .wf-boolean-switch.wf-active,
        .rl-security-skin .wf-boolean-switch-on,
        .rl-security-skin .wf-option-toggled-segmented .wf-active { background-color: #10b981 !important; border-color: #10b981 !important; }
        .rl-security-skin .wf-boolean-switch { border-radius: 9999px !important; }

        /* --- Tabs and pills ----------------------------------------------------------------
           `:not(.wf-visible-xs)` is load-bearing. WordFence's responsive utilities hide the
           mobile-only nav with `.wf-visible-xs { display: none !important }`; a bare
           `.wf-nav-pills { display: inline-flex !important }` ties that on !important and
           wins on source order, which put a stray "Go to" dropdown on every desktop Tools
           screen. Anything WordFence's grid has hidden stays hidden. */
        .rl-security-skin .wf-nav-tabs:not(.wf-visible-xs):not(.wf-hidden-xs),
        .rl-security-skin .wf-nav-pills:not(.wf-visible-xs):not(.wf-hidden-xs) {
            display: inline-flex !important; height: 38px !important; align-items: center !important;
            border-radius: 8px !important; background: #f4f4f5 !important; padding: 3px !important;
            gap: 2px !important; border: 1px solid #e4e4e7 !important; margin: 0 0 18px !important;
        }
        .rl-security-skin .wf-visible-xs { display: none !important; }
        .rl-security-skin .wf-nav-tabs > li > a,
        .rl-security-skin .wf-nav-pills > li > a {
            border: 0 !important; border-radius: 6px !important; padding: 6px 14px !important;
            font-size: 13px !important; font-weight: 500 !important; color: #71717a !important;
            background: transparent !important; margin: 0 !important; text-transform: none !important;
        }
        .rl-security-skin .wf-nav-tabs > li.wf-active > a,
        .rl-security-skin .wf-nav-pills > li.wf-active > a {
            background: #fff !important; color: #09090b !important; font-weight: 600 !important;
            box-shadow: 0 1px 2px rgba(0,0,0,.06) !important;
        }

        /* --- WordFence's own sub-tab row -----------------------------------------------------
           Tools and Firewall carry a second level of navigation (`ul.wf-page-fixed-tabs` /
           `.wf-page-tabs` in a `.wf-tab-container` that collapses to zero height, so the row
           overhangs the content). Styled as an underlined secondary row rather than a second
           set of pills, so it reads as subordinate to the Security tabs injected above it. */
        .rl-security-skin .wf-tab-container {
            max-width: 1130px !important; margin: 0 auto !important;
            padding: 0 15px !important; height: auto !important;
        }
        .rl-security-skin .wf-tab-container > .wf-col-xs-12 { padding: 0 !important; }
        .rl-security-skin .wf-page-fixed-tabs,
        .rl-security-skin .wf-page-tabs {
            display: flex !important; gap: 2px !important; align-items: flex-end !important;
            margin: 6px 0 0 !important; padding: 0 !important; list-style: none !important;
            border-bottom: 1px solid #e4e4e7 !important; background: transparent !important;
            position: static !important; width: 100% !important;
        }
        .rl-security-skin .wf-page-fixed-tabs > li.wf-tab,
        .rl-security-skin .wf-page-tabs > li.wf-tab {
            margin: 0 !important; border: 0 !important; background: transparent !important;
            box-shadow: none !important; border-radius: 0 !important;
        }
        .rl-security-skin .wf-page-fixed-tabs > li.wf-tab > a,
        .rl-security-skin .wf-page-tabs > li.wf-tab > a {
            display: block !important; padding: 9px 14px !important; font-size: 13px !important;
            font-weight: 500 !important; color: #71717a !important; background: transparent !important;
            border: 0 !important; border-bottom: 2px solid transparent !important;
            border-radius: 0 !important; text-decoration: none !important; margin-bottom: -1px !important;
        }
        .rl-security-skin .wf-page-fixed-tabs > li.wf-tab > a:hover,
        .rl-security-skin .wf-page-tabs > li.wf-tab > a:hover { color: #09090b !important; }
        .rl-security-skin .wf-page-fixed-tabs > li.wf-tab.wf-active,
        .rl-security-skin .wf-page-tabs > li.wf-tab.wf-active { background: transparent !important; }
        .rl-security-skin .wf-page-fixed-tabs > li.wf-tab.wf-active > a,
        .rl-security-skin .wf-page-tabs > li.wf-tab.wf-active > a {
            color: #09090b !important; font-weight: 600 !important;
            border-bottom-color: #18181b !important; background: transparent !important;
        }

        /* --- Scan progress and issues --------------------------------------------------------- */
        .rl-security-skin .wf-scan-step { border-bottom: 1px solid #f4f4f5 !important; padding: 10px 0 !important; }
        .rl-security-skin .wf-scan-step:last-child { border-bottom: 0 !important; }
        .rl-security-skin .wf-issue {
            border: 1px solid #e4e4e7 !important; border-radius: 10px !important;
            margin: 0 0 10px !important; background: #fff !important; box-shadow: none !important;
        }
        .rl-security-skin .wf-issue-severity-critical { border-left: 3px solid #ef4444 !important; }
        .rl-security-skin .wf-issue-severity-warning { border-left: 3px solid #f59e0b !important; }
        .rl-security-skin .wf-issue-title { font-size: 14px !important; font-weight: 600 !important; color: #09090b !important; }
        .rl-security-skin .wf-issue-detail, .rl-security-skin .wf-issue-short-stats { font-size: 12px !important; color: #71717a !important; }

        /* --- Modals and drawers ----------------------------------------------------------------- */
        .rl-security-skin .wf-modal-content,
        .rl-security-skin .wf-drawer {
            border-radius: 12px !important; border: 1px solid #e4e4e7 !important;
            box-shadow: 0 10px 30px rgba(0,0,0,.12) !important; background: #fff !important;
        }
        .rl-security-skin .wf-modal-header { border-bottom: 1px solid #f4f4f5 !important; padding: 16px 20px !important; }
        .rl-security-skin .wf-modal-title { font-size: 16px !important; font-weight: 600 !important; letter-spacing: -0.01em !important; }
        .rl-security-skin .wf-modal-footer { border-top: 1px solid #f4f4f5 !important; padding: 14px 20px !important; background: #fafafa !important; }

        /* --- Notices ------------------------------------------------------------------------------ */
        .rl-security-skin .wf-admin-notice,
        .rl-security-skin .wf-notice {
            border-radius: 10px !important; border: 1px solid #e4e4e7 !important; background: #fafafa !important;
            padding: 11px 14px !important; font-size: 13px !important; color: #3f3f46 !important;
            box-shadow: none !important; border-left-width: 1px !important;
        }
        .rl-security-skin .wf-admin-notice-error { border-color: #fecaca !important; background: #fef2f2 !important; color: #991b1b !important; }
        .rl-security-skin .wf-admin-notice-warning { border-color: #fde68a !important; background: #fffbeb !important; color: #92400e !important; }
        .rl-security-skin .wf-admin-notice-success { border-color: #bbf7d0 !important; background: #f0fdf4 !important; color: #166534 !important; }

        /* --- Links and residual brand colour ---------------------------------------------------------
           WordFence paints links, headings and status text in its teal (#16bc9b), blue (#00709e)
           and amber (#fcb214). Left alone these read as a second design system inside the page. */
        .rl-security-skin .wordfence a,
        .rl-security-skin .wf-block a:not(.wf-btn) {
            color: #09090b !important; text-decoration: underline !important;
            text-underline-offset: 2px !important; text-decoration-color: #d4d4d8 !important;
        }
        .rl-security-skin .wordfence a:hover,
        .rl-security-skin .wf-block a:not(.wf-btn):hover { color: #18181b !important; text-decoration-color: #09090b !important; }
        .rl-security-skin .wf-status-good, .rl-security-skin .wf-text-success { color: #15803d !important; }
        .rl-security-skin .wf-status-bad, .rl-security-skin .wf-text-danger { color: #b91c1c !important; }
        .rl-security-skin .wf-status-warning, .rl-security-skin .wf-text-warning { color: #a16207 !important; }

        /* --- Status gauges ---------------------------------------------------------------------------
           The donuts on the Firewall and Scan pages are SVG paths whose colour WordFence sets
           as a `stroke` presentation attribute. A CSS `stroke` beats a presentation attribute,
           so these remap by value rather than flattening every ring to one colour — a gauge
           that is green because it is healthy stays green, it just stops being WordFence teal.
           A hex not listed here keeps WordFence's own colour, which is the right way for this
           to age. */
        .rl-security-skin .wf-status-circular-inactive-path { stroke: #f4f4f5 !important; }
        .rl-security-skin [stroke="#16bc9b"],
        .rl-security-skin [stroke="#11967a"] { stroke: #10b981 !important; }
        .rl-security-skin [stroke="#fcb214"],
        .rl-security-skin [stroke="#ffd10a"] { stroke: #f59e0b !important; }
        .rl-security-skin [stroke="#c0392b"],
        .rl-security-skin [stroke="#c10000"],
        .rl-security-skin [stroke="#930000"],
        .rl-security-skin [stroke="#d1584b"] { stroke: #ef4444 !important; }
        .rl-security-skin .wf-status-circular-text {
            font-family: inherit !important; color: #09090b !important;
            font-size: 18px !important; font-weight: 600 !important; letter-spacing: -0.02em !important;
        }

        /* --- Page tabs and inline toggles ------------------------------------------------------------- */
        .rl-security-skin .wf-page-tabs .wf-tab.wf-active,
        .rl-security-skin .wf-page-tabs .wf-tab:hover,
        .rl-security-skin .wf-scan-tabs .wf-tab.wf-active,
        .rl-security-skin .wf-scan-tabs .wf-tab:hover {
            background: #f4f4f5 !important; border-bottom-color: #f4f4f5 !important; color: #09090b !important;
        }
        .rl-security-skin .wf-tab.wf-active > a,
        .rl-security-skin .wf-tab.wf-active { color: #09090b !important; }
        .rl-security-skin #wpbody-content a.wf-dashboard-graph-attacks,
        .rl-security-skin #wpbody-content a.wf-dashboard-login-attempts {
            background-color: #18181b !important; color: #fafafa !important; border-color: #18181b !important;
            border-radius: 6px !important; text-transform: none !important;
        }
        .rl-security-skin .wf-fa-lightbulb-o.wf-tip,
        .rl-security-skin .wf-tip { color: #a1a1aa !important; }

        /* Segmented controls and checkboxes paint their selected state in the brand blue.
           These are the last two surfaces on the Audit Log screen. */
        .rl-security-skin .wf-option-segments li.wf-active,
        .rl-security-skin .wf-btn-group li.wf-active,
        .rl-security-skin ul.wf-option-segments > li.wf-active {
            background-color: #18181b !important; border-color: #18181b !important; color: #fafafa !important;
        }
        .rl-security-skin .wf-option-segments li.wf-active a,
        .rl-security-skin ul.wf-option-segments > li.wf-active a { color: #fafafa !important; }
        .rl-security-skin .wf-option-checkbox.wf-checked,
        .rl-security-skin .wfls-option-checkbox.wfls-checked {
            background-color: #18181b !important; border-color: #18181b !important;
        }
        /* WordFence marks the selected item of several inline pickers with a bare
           `li.wf-active` and no component class. `:not(.wf-tab)` keeps the page tab rows on
           their underline treatment above rather than turning them into dark pills. */
        .rl-security-skin li.wf-active:not(.wf-tab) {
            background-color: #18181b !important; border-color: #18181b !important; color: #fafafa !important;
        }
        .rl-security-skin li.wf-active:not(.wf-tab) > a { color: #fafafa !important; }

        /* --- Upsell and telemetry surfaces ------------------------------------------------------------
           These are wordfence.com marketing inside our admin: the premium banner on every page,
           the "upgrade" callouts, and the satisfaction prompt. Removed rather than restyled. */
        .rl-security-skin .wf-premium-callout,
        .rl-security-skin .wf-upgrade-callout,
        .rl-security-skin .wf-block-premium-upsell,
        .rl-security-skin #wf-satisfaction-prompt,
        .rl-security-skin .wf-satisfaction-prompt,
        .rl-security-skin .wf-license-upgrade-prompt,
        .rl-security-skin #wf-site-cleaning-bottom,
        .rl-security-skin .wf-audit-log-premium-callout { display: none !important; }

        /* --- Scan status ------------------------------------------------------------------------------
           The Scan page leads with the Wordfence wordmark drawn as stacked bars, which at this
           size reads as a broken image rather than as branding. The graphic goes; the label it
           sat above becomes the status line, with a dot matching the ones on the overview. */
        .rl-security-skin .wf-scan-status .wf-block-labeled-value-value { display: none !important; }
        .rl-security-skin .wf-scan-status {
            display: flex !important; align-items: center !important; justify-content: center !important;
            gap: 8px !important; min-height: 60px !important;
        }
        .rl-security-skin .wf-scan-status .wf-block-labeled-value-label {
            font-size: 14px !important; font-weight: 600 !important; color: #09090b !important;
            text-transform: none !important; letter-spacing: -0.01em !important;
        }
        .rl-security-skin .wf-scan-status:before {
            content: "" !important; width: 8px; height: 8px; border-radius: 9999px;
            background: #a1a1aa; flex: 0 0 auto; display: block;
        }
        .rl-security-skin .wf-scan-status-enabled:before {
            background: #10b981; box-shadow: 0 0 0 3px rgba(16,185,129,.18);
        }
        .rl-security-skin .wf-scan-status-disabled:before { background: #ef4444; }

        /* --- Live Traffic legend --------------------------------------------------------------------
           `#wf-live-traffic-legend` is `position: fixed; bottom: 0` by design — a key for the
           traffic table that stays put while you scroll. Unstyled it is a bare white rectangle
           sitting across the admin footer, which reads as a rendering fault rather than as a
           legend. Kept (it is real UI when traffic exists) and given the card treatment so it
           looks deliberate. */
        .rl-security-skin #wf-live-traffic-legend {
            bottom: 52px !important; left: 196px !important;
            background: #fff !important; border: 1px solid #e4e4e7 !important;
            border-radius: 10px !important; box-shadow: 0 4px 12px rgba(0,0,0,.08) !important;
            padding: 8px 14px !important; font-size: 12px !important; color: #71717a !important;
            width: auto !important; height: auto !important;
        }
        .rl-security-skin #wf-live-traffic-legend ul {
            display: flex !important; flex-wrap: wrap !important; gap: 4px 14px !important;
            margin: 0 !important; padding: 0 !important; list-style: none !important;
        }
        .rl-security-skin #wf-live-traffic-legend li {
            margin: 0 !important; font-size: 12px !important; color: #71717a !important;
            display: inline-flex !important; align-items: center !important; white-space: nowrap !important;
        }
        /* Both classes land on <body>, so this is one compound selector, not a descendant one. */
        .rl-security-skin.folded #wf-live-traffic-legend { left: 52px !important; }

        /* --- Login Security module ---------------------------------------------------------------------
           WordFence's Login Security ships as a separate module with its own `wfls-` prefix and
           its own copy of the same component set — block, btn, table, option, modal,
           section-title. None of the `wf-` rules above touch it, which left the whole Login
           Security screen in stock WordFence blue inside an otherwise themed section. This is
           the parallel set. It needs no specificity tricks (the module does not double its
           classes) except on button text, where the console's `#wpbody-content a` reset applies
           the same way it does everywhere else. */
        .rl-security-skin .wfls-container,
        .rl-security-skin .wfls-container-fluid { color: #09090b !important; }

        .rl-security-skin .wfls-block {
            background: #fff !important; border: 1px solid #e4e4e7 !important;
            border-radius: 12px !important; box-shadow: 0 1px 2px rgba(0,0,0,.04) !important;
            margin: 0 0 14px !important;
        }
        .rl-security-skin .wfls-block-header { padding: 14px 18px !important; border-bottom: 1px solid #f4f4f5 !important; }
        .rl-security-skin .wfls-block-content { padding: 16px 18px !important; }
        .rl-security-skin .wfls-block-footer {
            padding: 12px 18px !important; border-top: 1px solid #f4f4f5 !important; background: #fafafa !important;
        }

        .rl-security-skin .wfls-section-title h1,
        .rl-security-skin .wfls-section-title h2,
        .rl-security-skin .wfls-section-title h3 {
            font-size: 20px !important; font-weight: 700 !important; letter-spacing: -0.02em !important;
            color: #09090b !important; margin: 0 !important;
        }

        .rl-security-skin .wfls-btn,
        .rl-security-skin a.wfls-btn,
        .rl-security-skin button.wfls-btn {
            text-transform: none !important; border-radius: 6px !important;
            font-size: 13px !important; font-weight: 500 !important; line-height: 1.4 !important;
            padding: 7px 14px !important; letter-spacing: 0 !important;
            border: 1px solid transparent !important; box-shadow: 0 1px 2px rgba(0,0,0,.04) !important;
            text-shadow: none !important; background-image: none !important; height: auto !important;
        }
        .rl-security-skin .wfls-btn-primary,
        .rl-security-skin .wfls-btn-success,
        .rl-security-skin .wfls-btn-info {
            background-color: #18181b !important; border-color: #18181b !important;
        }
        .rl-security-skin .wfls-btn-primary:hover,
        .rl-security-skin .wfls-btn-success:hover,
        .rl-security-skin .wfls-btn-info:hover {
            background-color: #27272a !important; border-color: #27272a !important;
        }
        .rl-security-skin .wfls-btn-default {
            background-color: #fff !important; border-color: #e4e4e7 !important;
        }
        .rl-security-skin .wfls-btn-default:hover { background-color: #f4f4f5 !important; border-color: #d4d4d8 !important; }
        .rl-security-skin .wfls-btn-danger {
            background-color: #fff !important; border-color: #fecaca !important;
        }
        .rl-security-skin .wfls-btn-danger:hover { background-color: #fef2f2 !important; border-color: #f87171 !important; }
        .rl-security-skin .wfls-btn-warning {
            background-color: #fff !important; border-color: #fde68a !important;
        }

        /* Button text, past the console's link reset. */
        .rl-security-skin #wpbody-content a.wfls-btn-primary,
        .rl-security-skin #wpbody-content a.wfls-btn-success,
        .rl-security-skin #wpbody-content a.wfls-btn-info,
        .rl-security-skin #wpbody-content button.wfls-btn-primary,
        .rl-security-skin a.wfls-btn-primary,
        .rl-security-skin button.wfls-btn-primary,
        .rl-security-skin .wfls-btn-primary,
        .rl-security-skin .wfls-btn-success,
        .rl-security-skin .wfls-btn-info {
            color: #fafafa !important; text-decoration: none !important;
        }
        .rl-security-skin #wpbody-content a.wfls-btn-default,
        .rl-security-skin .wfls-btn-default { color: #09090b !important; text-decoration: none !important; }
        .rl-security-skin #wpbody-content a.wfls-btn-danger,
        .rl-security-skin .wfls-btn-danger { color: #ef4444 !important; text-decoration: none !important; }
        .rl-security-skin #wpbody-content a.wfls-btn-warning,
        .rl-security-skin .wfls-btn-warning { color: #a16207 !important; text-decoration: none !important; }

        .rl-security-skin .wfls-table {
            border-collapse: collapse !important; font-size: 13px !important;
            border: 1px solid #e4e4e7 !important; border-radius: 10px !important; overflow: hidden !important;
        }
        .rl-security-skin .wfls-table th {
            text-align: left !important; font-size: 11px !important; text-transform: uppercase !important;
            letter-spacing: .04em !important; color: #71717a !important; font-weight: 600 !important;
            padding: 10px 14px !important; background: #fafafa !important;
            border-bottom: 1px solid #e4e4e7 !important; border-top: 0 !important;
        }
        .rl-security-skin .wfls-table td {
            padding: 11px 14px !important; border-bottom: 1px solid #f4f4f5 !important;
            border-top: 0 !important; color: #09090b !important;
        }

        .rl-security-skin .wfls-form-control,
        .rl-security-skin .wfls-block input[type="text"],
        .rl-security-skin .wfls-block input[type="number"],
        .rl-security-skin .wfls-block select {
            border: 1px solid #e4e4e7 !important; border-radius: 6px !important;
            padding: 7px 11px !important; font-size: 13px !important; color: #09090b !important;
            background: #fff !important; box-shadow: none !important; min-height: 36px !important;
        }
        .rl-security-skin .wfls-option { border-bottom: 1px solid #f4f4f5 !important; padding: 14px 0 !important; }
        .rl-security-skin .wfls-option-title { font-size: 13px !important; font-weight: 600 !important; color: #09090b !important; }
        .rl-security-skin .wfls-option-text { font-size: 12px !important; color: #71717a !important; }

        .rl-security-skin .wfls-nav-tabs,
        .rl-security-skin .wfls-nav-pills:not(.wfls-visible-xs) {
            display: inline-flex !important; align-items: center !important; border-radius: 8px !important;
            background: #f4f4f5 !important; padding: 3px !important; gap: 2px !important;
            border: 1px solid #e4e4e7 !important; margin: 0 0 18px !important;
        }
        .rl-security-skin .wfls-nav-tabs > li > a,
        .rl-security-skin .wfls-nav-pills > li > a,
        .rl-security-skin .wfls-tab > a {
            border: 0 !important; border-radius: 6px !important; padding: 6px 14px !important;
            font-size: 13px !important; font-weight: 500 !important; color: #71717a !important;
            background: transparent !important; margin: 0 !important; text-transform: none !important;
        }
        .rl-security-skin .wfls-nav-tabs > li.wfls-active > a,
        .rl-security-skin .wfls-nav-pills > li.wfls-active > a,
        .rl-security-skin .wfls-tab.wfls-active > a {
            background: #fff !important; color: #09090b !important; font-weight: 600 !important;
            box-shadow: 0 1px 2px rgba(0,0,0,.06) !important;
        }

        .rl-security-skin .wfls-modal-content {
            border-radius: 12px !important; border: 1px solid #e4e4e7 !important;
            box-shadow: 0 10px 30px rgba(0,0,0,.12) !important;
        }
        .rl-security-skin .wfls-modal-header { border-bottom: 1px solid #f4f4f5 !important; }
        .rl-security-skin .wfls-modal-footer { border-top: 1px solid #f4f4f5 !important; background: #fafafa !important; }
        .rl-security-skin .wfls-badge {
            background: #f4f4f5 !important; color: #3f3f46 !important; border-radius: 9999px !important;
            font-size: 11px !important; font-weight: 600 !important;
        }
        .rl-security-skin .wfls-block a:not(.wfls-btn) {
            color: #09090b !important; text-decoration: underline !important;
            text-decoration-color: #d4d4d8 !important; text-underline-offset: 2px !important;
        }

        /* --- Onboarding tour leftovers -----------------------------------------------------------------
           `ul.wf-tour-template` is markup WordFence prints for its guided tour and hides with
           `css/wf-onboarding.css`. On this install that stylesheet is never enqueued — the
           condition in `wfOnboardingController::enqueue_assets()` does not fire — so ~2,400px
           of tour copy, buttons and a full-width gear illustration render at the foot of the
           Firewall page. Verified pre-existing: it renders identically with every one of our
           inline stylesheets disabled. Hidden here because it is on our admin regardless of
           whose bug it is. */
        .rl-security-skin .wf-tour-template,
        .rl-security-skin .wf-pointer-template,
        .rl-security-skin #wf-tour-close,
        .rl-security-skin #wf-onboarding-overlay:empty { display: none !important; }
        CSS;
    }
}
