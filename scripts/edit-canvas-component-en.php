<?php
/**
 * @file
 * Edit a single component instance's input on the EN translation of a
 * canvas_page. Use this when you need to change a string/value INSIDE the
 * component tree of an alternate translation — Canvas 1.3.x's visual editor
 * doesn't expose translations, so we do it programmatically.
 *
 * USAGE
 * -----
 * 1. Identify the page via its alias (resolves portably across envs):
 *      /inicio   → Inicio
 *      /home     → Inicio (EN view)
 *      /proyectos / /projects, /notas / /notes, /contacto / /contact
 *
 * 2. Identify the component instance UUID. Easiest: open
 *      /admin/content/pages → page → Edit (visual editor)
 *      → click on the component you want to edit → the right panel shows
 *      its UUID in the URL: `?component=<uuid>`. Or query via drush:
 *
 *      drush php:eval '$p=\Drupal\canvas\Entity\Page::load(8); foreach ($p->getTranslation("en")->get("components") as $i) print $i->uuid . " -> " . $i->component_id . "\n";'
 *
 * 3. Edit this file (CONFIG section below) and run:
 *      ddev exec ./web/vendor/bin/drush php:script scripts/edit-canvas-component-en.php
 *
 *    Or in production via:
 *      gh workflow run seed-content.yml --field script=scripts/edit-canvas-component-en.php
 *
 * The script ONLY touches the EN translation of the target component. The
 * defensive translation-wipe guard hook in jalvarez_site.module covers any
 * sibling translation, so an accidental wipe can't slip through.
 */

// ─── CONFIG — edit these to target your change ───
$page_alias    = '/inicio';      // Use the ES alias to resolve the entity.
$component_uuid = 'PASTE_UUID_HERE';
$prop_name     = 'title_a';      // Which prop on the SDC to update.
$new_value     = 'I believe a website';

// ─── Resolution + diff loop ───
$home_path = \Drupal::service('path_alias.manager')->getPathByAlias($page_alias);
if (!preg_match('#^/page/(\d+)$#', $home_path, $m)) {
  fwrite(STDERR, "✗ Could not resolve '{$page_alias}' to a canvas_page.\n");
  exit(1);
}
$page_id = (int) $m[1];

$page = \Drupal\canvas\Entity\Page::load($page_id);
if (!$page->hasTranslation('en')) {
  fwrite(STDERR, "✗ Page {$page_id} has no EN translation. Run scripts/restore-canvas-home-en.php first.\n");
  exit(1);
}
$en = $page->getTranslation('en');
echo "→ Target: canvas_page id={$page_id} translation=en\n";

// Find the component instance by UUID and rewrite its `inputs` JSON.
$tree = $en->get('components')->getValue();
$found = FALSE;
foreach ($tree as $i => $row) {
  if (($row['uuid'] ?? '') !== $component_uuid) {
    continue;
  }
  $found = TRUE;
  $inputs = json_decode($row['inputs'], TRUE) ?? [];
  $old = $inputs[$prop_name]['value'] ?? '<unset>';

  if (!isset($inputs[$prop_name])) {
    fwrite(STDERR, "✗ Prop '{$prop_name}' not declared on this component (component_id={$row['component_id']}). Inputs has: " . implode(', ', array_keys($inputs)) . "\n");
    exit(1);
  }

  $inputs[$prop_name]['value'] = $new_value;
  $tree[$i]['inputs'] = json_encode($inputs, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
  echo "  · component_id={$row['component_id']}\n";
  echo "  · {$prop_name}: '{$old}' → '{$new_value}'\n";
  break;
}

if (!$found) {
  fwrite(STDERR, "✗ Component UUID {$component_uuid} not found in EN tree. Available UUIDs:\n");
  foreach ($tree as $row) {
    fwrite(STDERR, "    {$row['uuid']} ({$row['component_id']})\n");
  }
  exit(1);
}

$en->set('components', $tree);
$en->save();
drupal_flush_all_caches();

echo "✓ Saved EN translation. Verify at /en (or use cache-buster header).\n";
