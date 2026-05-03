<?php
/**
 * @file
 * Quick wipe of the dynamic page cache + render cache.
 *
 * After applying a content/translation fix, the page cache (TTL 900s in
 * settings.hostinger.php.template) keeps serving the stale HTML until
 * expiry. This bypasses the wait by clearing those caches directly.
 *
 * Lighter than `drupal_flush_all_caches()` — only touches the rendered HTML
 * cache, leaves config / discovery / library caches alone.
 */
\Drupal::cache('dynamic_page_cache')->deleteAll();
\Drupal::cache('page')->deleteAll();
\Drupal::cache('render')->deleteAll();
echo "✓ dynamic_page_cache, page, render caches cleared\n";
