<?php

/**
 * @file
 * Migrates the hardcoded NavGlassBlock links into menu_link_content entities.
 *
 * Idempotent: looks up menu_link_content by deterministic UUID before insert.
 *
 * Run with: drush php:script scripts/maintenance/migrate-main-menu.php
 *
 * After running, export config:
 *   drush config:export --yes
 */

declare(strict_types=1);

use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\menu_link_content\Entity\MenuLinkContent;

assert(class_exists(\Drupal::class), 'Run via drush php:script.');

// 1) Enable Spanish-default + EN-translatable on menu_link_content.
$settings = ContentLanguageSettings::loadByEntityTypeBundle('menu_link_content', 'menu_link_content');
$settings
  ->setDefaultLangcode('es')
  ->setLanguageAlterable(TRUE)
  ->setThirdPartySetting('content_translation', 'enabled', TRUE)
  ->save();
echo "[ok] menu_link_content translation enabled (default es).\n";

// 2) Define the 4 main menu links. Order = UI order. UUIDs are static so
// a re-run finds and updates instead of duplicating.
$links = [
  [
    'uuid'    => 'b8e2a4f0-1a2b-4c3d-8e5f-000000000001',
    'title'   => 'inicio',
    'uri'     => 'route:<front>',
    'weight'  => -50,
    'translations' => [
      'en' => 'home',
    ],
  ],
  [
    'uuid'    => 'b8e2a4f0-1a2b-4c3d-8e5f-000000000002',
    'title'   => 'proyectos',
    'uri'     => 'entity:canvas_page/5',
    'weight'  => -49,
    'translations' => [
      'en' => 'work',
    ],
  ],
  [
    'uuid'    => 'b8e2a4f0-1a2b-4c3d-8e5f-000000000003',
    'title'   => 'notas',
    'uri'     => 'entity:canvas_page/6',
    'weight'  => -48,
    'translations' => [
      'en' => 'writing',
    ],
  ],
  [
    'uuid'    => 'b8e2a4f0-1a2b-4c3d-8e5f-000000000004',
    'title'   => 'contacto',
    'uri'     => 'entity:canvas_page/7',
    'weight'  => -47,
    'translations' => [
      'en' => 'contact',
    ],
  ],
];

$storage = \Drupal::entityTypeManager()->getStorage('menu_link_content');

foreach ($links as $def) {
  $existing = $storage->loadByProperties(['uuid' => $def['uuid']]);
  $entity = $existing ? reset($existing) : NULL;

  if ($entity instanceof MenuLinkContent) {
    $entity->set('title', $def['title']);
    $entity->set('link', ['uri' => $def['uri']]);
    $entity->set('menu_name', 'main');
    $entity->set('weight', $def['weight']);
    $entity->set('enabled', TRUE);
    $entity->set('expanded', FALSE);
    $entity->set('langcode', 'es');
    $entity->save();
    echo "[updated] {$def['title']} ({$def['uri']})\n";
  }
  else {
    $entity = MenuLinkContent::create([
      'uuid'      => $def['uuid'],
      'title'     => $def['title'],
      'link'      => ['uri' => $def['uri']],
      'menu_name' => 'main',
      'weight'    => $def['weight'],
      'enabled'   => TRUE,
      'expanded'  => FALSE,
      'langcode'  => 'es',
    ]);
    $entity->save();
    echo "[created] {$def['title']} ({$def['uri']})\n";
  }

  foreach ($def['translations'] as $langcode => $translated_title) {
    if ($entity->hasTranslation($langcode)) {
      $entity->getTranslation($langcode)->set('title', $translated_title)->save();
      echo "  [updated:{$langcode}] {$translated_title}\n";
    }
    else {
      $entity->addTranslation($langcode, ['title' => $translated_title])->save();
      echo "  [added:{$langcode}] {$translated_title}\n";
    }
  }
}

echo "\nDone. Now run: drush cache:rebuild && drush config:export --yes\n";
