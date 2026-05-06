<?php
/**
 * @file
 * Finishes off the project translation pass:
 *   1. Adds EN translation to Maluma testimonial paragraph (pid=11).
 *   2. Appends a 4th 'Launch + monitoring' step to Royalty's
 *      field_approach_steps so the case has a complete arc.
 *
 * Idempotent.
 */

use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;

// ─── 1. Maluma testimonial pid=11 → add EN ───────────────────────────────
$paragraph_storage = \Drupal::entityTypeManager()->getStorage('paragraph');
$tst = $paragraph_storage->load(11);
if ($tst && !$tst->hasTranslation('en')) {
  $tst->addTranslation('en', [
    'field_quote' => 'John implemented our site with flawless technical execution. He optimised performance and delivered more than 40% improvement in speed and stability.',
    'field_author_name'     => $tst->get('field_author_name')->value ?? 'Tatiana Restrepo',
    'field_author_role'     => 'Maluma.online',
    'field_author_initials' => 'TR',
  ])->save();
  echo "+ added EN translation to testimonial pid=11\n";
}
elseif ($tst) {
  echo "= testimonial pid=11 already has EN\n";
}

// ─── 2. Royalty Films — 4th approach step ────────────────────────────────
$royalty = Node::load(2);
$has_step_launch = FALSE;
foreach ($royalty->get('field_approach_steps')->referencedEntities() as $p) {
  $title = $p->get('field_step_title')->value ?? '';
  if (stripos($title, 'lanzamiento') !== FALSE || stripos($title, 'launch') !== FALSE) {
    $has_step_launch = TRUE;
    break;
  }
}
if (!$has_step_launch) {
  $step = Paragraph::create([
    'type' => 'case_step',
    'langcode' => 'es',
    'field_step_title' => 'Lanzamiento + monitoreo',
    'field_step_tag'   => 'Mes 4',
    'field_step_body'  => 'Migración del catálogo legacy con Migrate API y redirects 301 desde las URLs antiguas. Lanzamiento sin downtime, monitoreo de Core Web Vitals durante las primeras 4 semanas, y plan de soporte editorial para que el equipo siga publicando solo.',
  ]);
  $step->save();
  $step->addTranslation('en', [
    'field_step_title' => 'Launch + monitoring',
    'field_step_tag'   => 'Month 4',
    'field_step_body'  => 'Legacy catalogue migrated via Migrate API with 301 redirects from the old URLs. Zero-downtime launch, Core Web Vitals monitoring through the first 4 weeks, and an editorial support plan so the team keeps publishing on its own.',
  ])->save();

  $existing_refs = $royalty->get('field_approach_steps')->getValue();
  $existing_refs[] = ['target_id' => $step->id(), 'target_revision_id' => $step->getRevisionId()];
  $royalty->set('field_approach_steps', $existing_refs);
  foreach ($royalty->getTranslationLanguages() as $lc => $_) {
    $royalty->getTranslation($lc)->setPublished()->save();
  }
  echo "+ created Royalty step 4 'Lanzamiento + monitoreo' (pid={$step->id()})\n";
}
else {
  echo "= Royalty already has a launch step\n";
}

drupal_flush_all_caches();
echo "\n✓ Done.\n";
