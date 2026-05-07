<?php

/**
 * @file
 * Idempotent Views builder for jalvarez.tech.
 *
 * Run via: ddev exec ./web/vendor/bin/drush php:script scripts/build-views.php
 *
 * Creates 7 views per docs/ARCHITECTURE.md §6:
 *   - projects (page /proyectos + block, filtros expuestos)
 *   - featured_projects (block, featured_home=1)
 *   - related_projects (block, contextual: project_category)
 *   - notes (page /notas, filter expuesto: note_topic)
 *   - recent_notes (block, latest 3)
 *   - related_notes (block, contextual: note_topic)
 *   - featured_testimonials (block, featured=1)
 */

use Drupal\views\Entity\View;

/**
 * Helper: build a View with sensible defaults; idempotent.
 */
function ensure_view(string $id, array $config): void {
  if (View::load($id)) {
    echo "= View exists: {$id} (skipping)\n";
    return;
  }
  View::create($config + ['id' => $id, 'status' => TRUE, 'core' => '11'])->save();
  echo "✓ View: {$id}\n";
}

/**
 * Common defaults shared by all displays.
 */
function default_display_options(array $overrides = []): array {
  return $overrides + [
    'access'  => ['type' => 'perm', 'options' => ['perm' => 'access content']],
    'cache'   => ['type' => 'tag', 'options' => []],
    'query'   => ['type' => 'views_query', 'options' => ['disable_sql_rewrite' => FALSE, 'distinct' => FALSE]],
    'exposed_form' => ['type' => 'basic', 'options' => ['submit_button' => 'Filtrar', 'reset_button' => TRUE, 'reset_button_label' => 'Limpiar']],
    'pager'   => ['type' => 'mini', 'options' => ['items_per_page' => 12, 'offset' => 0]],
    'style'   => ['type' => 'default', 'options' => ['grouping' => []]],
    'row'     => ['type' => 'fields'],
    'fields'  => [
      'title' => [
        'id' => 'title', 'table' => 'node_field_data', 'field' => 'title',
        'plugin_id' => 'field', 'entity_type' => 'node', 'entity_field' => 'title',
        'label' => '', 'settings' => ['link_to_entity' => TRUE],
      ],
    ],
    'filters' => [
      'status' => [
        'id' => 'status', 'table' => 'node_field_data', 'field' => 'status',
        'plugin_id' => 'boolean', 'entity_type' => 'node', 'entity_field' => 'status',
        'value' => '1',
      ],
    ],
    'sorts'   => [],
    'arguments' => [],
    'display_extenders' => [],
  ];
}

/**
 * Filter config for "type = X".
 */
function filter_type(string $bundle): array {
  return [
    'id' => 'type', 'table' => 'node_field_data', 'field' => 'type',
    'plugin_id' => 'bundle', 'entity_type' => 'node', 'entity_field' => 'type',
    'value' => [$bundle => $bundle],
  ];
}

/* ─────────────────────────────────────────────────────────────────────────
 * 1. projects (page /proyectos + block, filtros expuestos)
 * ───────────────────────────────────────────────────────────────────────── */
ensure_view('projects', [
  'label' => 'Proyectos',
  'base_table' => 'node_field_data',
  'base_field' => 'nid',
  'description' => 'Listado de proyectos del portafolio.',
  'tag' => 'jalvarez',
  'display' => [
    'default' => [
      'id' => 'default', 'display_title' => 'Default', 'display_plugin' => 'default',
      'position' => 0,
      'display_options' => default_display_options([
        'title' => 'Proyectos',
        'filters' => [
          'status' => [
            'id' => 'status', 'table' => 'node_field_data', 'field' => 'status',
            'plugin_id' => 'boolean', 'entity_type' => 'node', 'entity_field' => 'status',
            'value' => '1',
          ],
          'type' => filter_type('project'),
        ],
        'sorts' => [
          'field_sort_order_value' => [
            'id' => 'field_sort_order_value', 'table' => 'node__field_sort_order',
            'field' => 'field_sort_order_value', 'plugin_id' => 'standard',
            'order' => 'ASC',
          ],
        ],
        'pager' => ['type' => 'mini', 'options' => ['items_per_page' => 12]],
      ]),
    ],
    'page_1' => [
      'id' => 'page_1', 'display_title' => 'Page', 'display_plugin' => 'page', 'position' => 1,
      'display_options' => ['path' => 'proyectos'],
    ],
    'block_1' => [
      'id' => 'block_1', 'display_title' => 'Block', 'display_plugin' => 'block', 'position' => 2,
      'display_options' => ['block_description' => 'Listado de proyectos (block)'],
    ],
  ],
]);

