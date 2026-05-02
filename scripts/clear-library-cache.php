<?php
\Drupal::service('library.discovery')->clearCachedDefinitions();
\Drupal::service('asset.css.collection_optimizer')->deleteAll();
\Drupal::service('asset.js.collection_optimizer')->deleteAll();
drupal_flush_all_caches();
echo "✓ libraries + asset caches cleared\n";
