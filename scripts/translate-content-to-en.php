<?php
/**
 * @file
 * Add English translations to:
 *  - taxonomy terms (note_topics, project_categories, technologies)
 *  - node.project (Maluma.online, Royalty Films, 333 Creativo)
 *  - node.note (5 sample notes)
 *
 * Idempotent: skips entries that already have an EN translation.
 *
 * Run: ddev exec ./web/vendor/bin/drush php:script scripts/translate-content-to-en.php
 */

use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Helper: add EN translation to a content entity if not already present.
 */
function add_en(\Drupal\Core\Entity\ContentEntityInterface $entity, array $fields): void {
  $type = $entity->getEntityTypeId();
  $id = $entity->id();
  $label = $entity->label();
  if ($entity->hasTranslation('en')) {
    echo "  = {$type}#{$id} \"{$label}\" — ya tiene EN\n";
    return;
  }
  $entity->addTranslation('en', $fields)->save();
  echo "  ✓ {$type}#{$id} \"{$label}\" → EN translation creada\n";
}

// ============================================================================
// TAXONOMY TERMS
// ============================================================================
echo "── Taxonomy: note_topics ──\n";
$note_topic_translations = [
  'Performance'    => 'Performance',
  'Arquitectura'   => 'Architecture',
  'Drupal'         => 'Drupal',
  'Accesibilidad'  => 'Accessibility',
  'Automatización' => 'Automation',
];
foreach ($note_topic_translations as $es => $en) {
  $tids = \Drupal::entityQuery('taxonomy_term')
    ->condition('vid', 'note_topics')->condition('name', $es)
    ->accessCheck(FALSE)->execute();
  foreach (Term::loadMultiple($tids) as $t) {
    add_en($t, ['name' => $en]);
  }
}

echo "\n── Taxonomy: project_categories ──\n";
$project_cat_translations = [
  'Web'      => 'Web',
  'Branding' => 'Branding',
  'Producto' => 'Product',
  'IA'       => 'AI',
];
foreach ($project_cat_translations as $es => $en) {
  $tids = \Drupal::entityQuery('taxonomy_term')
    ->condition('vid', 'project_categories')->condition('name', $es)
    ->accessCheck(FALSE)->execute();
  foreach (Term::loadMultiple($tids) as $t) {
    add_en($t, ['name' => $en]);
  }
}

echo "\n── Taxonomy: technologies ──\n";
// Tech names typically don't translate (proper nouns).
$tech_terms = ['WordPress', 'Drupal 11', 'n8n', 'React', 'Next.js', 'WPML'];
foreach ($tech_terms as $name) {
  $tids = \Drupal::entityQuery('taxonomy_term')
    ->condition('vid', 'technologies')->condition('name', $name)
    ->accessCheck(FALSE)->execute();
  foreach (Term::loadMultiple($tids) as $t) {
    add_en($t, ['name' => $name]);
  }
}

// ============================================================================
// NODE PROJECT
// ============================================================================
echo "\n── node.project ──\n";
$project_translations = [
  'Maluma.online' => [
    'title' => 'Maluma.online',
    'field_summary' => 'Multilingual site for the artist, optimized with 40%+ speed improvement.',
    'field_intro' => 'A complete redesign with multilingual architecture, performance optimization, and a CMS that the artist\'s team could actually use without training.',
    'field_role' => 'Lead developer',
    'field_duration' => '3 months',
    'field_cta_heading' => 'Want a site this performant?',
    'field_cta_sub' => 'Real-world performance is hard. Tell me your constraints — I\'ll show you what\'s possible.',
    'field_challenge_intro' => 'Music sites have brutal performance budgets: heavy media, global audience, mobile-first traffic. The previous site was failing Core Web Vitals across the board.',
    'field_challenge_bullets' => [
      'LCP > 4s in 60% of visits',
      'CLS spikes from late-loading promotional banners',
      'Translation drift across ES/EN/PT',
    ],
    'field_lesson' => 'Performance is not a feature — it\'s a constraint that shapes every decision. We rebuilt with a performance budget that fails the build if exceeded.',
  ],
  'Royalty Films' => [
    'title' => 'Royalty Films',
    'field_summary' => 'Multilingual architecture for the production company, ready for international expansion.',
    'field_intro' => 'A film production company needed a portfolio that worked in 3 languages and could scale to 5+ markets without rebuilding.',
    'field_role' => 'Architecture + frontend',
    'field_duration' => '6 weeks',
    'field_cta_heading' => 'Building for international scale?',
    'field_cta_sub' => 'Multilingual is more than translation. Let\'s talk about content modeling, URL strategy, and editorial workflow.',
    'field_challenge_intro' => 'The team was already producing content in 3 languages but the previous site forced them to maintain 3 separate copies. Editorial overhead was killing them.',
    'field_lesson' => 'Translation is a content workflow problem, not a technical problem. Solve the workflow first; the tech follows.',
  ],
  '333 Creativo' => [
    'title' => '333 Creativo',
    'field_summary' => 'Scalable platform for personal brand agency with strong visual design.',
    'field_intro' => 'A creative agency wanted a platform that reflected their visual edge while being genuinely manageable for non-technical staff.',
    'field_role' => 'Full-stack',
    'field_duration' => '8 weeks',
    'field_cta_heading' => 'Need a site that\'s editor-friendly AND performant?',
    'field_cta_sub' => 'These two goals usually fight each other. With Drupal Canvas + SDC components, you can have both.',
  ],
];
$nids = \Drupal::entityQuery('node')->condition('type', 'project')->accessCheck(FALSE)->execute();
foreach (Node::loadMultiple($nids) as $n) {
  $translation = $project_translations[$n->label()] ?? NULL;
  if (!$translation) {
    echo "  ! {$n->label()} — sin traducción mapeada, saltando\n";
    continue;
  }
  add_en($n, $translation);
}

