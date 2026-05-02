<?php
$blocks = \Drupal::entityTypeManager()->getStorage('block')->loadMultiple();
echo "── Local tasks / page title blocks (in any theme) ──\n";
foreach ($blocks as $b) {
  $plugin = $b->getPlugin()->getPluginId();
  if (str_contains($plugin, 'local_tasks') || str_contains($plugin, 'page_title')) {
    echo "  · {$b->id()} → plugin={$plugin} theme={$b->getTheme()} region={$b->getRegion()} status=" . ($b->status() ? "on" : "off") . "\n";
  }
}
echo "\n── byte theme regions ──\n";
$theme = \Drupal::service('theme_handler')->getTheme('byte');
echo "info: " . print_r($theme->info['regions'] ?? 'none', TRUE);
