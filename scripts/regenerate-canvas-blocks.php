<?php
/**
 * @file
 * Force regenerate Canvas Component entries for all block-source plugins.
 * Run this after modifying any custom Block plugin's defaultConfiguration()
 * so Canvas picks up the new settings keys (otherwise Canvas validation
 * rejects them with "X is not a supported key").
 */

use Drupal\canvas\ComponentSource\ComponentSourceManager;
use Drupal\canvas\Entity\Component;

/** @var ComponentSourceManager $sm */
$sm = \Drupal::service(ComponentSourceManager::class);

// Trigger regeneration for the block source (also recomputes version hashes).
$sm->generateComponents();
drupal_flush_all_caches();

echo "── jalvarez_projects_grid post-regen ──\n";
$c = Component::load('block.jalvarez_projects_grid');
if ($c) {
  $settings = $c->getSettings();
  $keys = array_keys($settings['default_settings'] ?? []);
  echo "  default_settings keys: " . implode(',', $keys) . "\n";
  echo "  active_version: " . $c->getActiveVersion() . "\n";
}

echo "\n── jalvarez_notes_grid post-regen ──\n";
$c = Component::load('block.jalvarez_notes_grid');
if ($c) {
  $settings = $c->getSettings();
  $keys = array_keys($settings['default_settings'] ?? []);
  echo "  default_settings keys: " . implode(',', $keys) . "\n";
}
