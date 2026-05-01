<?php

/**
 * @file
 * Disable the page_1 display of the projects view so our controller
 * at /proyectos doesn't conflict.
 */

use Drupal\views\Entity\View;

$view = View::load('projects');
if (!$view) {
  echo "view 'projects' not found\n";
  return;
}

$displays = $view->get('display');
if (isset($displays['page_1'])) {
  unset($displays['page_1']);
  $view->set('display', $displays);
  $view->save();
  echo "✓ removed page_1 display from view 'projects'\n";
} else {
  echo "= view 'projects' already has no page_1 display\n";
}
