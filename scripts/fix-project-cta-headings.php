<?php
/**
 * @file
 * Populate field_cta_heading + field_cta_sub on every project translation
 * that has them empty. Without these the case-detail twig short-circuits
 * the cta-final include, so the WhatsApp deep link never reaches the
 * page even though the rest of the case study renders fine.
 *
 * Maluma ES on prod lost field_cta_heading at some point during the
 * earlier seed runs (the ES create script differed from the EN one); the
 * other two projects were intact. This script is idempotent: it only
 * fills fields that are currently empty.
 *
 * Pattern: match by node title to be portable across envs (the same
 * reason rebuild-project-paragraphs.php does it that way).
 */

use Drupal\node\Entity\Node;

const CTA = [
  'Maluma.online' => [
    'es' => [
      'heading' => '¿Tu sitio carga lento y pierde audiencia internacional?',
      'sub'     => 'Hablemos del estado real del proyecto y del plan más corto para llegar al objetivo.',
    ],
    'en' => [
      'heading' => 'Want a site this performant?',
      'sub'     => 'Let\'s talk about where the project actually is and the shortest plan to hit the goal.',
    ],
  ],
  'Royalty Films' => [
    'es' => [
      'heading' => '¿Tu portfolio audiovisual no muestra lo que tu trabajo realmente es?',
      'sub'     => 'Hablemos de cómo presentar el trabajo con la calidad que merece.',
    ],
    'en' => [
      'heading' => 'Is your audiovisual portfolio not showing what your work actually is?',
      'sub'     => 'Let\'s talk about presenting the work with the quality it deserves.',
    ],
  ],
  '333 Creativo' => [
    'es' => [
      'heading' => '¿Tu agencia opera con tres herramientas distintas que deberían ser una sola?',
      'sub'     => 'Hablemos de unificar la operación en una sola plataforma sin perder lo que ya funciona.',
    ],
    'en' => [
      'heading' => 'Is your agency running on three different tools that should be one?',
      'sub'     => 'Let\'s talk about consolidating the operation on a single platform without losing what already works.',
    ],
  ],
];

foreach (CTA as $title => $by_lang) {
  $nids = \Drupal::entityQuery('node')
    ->condition('type', 'project')
    ->condition('title', $title)
    ->accessCheck(FALSE)
    ->execute();
  if (!$nids) {
    echo "⚠ project '{$title}' not found, skip\n";
    continue;
  }
  $node = Node::load(reset($nids));
  $changed = false;

  foreach ($by_lang as $lc => $vals) {
    if (!$node->hasTranslation($lc)) continue;
    $t = $node->getTranslation($lc);
    foreach (['heading' => 'field_cta_heading', 'sub' => 'field_cta_sub'] as $key => $field) {
      if (!$t->hasField($field)) continue;
      if ($t->get($field)->isEmpty()) {
        $t->set($field, $vals[$key]);
        echo "  → '{$title}' ({$lc}) {$field} populated\n";
        $changed = true;
      }
    }
  }
  if ($changed) {
    // setNewRevision() returns void on Drupal 11 — cannot chain ->save().
    foreach ($node->getTranslationLanguages() as $lc => $_) {
      $t = $node->getTranslation($lc);
      $t->setNewRevision(false);
      $t->save();
    }
  }
  else {
    echo "= '{$title}' already populated\n";
  }
}

drupal_flush_all_caches();
echo "\n✓ Project CTA headings + subs filled where missing.\n";
