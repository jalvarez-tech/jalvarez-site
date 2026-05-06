<?php
/**
 * @file
 * Check whether the page_attachments_alter hook from jalvarez_site is
 * registered, and force-clear OPcache. On Hostinger with PHP-FPM, drush
 * cr clears CLI OPcache but FPM keeps the old bytecode.
 */

// 1. Verify the hook is registered.
$mh = \Drupal::moduleHandler();
$jal_implements = FALSE;
$mh->invokeAllWith('page_attachments_alter', function (callable $hook, string $module) use (&$jal_implements) {
  if ($module === 'jalvarez_site') $jal_implements = TRUE;
});
echo "jalvarez_site implements page_attachments_alter: " . ($jal_implements ? 'YES' : 'NO') . "\n";

// 2. List the implementing modules in invocation order.
$impls = [];
$mh->invokeAllWith('page_attachments_alter', function (callable $hook, string $module) use (&$impls) {
  $impls[] = $module;
});
echo "All modules implementing: " . implode(', ', $impls) . "\n";

// 3. Force regenerate the asset bundles and bust OPcache hard.
\Drupal::service('library.discovery')->clearCachedDefinitions();
\Drupal::service('asset.css.collection_optimizer')->deleteAll();
\Drupal::service('asset.js.collection_optimizer')->deleteAll();
drupal_flush_all_caches();

// OPcache. Won't reach FPM from CLI, but try anyway.
if (function_exists('opcache_reset')) {
  opcache_reset();
  echo "✓ opcache_reset called (CLI process — FPM unaffected from here)\n";
}

// Touch the .module file's mtime so PHP-FPM's OPcache validate_timestamps
// (if enabled) re-checks it on the next FPM request.
$module_path = DRUPAL_ROOT . '/modules/custom/jalvarez_site/jalvarez_site.module';
if (file_exists($module_path)) {
  touch($module_path);
  echo "✓ Touched mtime of jalvarez_site.module ($module_path)\n";
}

echo "Done. Curl with cache-buster + admin session to verify the bundle changed.\n";
