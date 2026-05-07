<?php

/**
 * @file
 * Idempotent content model builder for jalvarez.tech.
 *
 * Run via: ddev exec ./web/vendor/bin/drush php:script scripts/build-content-model.php
 *
 * Creates: 3 vocabularies, 4 content types, 3 paragraph types, ~50 fields.
 * Source of truth: docs/ARCHITECTURE.md §3-5.
 */

use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\node\Entity\NodeType;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;

/* ─────────────────────────────────────────────────────────────────────────
 * 1. Vocabularies (taxonomies)
 * ───────────────────────────────────────────────────────────────────────── */
$vocabularies = [
  ['vid' => 'project_categories', 'name' => 'Categorías de proyecto', 'description' => 'Categorías temáticas de proyectos.'],
  ['vid' => 'technologies',       'name' => 'Tecnologías',            'description' => 'Stack y tecnologías usadas en proyectos.'],
  ['vid' => 'note_topics',        'name' => 'Temas de notas',         'description' => 'Temas para artículos del blog.'],
];
foreach ($vocabularies as $voc) {
  if (!Vocabulary::load($voc['vid'])) {
    Vocabulary::create($voc)->save();
    echo "✓ Vocabulary: {$voc['vid']}\n";
  }
}

/* ─────────────────────────────────────────────────────────────────────────
 * 2. Node types
 * ───────────────────────────────────────────────────────────────────────── */
$nodeTypes = [
  ['type' => 'project',     'name' => 'Proyecto',   'description' => 'Casos de estudio y proyectos del portafolio.'],
  ['type' => 'note',        'name' => 'Nota',       'description' => 'Artículos técnicos del blog.'],
  ['type' => 'testimonial', 'name' => 'Testimonio', 'description' => 'Citas de clientes reutilizables.'],
  ['type' => 'page',        'name' => 'Página',     'description' => 'Páginas Canvas de composición libre.'],
];
foreach ($nodeTypes as $nt) {
  if (!NodeType::load($nt['type'])) {
    NodeType::create([
      'type'        => $nt['type'],
      'name'        => $nt['name'],
      'description' => $nt['description'],
      'new_revision'=> TRUE,
      'preview_mode'=> 1,
    ])->save();
    echo "✓ Node type: {$nt['type']}\n";
  }
}

/* ─────────────────────────────────────────────────────────────────────────
 * 3. Paragraph types
 * ───────────────────────────────────────────────────────────────────────── */
$paragraphTypes = [
  ['id' => 'case_step',           'label' => 'Paso de caso (case_step)'],
  ['id' => 'metric',              'label' => 'Métrica (metric)'],
  ['id' => 'testimonial_embedded','label' => 'Testimonio embebido (testimonial_embedded)'],
];
foreach ($paragraphTypes as $pt) {
  if (!ParagraphsType::load($pt['id'])) {
    ParagraphsType::create($pt)->save();
    echo "✓ Paragraph type: {$pt['id']}\n";
  }
}

/* ─────────────────────────────────────────────────────────────────────────
 * 4. Helper to add field storage + field instance
 * ───────────────────────────────────────────────────────────────────────── */
function add_field(string $entity, string $bundle, string $name, string $type, string $label, array $opts = []): void {
  $cardinality   = $opts['cardinality']     ?? 1;
  $required      = $opts['required']        ?? FALSE;
  $storage_extra = $opts['storage_settings'] ?? [];
  $field_extra   = $opts['field_settings']   ?? [];

  $storage = FieldStorageConfig::loadByName($entity, $name);
  if (!$storage) {
    $storage = FieldStorageConfig::create([
      'field_name'  => $name,
      'entity_type' => $entity,
      'type'        => $type,
      'cardinality' => $cardinality,
      'settings'    => $storage_extra,
    ]);
    $storage->save();
  }

  $field = FieldConfig::loadByName($entity, $bundle, $name);
  if (!$field) {
    FieldConfig::create([
      'field_name'  => $name,
      'entity_type' => $entity,
      'bundle'      => $bundle,
      'label'       => $label,
      'required'    => $required,
      'settings'    => $field_extra,
    ])->save();
    echo "  + {$entity}.{$bundle}.{$name} ({$type})\n";
  }
}

/* ─────────────────────────────────────────────────────────────────────────
 * 5. Fields — Paragraph case_step
 * ───────────────────────────────────────────────────────────────────────── */
echo "Fields → paragraph.case_step\n";
add_field('paragraph', 'case_step', 'field_step_tag',   'string',     'Tag');
add_field('paragraph', 'case_step', 'field_step_title', 'string',     'Título', ['required' => TRUE]);
add_field('paragraph', 'case_step', 'field_step_body',  'text_long',  'Cuerpo');

/* ─────────────────────────────────────────────────────────────────────────
 * 6. Fields — Paragraph metric
 * ───────────────────────────────────────────────────────────────────────── */
