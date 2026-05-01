<?php

/**
 * @file
 * Sample taxonomy terms + 3 sample Project nodes (idempotent).
 *
 * Run: ddev exec ./web/vendor/bin/drush php:script scripts/build-sample-projects.php
 */

use Drupal\taxonomy\Entity\Term;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;

/**
 * Helper: create a taxonomy term if not exists by vid+name.
 */
function ensure_term(string $vid, string $name): Term {
  $tids = \Drupal::entityQuery('taxonomy_term')
    ->condition('vid', $vid)
    ->condition('name', $name)
    ->accessCheck(FALSE)
    ->execute();
  if (!empty($tids)) {
    return Term::load(reset($tids));
  }
  $term = Term::create(['vid' => $vid, 'name' => $name, 'langcode' => 'es']);
  $term->save();
  echo "  + term {$vid}.{$name}\n";
  return $term;
}

echo "Taxonomy terms\n";
$cat_web      = ensure_term('project_categories', 'Web');
$cat_branding = ensure_term('project_categories', 'Branding');
$cat_producto = ensure_term('project_categories', 'Producto');
$cat_ia       = ensure_term('project_categories', 'IA');

$tech_wp     = ensure_term('technologies', 'WordPress');
$tech_drupal = ensure_term('technologies', 'Drupal 11');
$tech_n8n    = ensure_term('technologies', 'n8n');
$tech_react  = ensure_term('technologies', 'React');
$tech_next   = ensure_term('technologies', 'Next.js');
$tech_wpml   = ensure_term('technologies', 'WPML');

echo "\nSample projects\n";

$projects = [
  [
    'title'    => 'Maluma.online',
    'category' => $cat_web,
    'tech'     => $tech_wp,
    'stack'    => [$tech_wp, $tech_wpml, $tech_n8n],
    'year'     => 2025,
    'role'     => 'Lead Developer',
    'duration' => '3 meses',
    'hue'      => 18,
    'summary'  => 'Sitio multilenguaje para el artista, optimizado con mejora superior al 40% en velocidad.',
    'intro'    => 'Migración + rediseño completo del sitio del artista, con foco en performance, multilenguaje y SEO técnico para captar audiencia internacional.',
    'featured' => TRUE,
    'sort'     => 1,
    'metrics'  => [
      ['key' => 'LCP',  'value' => '1.4s', 'note' => 'Core Web Vitals OK'],
      ['key' => 'Peso', 'value' => '−38%', 'note' => 'Reducción de bundle'],
    ],
  ],
  [
    'title'    => 'Royalty Films',
    'category' => $cat_web,
    'tech'     => $tech_wp,
    'stack'    => [$tech_wp, $tech_wpml],
    'year'     => 2025,
    'role'     => 'Tech Lead',
    'duration' => 'Q1 2025',
    'hue'      => 0,
    'summary'  => 'Arquitectura multilenguaje para la productora, preparada para expansión internacional.',
    'intro'    => 'Plataforma web para productora cinematográfica con presencia en 3 idiomas y arquitectura escalable para nuevos mercados.',
    'featured' => TRUE,
    'sort'     => 2,
    'metrics'  => [
      ['key' => 'Idiomas', 'value' => '3'],
      ['key' => 'Perf',    'value' => '94', 'note' => 'Lighthouse'],
    ],
  ],
  [
    'title'    => '333 Creativo',
    'category' => $cat_branding,
    'tech'     => $tech_drupal,
    'stack'    => [$tech_drupal, $tech_n8n],
    'year'     => 2024,
    'role'     => 'Lead Developer',
    'duration' => '4 meses',
    'hue'      => 280,
    'summary'  => 'Plataforma escalable para agencia de marca personal con diseño visual fuerte.',
    'intro'    => 'Sitio en Drupal 11 con sistema de componentes custom y pipeline de marca para asistir a clientes en el desarrollo de su identidad personal.',
    'featured' => TRUE,
    'sort'     => 3,
    'metrics'  => [
      ['key' => 'Módulos', 'value' => 'Custom'],
      ['key' => 'A11y',    'value' => 'AA',     'note' => 'WCAG 2.2'],
    ],
  ],
];

foreach ($projects as $p) {
  // Skip if a node with same title already exists.
  $existing = \Drupal::entityQuery('node')
    ->condition('type', 'project')
    ->condition('title', $p['title'])
    ->accessCheck(FALSE)
    ->execute();
  if (!empty($existing)) {
    echo "  = project '{$p['title']}' exists (skipping)\n";
    continue;
  }

  // Build metric paragraphs.
  $metric_paragraphs = [];
  foreach ($p['metrics'] as $m) {
    $para = Paragraph::create([
      'type' => 'metric',
      'field_metric_key'   => $m['key'],
      'field_metric_value' => $m['value'],
      'field_metric_note'  => $m['note'] ?? '',
    ]);
    $para->save();
    $metric_paragraphs[] = $para;
  }

  $node = Node::create([
    'type'     => 'project',
    'title'    => $p['title'],
    'langcode' => 'es',
    'status'   => 1,
    'field_summary'             => ['value' => $p['summary'], 'format' => 'plain_text'],
    'field_intro'               => ['value' => $p['intro'],   'format' => 'plain_text'],
    'field_publish_date'        => '2025-01-01',
    'field_project_category'    => ['target_id' => $p['category']->id()],
    'field_primary_technology'  => ['target_id' => $p['tech']->id()],
    'field_stack'               => array_map(fn($t) => ['target_id' => $t->id()], $p['stack']),
    'field_project_year'        => $p['year'],
    'field_role'                => $p['role'],
    'field_duration'            => $p['duration'],
    'field_featured_home'       => $p['featured'] ? 1 : 0,
    'field_sort_order'          => $p['sort'],
    'field_cover_hue'           => 'green',  // list_string allowed value
    'field_results_metrics'     => array_map(
      fn($para) => ['target_id' => $para->id(), 'target_revision_id' => $para->getRevisionId()],
      $metric_paragraphs,
    ),
  ]);
  $node->save();
  echo "  + project '{$p['title']}' (nid={$node->id()})\n";
}

\Drupal::service('cache.discovery')->invalidateAll();
drupal_flush_all_caches();
echo "\n✅ Sample content built.\n";
