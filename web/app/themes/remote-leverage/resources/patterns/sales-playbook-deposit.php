<?php

/**
 * The "having trouble booking?" failsafe row and the Deposit & Payment Options tab card, shared
 * by the internal sales playbooks (/vastore5/, /store/). Both pages carry the same two widgets
 * over different link sets, so the markup and the tab/toggle behaviour live here once.
 *
 * Production ships this as an Elementor HTML widget on each page. Note that production's own
 * copy is broken: its click handler reads `abs.forEach(...)` where it means `tabs.forEach(...)`,
 * so on production the tabs never switch and only the first panel is ever reachable. The
 * behaviour reproduced here is the intended one — clicking a tab renders its panel.
 *
 * Expects to sit inside a section carrying the shared skin's root class; its own classes are
 * scoped under that. The element ids are global, so include this at most once per page.
 *
 * Config (all keys optional):
 *   failsafe — [label, url] pairs for the buttons the toggle reveals. Defaults to none, which
 *              renders the toggle over an empty panel.
 *   tabs     — key => [tab label, panel heading, [list items]]. Labels, headings and items are
 *              rendered as HTML so they can carry entities and links; they are authored in the
 *              pattern, never user input. Defaults to none, which renders the card with no tabs.
 *
 * Not a block pattern: it has no pattern header and lives outside patterns/ so WordPress does
 * not try to register it.
 */
$failsafe = $playbookDeposit['failsafe'] ?? [];
$tabs = $playbookDeposit['tabs'] ?? [];
?>
<div class="v5-failsafe">
    <button type="button" class="v5-failsafe__toggle" aria-expanded="false">Having trouble booking above?</button>
    <div class="v5-failsafe__panel">
        <?php foreach ($failsafe as $link) { ?>
            <a class="v5-failsafe__btn" href="<?= esc_url($link[1]) ?>" target="_blank" rel="noopener"><?= esc_html($link[0]) ?></a>
        <?php } ?>
    </div>
</div>

<div class="v5-card v5-tabcard" id="v5-deposit" style="margin-top:clamp(28px,3vw,44px)">
    <div class="v5-tabcard__head">
        <h2>Deposit &amp; Payment Options</h2>
    </div>
    <div class="v5-tabcard__tabs" role="tablist" aria-label="Deposit options">
        <?php $first = true;
foreach ($tabs as $key => $group) { ?>
            <button type="button"<?= $first ? ' class="is-active"' : '' ?> data-tab="<?= esc_attr($key) ?>" role="tab" aria-selected="<?= $first ? 'true' : 'false' ?>"><?= $group[0] ?></button>
        <?php $first = false;
} ?>
    </div>
    <div class="v5-tabcard__body" id="v5-deposit-body" aria-live="polite"></div>
</div>

<script>
(function () {
    document.querySelectorAll('.v5-failsafe').forEach(function (wrap) {
        var toggle = wrap.querySelector('.v5-failsafe__toggle');
        var panel = wrap.querySelector('.v5-failsafe__panel');
        toggle.addEventListener('click', function () {
            var open = panel.classList.toggle('is-open');
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    });
})();
(function () {
    var Q = <?= wp_json_encode($tabs) ?>;
    var root = document.getElementById('v5-deposit');
    var body = document.getElementById('v5-deposit-body');
    var tabs = root.querySelectorAll('.v5-tabcard__tabs > button');

    if (!tabs.length) { return; }

    function render(group) {
        body.innerHTML = '<h4>' + group[1] + '</h4><ul>' +
            group[2].map(function (i) { return '<li>' + i + '</li>'; }).join('') + '</ul>';
    }
    render(Q[tabs[0].getAttribute('data-tab')]);

    /* Production's own copy of this handler reads `abs.forEach` where it means `tabs`, so on
       production the tabs never switch. This is the intended behaviour. */
    Array.prototype.forEach.call(tabs, function (btn) {
        btn.addEventListener('click', function () {
            Array.prototype.forEach.call(tabs, function (b) {
                var on = b === btn;
                b.classList.toggle('is-active', on);
                b.setAttribute('aria-selected', on ? 'true' : 'false');
            });
            render(Q[btn.getAttribute('data-tab')]);
        });
    });
})();
</script>
