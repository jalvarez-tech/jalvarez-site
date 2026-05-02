<?php
foreach (['project', 'note', 'page'] as $bundle) {
  echo "\n=== node.{$bundle} ===\n";
  $field_defs = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', $bundle);
  foreach ($field_defs as $name => $def) {
    if ($def->getFieldStorageDefinition()->isBaseField()) continue;
    $type = $def->getType();
    $label = $def->getLabel();
    $cardinality = $def->getFieldStorageDefinition()->getCardinality();
    $card = $cardinality === -1 ? '∞' : $cardinality;
    $target = '';
    if (in_array($type, ['entity_reference', 'entity_reference_revisions'])) {
      $settings = $def->getSettings();
      $target = ' → ' . ($settings['target_type'] ?? '?');
      if (isset($settings['handler_settings']['target_bundles'])) {
        $target .= ':' . implode(',', array_keys($settings['handler_settings']['target_bundles']));
      }
    }
    echo sprintf("  %-35s %-30s [%s] %s%s\n", $name, $type, $card, $label, $target);
  }
}
