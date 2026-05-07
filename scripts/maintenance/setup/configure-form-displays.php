<?php
/**
 * @file
 * Configure user-friendly form displays for node.project, node.note and
 * node.page bundles using field_group (collapsible tabs / accordions).
 *
 * - project: 25 campos organizados en 6 grupos (vertical tabs).
 * - note: 9 campos organizados en 3 grupos.
 * - page: body + field_canvas (component_tree) ambos visibles, con el campo
 *   Canvas usando su widget oficial para abrir el editor visual.
 *
 * Idempotente: borra los grupos existentes y los recrea.
 */

use Drupal\Core\Entity\Entity\EntityFormDisplay;

/**
 * Apply a flat map of components onto a form display, then save it.
 *
 * @param string $bundle
 * @param array<string, array<string, mixed>> $components
 *   Field name → ['type'=>widget, 'weight'=>n, 'settings'=>[], 'group'=>'group_id', 'region'=>'content']
 * @param array<string, array<string, mixed>> $groups
 *   Group ID → ['label'=>..., 'weight'=>n, 'children'=>[fields], 'format_type'=>'tab|details|html_element', 'format_settings'=>[]]
 * @param array<string> $hidden Field names that should remain hidden.
 */
function build_form_display(string $bundle, array $components, array $groups, array $hidden = []): void {
  $form = EntityFormDisplay::load("node.{$bundle}.default");
  if (!$form) {
    $form = EntityFormDisplay::create([
      'targetEntityType' => 'node',
      'bundle' => $bundle,
      'mode' => 'default',
      'status' => TRUE,
    ]);
  }

  // Wipe existing components and groups so this script is idempotent.
  foreach (array_keys($form->getComponents()) as $name) {
    $form->removeComponent($name);
  }
  foreach (array_keys((array) $form->getThirdPartySettings('field_group')) as $group_id) {
    $form->unsetThirdPartySetting('field_group', $group_id);
  }

  // Common base components (title, status, langcode, etc.) — always shown.
  $base = [
    'title' => ['type' => 'string_textfield', 'weight' => -10, 'settings' => ['size' => 60]],
    'langcode' => ['type' => 'language_select', 'weight' => -9, 'settings' => ['include_locked' => TRUE]],
    'status' => ['type' => 'boolean_checkbox', 'weight' => 110, 'settings' => ['display_label' => TRUE]],
    'created' => ['type' => 'datetime_timestamp', 'weight' => 105],
    'uid' => [
      'type' => 'entity_reference_autocomplete', 'weight' => 106,
      'settings' => ['match_operator' => 'CONTAINS', 'match_limit' => 10, 'size' => 60],
    ],
    'path' => ['type' => 'path', 'weight' => 100],
    'promote' => ['type' => 'boolean_checkbox', 'weight' => 115, 'settings' => ['display_label' => TRUE]],
    'sticky' => ['type' => 'boolean_checkbox', 'weight' => 116, 'settings' => ['display_label' => TRUE]],
    // The translation widget (UI for translations of fields) sits in a region too.
    'translation' => ['weight' => 99, 'region' => 'content'],
    'url_redirects' => ['weight' => 101, 'region' => 'content'],
  ];
  foreach ($base as $name => $spec) {
    $form->setComponent($name, [
      'type' => $spec['type'] ?? '',
      'weight' => $spec['weight'],
      'region' => $spec['region'] ?? 'content',
      'settings' => $spec['settings'] ?? [],
      'third_party_settings' => [],
    ]);
  }

  // Set declared components.
  foreach ($components as $name => $spec) {
    $form->setComponent($name, [
      'type' => $spec['type'],
      'weight' => $spec['weight'],
      'region' => $spec['region'] ?? 'content',
      'settings' => $spec['settings'] ?? [],
      'third_party_settings' => $spec['third_party_settings'] ?? [],
    ]);
  }

  // Hidden fields.
  foreach ($hidden as $name) {
    $form->removeComponent($name);
  }

  // Apply field_group groups.
  $weight = 0;
  foreach ($groups as $group_id => $group) {
    $form->setThirdPartySetting('field_group', $group_id, [
      'children' => $group['children'],
      'parent_name' => $group['parent'] ?? '',
      'weight' => $group['weight'] ?? $weight++,
      'format_type' => $group['format_type'] ?? 'details',
      'format_settings' => $group['format_settings'] ?? [
        'classes' => '',
        'show_empty_fields' => TRUE,
        'id' => '',
        'open' => TRUE,
        'description' => '',
        'required_fields' => TRUE,
      ],
      'label' => $group['label'],
      'region' => 'content',
    ]);
  }

  $form->save();
  echo "  ✓ form display node.{$bundle}.default updated\n";
}