// ============================================================================
// NODE NOTE
// ============================================================================
echo "\n── node.note ──\n";
$note_translations = [
  'Lo que aprendí midiendo Core Web Vitals en 3 clientes' => [
    'title' => 'What I learned measuring Core Web Vitals across 3 clients',
    'field_excerpt' => 'LCP, CLS and page weight: three numbers that changed how I propose architectures. Honest field notes, not lab notes.',
    'body' => [
      'value' => "<p>Three real client projects. Three very different stacks. Same three numbers I track every time.</p><h2>LCP first</h2><p>Largest Contentful Paint is the one metric that correlates with bounce rate in every site I've measured. Optimize for it before anything else.</p><h2>CLS is sneaky</h2><p>Layout shift looks small in dev tools but feels enormous on a phone. Fix it by reserving space for everything.</p><h2>Page weight is a budget</h2><p>I treat page weight like a budget: every dependency must justify its cost. If it can't, it goes.</p>",
      'format' => 'basic_html',
    ],
  ],
  'Drupal o WordPress: la matriz que uso para decidir' => [
    'title' => 'Drupal or WordPress: the matrix I use to decide',
    'field_excerpt' => 'It\'s not "which is better". It\'s: when to use one or the other. A 5-axis matrix I apply before proposing a stack to a client.',
    'body' => [
      'value' => "<p>Both tools are excellent. Both can fail. The question is fit.</p><h2>Five axes</h2><p><strong>Editor count</strong>, <strong>content complexity</strong>, <strong>integration count</strong>, <strong>performance budget</strong>, <strong>team skills</strong>. Score each 1–5. Drupal wins above 15. WordPress wins below.</p><p>Like every framework, it breaks at the edges. But for 80% of decisions, it removes the religious debate.</p>",
      'format' => 'basic_html',
    ],
  ],
  'El workflow de n8n que me devolvió 4 horas a la semana' => [
    'title' => 'The n8n workflow that gave me 4 hours back per week',
    'field_excerpt' => 'I receive contact form submissions, enrich them with Clearbit, push them to HubSpot, and notify Slack — all automatic. JSON exported inside.',
    'body' => [
      'value' => "<p>Four hours a week of low-value lead-handling work, gone. Here's the n8n graph.</p><p>Trigger: webhook from the contact form. Branch on company size (Clearbit). Push to HubSpot with the right pipeline. Post to Slack with a summary card. Done.</p><p>The JSON to import is at the bottom of the post.</p>",
      'format' => 'basic_html',
    ],
  ],
  '10 componentes accesibles que copio y pego en cada proyecto' => [
    'title' => '10 accessible components I copy-paste into every project',
    'field_excerpt' => 'Modal, dropdown, tabs, tooltip, accordion. Code-ready, tested with NVDA, JAWS, VoiceOver. No heavy libraries.',
    'body' => [
      'value' => "<p>Accessibility doesn't have to mean a 50KB library. Here are 10 patterns I've tested with three screen readers and keyboard-only.</p><p>Each is under 50 lines of HTML+CSS+JS. Drop them in. Adjust styling. Move on.</p>",
      'format' => 'basic_html',
    ],
  ],
  'Mi stack 2026: las herramientas que de verdad uso' => [
    'title' => 'My 2026 stack: the tools I actually use',
    'field_excerpt' => 'No affiliates, no hype. The tools that survived 12 months of real use on paid projects. And why.',
    'body' => [
      'value' => "<p>Lots of \"my stack\" posts are signaling exercises. This isn't.</p><p>If a tool isn't here, I tried it and dropped it. If a tool is here, it survived a year of real client work.</p><p>The list, with brief reasons why each made it in: Drupal 11, DDEV, GitHub Actions, Cloudflare, Hostinger, n8n.</p>",
      'format' => 'basic_html',
    ],
  ],
];
$nids = \Drupal::entityQuery('node')->condition('type', 'note')->accessCheck(FALSE)->execute();
foreach (Node::loadMultiple($nids) as $n) {
  $translation = $note_translations[$n->label()] ?? NULL;
  if (!$translation) {
    echo "  ! {$n->label()} — sin traducción mapeada, saltando\n";
    continue;
  }
  add_en($n, $translation);
}

drupal_flush_all_caches();
echo "\n✅ Traducciones EN aplicadas.\n";
