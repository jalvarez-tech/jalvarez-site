<?php
/**
 * @file
 * Refresh the Maluma project's narrative to match the actual scope:
 *   • Complete redesign of the artist's site (vs. the older 'migration')
 *   • 2-month duration (was 3)
 *   • 2 languages (was 3)
 *   • Marketing team owns content updates with no developer in the loop
 *
 * Updates field_summary, field_intro, field_duration, field_challenge_intro,
 * and field_challenge_bullets in both ES and EN translations.
 *
 * Idempotent. Safe to re-run.
 */

use Drupal\node\Entity\Node;

$updates = [
  'es' => [
    'field_summary' => 'Rediseño completo del sitio del artista en 2 meses: dos idiomas, performance al límite y un CMS que el equipo de marketing administra sin tickets ni desarrolladores.',
    'field_intro' => 'Rediseño completo del sitio del artista con foco en performance real, dos idiomas y un panel administrable que el equipo de marketing usa sin entrenamiento técnico — ningún cambio editorial pasa por código.',
    'field_duration' => '2 meses',
    'field_challenge_intro' => 'El sitio del artista cargaba lento, necesitaba operar en dos idiomas y cualquier cambio editorial dependía de un developer. El equipo de marketing estaba bloqueado en cada release y la audiencia internacional veía un sitio que no era para ella.',
    'field_challenge_bullets' => [
      'LCP de 4.2s en mobile, dato de campo (no laboratorio) — usuarios abandonando antes de ver el hero.',
      'Sin soporte multilenguaje: el 40% de la audiencia internacional veía contenido solo en español.',
      'Cada cambio editorial pasaba por un developer — el equipo de marketing dependía de tickets para publicar una nota o swap de imagen.',
    ],
  ],
  'en' => [
    'field_summary' => 'Complete redesign of the artist\'s site in 2 months: two languages, performance at the edge, and a CMS the marketing team owns without tickets or developers.',
    'field_intro' => 'Complete redesign of the artist\'s site, focused on real-world performance, two languages, and an admin panel the marketing team uses without technical training — no editorial change goes through code.',
    'field_duration' => '2 months',
    'field_challenge_intro' => 'The artist\'s site loaded slowly, needed to ship in two languages, and any editorial change required a developer. The marketing team was blocked on every release and the international audience saw a site that wasn\'t for them.',
    'field_challenge_bullets' => [
      'LCP of 4.2s on mobile, field data (not lab) — users bouncing before seeing the hero.',
      'No multilingual support: 40% of the international audience saw Spanish-only content.',
      'Every editorial change went through a developer — marketing was stuck waiting on tickets to publish a post or swap an image.',
    ],
  ],
];

$node = Node::load(1);
if (!$node || $node->bundle() !== 'project' || stripos($node->label(), 'maluma') === FALSE) {
  fwrite(STDERR, "✗ Node nid=1 isn't the Maluma project. Aborting.\n");
  exit(1);
}

foreach ($updates as $langcode => $fields) {
  if (!$node->hasTranslation($langcode)) {
    echo "⚠ Skipping {$langcode}: translation doesn't exist.\n";
    continue;
  }
  $trans = $node->getTranslation($langcode);
  echo "→ Updating {$langcode}\n";
  foreach ($fields as $field => $value) {
    if (!$trans->hasField($field)) {
      echo "  ⚠ {$field}: field not on bundle, skip\n";
      continue;
    }
    if ($field === 'field_challenge_bullets') {
      // Multi-value text field — pass an array of {value} items.
      $items = array_map(fn($t) => ['value' => $t], $value);
      $trans->set($field, $items);
      echo "  · {$field}: " . count($items) . " bullets\n";
    }
    else {
      $trans->set($field, $value);
      $preview = substr($value, 0, 70) . (strlen($value) > 70 ? '…' : '');
      echo "  · {$field}: {$preview}\n";
    }
  }
}

// Make sure both translations stay published.
foreach ($node->getTranslationLanguages() as $langcode => $_) {
  $node->getTranslation($langcode)->setPublished()->save();
}

drupal_flush_all_caches();
echo "\n✓ Maluma content refreshed and saved (published) in ES + EN.\n";
echo "  Verify: /es/proyectos/malumaonline · /en/projects/malumaonline\n";
