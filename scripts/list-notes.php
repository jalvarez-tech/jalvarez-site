<?php
$nids = \Drupal::entityQuery('node')->condition('type', 'note')->accessCheck(FALSE)->execute();
echo "Note nids (" . count($nids) . " total): " . implode(',', $nids) . "\n";
foreach ($nids as $nid) {
  $n = \Drupal\node\Entity\Node::load($nid);
  echo "  · nid={$nid} title=\"{$n->label()}\" status=" . ($n->isPublished() ? 'pub' : 'DRAFT') . " lang={$n->language()->getId()}\n";
}
