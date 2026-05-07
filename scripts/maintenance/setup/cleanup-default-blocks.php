<?php
/**
 * @file
 * Disable default byte theme blocks that duplicate what byte:nav-glass
 * already renders. Idempotent.
 *
 * Blocks disabled: branding, admin (the "Tools / Administration" links).
 * Kept enabled: page_title, content, local_actions, local_tasks, messages.
 */

use Drupal\block\Entity\Block;

$disable = [
  'byte_branding',
  'byte_admin',
  'byte_local_actions',
  'byte_tools',
  'byte_page_title',
];
foreach ($disable as $id) {
  $block = Block::load($id);
  if (!$block) {
    echo "= block '{$id}' not found (skipping)\n";
    continue;
  }
  if ($block->status()) {
    $block->disable()->save();
    echo "✓ disabled '{$id}'\n";
  } else {
    echo "= '{$id}' already disabled\n";
  }
}
