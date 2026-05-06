<?php
/**
 * @file
 * Build out the Royalty Films project case study with the actual scope:
 *
 *   • royaltyfilms.co — film production company
 *   • 3 months
 *   • 2 languages with team-owned admin
 *   • Improved product pages (more context per audiovisual piece)
 *   • Home page that surfaces every work in the catalogue
 *   • Custom Drupal modules for silent looped video previews that expand
 *     to a full player on click
 *
 * Updates: summary, intro, duration, challenge_intro + bullets, lesson,
 * cta_heading + sub on both translations. Adjusts the existing
 * results_metrics paragraphs so the cards on /proyectos show numbers
 * that match the new narrative.
 *
 * Idempotent. Safe to re-run.
 */

use Drupal\node\Entity\Node;

$updates = [
  'es' => [
    'field_summary' => 'Sitio de productora cinematográfica en 3 meses: dos idiomas, módulos custom para mostrar cada trabajo en video con loop silencioso y reproducción expandible al click, y un CMS que el equipo administra sin asistencia técnica.',
    'field_intro' => 'Rediseño completo de royaltyfilms.co — productora con catálogo audiovisual amplio. Construimos módulos Drupal personalizados para que cada producción se muestre en el home con un loop silencioso, y al click expanda a player completo con sonido. La página de cada trabajo amplía con créditos, cliente, año y contexto, y el equipo administra todo desde un panel simple.',
    'field_duration' => '3 meses',
    'field_role' => 'Tech Lead + Drupal frontend',
    'field_challenge_intro' => 'Una productora con catálogo profundo de trabajos audiovisuales — pero el sitio anterior los reducía a thumbnails estáticos. La página de productos no daba contexto suficiente para que un cliente entendiera qué mirar y qué pedir, y el equipo dependía de developers para cada actualización del catálogo.',
    'field_challenge_bullets' => [
      'Catálogo de trabajos mostrado solo como thumbnails — el cliente no veía el ritmo, color o tono real de cada producción.',
      'Página de producto sin información estructurada (cliente, año, equipo, créditos) — todo eran descripciones libres.',
      'Cada cambio editorial pasaba por un developer; el equipo de producción esperaba semanas para subir un trabajo nuevo al catálogo.',
    ],
    'field_lesson' => 'Los videos venden mejor que las imágenes en este sector — pero solo si el sitio no exige que el visitante decida "voy a hacer click y esperar". El loop silencioso resuelve la fricción: muestra el ritmo del trabajo de inmediato, y la decisión de expandir o subir el sonido es del visitante, no nuestra.',
    'field_cta_heading' => '¿Tu portfolio audiovisual no muestra lo que tu trabajo realmente es?',
    'field_cta_sub' => 'El método aplica si tu productora, agencia creativa o estudio necesita que cada trabajo hable por sí mismo en el home — y que el equipo lo administre sin tickets.',
  ],
  'en' => [
    'field_summary' => 'Production company site in 3 months: two languages, custom modules to showcase each work as a silent looped preview that expands into full playback on click, and a CMS the team manages without technical assistance.',
    'field_intro' => 'Complete redesign of royaltyfilms.co — production company with a deep audiovisual catalogue. Built custom Drupal modules so each production showcases on the home with a silent looped preview, and click-to-expand into a full player with sound. Each work\'s detail page exposes credits, client, year and context, and the team manages everything from a simple admin panel.',
    'field_duration' => '3 months',
    'field_role' => 'Tech Lead + Drupal frontend',
    'field_challenge_intro' => 'A production company with a deep catalogue of audiovisual work — but the previous site reduced everything to static thumbnails. The product pages didn\'t give a prospective client enough context to understand what to watch or what to ask for, and the team depended on developers for every catalogue update.',
    'field_challenge_bullets' => [
      'Work catalogue shown only as static thumbnails — clients couldn\'t sense the pace, color or tone of each production.',
      'Product pages had no structured information (client, year, crew, credits) — everything was free-form description.',
      'Every editorial change went through a developer; the production team waited weeks to publish a new piece in the catalogue.',
    ],
    'field_lesson' => 'Video sells better than stills in this industry — but only if the site doesn\'t ask the visitor to commit to "I\'ll click and wait". The silent loop removes that friction: it shows the pace of the work immediately, and the visitor decides if they want full-screen or sound, not us.',
    'field_cta_heading' => 'Is your audiovisual portfolio not showing what your work actually is?',
    'field_cta_sub' => 'The method applies if your production company, creative agency or studio needs each piece to speak for itself on the home — and the team to manage it without tickets.',
  ],
];

// Existing metric paragraphs to refresh on /proyectos card. Field structure:
// pid=3: Idiomas (was 3 → now 2)
// pid=4: Perf (Lighthouse 94 — keep)
$metric_updates = [
  3 => [
    'es' => ['field_metric_key' => 'Idiomas',  'field_metric_value' => '2',  'field_metric_note' => 'ES + EN'],
    'en' => ['field_metric_key' => 'Languages','field_metric_value' => '2',  'field_metric_note' => 'ES + EN'],
  ],
  // pid=4 keeps its Lighthouse score as-is.
];

$node = Node::load(2);
if (!$node || $node->bundle() !== 'project' || stripos($node->label(), 'royalty') === FALSE) {
  fwrite(STDERR, "✗ nid=2 isn't the Royalty Films project. Aborting.\n");
  exit(1);
}

foreach ($updates as $langcode => $fields) {
  if (!$node->hasTranslation($langcode)) {
    echo "⚠ Skipping {$langcode}: translation doesn't exist.\n";
    continue;
  }
  $trans = $node->getTranslation($langcode);
  echo "→ Updating node nid=2 ({$langcode})\n";
  foreach ($fields as $field => $value) {
    if (!$trans->hasField($field)) {
      echo "  ⚠ {$field}: field not on bundle, skip\n";
      continue;
    }
    if ($field === 'field_challenge_bullets') {
      $items = array_map(fn($t) => ['value' => $t], $value);
      $trans->set($field, $items);
      echo "  · {$field}: " . count($items) . " bullets\n";
    }
    else {
      $trans->set($field, $value);
      echo "  · {$field}: " . substr($value, 0, 70) . (strlen($value) > 70 ? '…' : '') . "\n";
    }
  }
}

// Persist the node first so any new translation rows exist before
// we touch their paragraph references.
foreach ($node->getTranslationLanguages() as $langcode => $_) {
  $node->getTranslation($langcode)->setPublished()->save();
}

// ─── Metric paragraph translations ────────────────────────────────────────
$paragraph_storage = \Drupal::entityTypeManager()->getStorage('paragraph');
foreach ($metric_updates as $pid => $by_lang) {
  $p = $paragraph_storage->load($pid);
  if (!$p) {
    echo "⚠ paragraph pid={$pid} not found; skip.\n";
    continue;
  }
  foreach ($by_lang as $langcode => $vals) {
    if (!$p->hasTranslation($langcode)) {
      $p->addTranslation($langcode, $vals);
      echo "  + paragraph pid={$pid} ({$langcode}): added translation\n";
    }
    else {
      $t = $p->getTranslation($langcode);
      foreach ($vals as $f => $v) {
        if ($t->hasField($f)) $t->set($f, $v);
      }
      echo "  · paragraph pid={$pid} ({$langcode}): updated\n";
    }
  }
  $p->save();
}

drupal_flush_all_caches();
echo "\n✓ Royalty Films content seeded and published in ES + EN.\n";
echo "  Verify: /es/proyectos · /en/projects · the case detail page.\n";
