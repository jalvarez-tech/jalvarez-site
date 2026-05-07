<?php
/**
 * @file
 * Configure simple_sitemap to include canvas_page + node:project + node:note
 * in the default hreflang sitemap variant.
 *
 * Usage:
 *   ddev exec "cd web && vendor/bin/drush scr ../scripts/configure-simple-sitemap.php"
 */

// Add canvas_page to the list of entity types simple_sitemap can sitemap.
$settings = \Drupal::configFactory()->getEditable('simple_sitemap.settings');
$enabled = $settings->get('enabled_entity_types') ?: [];
foreach (['canvas_page'] as $type) {
  if (!in_array($type, $enabled, TRUE)) {
    $enabled[] = $type;
  }
}
$settings->set('enabled_entity_types', array_values($enabled))->save();
echo "enabled_entity_types: " . implode(', ', $enabled) . "\n";

/** @var \Drupal\simple_sitemap\Entity\EntityHelper $helper */
$entity_helper = \Drupal::service('simple_sitemap.entity_helper');
/** @var \Drupal\simple_sitemap\Manager\EntityManager $manager */
$manager = \Drupal::service('simple_sitemap.entity_manager');

$bundles = [
  'canvas_page' => ['canvas_page'],
  'node' => ['project', 'note'],
];

foreach ($bundles as $entity_type => $list) {
  foreach ($list as $bundle) {
    $manager
      ->setVariants(['default'])
      ->setBundleSettings($entity_type, $bundle, [
        'index' => TRUE,
        'priority' => $bundle === 'canvas_page' ? '1.0' : '0.7',
        'changefreq' => $bundle === 'note' ? 'monthly' : 'weekly',
        'include_images' => TRUE,
      ]);
    echo "OK {$entity_type}:{$bundle} → indexed in 'default' variant\n";
  }
}

// Make sure homepage gets priority 1.0 (handled via canvas_page bundle above).
echo "\nDone. Run: drush ssg\n";