echo "Fields → paragraph.metric\n";
add_field('paragraph', 'metric', 'field_metric_key',   'string',    'Clave',  ['required' => TRUE]);
add_field('paragraph', 'metric', 'field_metric_value', 'string',    'Valor',  ['required' => TRUE]);
add_field('paragraph', 'metric', 'field_metric_note',  'string',    'Nota');

/* ─────────────────────────────────────────────────────────────────────────
 * 7. Fields — Paragraph testimonial_embedded
 * ───────────────────────────────────────────────────────────────────────── */
echo "Fields → paragraph.testimonial_embedded\n";
add_field('paragraph', 'testimonial_embedded', 'field_quote',           'text_long', 'Cita',     ['required' => TRUE]);
add_field('paragraph', 'testimonial_embedded', 'field_author_name',     'string',    'Autor');
add_field('paragraph', 'testimonial_embedded', 'field_author_role',     'string',    'Rol / empresa');
add_field('paragraph', 'testimonial_embedded', 'field_author_initials', 'string',    'Iniciales (avatar fallback)');

/* ─────────────────────────────────────────────────────────────────────────
 * 8. Fields — Node project
 * ───────────────────────────────────────────────────────────────────────── */
echo "Fields → node.project\n";
// Identidad
add_field('node', 'project', 'field_summary',      'text_long',  'Resumen (card)');
add_field('node', 'project', 'field_intro',        'text_long',  'Introducción');
add_field('node', 'project', 'field_publish_date', 'datetime',   'Fecha de publicación', [
  'storage_settings' => ['datetime_type' => 'date'],
]);
// Clasificación
add_field('node', 'project', 'field_project_category', 'entity_reference', 'Categoría', [
  'storage_settings' => ['target_type' => 'taxonomy_term'],
  'field_settings'   => ['handler' => 'default:taxonomy_term', 'handler_settings' => ['target_bundles' => ['project_categories' => 'project_categories']]],
]);
add_field('node', 'project', 'field_primary_technology', 'entity_reference', 'Tecnología principal', [
  'storage_settings' => ['target_type' => 'taxonomy_term'],
  'field_settings'   => ['handler' => 'default:taxonomy_term', 'handler_settings' => ['target_bundles' => ['technologies' => 'technologies']]],
]);
add_field('node', 'project', 'field_stack', 'entity_reference', 'Stack', [
  'cardinality'      => -1,
  'storage_settings' => ['target_type' => 'taxonomy_term'],
  'field_settings'   => ['handler' => 'default:taxonomy_term', 'handler_settings' => ['target_bundles' => ['technologies' => 'technologies']]],
]);
add_field('node', 'project', 'field_project_year', 'integer',  'Año');
add_field('node', 'project', 'field_role',         'string',   'Rol');
add_field('node', 'project', 'field_duration',     'string',   'Duración');
add_field('node', 'project', 'field_external_url', 'link',     'URL externa');
add_field('node', 'project', 'field_featured_home','boolean',  'Destacado en home');
add_field('node', 'project', 'field_sort_order',   'integer',  'Peso (orden)');
// Portada / visual
add_field('node', 'project', 'field_cover_media', 'entity_reference', 'Cover media', [
  'storage_settings' => ['target_type' => 'media'],
]);
add_field('node', 'project', 'field_cover_variant', 'list_string', 'Cover variant', [
  'storage_settings' => ['allowed_values' => ['a' => 'A', 'b' => 'B', 'c' => 'C']],
]);
add_field('node', 'project', 'field_cover_hue', 'list_string', 'Cover hue', [
  'storage_settings' => ['allowed_values' => [
    'green' => 'Verde', 'orange' => 'Naranja', 'blue' => 'Azul',
    'purple' => 'Púrpura', 'red' => 'Rojo', 'amber' => 'Ámbar',
  ]],
]);
add_field('node', 'project', 'field_gallery', 'entity_reference', 'Galería', [
  'cardinality'      => -1,
  'storage_settings' => ['target_type' => 'media'],
]);
// Caso de estudio
add_field('node', 'project', 'field_challenge_intro',   'text_long',  'Desafío — intro');
add_field('node', 'project', 'field_challenge_bullets', 'string_long','Desafío — bullets', ['cardinality' => -1]);
add_field('node', 'project', 'field_approach_steps', 'entity_reference_revisions', 'Enfoque — pasos', [
  'cardinality'      => -1,
  'storage_settings' => ['target_type' => 'paragraph'],
  'field_settings'   => ['handler' => 'default:paragraph', 'handler_settings' => ['target_bundles' => ['case_step' => 'case_step']]],
]);
add_field('node', 'project', 'field_results_metrics', 'entity_reference_revisions', 'Resultados — métricas', [
  'cardinality'      => -1,
  'storage_settings' => ['target_type' => 'paragraph'],
  'field_settings'   => ['handler' => 'default:paragraph', 'handler_settings' => ['target_bundles' => ['metric' => 'metric']]],
]);
add_field('node', 'project', 'field_lesson', 'text_long', 'Aprendizaje');
add_field('node', 'project', 'field_testimonial_embed', 'entity_reference_revisions', 'Testimonio embebido', [
  'storage_settings' => ['target_type' => 'paragraph'],
  'field_settings'   => ['handler' => 'default:paragraph', 'handler_settings' => ['target_bundles' => ['testimonial_embedded' => 'testimonial_embedded']]],
]);
// CTA
add_field('node', 'project', 'field_cta_heading', 'string',    'CTA heading');
add_field('node', 'project', 'field_cta_sub',     'text_long', 'CTA sub');

