/**
 * Renames Yoast's Gutenberg sidebar panel from "Yoast SEO" to "SEO".
 *
 * The panel is a React `PluginDocumentSettingPanel` whose title is a hardcoded JS string, so
 * there is no PHP filter to reach it: `gettext` never sees it, and `load_script_translations`
 * only fires when a translation file exists, which it does not for English. Rewriting the
 * rendered label is the only seam left.
 *
 * Scoped as tightly as the problem allows — an exact-match on the panel toggle's own text, so
 * it cannot touch a panel that merely mentions Yoast, and it stops observing once renamed.
 */
(function () {
    const FROM = 'Yoast SEO';
    const TO = 'SEO';

    const rename = () => {
        const toggles = document.querySelectorAll('.components-panel__body-toggle');
        let renamed = false;

        toggles.forEach((toggle) => {
            // The toggle wraps its label in a span alongside the chevron icon; walking the text
            // nodes avoids clobbering that icon by reassigning textContent on the button.
            toggle.childNodes.forEach((node) => {
                if (node.nodeType === Node.TEXT_NODE && node.nodeValue.trim() === FROM) {
                    node.nodeValue = node.nodeValue.replace(FROM, TO);
                    renamed = true;
                }
            });

            toggle.querySelectorAll('span').forEach((span) => {
                if (span.children.length === 0 && span.textContent.trim() === FROM) {
                    span.textContent = TO;
                    renamed = true;
                }
            });
        });

        return renamed;
    };

    if (rename()) {
        return;
    }

    // The sidebar mounts after this runs, so watch until it appears — then stop.
    const observer = new MutationObserver(() => {
        if (rename()) {
            observer.disconnect();
        }
    });

    observer.observe(document.body, { childList: true, subtree: true });

    // Never observe forever: a screen without the panel should not keep a live observer.
    setTimeout(() => observer.disconnect(), 15000);
})();
