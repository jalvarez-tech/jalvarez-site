<?php
/**
 * @file
 * One-shot: bump system.css_js_query_string + flush asset caches so the
 * deployed theme CSS (with the new nav max-width) shows up in the
 * aggregated bundle. drush cr alone wasn't busting the aggregated file
 * URL hash on prod after the SCSS source changed.
 */

\Drupal::service('asset.css.collection_optimizer')->deleteAll();
\Drupal::service('asset.js.collection_optimizer')->deleteAll();
// Bump the cache-busting query string so any browser/CDN copies of the
// aggregated bundle URLs become stale.
\Drupal::state()->set('system.css_js_query_string', base_convert((string) \Drupal::time()->getRequestTime(), 10, 36));
drupal_flush_all_caches();
echo "✓ CSS/JS aggregation flushed and css_js_query_string bumped.\n";
