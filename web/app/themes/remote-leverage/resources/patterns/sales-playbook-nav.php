<?php

/**
 * The sticky jump nav shared by the internal sales playbooks (/vastore5/, /store/): a pill row
 * parked directly under the site header, with a scroll-spy that lights the section a rep is
 * currently reading.
 *
 * Extracted out of patterns/vastore5.php on 2026-09-15 when /store/ was migrated, so the two
 * pages run one copy of the spy rather than two.
 *
 * Config (all keys optional):
 *   scope — the root class the shared skin's rules hang off. Defaults to 'vastore5'.
 *   items — [element id, label] pairs. Labels are rendered as HTML so they can carry entities;
 *           they are authored in the pattern, never user input. Defaults to none, which
 *           renders an empty bar — pass items.
 *
 * Not a block pattern: it has no pattern header and lives outside patterns/ so WordPress does
 * not try to register it.
 */
$scope = $nav['scope'] ?? 'vastore5';
$items = $nav['items'] ?? [];
?>
<nav class="<?= esc_attr($scope) ?> v5-nav" aria-label="Playbook sections">
    <div class="v5-inner v5-nav__inner">
        <?php foreach ($items as $item) { ?>
            <a href="#<?= esc_attr($item[0]) ?>" data-v5-nav="<?= esc_attr($item[0]) ?>"><?= $item[1] ?></a>
        <?php } ?>
    </div>
</nav>
<script>
(function () {
    var nav = document.querySelector('.v5-nav');
    if (!nav) { return; }

    /* The site header is sticky at top:0; park this bar directly beneath it. */
    function place() {
        var header = document.querySelector('#app > header');
        var h = header ? Math.round(header.getBoundingClientRect().height) : 80;
        document.documentElement.style.setProperty('--v5-navtop', h + 'px');
    }
    place();
    window.addEventListener('resize', place);

    var links = Array.prototype.slice.call(nav.querySelectorAll('[data-v5-nav]'));

    /* The sections are further down the document than this inline script, so the
       targets are resolved once the parser has finished — looking them up now
       would find nothing and silently disable the scroll-spy. */
    function start() {
        var targets = links
            .map(function (a) { return document.getElementById(a.getAttribute('data-v5-nav')); })
            .filter(Boolean);
        if (!targets.length) { return; }

        var queued = false;
        function spy() {
            queued = false;
            var line = (window.pageYOffset || 0) + nav.getBoundingClientRect().bottom + 90;
            var current = targets[0].id;
            targets.forEach(function (t) {
                if ((window.pageYOffset || 0) + t.getBoundingClientRect().top <= line) { current = t.id; }
            });
            links.forEach(function (a) {
                a.classList.toggle('is-current', a.getAttribute('data-v5-nav') === current);
            });
        }
        function queue() {
            if (queued) { return; }
            queued = true;
            window.requestAnimationFrame(spy);
        }
        spy();
        window.addEventListener('scroll', queue, { passive: true });
        window.addEventListener('resize', queue);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
</script>
