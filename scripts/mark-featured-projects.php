<?php
/**
 * @file
 * Marca los proyectos sample como destacados (field_featured_home = TRUE)
 * y les asigna un sort order. Idempotente.
 */

use Drupal\node\Entity\Node;

// Featured ordering: lower number = appears first.
$featured = [
  'Maluma.online' => 1,
  'Royalty Films' => 2,
  '333 Creativo'  => 3,
];

foreach ($featured as $title => $order) {
  $nids = \Drupal::entityQuery('node')
    ->condition('type', 'project')
    ->condition('title', $title)
    ->accessCheck(FALSE)
    ->execute();
  if (!$nids) {
    echo "  · {$title} — no encontrado\n";
    continue;
  }
  foreach (Node::loadMultiple($nids) as $node) {
    $changed = FALSE;
    if ($node->hasField('field_featured_home') && (int) $node->get('field_featured_home')->value !== 1) {
      $node->set('field_featured_home', 1);
      $changed = TRUE;
    }
    if ($node->hasField('field_sort_order') && (int) $node->get('field_sort_order')->value !== $order) {
      $node->set('field_sort_order', $order);
      $changed = TRUE;
    }
    if ($changed) {
      $node->save();
      echo "  ✓ {$title} (nid={$node->id()}) → featured=1, sort_order={$order}\n";
    } else {
      echo "  = {$title} (nid={$node->id()}) — ya marcado\n";
    }
  }
}

drupal_flush_all_caches();
echo "\n✅ Featured projects updated.\n";