// ---------------------------------------------------------------------------
// node.project — 25 fields in 6 fieldsets (collapsible details).
// ---------------------------------------------------------------------------
echo "\n── node.project ──\n";

// Helper: standard format_settings for `details` (collapsible fieldset).
$details_settings = function (string $description, bool $open = FALSE, bool $required = FALSE): array {
  return [
    'classes' => '',
    'show_empty_fields' => TRUE,
    'id' => '',
    'open' => $open,
    'description' => $description,
    'required_fields' => $required,
  ];
};

build_form_display(
  'project',
  components: [
    // Identidad
    'field_summary' => ['type' => 'text_textarea', 'weight' => 1, 'settings' => ['rows' => 3, 'placeholder' => '']],
    'field_intro' => ['type' => 'text_textarea', 'weight' => 2, 'settings' => ['rows' => 5, 'placeholder' => '']],
    'field_publish_date' => ['type' => 'datetime_default', 'weight' => 3],

    // Clasificación
    'field_project_category' => ['type' => 'options_select', 'weight' => 10],
    'field_primary_technology' => ['type' => 'options_select', 'weight' => 11],
    'field_stack' => ['type' => 'entity_reference_autocomplete_tags', 'weight' => 12, 'settings' => ['match_operator' => 'CONTAINS', 'match_limit' => 10, 'size' => 60, 'placeholder' => '']],
    'field_project_year' => ['type' => 'number', 'weight' => 13, 'settings' => ['placeholder' => '']],
    'field_role' => ['type' => 'string_textfield', 'weight' => 14, 'settings' => ['size' => 60]],
    'field_duration' => ['type' => 'string_textfield', 'weight' => 15, 'settings' => ['size' => 60]],
    'field_external_url' => ['type' => 'link_default', 'weight' => 16, 'settings' => ['placeholder_url' => 'https://', 'placeholder_title' => '']],
    'field_featured_home' => ['type' => 'boolean_checkbox', 'weight' => 17, 'settings' => ['display_label' => TRUE]],
    'field_sort_order' => ['type' => 'number', 'weight' => 18],

    // Visual
    'field_cover_media' => ['type' => 'media_library_widget', 'weight' => 20, 'settings' => ['media_types' => []]],
    'field_cover_variant' => ['type' => 'options_select', 'weight' => 21],
    'field_cover_hue' => ['type' => 'options_select', 'weight' => 22],
    'field_gallery' => ['type' => 'media_library_widget', 'weight' => 23, 'settings' => ['media_types' => []]],

    // Caso de estudio
    'field_challenge_intro' => ['type' => 'text_textarea', 'weight' => 30, 'settings' => ['rows' => 4]],
    'field_challenge_bullets' => ['type' => 'string_textarea', 'weight' => 31, 'settings' => ['rows' => 4]],
    'field_approach_steps' => ['type' => 'paragraphs', 'weight' => 32, 'settings' => [
      'title' => 'Paso',
      'title_plural' => 'Pasos',
      'edit_mode' => 'open',
      'closed_mode' => 'summary',
      'autocollapse' => 'none',
      'add_mode' => 'dropdown',
      'form_display_mode' => 'default',
      'default_paragraph_type' => '_none',
      'features' => ['collapse_edit_all' => 'collapse_edit_all', 'duplicate' => 'duplicate'],
    ]],
    'field_results_metrics' => ['type' => 'paragraphs', 'weight' => 33, 'settings' => [
      'title' => 'Métrica',
      'title_plural' => 'Métricas',
      'edit_mode' => 'open',
      'closed_mode' => 'summary',
      'autocollapse' => 'none',
      'add_mode' => 'dropdown',
      'form_display_mode' => 'default',
      'default_paragraph_type' => '_none',
      'features' => ['collapse_edit_all' => 'collapse_edit_all', 'duplicate' => 'duplicate'],
    ]],
    'field_lesson' => ['type' => 'text_textarea', 'weight' => 34, 'settings' => ['rows' => 5]],
    'field_testimonial_embed' => ['type' => 'paragraphs', 'weight' => 35, 'settings' => [
      'title' => 'Testimonio',
      'title_plural' => 'Testimonios',
      'edit_mode' => 'open',
      'closed_mode' => 'summary',
      'autocollapse' => 'none',
      'add_mode' => 'dropdown',
      'form_display_mode' => 'default',
      'default_paragraph_type' => '_none',
      'features' => ['collapse_edit_all' => 'collapse_edit_all', 'duplicate' => 'duplicate'],
    ]],

    // CTA final
    'field_cta_heading' => ['type' => 'string_textfield', 'weight' => 40, 'settings' => ['size' => 60]],
    'field_cta_sub' => ['type' => 'text_textarea', 'weight' => 41, 'settings' => ['rows' => 3]],
  ],
  groups: [
    'group_identity' => [
      'label' => '① Identidad',
      'children' => ['field_summary', 'field_intro', 'field_publish_date'],
      'format_type' => 'details',
      'format_settings' => $details_settings('Resumen visible en el card del listado, intro completa de la página de detalle, y fecha de publicación.', open: TRUE),
      'weight' => 1,
    ],
    'group_classification' => [
      'label' => '② Clasificación',
      'children' => ['field_project_category', 'field_primary_technology', 'field_stack', 'field_project_year', 'field_role', 'field_duration', 'field_external_url', 'field_featured_home', 'field_sort_order'],
      'format_type' => 'details',
      'format_settings' => $details_settings('Categoría, tecnología principal, stack completo, año, rol, duración, URL externa, peso de orden y flag “destacado en home”.', open: FALSE),
      'weight' => 2,
    ],
    'group_visual' => [
      'label' => '③ Visual',
      'children' => ['field_cover_media', 'field_cover_variant', 'field_cover_hue', 'field_gallery'],
      'format_type' => 'details',
      'format_settings' => $details_settings('Cover (imagen o video) + variante + hue del placeholder, y galería para la página de detalle.', open: FALSE),
      'weight' => 3,
    ],
    'group_case_study' => [
      'label' => '④ Caso de estudio',
      'children' => ['field_challenge_intro', 'field_challenge_bullets', 'field_approach_steps', 'field_results_metrics', 'field_lesson', 'field_testimonial_embed'],
      'format_type' => 'details',
      'format_settings' => $details_settings('§01 Reto (intro + bullets) · §02 Enfoque (pasos) · §03 Resultados (métricas) · §04 Lección + testimonio embebido.', open: FALSE),
      'weight' => 4,
    ],
    'group_cta' => [
      'label' => '⑤ CTA final',
      'children' => ['field_cta_heading', 'field_cta_sub'],
      'format_type' => 'details',
      'format_settings' => $details_settings('Heading y subtítulo del bloque de cierre al final del case study.', open: FALSE),
      'weight' => 5,
    ],
    'group_publishing' => [
      'label' => '⑥ Publicación',
      'children' => ['status', 'created', 'uid', 'path', 'url_redirects', 'promote', 'sticky', 'langcode', 'translation'],
      'format_type' => 'details',
      'format_settings' => $details_settings('Estado, autor, fecha de creación, URL alias, redirects, idioma y ajustes de traducción.', open: FALSE),
      'weight' => 6,
    ],
  ],
);