/* ─────────────────────────────────────────────────────────────────────────
 * 9. Fields — Node note
 * ───────────────────────────────────────────────────────────────────────── */
echo "Fields → node.note\n";
add_field('node', 'note', 'body',                 'text_with_summary', 'Cuerpo');
add_field('node', 'note', 'field_excerpt',        'text_long',         'Excerpt');
add_field('node', 'note', 'field_publish_date',   'datetime',          'Fecha de publicación', ['storage_settings' => ['datetime_type' => 'date']]);
add_field('node', 'note', 'field_note_topic', 'entity_reference', 'Tema', [
  'storage_settings' => ['target_type' => 'taxonomy_term'],
  'field_settings'   => ['handler' => 'default:taxonomy_term', 'handler_settings' => ['target_bundles' => ['note_topics' => 'note_topics']]],
]);
add_field('node', 'note', 'field_note_tags', 'entity_reference', 'Etiquetas', [
  'cardinality'      => -1,
  'storage_settings' => ['target_type' => 'taxonomy_term'],
  'field_settings'   => ['handler' => 'default:taxonomy_term', 'handler_settings' => ['target_bundles' => ['note_topics' => 'note_topics']]],
]);
add_field('node', 'note', 'field_featured_media', 'entity_reference', 'Imagen destacada', [
  'storage_settings' => ['target_type' => 'media'],
]);
add_field('node', 'note', 'field_thumb_glyph', 'list_string', 'Thumb glyph', [
  'storage_settings' => ['allowed_values' => [
    'gauge' => 'Gauge', 'layers' => 'Layers', 'workflow' => 'Workflow',
    'accessibility' => 'Accessibility', 'mail' => 'Mail', 'calendar' => 'Calendar',
  ]],
]);
add_field('node', 'note', 'field_thumb_hue', 'list_string', 'Thumb hue', [
  'storage_settings' => ['allowed_values' => [
    'green' => 'Verde', 'orange' => 'Naranja', 'blue' => 'Azul', 'purple' => 'Púrpura',
  ]],
]);

/* ─────────────────────────────────────────────────────────────────────────
 * 10. Fields — Node testimonial (standalone CT)
 * ───────────────────────────────────────────────────────────────────────── */
echo "Fields → node.testimonial\n";
add_field('node', 'testimonial', 'field_quote',           'text_long', 'Cita', ['required' => TRUE]);
add_field('node', 'testimonial', 'field_author_name',     'string',    'Autor', ['required' => TRUE]);
add_field('node', 'testimonial', 'field_author_role',     'string',    'Rol / empresa');
add_field('node', 'testimonial', 'field_author_initials', 'string',    'Iniciales');
add_field('node', 'testimonial', 'field_author_avatar',   'entity_reference', 'Avatar', [
  'storage_settings' => ['target_type' => 'media'],
]);
add_field('node', 'testimonial', 'field_related_project', 'entity_reference', 'Proyecto relacionado', [
  'storage_settings' => ['target_type' => 'node'],
  'field_settings'   => ['handler' => 'default:node', 'handler_settings' => ['target_bundles' => ['project' => 'project']]],
]);
add_field('node', 'testimonial', 'field_featured', 'boolean',  'Destacado');
add_field('node', 'testimonial', 'field_weight',   'integer',  'Peso');

/* ─────────────────────────────────────────────────────────────────────────
 * 11. Fields — Node page (Canvas)
 * ───────────────────────────────────────────────────────────────────────── */
echo "Fields → node.page\n";
add_field('node', 'page', 'body', 'text_with_summary', 'Cuerpo (opcional)');
// field_canvas: drupal/canvas provides its own field type.
// Skipping for now; will add when we wire up Canvas pages in Phase F.

/* ─────────────────────────────────────────────────────────────────────────
 * 12. Enable content translation
 * ───────────────────────────────────────────────────────────────────────── */
echo "Enable content_translation per bundle\n";
$ct_storage = \Drupal::service('content_translation.manager');
foreach (['node' => ['project', 'note', 'testimonial', 'page'],
          'paragraph' => ['case_step', 'metric', 'testimonial_embedded'],
          'taxonomy_term' => ['project_categories', 'technologies', 'note_topics']] as $entity_type => $bundles) {
  foreach ($bundles as $bundle) {
    $ct_storage->setEnabled($entity_type, $bundle, TRUE);
    echo "  ✓ translate {$entity_type}.{$bundle}\n";
  }
}

\Drupal::service('cache.discovery')->invalidateAll();
drupal_flush_all_caches();
echo "\n✅ Content model built.\n";
