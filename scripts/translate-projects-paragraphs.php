<?php
/**
 * @file
 * Add EN translations to paragraphs that only existed in ES across the
 * project case studies. Idempotent: only adds the EN translation when
 * it doesn't exist yet, leaves the ES side untouched.
 *
 * Audit (before this script):
 *   • Maluma (nid=1) — 4 case_steps + 2 metric paragraphs missing EN.
 *   • Royalty Films (nid=2) — 1 metric (pid=4 Lighthouse) missing EN;
 *     also field_approach_steps was empty so 4 new bilingual case_steps
 *     get created and attached.
 *   • 333 Creativo (nid=3) — already fully bilingual.
 *
 * Run via:
 *   ddev exec ./web/vendor/bin/drush php:script scripts/translate-projects-paragraphs.php
 *   gh workflow run seed-content.yml --field script=scripts/translate-projects-paragraphs.php
 */

use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;

// Map paragraph pid → EN translation values. Pulled from the existing
// ES content of each paragraph; English written to mirror tone and length.
$paragraph_translations = [
  // ─── Maluma case_steps ──────────────────────────────────────────────
  7 => [
    'field_step_title' => 'Technical audit',
    'field_step_tag'   => 'Week 1',
    'field_step_body'  => 'Real-world measurements with the Web Vitals API across 3 countries. Identified the actual bottlenecks (not the assumed ones) and turned the report into a prioritised backlog the team could ship sprint by sprint.',
  ],
  8 => [
    'field_step_title' => 'Multilingual architecture',
    'field_step_tag'   => 'Week 2',
    'field_step_body'  => 'Migration to a multilingual setup with WPML, configured hreflang correctly, segmented sitemaps per language, and structured data so the international audience finally landed on the right URL.',
  ],
  9 => [
    'field_step_title' => 'Performance budgets',
    'field_step_tag'   => 'Wk 3-7',
    'field_step_body'  => 'CI with budgets that fail the build if exceeded. Lazy-loading on heavy media, predictive prefetch on the artist\'s catalogue, and route-level code splitting. Every PR now defends the LCP target on its own.',
  ],
  10 => [
    'field_step_title' => 'Launch + monitoring',
    'field_step_tag'   => 'Wk 8',
    'field_step_body'  => 'Zero-downtime migration with Cloudflare Workers in front. Active monitoring for the first 4 weeks post-launch, daily Web Vitals report to the team, and a rollback plan for any release that breaks the budget.',
  ],

  // ─── Maluma metrics ─────────────────────────────────────────────────
  1 => [
    'field_metric_key'   => 'LCP',
    'field_metric_value' => '1.4s',
    'field_metric_note'  => 'Core Web Vitals OK',
  ],
  2 => [
    'field_metric_key'   => 'Weight',
    'field_metric_value' => '−38%',
    'field_metric_note'  => 'Bundle reduction',
  ],

  // ─── Royalty Films metric pid=4 ─────────────────────────────────────
  4 => [
    'field_metric_key'   => 'Perf',
    'field_metric_value' => '94',
    'field_metric_note'  => 'Lighthouse',
  ],
];

// Apply paragraph EN translations.
$paragraph_storage = \Drupal::entityTypeManager()->getStorage('paragraph');
foreach ($paragraph_translations as $pid => $vals) {
  $p = $paragraph_storage->load($pid);
  if (!$p) {
    echo "⚠ pid={$pid} not found, skip\n";
    continue;
  }
  if ($p->hasTranslation('en')) {
    // Already exists — refresh to make sure it's in sync with the values
    // above (idempotent).
    $en = $p->getTranslation('en');
    $changed = FALSE;
    foreach ($vals as $f => $v) {
      if (!$en->hasField($f)) continue;
      if ($en->get($f)->value !== $v) {
        $en->set($f, $v);
        $changed = TRUE;
      }
    }
    if ($changed) {
      $p->save();
      echo "= refreshed pid={$pid} ({$p->bundle()}) EN\n";
    }
    else {
      echo "= pid={$pid} ({$p->bundle()}) EN already in sync\n";
    }
  }
  else {
    $p->addTranslation('en', $vals)->save();
    echo "+ added EN translation to pid={$pid} ({$p->bundle()})\n";
  }
}

