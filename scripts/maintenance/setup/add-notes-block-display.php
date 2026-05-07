<?php
/**
 * @file
 * Add a `block_1` display to the `notes` View so Canvas can pick it up as
 * `block.views_block.notes-block_1`. Mirrors the projects view structure.
 *
 * Idempotent.
 */

use Drupal\views\Entity\View;

$view = View::load('notes');
if (!$view) {
  echo "  ✗ View 'notes' not found.\n";
  return;
}

$displays = $view->get('display');
if (isset($displays['block_1'])) {
  echo "  = block_1 display already exists in notes view.\n";
  return;
}

$displays['block_1'] = [
  'id' => 'block_1',
  'display_title' => 'Block',
  'display_plugin' => 'block',
  'position' => 1,
  'display_options' => [
    'display_extenders' => [],
    'block_description' => 'Listado de notas (block)',
  ],
  'cache_metadata' => [
    'max-age' => -1,
    'contexts' => [
      'languages:language_content',
      'languages:language_interface',
      'url.query_args',
      'user.node_grants:view',
      'user.permissions',
    ],
    'tags' => [],
  ],
];

$view->set('display', $displays);
$view->save();

echo "  ✓ Added block_1 display to view 'notes'\n";

drupal_flush_all_caches();
echo "  ✓ Caches flushed\n";
