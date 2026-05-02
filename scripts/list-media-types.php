<?php
$types = \Drupal::entityTypeManager()->getStorage('media_type')->loadMultiple();
if (!$types) {
  echo "  (ningún media_type existe)\n";
}
foreach ($types as $t) {
  $plugin_id = $t->getSource()->getPluginId();
  $defn = $t->getSource()->getPluginDefinition();
  $allowed = $defn['allowed_field_types'] ?? [];
  echo "  · {$t->id()} (source: {$plugin_id}, allowed_field_types: " . implode(',', $allowed) . ")\n";
}
