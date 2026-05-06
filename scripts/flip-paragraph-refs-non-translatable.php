<?php
/**
 * @file
 * Flip the paragraph reference fields on node.project from translatable
 * to non-translatable, then re-publish each node so the (now shared) refs
 * propagate from ES into every translation row.
 *
 * Why: structural references (which paragraphs belong to a project) are
 * the same regardless of language — only the text INSIDE each paragraph
 * differs, and that's already handled per-translation on the paragraph
 * itself (the project-detail twig now resolves paragraph translations
 * via getTranslation(lang)). Keeping the references translatable led to
 * EN nodes with empty refs and empty Approach/Results sections.
 *
 * Same pattern applied to field_cover_media on commit 728b101.
 *
 * Idempotent.
 */

use Drupal\node\Entity\Node;

$fields = [
  'field.field.node.project.field_approach_steps',
  'field.field.node.project.field_results_metrics',
  'field.field.node.project.field_testimonial_embed',
];

$config_factory = \Drupal::configFactory();
foreach ($fields as $config_name) {
  $config = $config_factory->getEditable($config_name);
  if ($config->isNew()) {
    echo "⚠ {$config_name} not found, skip\n";
    continue;
  }
  $current = $config->get('translatable');
  if ($current === FALSE) {
    echo "= {$config_name} already non-translatable\n";
    continue;
  }
  $config->set('translatable', FALSE)->save();
  echo "✓ {$config_name}: translatable {$current} → false\n";
}

// Force-resave each project node so the field storage reads the new
// translatable flag and propagates the canonical (ES) refs into every
// translation row.
$nids = \Drupal::entityQuery('node')->condition('type', 'project')->accessCheck(FALSE)->execute();
foreach (Node::loadMultiple($nids) as $node) {
  // Read refs from the default-language (ES) translation.
  $default = $node->getTranslation($node->getUntranslated()->language()->getId());
  foreach (['field_approach_steps', 'field_results_metrics', 'field_testimonial_embed'] as $f) {
    if (!$node->hasField($f)) continue;
    $node->set($f, $default->get($f)->getValue());
  }
  foreach ($node->getTranslationLanguages() as $lc => $_) {
    $node->getTranslation($lc)->setPublished()->save();
  }
  echo "  · republished nid={$node->id()} '{$node->label()}'\n";
}

drupal_flush_all_caches();
echo "\n✓ Paragraph reference fields are now non-translatable across project nodes.\n";
