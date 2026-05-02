<?php
/**
 * @file
 * Remove the obsolete `field_canvas` from node.page.
 *
 * Originally added so we could try editing nodes via Canvas, but Canvas 1.3.x
 * only supports its native `canvas_page` entity. All Canvas content now lives
 * in canvas_page entities; node.page is kept for legacy/future content.
 */

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

// Only delete if no nodes use it.
$nids = \Drupal::entityQuery('node')
  ->condition('type', 'page')
  ->accessCheck(FALSE)
  ->execute();
echo "node.page count: " . count($nids) . "\n";

$field = FieldConfig::loadByName('node', 'page', 'field_canvas');
if ($field) {
  $field->delete();
  echo "  ✓ Deleted FieldConfig node.page.field_canvas\n";
}

$storage = FieldStorageConfig::loadByName('node', 'field_canvas');
if ($storage) {
  $storage->delete();
  echo "  ✓ Deleted FieldStorageConfig node.field_canvas\n";
}

drupal_flush_all_caches();
echo "\n✅ field_canvas removed from node.page (canvas_page is the canonical home for Canvas trees).\n";