/* ─────────────────────────────────────────────────────────────────────────
 * 2. featured_projects (block, featured_home=1)
 * ───────────────────────────────────────────────────────────────────────── */
ensure_view('featured_projects', [
  'label' => 'Proyectos destacados (home)',
  'base_table' => 'node_field_data',
  'base_field' => 'nid',
  'description' => 'Proyectos marcados como destacado en home.',
  'tag' => 'jalvarez',
  'display' => [
    'default' => [
      'id' => 'default', 'display_title' => 'Default', 'display_plugin' => 'default', 'position' => 0,
      'display_options' => default_display_options([
        'title' => 'Proyectos destacados',
        'filters' => [
          'status' => [
            'id' => 'status', 'table' => 'node_field_data', 'field' => 'status',
            'plugin_id' => 'boolean', 'entity_type' => 'node', 'entity_field' => 'status',
            'value' => '1',
          ],
          'type' => filter_type('project'),
          'field_featured_home_value' => [
            'id' => 'field_featured_home_value', 'table' => 'node__field_featured_home',
            'field' => 'field_featured_home_value', 'plugin_id' => 'boolean',
            'value' => '1',
          ],
        ],
        'sorts' => [
          'field_sort_order_value' => [
            'id' => 'field_sort_order_value', 'table' => 'node__field_sort_order',
            'field' => 'field_sort_order_value', 'plugin_id' => 'standard', 'order' => 'ASC',
          ],
        ],
        'pager' => ['type' => 'some', 'options' => ['items_per_page' => 3]],
      ]),
    ],
    'block_1' => [
      'id' => 'block_1', 'display_title' => 'Block', 'display_plugin' => 'block', 'position' => 1,
      'display_options' => ['block_description' => 'Featured projects (home)'],
    ],
  ],
]);

/* ─────────────────────────────────────────────────────────────────────────
 * 3. related_projects (block, contextual: misma categoría, excluye actual)
 * ───────────────────────────────────────────────────────────────────────── */
ensure_view('related_projects', [
  'label' => 'Proyectos relacionados',
  'base_table' => 'node_field_data',
  'base_field' => 'nid',
  'description' => 'Proyectos de la misma categoría, en detalle de proyecto.',
  'tag' => 'jalvarez',
  'display' => [
    'default' => [
      'id' => 'default', 'display_title' => 'Default', 'display_plugin' => 'default', 'position' => 0,
      'display_options' => default_display_options([
        'title' => 'Proyectos relacionados',
        'filters' => [
          'status' => [
            'id' => 'status', 'table' => 'node_field_data', 'field' => 'status',
            'plugin_id' => 'boolean', 'entity_type' => 'node', 'entity_field' => 'status',
            'value' => '1',
          ],
          'type' => filter_type('project'),
        ],
        'pager' => ['type' => 'some', 'options' => ['items_per_page' => 3]],
      ]),
    ],
    'block_1' => [
      'id' => 'block_1', 'display_title' => 'Block', 'display_plugin' => 'block', 'position' => 1,
      'display_options' => ['block_description' => 'Related projects'],
    ],
  ],
]);

/* ─────────────────────────────────────────────────────────────────────────
 * 4. notes (page /notas + block, filtro expuesto)
 * ───────────────────────────────────────────────────────────────────────── */
ensure_view('notes', [
  'label' => 'Notas',
  'base_table' => 'node_field_data',
  'base_field' => 'nid',
  'description' => 'Listado de artículos del blog.',
  'tag' => 'jalvarez',
  'display' => [
    'default' => [
      'id' => 'default', 'display_title' => 'Default', 'display_plugin' => 'default', 'position' => 0,
      'display_options' => default_display_options([
        'title' => 'Notas',
        'filters' => [
          'status' => [
            'id' => 'status', 'table' => 'node_field_data', 'field' => 'status',
            'plugin_id' => 'boolean', 'entity_type' => 'node', 'entity_field' => 'status',
            'value' => '1',
          ],
          'type' => filter_type('note'),
        ],
        'sorts' => [
          'field_publish_date_value' => [
            'id' => 'field_publish_date_value', 'table' => 'node__field_publish_date',
            'field' => 'field_publish_date_value', 'plugin_id' => 'standard', 'order' => 'DESC',
          ],
        ],
        'pager' => ['type' => 'mini', 'options' => ['items_per_page' => 10]],
      ]),
    ],
    'page_1' => [
      'id' => 'page_1', 'display_title' => 'Page', 'display_plugin' => 'page', 'position' => 1,
      'display_options' => ['path' => 'notas'],
    ],
  ],
]);

