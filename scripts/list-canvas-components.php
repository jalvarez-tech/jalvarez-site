<?php
/**
 * @file
 * List every Canvas Component config entity grouped by source.
 */

use Drupal\canvas\Entity\Component;

$by_source = [];
foreach (Component::loadMultiple() as $id => $c) {
  $source = $c->get('source');
  $status = $c->status() ? 'on ' : 'off';
  $by_source[$source][] = "  [{$status}] {$id}";
}

foreach ($by_source as $source => $list) {
  echo "── {$source} (" . count($list) . ")\n";
  sort($list);
  foreach ($list as $line) echo "{$line}\n";
  echo "\n";
}

echo "Total: " . count(Component::loadMultiple()) . " components\n";
