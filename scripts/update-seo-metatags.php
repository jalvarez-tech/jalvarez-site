<?php
/**
 * @file
 * Update the `metatags` JSON field of every canvas_page (ES + EN) with
 * SEO-optimized title and description per page.
 *
 * Other meta tags (Open Graph, Twitter Card, hreflang, JSON-LD, robots)
 * are injected at render time by jalvarez_site_page_attachments_alter()
 * because the canvas_page metatags widget only exposes 4 keys.
 *
 * Usage:
 *   ddev exec "cd web && vendor/bin/drush scr ../scripts/update-seo-metatags.php"
 */

use Drupal\canvas\Entity\Page;

$seo = [
  // Inicio (Canvas) — id=8.
  8 => [
    'es' => [
      'title' => 'John Stevans Alvarez · Senior Web Specialist en Drupal, WordPress y Headless | Medellín',
      'description' => 'Desarrollo web profesional con +15 años de experiencia: Drupal 11, WordPress, Headless, n8n, Core Web Vitals y WCAG 2.2. Sitios rápidos, accesibles y honestos desde Medellín, Colombia.',
    ],
    'en' => [
      'title' => 'John Stevans Alvarez · Senior Web Specialist in Drupal, WordPress & Headless | Medellín',
      'description' => 'Professional web development with 15+ years of experience: Drupal 11, WordPress, Headless, n8n, Core Web Vitals and WCAG 2.2. Fast, accessible and honest sites from Medellín, Colombia.',
    ],
  ],
  // Proyectos — id=5.
  5 => [
    'es' => [
      'title' => 'Proyectos · Casos de estudio en Drupal, WordPress y Headless | jalvarez.tech',
      'description' => 'Portafolio de proyectos web: Maluma.online, Royalty Films, 333 Creativo. Plataformas multilenguaje, rendimiento al límite y CMS que el equipo administra sin tickets ni desarrolladores.',
    ],
    'en' => [
      'title' => 'Projects · Drupal, WordPress & Headless case studies | jalvarez.tech',
      'description' => 'Web project portfolio: Maluma.online, Royalty Films, 333 Creativo. Multilingual platforms, edge performance and CMS the team manages without tickets or developers.',
    ],
  ],
  // Notas — id=6.
  6 => [
    'es' => [
      'title' => 'Notas técnicas · Drupal 11, WordPress, Headless y CI/CD | jalvarez.tech',
      'description' => 'Blog técnico de John Stevans Alvarez: decisiones de arquitectura, recetas de Drupal 11, WordPress, n8n, Headless y CI/CD documentadas con fricción real.',
    ],
    'en' => [
      'title' => 'Technical notes · Drupal 11, WordPress, Headless & CI/CD | jalvarez.tech',
      'description' => "John Stevans Alvarez's technical blog: architecture decisions, Drupal 11 recipes, WordPress, n8n, Headless and CI/CD documented with real friction.",
    ],
  ],
  // Contacto — id=7.
  7 => [
    'es' => [
      'title' => 'Contacto · Disponible Q3 2026 · 2 cupos | jalvarez.tech',
      'description' => 'Hablemos de tu proyecto web. Respuesta en menos de 24 horas vía email, WhatsApp o formulario directo. Disponible Q3 2026 para 2 cupos. Medellín, Colombia.',
    ],
    'en' => [
      'title' => 'Contact · Available Q3 2026 · 2 slots | jalvarez.tech',
      'description' => "Let's talk about your web project. Reply within 24 hours via email, WhatsApp or direct form. Available Q3 2026 for 2 slots. Medellín, Colombia.",
    ],
  ],
];

foreach ($seo as $id => $by_lang) {
  /** @var \Drupal\canvas\Entity\Page|null $page */
  $page = Page::load($id);
  if (!$page) {
    echo "SKIP id={$id}: no canvas_page found\n";
    continue;
  }
  foreach ($by_lang as $langcode => $tags) {
    if (!$page->hasTranslation($langcode)) {
      echo "SKIP id={$id} lang={$langcode}: no translation\n";
      continue;
    }
    $trans = $page->getTranslation($langcode);
    $current = $trans->get('metatags')->value;
    $merged = is_string($current) && $current !== ''
      ? json_decode($current, TRUE) ?: []
      : [];
    $merged['title'] = $tags['title'];
    $merged['description'] = $tags['description'];
    // Keep canonical_url + image_src tokens that the widget already wrote.
    $merged += [
      'canonical_url' => '[canvas_page:url]',
      'image_src'     => '[canvas_page:image:entity:field_media_image:entity:url]',
    ];
    $trans->set('metatags', json_encode($merged, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
  }
  $page->save();
  echo "OK id={$id} ('{$page->label()}') metatags updated for langs: " . implode(', ', array_keys($by_lang)) . "\n";
}

echo "\nDone.\n";