// ---------------------------------------------------------------------------
// node.note — 9 fields in 4 fieldsets (collapsible details).
// ---------------------------------------------------------------------------
echo "\n── node.note ──\n";

build_form_display(
  'note',
  components: [
    'body' => ['type' => 'text_textarea_with_summary', 'weight' => 5, 'settings' => ['rows' => 12, 'summary_rows' => 3, 'placeholder' => '', 'show_summary' => FALSE]],
    'field_excerpt' => ['type' => 'text_textarea', 'weight' => 1, 'settings' => ['rows' => 3]],
    'field_publish_date' => ['type' => 'datetime_default', 'weight' => 2],

    'field_note_topic' => ['type' => 'options_select', 'weight' => 10],
    'field_note_tags' => ['type' => 'entity_reference_autocomplete_tags', 'weight' => 11, 'settings' => ['match_operator' => 'CONTAINS', 'match_limit' => 10, 'size' => 60, 'placeholder' => '']],

    'field_featured_media' => ['type' => 'media_library_widget', 'weight' => 20, 'settings' => ['media_types' => []]],
    'field_thumb_glyph' => ['type' => 'options_select', 'weight' => 21],
    'field_thumb_hue' => ['type' => 'options_select', 'weight' => 22],
  ],
  groups: [
    'group_identity' => [
      'label' => '① Identidad',
      'children' => ['field_excerpt', 'body', 'field_publish_date'],
      'format_type' => 'details',
      'format_settings' => $details_settings('Excerpt para el listado, cuerpo principal del artículo y fecha de publicación.', open: TRUE),
      'weight' => 1,
    ],
    'group_classification' => [
      'label' => '② Clasificación',
      'children' => ['field_note_topic', 'field_note_tags'],
      'format_type' => 'details',
      'format_settings' => $details_settings('Tema principal (vocabulario note_topics) y etiquetas adicionales.', open: FALSE),
      'weight' => 2,
    ],
    'group_visual' => [
      'label' => '③ Visual',
      'children' => ['field_featured_media', 'field_thumb_glyph', 'field_thumb_hue'],
      'format_type' => 'details',
      'format_settings' => $details_settings('Imagen destacada o, en su defecto, glifo + hue OKLCH para el thumbnail del listado.', open: FALSE),
      'weight' => 3,
    ],
    'group_publishing' => [
      'label' => '④ Publicación',
      'children' => ['status', 'created', 'uid', 'path', 'url_redirects', 'promote', 'sticky', 'langcode', 'translation'],
      'format_type' => 'details',
      'format_settings' => $details_settings('Estado, autor, fecha de creación, URL alias, redirects, idioma y ajustes de traducción.', open: FALSE),
      'weight' => 4,
    ],
  ],
);

