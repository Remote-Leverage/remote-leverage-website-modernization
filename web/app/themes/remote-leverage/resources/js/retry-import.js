/**
 * Retries a dynamic import() on failure.
 *
 * Vite wraps every dynamic import() in a preload helper that also fetches that chunk's CSS
 * dependencies and rejects the whole import if any of them fail — so a single dropped request
 * can take down an otherwise-fine JS chunk. A failed dynamic import isn't cached by the module
 * system, so calling import() again on the same specifier genuinely retries the fetch rather
 * than replaying the same failure.
 */
export function retryImport(importer, retries = 2, retryDelayMs = 500) {
  return importer().catch((err) => {
    if (retries <= 0) throw err;
    return new Promise((resolve) => setTimeout(resolve, retryDelayMs)).then(() =>
      retryImport(importer, retries - 1, retryDelayMs)
    );
  });
}