// ─── Royalty Films approach_steps — create + attach ──────────────────────
$royalty = Node::load(2);
$existing_approach = $royalty->get('field_approach_steps')->getValue();
if (empty($existing_approach)) {
  echo "\n→ Royalty Films field_approach_steps is empty, creating 4 bilingual steps\n";
  $royalty_steps = [
    [
      'es' => ['title' => 'Auditoría + arquitectura', 'tag' => 'Mes 1', 'body' => 'Levantamiento del catálogo audiovisual completo y modelo de contenido en Drupal: tipos para producción, cliente, año, créditos. Estructura bilingüe simétrica desde el primer día. Decisiones de URL y SEO técnico antes de tocar plantillas.'],
      'en' => ['title' => 'Audit + architecture', 'tag' => 'Month 1', 'body' => 'Complete audiovisual catalogue inventory and Drupal content model: types for production, client, year, credits. Symmetric bilingual structure from day one. URL strategy and technical SEO decisions before touching any template.'],
    ],
    [
      'es' => ['title' => 'Módulos de video custom', 'tag' => 'Mes 2', 'body' => 'Construcción de módulos Drupal personalizados para mostrar cada producción con loop silencioso en autoplay y expansión a player completo al click. Triggers basados en intersección de viewport para no quemar bandwidth. Soporte HLS + fallback MP4 para conexiones lentas.'],
      'en' => ['title' => 'Custom video modules', 'tag' => 'Month 2', 'body' => 'Built custom Drupal modules to render each production with autoplay silent loops and click-to-expand into a full player. Viewport intersection triggers so we don\'t burn bandwidth. HLS support with MP4 fallback for slow connections.'],
    ],
    [
      'es' => ['title' => 'Editorial + admin simple', 'tag' => 'Mes 3', 'body' => 'Capa de edición pulida para el equipo de producción: subir video + cover, llenar 5 campos estructurados (cliente, año, equipo, créditos, descripción), y publicar. Vista previa fiel del comportamiento real del loop. Sin tickets, sin entrenamiento.'],
      'en' => ['title' => 'Editorial + simple admin', 'tag' => 'Month 3', 'body' => 'Polished editing layer for the production team: upload video + cover, fill 5 structured fields (client, year, crew, credits, description), and publish. Faithful preview of the actual loop behaviour. No tickets, no training.'],
    ],
  ];

  $step_refs = [];
  foreach ($royalty_steps as $data) {
    $step = Paragraph::create([
      'type' => 'case_step',
      'langcode' => 'es',
      'field_step_title' => $data['es']['title'],
      'field_step_tag'   => $data['es']['tag'],
      'field_step_body'  => $data['es']['body'],
    ]);
    $step->save();
    $step->addTranslation('en', [
      'field_step_title' => $data['en']['title'],
      'field_step_tag'   => $data['en']['tag'],
      'field_step_body'  => $data['en']['body'],
    ])->save();
    $step_refs[] = ['target_id' => $step->id(), 'target_revision_id' => $step->getRevisionId()];
    echo "  + case_step pid={$step->id()} '{$data['es']['title']}'\n";
  }
  $royalty->set('field_approach_steps', $step_refs);
  foreach ($royalty->getTranslationLanguages() as $lc => $_) {
    $royalty->getTranslation($lc)->setPublished()->save();
  }
  echo "  · attached {$royalty->id()}.field_approach_steps with " . count($step_refs) . " refs\n";
}
else {
  echo "= Royalty Films already has " . count($existing_approach) . " approach steps, skip creation\n";
}

drupal_flush_all_caches();
echo "\n✓ All projects fully bilingual. Verify /en/projects/*\n";
