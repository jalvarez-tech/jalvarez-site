<?php
/**
 * @file
 * Hard rebuild of approach_steps + metrics on every project node so the
 * paragraph content is consistent and bilingual. Avoids the pid-mismatch
 * problem we hit when translate-projects-paragraphs.php hardcoded pid=7-10
 * (those pids belonged to Maluma in local but to 333 Creativo in prod
 * because the seed scripts ran in different order).
 *
 * For each project (matched by title), this script:
 *   1. Deletes the existing referenced paragraphs (approach_steps + metrics).
 *   2. Creates new ones from the canonical bilingual data below.
 *   3. Re-attaches the refs to the node and republishes both translations.
 *
 * field_approach_steps + field_results_metrics are non-translatable
 * (commit 6353327) so the new refs propagate to every translation row
 * automatically — only the canonical (ES) save is needed; getTranslation
 * inside the case-detail twig resolves each paragraph's EN content.
 *
 * Idempotent end-to-end. Safe to re-run on any environment.
 */

use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;

$projects = [
  // ─── Maluma ────────────────────────────────────────────────────────
  'Maluma.online' => [
    'approach_steps' => [
      [
        'es' => ['title' => 'Auditoría técnica', 'tag' => 'Semana 1', 'body' => 'Mediciones reales con Web Vitals API en 3 países. Identifiqué los cuellos de botella reales (no los asumidos) y convertí el reporte en un backlog priorizado que el equipo pudo entregar sprint a sprint.'],
        'en' => ['title' => 'Technical audit', 'tag' => 'Week 1', 'body' => 'Real-world measurements with the Web Vitals API across 3 countries. Identified the actual bottlenecks (not the assumed ones) and turned the report into a prioritised backlog the team could ship sprint by sprint.'],
      ],
      [
        'es' => ['title' => 'Arquitectura multilenguaje', 'tag' => 'Semana 2', 'body' => 'Migración a sitio multilenguaje con WPML, configuración correcta de hreflang, sitemaps segmentados por idioma, y datos estructurados para que la audiencia internacional aterrizara en la URL correcta.'],
        'en' => ['title' => 'Multilingual architecture', 'tag' => 'Week 2', 'body' => 'Migration to a multilingual setup with WPML, configured hreflang correctly, segmented sitemaps per language, and structured data so the international audience finally landed on the right URL.'],
      ],
      [
        'es' => ['title' => 'Performance budgets', 'tag' => 'Sem. 3-7', 'body' => 'CI con presupuestos que rompen el build si se exceden. Lazy-loading en media pesada, prefetch predictivo del catálogo del artista, y code splitting por ruta. Cada PR defiende el target de LCP por sí mismo.'],
        'en' => ['title' => 'Performance budgets', 'tag' => 'Wk 3-7', 'body' => 'CI with budgets that fail the build if exceeded. Lazy-loading on heavy media, predictive prefetch on the artist\'s catalogue, and route-level code splitting. Every PR now defends the LCP target on its own.'],
      ],
      [
        'es' => ['title' => 'Lanzamiento + monitoreo', 'tag' => 'Sem. 8', 'body' => 'Migración sin downtime con Cloudflare Workers en frente. Monitoreo activo durante las primeras 4 semanas post-lanzamiento, reporte diario de Web Vitals al equipo, y plan de rollback para cualquier release que rompa el budget.'],
        'en' => ['title' => 'Launch + monitoring', 'tag' => 'Wk 8', 'body' => 'Zero-downtime migration with Cloudflare Workers in front. Active monitoring for the first 4 weeks post-launch, daily Web Vitals report to the team, and a rollback plan for any release that breaks the budget.'],
      ],
    ],
    'metrics' => [
      ['es' => ['key' => 'LCP',   'value' => '1.4s',  'note' => 'Core Web Vitals OK'],
       'en' => ['key' => 'LCP',   'value' => '1.4s',  'note' => 'Core Web Vitals OK']],
      ['es' => ['key' => 'Peso',  'value' => '−38%',  'note' => 'Reducción de bundle'],
       'en' => ['key' => 'Weight','value' => '−38%',  'note' => 'Bundle reduction']],
    ],
  ],

  // ─── Royalty Films ─────────────────────────────────────────────────
  'Royalty Films' => [
    'approach_steps' => [
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
      [
        'es' => ['title' => 'Lanzamiento + monitoreo', 'tag' => 'Mes 4', 'body' => 'Migración del catálogo legacy con Migrate API y redirects 301 desde las URLs antiguas. Lanzamiento sin downtime, monitoreo de Core Web Vitals durante las primeras 4 semanas, y plan de soporte editorial para que el equipo siga publicando solo.'],
        'en' => ['title' => 'Launch + monitoring', 'tag' => 'Month 4', 'body' => 'Legacy catalogue migrated via Migrate API with 301 redirects from the old URLs. Zero-downtime launch, Core Web Vitals monitoring through the first 4 weeks, and an editorial support plan so the team keeps publishing on its own.'],
      ],
    ],
    'metrics' => [
      ['es' => ['key' => 'Idiomas', 'value' => '2', 'note' => 'ES + EN'],
       'en' => ['key' => 'Languages', 'value' => '2', 'note' => 'ES + EN']],
      ['es' => ['key' => 'Perf',   'value' => '94', 'note' => 'Lighthouse'],
       'en' => ['key' => 'Perf',   'value' => '94', 'note' => 'Lighthouse']],
    ],
  ],

  // ─── 333 Creativo ──────────────────────────────────────────────────
  '333 Creativo' => [
    'approach_steps' => [
      [
        'es' => ['title' => 'Auditoría + arquitectura', 'tag' => 'Mes 1', 'body' => 'Inventario de contenido vivo en las 3 plataformas anteriores. Modelo de contenido en Drupal con tipos para servicio, caso de éxito, testimonio y artículo. Modelo bilingüe simétrico desde el día uno.'],
        'en' => ['title' => 'Audit + architecture', 'tag' => 'Month 1', 'body' => 'Inventoried live content across the 3 previous platforms. Defined the content model in Drupal with types for service, case study, testimonial and article. Symmetric bilingual model from day one.'],
      ],
      [
        'es' => ['title' => 'Drupal Commerce', 'tag' => 'Mes 2', 'body' => 'Configuración de Drupal Commerce para servicios contratables: variantes por alcance, checkout en pasos cortos, integración con pasarela local. El equipo administra precios y disponibilidad sin tocar código.'],
        'en' => ['title' => 'Drupal Commerce', 'tag' => 'Month 2', 'body' => 'Drupal Commerce setup for purchasable services: variants by scope, short-step checkout, local payment gateway integration. The team manages prices and availability without touching code.'],
      ],
      [
        'es' => ['title' => 'Editor + previews', 'tag' => 'Mes 3', 'body' => 'Capa de edición pulida: vistas previas fieles, campos agrupados por contexto, sin jerga técnica en el panel. Layout Builder configurado con bloques que el equipo creativo puede reordenar visualmente.'],
        'en' => ['title' => 'Editor + previews', 'tag' => 'Month 3', 'body' => 'Polished editing layer: faithful previews, fields grouped by context, no technical jargon in the panel. Layout Builder configured with blocks the creative team can rearrange visually.'],
      ],
      [
        'es' => ['title' => 'Migración + lanzamiento', 'tag' => 'Mes 4', 'body' => 'Migración del contenido legacy con Migrate API, redirects 301 desde las URLs antiguas. Lanzamiento sin downtime, monitoreo de Core Web Vitals durante las primeras 4 semanas, y plan de soporte editorial para el equipo.'],
        'en' => ['title' => 'Migration + launch', 'tag' => 'Month 4', 'body' => 'Legacy content migrated via Migrate API, 301 redirects from the old URLs. Zero-downtime launch, Core Web Vitals monitoring through the first 4 weeks, and an editorial support plan for the team.'],
      ],
    ],
    'metrics' => [
      ['es' => ['key' => 'Idiomas', 'value' => '2', 'note' => 'ES + EN'],
       'en' => ['key' => 'Languages', 'value' => '2', 'note' => 'ES + EN']],
      ['es' => ['key' => 'Plataformas', 'value' => '3→1', 'note' => 'unificadas en una sola'],
       'en' => ['key' => 'Platforms',   'value' => '3→1', 'note' => 'unified into one']],
    ],
  ],
];

