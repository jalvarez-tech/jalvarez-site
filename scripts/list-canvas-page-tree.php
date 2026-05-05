<?php
/**
 * @file
 * Inspect the components tree of a specific canvas_page translation, with
 * UUID, component_id, parent/slot, and a peek at each input value. Use this
 * to find the UUID + prop name for scripts/edit-canvas-component-en.php.
 *
 * Different from scripts/list-canvas-components.php — that one lists the
 * Component config-entity registry (component types). This one inspects a
 * specific page's tree of component instances.
 *
 * USAGE
 * -----
 *   ddev exec ./web/vendor/bin/drush php:script scripts/list-canvas-page-tree.php
 *
 * Edit CONFIG below to target a different page or language.
 */

// ─── CONFIG ───
$page_alias = '/inicio';   // /inicio /proyectos /notas /contacto
$language   = 'en';        // 'es' or 'en'

// ─── Resolution ───
$home_path = \Drupal::service('path_alias.manager')->getPathByAlias($page_alias);
if (!preg_match('#^/page/(\d+)$#', $home_path, $m)) {
  fwrite(STDERR, "✗ Could not resolve '{$page_alias}' to a canvas_page.\n");
  exit(1);
}
$page_id = (int) $m[1];

$page = \Drupal\canvas\Entity\Page::load($page_id);
if (!$page->hasTranslation($language)) {
  fwrite(STDERR, "✗ Page {$page_id} has no '{$language}' translation.\n");
  exit(1);
}
$trans = $page->getTranslation($language);

echo "canvas_page id={$page_id} title='{$trans->label()}' lang={$language}\n";
echo str_repeat('─', 80) . "\n";

foreach ($trans->get('components')->getValue() as $i => $row) {
  $uuid = $row['uuid'];
  $cid  = $row['component_id'];
  $parent = $row['parent_uuid'] ?? null;
  $slot = $row['slot'] ?? null;

  $inputs = json_decode($row['inputs'], TRUE) ?? [];
  // SDC inputs are {sourceType, value, expression}; block inputs are flat.
  $preview = [];
  foreach ($inputs as $prop => $entry) {
    $val = (is_array($entry) && array_key_exists('value', $entry))
      ? $entry['value']
      : $entry;
    if (is_string($val) && strlen($val) > 60) {
      $val = substr($val, 0, 57) . '...';
    } elseif (is_array($val) || is_object($val)) {
      $val = '<complex>';
    } elseif (is_bool($val)) {
      $val = $val ? 'true' : 'false';
    }
    $preview[$prop] = $val;
  }

  $indent = $parent ? '  └─ ' : '';
  $slot_label = $slot ? " [slot:{$slot}]" : '';
  echo sprintf("%s[%d] %s\n", $indent, $i, $cid);
  echo sprintf("    uuid: %s%s\n", $uuid, $slot_label);
  if ($preview) {
    foreach ($preview as $prop => $val) {
      echo sprintf("    %s: %s\n", $prop, var_export($val, TRUE));
    }
  }
  echo "\n";
}
