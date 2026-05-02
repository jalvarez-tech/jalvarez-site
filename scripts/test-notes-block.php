<?php
$plugin = \Drupal::service('plugin.manager.block')->createInstance('jalvarez_notes_grid', []);
$build = $plugin->build();
echo "Top-level build keys: " . implode(',', array_keys($build)) . "\n";
echo "Number of rows: " . (isset($build['rows']) ? count($build['rows']) : 'N/A') . "\n";
echo "\nRendered HTML:\n";
$rendered = (string) \Drupal::service('renderer')->renderRoot($build);
echo $rendered;