$paragraph_storage = \Drupal::entityTypeManager()->getStorage('paragraph');

foreach ($projects as $title => $data) {
  $nids = \Drupal::entityQuery('node')
    ->condition('type', 'project')
    ->condition('title', $title)
    ->accessCheck(FALSE)
    ->execute();
  if (!$nids) {
    echo "⚠ Project '{$title}' not found, skip\n";
    continue;
  }
  $node = Node::load(reset($nids));
  echo "→ Rebuilding nid={$node->id()} '{$title}'\n";

  // Wipe existing referenced paragraphs.
  $old_pids = [];
  foreach (['field_approach_steps', 'field_results_metrics'] as $f) {
    foreach ($node->get($f)->referencedEntities() as $p) $old_pids[] = $p->id();
  }
  foreach (array_unique($old_pids) as $pid) {
    if ($p = $paragraph_storage->load($pid)) $p->delete();
  }
  echo "  · deleted " . count(array_unique($old_pids)) . " stale paragraphs\n";

  // Approach steps: create + attach.
  $step_refs = [];
  foreach ($data['approach_steps'] as $sd) {
    $step = Paragraph::create([
      'type' => 'case_step',
      'langcode' => 'es',
      'field_step_title' => $sd['es']['title'],
      'field_step_tag'   => $sd['es']['tag'],
      'field_step_body'  => $sd['es']['body'],
    ]);
    $step->save();
    $step->addTranslation('en', [
      'field_step_title' => $sd['en']['title'],
      'field_step_tag'   => $sd['en']['tag'],
      'field_step_body'  => $sd['en']['body'],
    ])->save();
    $step_refs[] = ['target_id' => $step->id(), 'target_revision_id' => $step->getRevisionId()];
  }
  $node->set('field_approach_steps', $step_refs);
  echo "  · created " . count($step_refs) . " case_steps (ES + EN)\n";

  // Metrics: create + attach.
  $metric_refs = [];
  foreach ($data['metrics'] as $md) {
    $m = Paragraph::create([
      'type' => 'metric',
      'langcode' => 'es',
      'field_metric_key'   => $md['es']['key'],
      'field_metric_value' => $md['es']['value'],
      'field_metric_note'  => $md['es']['note'] ?? '',
    ]);
    $m->save();
    $m->addTranslation('en', [
      'field_metric_key'   => $md['en']['key'],
      'field_metric_value' => $md['en']['value'],
      'field_metric_note'  => $md['en']['note'] ?? '',
    ])->save();
    $metric_refs[] = ['target_id' => $m->id(), 'target_revision_id' => $m->getRevisionId()];
  }
  $node->set('field_results_metrics', $metric_refs);
  echo "  · created " . count($metric_refs) . " metrics (ES + EN)\n";

  foreach ($node->getTranslationLanguages() as $lc => $_) {
    $node->getTranslation($lc)->setPublished()->save();
  }
}

drupal_flush_all_caches();
echo "\n✓ All project paragraphs rebuilt and bilingual.\n";
