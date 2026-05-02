<?php
/**
 * @file
 * Force a full cache rebuild + router rebuild.
 */

drupal_flush_all_caches();
\Drupal::service('router.builder')->rebuild();
echo "✓ Caches rebuilt + router rebuilt.\n";
