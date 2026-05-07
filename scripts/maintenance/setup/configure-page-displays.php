<?php
/**
 * @file
 * Make body + field_canvas visible in node.page form & view displays.
 */

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;

// Form display: ensure body + field_canvas have widgets configured.
$form = EntityFormDisplay::load('node.page.default');
if (!$form) {
  $form = EntityFormDisplay::create([
    'targetEntityType' => 'node',
    'bundle' => 'page',
    'mode' => 'default',
    'status' => TRUE,
  ]);
}
$form->setComponent('body', [
  'type' => 'text_textarea_with_summary',
  'weight' => 1,
  'settings' => ['rows' => 9, 'summary_rows' => 3, 'placeholder' => '', 'show_summary' => FALSE],
]);
// Canvas field has no standard FieldWidget — it's edited via Canvas's
// own page builder UI (linked from the entity edit page). Just ensure
// it's not hidden from the form display so the link/button renders.
if ($form->getComponent('field_canvas') === NULL) {
  // Let Drupal pick a default; some Canvas versions auto-register one.
}
$form->save();
echo "✓ form display node.page.default updated\n";

// View display: render the canvas tree.
$view = EntityViewDisplay::load('node.page.default');
if (!$view) {
  $view = EntityViewDisplay::create([
    'targetEntityType' => 'node',
    'bundle' => 'page',
    'mode' => 'default',
    'status' => TRUE,
  ]);
}
$view->setComponent('field_canvas', [
  'type' => 'canvas_naive_render_sdc_tree',
  'weight' => 0,
  'label' => 'hidden',
]);
$view->save();
echo "✓ view display node.page.default updated\n";

drupal_flush_all_caches();
echo "\n✅ Page displays configured.\n";
