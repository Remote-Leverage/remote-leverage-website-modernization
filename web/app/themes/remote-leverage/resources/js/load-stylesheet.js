/**
 * Injects a <link rel="stylesheet"> and resolves once it loads, retrying on failure.
 *
 * Vite's `import('*.css')` fire-and-forgets the <link> it injects — the returned promise
 * resolves as soon as the module evaluates, not once the stylesheet actually loads, so a
 * dropped request is invisible to the caller and the styles just silently never apply. Import
 * the asset with `?url` instead to get its resolved (hashed) href, then load it through here.
 *
 * Never rejects: after exhausting retries it resolves anyway, so a persistently blocked
 * stylesheet degrades to unstyled-but-functional rather than breaking the caller's flow.
 */
export function loadStylesheet(href, { retries = 2, retryDelayMs = 500 } = {}) {
  return new Promise((resolve) => {
    let attempt = 0;

    const tryLoad = () => {
      attempt += 1;
      const link = document.createElement('link');
      link.rel = 'stylesheet';
      link.href = href;
      link.onload = () => resolve();
      link.onerror = () => {
        link.remove();
        if (attempt <= retries) {
          setTimeout(tryLoad, retryDelayMs);
        } else {
          resolve();
        }
      };
      document.head.appendChild(link);
    };

    tryLoad();
  });
}
