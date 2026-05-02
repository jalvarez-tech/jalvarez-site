<?php
$widget_manager = \Drupal::service('plugin.manager.field.widget');
foreach ($widget_manager->getDefinitions() as $id => $def) {
  $field_types = $def['field_types'] ?? [];
  if (in_array('component_tree', $field_types)) {
    echo "  · {$id} → {$def['label']}\n";
  }
}
echo "\n--- All widgets handling 'component_tree':\n";
foreach ($widget_manager->getDefinitions() as $id => $def) {
  if (in_array('component_tree', $def['field_types'] ?? [])) {
    print_r($def);
  }
}