/* ─────────────────────────────────────────────────────────────────────────
 * 5. recent_notes (block, últimas 3)
 * ───────────────────────────────────────────────────────────────────────── */
ensure_view('recent_notes', [
  'label' => 'Notas recientes',
  'base_table' => 'node_field_data',
  'base_field' => 'nid',
  'description' => 'Últimas notas para home.',
  'tag' => 'jalvarez',
  'display' => [
    'default' => [
      'id' => 'default', 'display_title' => 'Default', 'display_plugin' => 'default', 'position' => 0,
      'display_options' => default_display_options([
        'title' => 'Notas recientes',
        'filters' => [
          'status' => [
            'id' => 'status', 'table' => 'node_field_data', 'field' => 'status',
            'plugin_id' => 'boolean', 'entity_type' => 'node', 'entity_field' => 'status',
            'value' => '1',
          ],
          'type' => filter_type('note'),
        ],
        'sorts' => [
          'field_publish_date_value' => [
            'id' => 'field_publish_date_value', 'table' => 'node__field_publish_date',
            'field' => 'field_publish_date_value', 'plugin_id' => 'standard', 'order' => 'DESC',
          ],
        ],
        'pager' => ['type' => 'some', 'options' => ['items_per_page' => 3]],
      ]),
    ],
    'block_1' => [
      'id' => 'block_1', 'display_title' => 'Block', 'display_plugin' => 'block', 'position' => 1,
      'display_options' => ['block_description' => 'Recent notes (home)'],
    ],
  ],
]);

/* ─────────────────────────────────────────────────────────────────────────
 * 6. related_notes (block, contextual: mismo topic)
 * ───────────────────────────────────────────────────────────────────────── */
ensure_view('related_notes', [
  'label' => 'Notas relacionadas',
  'base_table' => 'node_field_data',
  'base_field' => 'nid',
  'description' => 'Notas del mismo topic, en detalle de nota.',
  'tag' => 'jalvarez',
  'display' => [
    'default' => [
      'id' => 'default', 'display_title' => 'Default', 'display_plugin' => 'default', 'position' => 0,
      'display_options' => default_display_options([
        'title' => 'Notas relacionadas',
        'filters' => [
          'status' => [
            'id' => 'status', 'table' => 'node_field_data', 'field' => 'status',
            'plugin_id' => 'boolean', 'entity_type' => 'node', 'entity_field' => 'status',
            'value' => '1',
          ],
          'type' => filter_type('note'),
        ],
        'pager' => ['type' => 'some', 'options' => ['items_per_page' => 3]],
      ]),
    ],
    'block_1' => [
      'id' => 'block_1', 'display_title' => 'Block', 'display_plugin' => 'block', 'position' => 1,
      'display_options' => ['block_description' => 'Related notes'],
    ],
  ],
]);

/* ─────────────────────────────────────────────────────────────────────────
 * 7. featured_testimonials (block, featured=1)
 * ───────────────────────────────────────────────────────────────────────── */
ensure_view('featured_testimonials', [
  'label' => 'Testimonios destacados',
  'base_table' => 'node_field_data',
  'base_field' => 'nid',
  'description' => 'Citas de clientes marcadas como destacado.',
  'tag' => 'jalvarez',
  'display' => [
    'default' => [
      'id' => 'default', 'display_title' => 'Default', 'display_plugin' => 'default', 'position' => 0,
      'display_options' => default_display_options([
        'title' => 'Testimonios destacados',
        'filters' => [
          'status' => [
            'id' => 'status', 'table' => 'node_field_data', 'field' => 'status',
            'plugin_id' => 'boolean', 'entity_type' => 'node', 'entity_field' => 'status',
            'value' => '1',
          ],
          'type' => filter_type('testimonial'),
          'field_featured_value' => [
            'id' => 'field_featured_value', 'table' => 'node__field_featured',
            'field' => 'field_featured_value', 'plugin_id' => 'boolean', 'value' => '1',
          ],
        ],
        'sorts' => [
          'field_weight_value' => [
            'id' => 'field_weight_value', 'table' => 'node__field_weight',
            'field' => 'field_weight_value', 'plugin_id' => 'standard', 'order' => 'ASC',
          ],
        ],
        'pager' => ['type' => 'some', 'options' => ['items_per_page' => 5]],
      ]),
    ],
    'block_1' => [
      'id' => 'block_1', 'display_title' => 'Block', 'display_plugin' => 'block', 'position' => 1,
      'display_options' => ['block_description' => 'Featured testimonials'],
    ],
  ],
]);

\Drupal::service('cache.discovery')->invalidateAll();
drupal_flush_all_caches();
echo "\n✅ Views built.\n";
