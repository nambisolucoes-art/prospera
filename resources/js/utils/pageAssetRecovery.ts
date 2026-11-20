const retryWindowMs = 60_000;
const retryPrefix = 'prospera.assets.retry:';

/** Only failed page assets are retried; API and application errors retain their normal handling. */
export function isPageAssetError(error: unknown): boolean {
  return (
    error instanceof Error &&
    /Unable to preload CSS for |Failed to fetch dynamically imported module|Importing a module script failed|error loading dynamically imported module|Failed to load module script/i.test(
      error.message,
    )
  );
}

function retryKey(destination: URL): string {
  return retryPrefix + destination.pathname + destination.search;
}

/** One GET navigation per destination, without replaying a form submission or creating a reload loop. */
export function reloadPageAssets(destination: URL, replace = false): boolean {
  if (destination.origin !== location.origin) return false;
  try {
    const last = Number(sessionStorage.getItem(retryKey(destination)) ?? 0);
    if (last && Date.now() - last < retryWindowMs) return false;
    sessionStorage.setItem(retryKey(destination), String(Date.now()));
  } catch {
    // With storage unavailable, leave an explicit reload action instead of risking a loop.
    return false;
  }
  const url = destination.pathname + destination.search + destination.hash;
  if (replace || destination.href === location.href) location.replace(url);
  else location.assign(url);
  return true;
}

export function clearPageAssetRetry(destination: URL): void {
  try {
    sessionStorage.removeItem(retryKey(destination));
  } catch {
    /* Storage is optional. */
  }
}
