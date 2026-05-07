<?php

/**
 * @file
 * Remove the page_1 display of one or more views so a custom controller
 * can own the path. Idempotent.
 *
 * Edit the $views array below to add view IDs.
 */

use Drupal\views\Entity\View;

$views = ['projects', 'notes'];

foreach ($views as $vid) {
  $view = View::load($vid);
  if (!$view) {
    echo "= view '{$vid}' not found (skipping)\n";
    continue;
  }
  $displays = $view->get('display');
  if (isset($displays['page_1'])) {
    unset($displays['page_1']);
    $view->set('display', $displays);
    $view->save();
    echo "✓ removed page_1 display from view '{$vid}'\n";
  } else {
    echo "= view '{$vid}' has no page_1 display\n";
  }
}
