<?php
$views = \Drupal\views\Entity\View::loadMultiple();
foreach ($views as $v) {
  $displays = $v->get('display');
  echo "{$v->id()}:\n";
  foreach ($displays as $id => $d) {
    $items = $d['display_options']['pager']['options']['items_per_page'] ?? '?';
    echo "  · [{$id}] plugin={$d['display_plugin']} items_per_page={$items}\n";
  }
}
