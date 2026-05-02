<?php
/**
 * @file
 * Place the byte top-nav block in the byte theme's header region. Idempotent.
 */

use Drupal\block\Entity\Block;

if (Block::load('byte_navglass')) {
  echo "= block 'byte_navglass' exists (skipping)\n";
  return;
}

Block::create([
  'id'     => 'byte_navglass',
  'theme'  => 'byte',
  'region' => 'header',
  'plugin' => 'jalvarez_nav_glass',
  'weight' => -10,
  'status' => 1,
  'visibility' => [],
  'settings' => [
    'id'            => 'jalvarez_nav_glass',
    'label'         => 'Byte top navigation',
    'label_display' => '0',
    'provider'      => 'jalvarez_site',
  ],
])->save();
echo "✓ placed block 'byte_navglass' in byte:header\n";
