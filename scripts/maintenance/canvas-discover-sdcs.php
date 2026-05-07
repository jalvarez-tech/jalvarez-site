<?php

/**
 * @file
 * Force Canvas to (re)discover and register all eligible byte SDCs.
 *
 * Reports any that fail checkRequirements. Uses the public
 * ComponentSourceManager::generateComponents() API (which internally
 * constructs SDC source plugins with proper configuration).
 */

use Drupal\canvas\ComponentSource\ComponentSourceManager;
use Drupal\canvas\Entity\Component;
use Drupal\canvas\Plugin\Canvas\ComponentSource\SingleDirectoryComponentDiscovery;

/**
 * @var \Drupal\canvas\ComponentSource\ComponentSourceManager $sm */
$sm = \Drupal::service(ComponentSourceManager::class);

// Get the discovery class directly (bypasses createInstance which requires local_source_id).
$class_resolver = \Drupal::service('class_resolver');
$source_definitions = $sm->getDefinitions();
$sdc_def = $source_definitions['sdc'] ?? NULL;
if (!$sdc_def) {
  echo "✗ SDC source not found in component source definitions.\n";
  return;
}
$discovery = $class_resolver->getInstanceFromDefinition($sdc_def['discovery']);
\assert($discovery instanceof SingleDirectoryComponentDiscovery);

$defs = $discovery->discover();

$byte_ids = [];
foreach (array_keys($defs) as $id) {
  if (str_starts_with($id, 'byte:')) {
    $byte_ids[] = $id;
  }
}
echo "Discovered byte SDCs: " . count($byte_ids) . "\n";

$ok = 0;
$fail = 0;
$eligible = [];
foreach ($byte_ids as $id) {
  try {
    $discovery->checkRequirements($id);
    echo "  ✓ {$id} — eligible\n";
    $eligible[] = $id;
    $ok++;
  }
  catch (\Throwable $e) {
    echo "  ✗ {$id} — " . $e->getMessage() . "\n";
    $fail++;
  }
}
echo "Eligible: {$ok} · Skipped: {$fail}\n\n";

// Now register all eligible components for the SDC source.
echo "Calling ComponentSourceManager::generateComponents('sdc', \$eligible)...\n";
$sm->generateComponents('sdc', $eligible);
echo "✓ Generation complete.\n\n";

// Report which Component config entities exist now.
$registered = [];
foreach ($byte_ids as $id) {
  $component_id = 'sdc.' . str_replace(':', '.', $id);
  $c = Component::load($component_id);
  if ($c) {
    $status = $c->status() ? 'enabled' : 'disabled';
    $registered[] = "{$component_id} ({$status})";
  }
}
echo "Registered Component config entities (" . count($registered) . "):\n";
foreach ($registered as $line) {
  echo "  • {$line}\n";
}
