<?php
/**
 * @file
 * Enrich existing sample projects with full case study data:
 * challenge_intro, challenge_bullets, approach_steps (case_step paragraphs),
 * lesson, testimonial_embed (testimonial_embedded paragraph), CTA.
 * Idempotent — only updates fields that are still empty.
 */

use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;

$enrichments = [
  'Maluma.online' => [
    'challenge_intro' => 'El sitio del artista cargaba lento, no estaba preparado para 3 idiomas, y el catálogo de música estaba desconectado del resto. Tres problemas distintos pero un mismo síntoma: el equipo perdía oportunidades en cada release.',
    'bullets' => [
      'LCP de 4.2s en mobile, dato de campo (no laboratorio) — usuarios abandonando antes de ver el hero.',
      'Catálogo discográfico hardcoded en HTML estático — cada release nuevo requería deploy manual.',
      'Solo español, mientras 60% del tráfico venía de fuera de LATAM.',
    ],
    'steps' => [
      ['tag' => 'Semana 1', 'title' => 'Auditoría técnica', 'body' => 'Mediciones reales con Web Vitals API en 3 países. Identifiqué 12 puntos de mejora. Salí con un documento que separaba "duele ya" de "puede esperar".'],
      ['tag' => 'Semana 2', 'title' => 'Arquitectura WPML', 'body' => 'Migración a sitio multilenguaje con WPML, configurando hreflang correctos y URL prefijos por idioma sin romper SEO existente.'],
      ['tag' => 'Sem. 3-7', 'title' => 'Performance budgets', 'body' => 'CI con presupuestos que rompen el build si se exceden. Lazy loading de imágenes, fonts subset, removed jQuery, image format AVIF para hero.'],
      ['tag' => 'Sem. 8', 'title' => 'Lanzamiento + monitoreo', 'body' => 'Migración sin downtime con Cloudflare Workers en frente. Monitoreo Web Vitals durante 4 semanas post-launch verificando que LCP se mantiene < 1.5s en producción real.'],
    ],
    'lesson' => 'Las mejoras de performance compoundean: cada milisegundo recuperado abre la puerta a métricas mejores en el siguiente sprint. La obsesión inicial con LCP nos llevó naturalmente a mejorar TTI, CLS, y conversión sin tener que pelear cada uno como batalla aparte.',
    'testimonial' => [
      'quote' => 'John implementó nuestro sitio con ejecución técnica impecable. Optimizó el rendimiento logrando una mejora superior al 40% en velocidad y estabilidad.',
      'author' => 'Tatiana Restrepo',
      'role' => 'Maluma.online',
      'initials' => 'TR',
    ],
    'cta_heading' => '¿Tu sitio carga lento y pierde audiencia internacional?',
    'cta_sub' => 'El método aplica si tu sitio de música, marca personal o medios necesita performance + multilenguaje sin sacrificar el contenido editorial.',
  ],
];

foreach ($enrichments as $title => $data) {
  $nids = \Drupal::entityQuery('node')
    ->condition('type', 'project')
    ->condition('title', $title)
    ->accessCheck(FALSE)
    ->execute();
  if (!$nids) {
    echo "  ✗ project '{$title}' not found\n";
    continue;
  }
  $node = Node::load(reset($nids));

  $changed = FALSE;

  if ($node->get('field_challenge_intro')->isEmpty()) {
    $node->set('field_challenge_intro', ['value' => $data['challenge_intro'], 'format' => 'plain_text']);
    $changed = TRUE;
  }

  if ($node->get('field_challenge_bullets')->isEmpty()) {
    $bullets = array_map(fn($b) => ['value' => $b], $data['bullets']);
    $node->set('field_challenge_bullets', $bullets);
    $changed = TRUE;
  }

  if ($node->get('field_approach_steps')->isEmpty()) {
    $step_paras = [];
    foreach ($data['steps'] as $i => $s) {
      $para = Paragraph::create([
        'type' => 'case_step',
        'field_step_tag'   => $s['tag'],
        'field_step_title' => $s['title'],
        'field_step_body'  => ['value' => $s['body'], 'format' => 'plain_text'],
      ]);
      $para->save();
      $step_paras[] = ['target_id' => $para->id(), 'target_revision_id' => $para->getRevisionId()];
    }
    $node->set('field_approach_steps', $step_paras);
    $changed = TRUE;
  }

  if ($node->get('field_lesson')->isEmpty()) {
    $node->set('field_lesson', ['value' => $data['lesson'], 'format' => 'plain_text']);
    $changed = TRUE;
  }

  if ($node->get('field_testimonial_embed')->isEmpty() && !empty($data['testimonial'])) {
    $t = $data['testimonial'];
    $tpara = Paragraph::create([
      'type' => 'testimonial_embedded',
      'field_quote'           => ['value' => $t['quote'], 'format' => 'plain_text'],
      'field_author_name'     => $t['author'],
      'field_author_role'     => $t['role'],
      'field_author_initials' => $t['initials'],
    ]);
    $tpara->save();
    $node->set('field_testimonial_embed', ['target_id' => $tpara->id(), 'target_revision_id' => $tpara->getRevisionId()]);
    $changed = TRUE;
  }

  if ($node->get('field_cta_heading')->isEmpty()) {
    $node->set('field_cta_heading', $data['cta_heading']);
    $changed = TRUE;
  }
  if ($node->get('field_cta_sub')->isEmpty()) {
    $node->set('field_cta_sub', ['value' => $data['cta_sub'], 'format' => 'plain_text']);
    $changed = TRUE;
  }

  if ($changed) {
    $node->save();
    echo "  ✓ enriched '{$title}'\n";
  } else {
    echo "  = '{$title}' already complete\n";
  }
}

drupal_flush_all_caches();
echo "\n✅ Sample projects enriched.\n";
