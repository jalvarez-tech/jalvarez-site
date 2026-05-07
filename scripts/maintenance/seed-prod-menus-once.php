<?php

/**
 * @file
 * ONE-SHOT recovery: seed all menu_link_content entities on production.
 *
 * Context (2026-05-07): the menu migration PR (#6) added menu_ui +
 * menu_link_content modules and created `system.menu.footer_contact` /
 * `system.menu.footer_social` via drush cim. Menu config landed in prod,
 * but the link items themselves are CONTENT (not config) so they were
 * never created → all 13 nav/footer links rendered empty.
 *
 * This script consolidates `migrate-main-menu.php` + `migrate-footer-menus.php`
 * into a single deterministic run with stable UUIDs (same UUIDs as the
 * source scripts, so a later re-seed via those scripts is a no-op).
 *
 * USAGE (run via SSH on prod, ONCE):
 *   drush php:script scripts/maintenance/seed-prod-menus-once.php
 *   drush cache:rebuild
 *
 * After verifying the menus render: DELETE THIS FILE in a follow-up PR.
 * The canonical reseed scripts (`migrate-main-menu.php`,
 * `migrate-footer-menus.php`) stay in the repo for fresh-install use.
 */

declare(strict_types=1);

use Drupal\jalvarez_site\BrandConfig;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\menu_link_content\Entity\MenuLinkContent;

assert(class_exists(\Drupal::class), 'Run via drush php:script.');

// Make sure menu_link_content is translatable. Idempotent — Drupal
// merges third-party settings rather than overwriting.
$settings = ContentLanguageSettings::loadByEntityTypeBundle('menu_link_content', 'menu_link_content');
$settings
  ->setDefaultLangcode('es')
  ->setLanguageAlterable(TRUE)
  ->setThirdPartySetting('content_translation', 'enabled', TRUE)
  ->save();
echo "[ok] menu_link_content translation enabled.\n";

$links = [
  // ── system.menu.main (4 items, ES + EN) ──
  [
    'uuid' => 'b8e2a4f0-1a2b-4c3d-8e5f-000000000001',
    'menu' => 'main',
    'title' => 'inicio',
    'uri' => 'route:<front>',
    'weight' => -50,
    'translations' => ['en' => 'home'],
  ],
  [
    'uuid' => 'b8e2a4f0-1a2b-4c3d-8e5f-000000000002',
    'menu' => 'main',
    'title' => 'proyectos',
    'uri' => 'entity:canvas_page/5',
    'weight' => -49,
    'translations' => ['en' => 'work'],
  ],
  [
    'uuid' => 'b8e2a4f0-1a2b-4c3d-8e5f-000000000003',
    'menu' => 'main',
    'title' => 'notas',
    'uri' => 'entity:canvas_page/6',
    'weight' => -48,
    'translations' => ['en' => 'writing'],
  ],
  [
    'uuid' => 'b8e2a4f0-1a2b-4c3d-8e5f-000000000004',
    'menu' => 'main',
    'title' => 'contacto',
    'uri' => 'entity:canvas_page/7',
    'weight' => -47,
    'translations' => ['en' => 'contact'],
  ],

  // ── system.menu.footer (4 items, ES + EN, capitalized) ──
  [
    'uuid' => 'b8e2a4f0-1a2b-4c3d-8e5f-100000000001',
    'menu' => 'footer',
    'title' => 'Inicio',
    'uri' => 'route:<front>',
    'weight' => -50,
    'translations' => ['en' => 'Home'],
  ],
  [
    'uuid' => 'b8e2a4f0-1a2b-4c3d-8e5f-100000000002',
    'menu' => 'footer',
    'title' => 'Proyectos',
    'uri' => 'entity:canvas_page/5',
    'weight' => -49,
    'translations' => ['en' => 'Projects'],
  ],
  [
    'uuid' => 'b8e2a4f0-1a2b-4c3d-8e5f-100000000003',
    'menu' => 'footer',
    'title' => 'Notas',
    'uri' => 'entity:canvas_page/6',
    'weight' => -48,
    'translations' => ['en' => 'Writing'],
  ],
  [
    'uuid' => 'b8e2a4f0-1a2b-4c3d-8e5f-100000000004',
    'menu' => 'footer',
    'title' => 'Contacto',
    'uri' => 'entity:canvas_page/7',
    'weight' => -47,
    'translations' => ['en' => 'Contact'],
  ],

  // ── system.menu.footer_contact (3 items, lang-agnostic) ──
  [
    'uuid' => 'b8e2a4f0-1a2b-4c3d-8e5f-200000000001',
    'menu' => 'footer_contact',
    'title' => BrandConfig::EMAIL,
    'uri' => 'mailto:' . BrandConfig::EMAIL,
    'weight' => -50,
  ],
  [
    'uuid' => 'b8e2a4f0-1a2b-4c3d-8e5f-200000000002',
    'menu' => 'footer_contact',
    'title' => BrandConfig::PHONE,
    'uri' => 'tel:' . BrandConfig::PHONE_TEL,
    'weight' => -49,
  ],
  [
    'uuid' => 'b8e2a4f0-1a2b-4c3d-8e5f-200000000003',
    'menu' => 'footer_contact',
    'title' => 'WhatsApp →',
    'uri' => BrandConfig::WHATSAPP_LINK,
    'weight' => -48,
  ],

  // ── system.menu.footer_social (2 items, lang-agnostic) ──
  [
    'uuid' => 'b8e2a4f0-1a2b-4c3d-8e5f-300000000001',
    'menu' => 'footer_social',
    'title' => 'LinkedIn ↗',
    'uri' => 'https://www.linkedin.com/in/jalvarez-tech/',
    'weight' => -50,
  ],
  [
    'uuid' => 'b8e2a4f0-1a2b-4c3d-8e5f-300000000002',
    'menu' => 'footer_social',
    'title' => 'GitHub ↗',
    'uri' => 'https://github.com/jalvarez-tech',
    'weight' => -49,
  ],
];

$storage = \Drupal::entityTypeManager()->getStorage('menu_link_content');
$created = 0;
$updated = 0;

foreach ($links as $def) {
  $existing = $storage->loadByProperties(['uuid' => $def['uuid']]);
  $entity = $existing ? reset($existing) : NULL;

  if ($entity instanceof MenuLinkContent) {
    $entity->set('title', $def['title']);
    $entity->set('link', ['uri' => $def['uri']]);
    $entity->set('menu_name', $def['menu']);
    $entity->set('weight', $def['weight']);
    $entity->set('enabled', TRUE);
    $entity->set('expanded', FALSE);
    $entity->set('langcode', 'es');
    $entity->save();
    echo "[updated] [{$def['menu']}] {$def['title']}\n";
    $updated++;
  }
  else {
    $entity = MenuLinkContent::create([
      'uuid' => $def['uuid'],
      'title' => $def['title'],
      'link' => ['uri' => $def['uri']],
      'menu_name' => $def['menu'],
      'weight' => $def['weight'],
      'enabled' => TRUE,
      'expanded' => FALSE,
      'langcode' => 'es',
    ]);
    $entity->save();
    echo "[created] [{$def['menu']}] {$def['title']}\n";
    $created++;
  }

  foreach ($def['translations'] ?? [] as $langcode => $translated_title) {
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

echo "\nSummary: {$created} created, {$updated} updated.\n";
echo "Now run: drush cache:rebuild\n";
echo "Then verify the site renders the menus and DELETE this script.\n";
