<?php
/**
 * @file
 * Add field_canvas (drupal/canvas) to node.page bundle.
 * Idempotent.
 */

use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;

$field_name = 'field_canvas';
$entity_type = 'node';
$bundle = 'page';

$storage = FieldStorageConfig::loadByName($entity_type, $field_name);
if (!$storage) {
  $storage = FieldStorageConfig::create([
    'field_name'  => $field_name,
    'entity_type' => $entity_type,
    'type'        => 'component_tree',
    'cardinality' => 1,
  ]);
  $storage->save();
  echo "✓ created storage {$entity_type}.{$field_name} (canvas)\n";
} else {
  echo "= storage {$entity_type}.{$field_name} exists\n";
}

$field = FieldConfig::loadByName($entity_type, $bundle, $field_name);
if (!$field) {
  FieldConfig::create([
    'field_name'  => $field_name,
    'entity_type' => $entity_type,
    'bundle'      => $bundle,
    'label'       => 'Canvas',
    'required'    => FALSE,
  ])->save();
  echo "✓ added field instance to {$entity_type}.{$bundle}\n";
} else {
  echo "= field {$entity_type}.{$bundle}.{$field_name} exists\n";
}

drupal_flush_all_caches();
echo "\n✅ Canvas field configured on node.page.\n";
