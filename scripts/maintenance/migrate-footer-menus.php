<?php

/**
 * @file
 * Seeds the 3 footer link sections as Drupal menus.
 *
 * Creates menu_link_content rows for:
 *   - system.menu.footer            (Navegación column, 4 items, ES+EN)
 *   - system.menu.footer_contact    (Contacto column, 3 items, lang-agnostic)
 *   - system.menu.footer_social     (En otros lugares column, 2 items)
 *
 * Idempotent: looks up by deterministic UUID before insert/update.
 *
 * Run with: drush php:script scripts/maintenance/migrate-footer-menus.php
 *
 * After running:
 *   drush cache:rebuild && drush config:export --yes
 */

declare(strict_types=1);

use Drupal\jalvarez_site\BrandConfig;
use Drupal\menu_link_content\Entity\MenuLinkContent;

assert(class_exists(\Drupal::class), 'Run via drush php:script.');

$links = [
  // ── Footer column 2: Navegación ──
  // Same paths as the main menu (canvas_pages 5/6/7) but capitalized
  // labels — the footer SCSS leaves links as-is (only headings are
  // uppercased), so capitalization here is what shows.
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

  // ── Footer column 3: Contacto ──
  // Labels are values (email/phone) so they don't need translation.
  // URIs use mailto:/tel:/external — Drupal's link field accepts those.
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

  // ── Footer column 4: En otros lugares ──
  // External profiles, identical labels in ES/EN.
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

echo "\nDone. Now run: drush cache:rebuild && drush config:export --yes\n";