// ---------------------------------------------------------------------------
// node.page — body editable; field_canvas stays hidden (no widget plugin —
// Canvas opens via /canvas/editor/node/{nid} accessible from the local task tab
// "Editor visual (Canvas)" added by jalvarez_site module).
// ---------------------------------------------------------------------------
echo "\n── node.page ──\n";

build_form_display(
  'page',
  components: [
    'body' => ['type' => 'text_textarea_with_summary', 'weight' => 2, 'settings' => ['rows' => 6, 'summary_rows' => 3, 'placeholder' => '', 'show_summary' => FALSE]],
  ],
  groups: [
    'group_basics' => [
      'label' => '① Básicos',
      'children' => ['body'],
      'format_type' => 'details',
      'format_settings' => $details_settings('Body es un fallback opcional. La composición visual se edita desde la pestaña “Editor visual (Canvas)” en la canónica del nodo.', open: TRUE),
      'weight' => 1,
    ],
    'group_publishing' => [
      'label' => '② Publicación',
      'children' => ['status', 'created', 'uid', 'path', 'url_redirects', 'promote', 'sticky', 'langcode', 'translation'],
      'format_type' => 'details',
      'format_settings' => $details_settings('Estado, autor, fecha, URL alias, redirects, idioma y ajustes de traducción.', open: FALSE),
      'weight' => 2,
    ],
  ],
  hidden: ['field_canvas'],
);

drupal_flush_all_caches();
echo "\n✅ Form displays configurados (project, note, page).\n";
echo "   Visita /admin/structure/types/manage/<bundle>/form-display para ajustar.\n";
echo "   Editar nodo: /node/<nid>/edit muestra los campos en pestañas verticales.\n";
echo "   Editar Canvas tree: el widget canvas_default en field_canvas abre el editor visual.\n";
