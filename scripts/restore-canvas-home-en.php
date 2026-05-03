<?php
/**
 * @file
 * Emergency restore for the EN translation of the Inicio canvas_page.
 *
 * Why this exists:
 *   Editing canvas_page id=1 (the home) in the visual editor while the
 *   active language is `es` can wipe the EN translation in Canvas 1.3.x.
 *   Symptoms after that happens:
 *     - /en/home → 404 (alias '/home' lived on the EN translation, gone)
 *     - /en      → 200 but renders ES content with <html lang="en"> because
 *                  Drupal falls back to the canonical (ES) translation
 *                  when the requested EN translation doesn't exist.
 *     - /en/projects, /en/notes, /en/contact → still fine (other entities,
 *                  not touched by the home edit).
 *
 * What this script does:
 *   1. Resolve the home canvas_page via alias '/inicio' (no hardcoded ID).
 *   2. Build the canonical EN component tree (matches scripts/create-canvas-
 *      home.php's tree_en — copy/paste, intentionally redundant so this
 *      script is self-contained and can run even if the source script changes).
 *   3. Apply as translation EN: addTranslation if missing, otherwise
 *      overwrite the existing EN translation's title/description/components/path.
 *   4. Restore the path alias '/home' on the EN translation.
 *   5. Flush caches so /en and /en/home work immediately.
 *
 * What it does NOT do:
 *   - Touch the ES translation (the user's edits in /es are preserved).
 *   - Update any other canvas_page (Proyectos, Notas, Contacto).
 *
 * Idempotent: safe to re-run. Logs which path it took.
 *
 * Run via:
 *   ./vendor/bin/drush php:script scripts/restore-canvas-home-en.php
 *
 * Or via the seed-content workflow:
 *   gh workflow run seed-content.yml --field script=scripts/restore-canvas-home-en.php
 */

use Drupal\canvas\Entity\Component;

// ---------------------------------------------------------------------------
// Helpers (copied from scripts/create-canvas-home.php — keep in sync if the
// SDC field-type contract changes upstream in Canvas).
// ---------------------------------------------------------------------------
function _restore_uuid(): string {
  return \Drupal::service('uuid')->generate();
}

function _restore_tree_item(string $uuid, string $component_id, array $values, ?string $parent_uuid = NULL, ?string $slot = NULL): array {
  $component = Component::load($component_id);
  if (!$component) {
    throw new \RuntimeException("Component '{$component_id}' not found. Run scripts/canvas-discover-sdcs.php first.");
  }
  $version = $component->getActiveVersion();
  $defs = $component->getSettings()['prop_field_definitions'] ?? [];

  $inputs = [];
  foreach ($values as $prop_name => $value) {
    if (!isset($defs[$prop_name])) continue;
    $field_type = $defs[$prop_name]['field_type'];
    $entry = [
      'sourceType' => "static:field_item:{$field_type}",
      'value' => $value,
      'expression' => $defs[$prop_name]['expression'],
    ];
    if ($field_type === 'list_string') {
      $entry['sourceTypeSettings'] = [
        'storage' => ['allowed_values_function' => 'canvas_load_allowed_values_for_component_prop'],
      ];
    }
    $inputs[$prop_name] = $entry;
  }

  return [
    'uuid' => $uuid,
    'component_id' => $component_id,
    'component_version' => $version,
    'inputs' => json_encode($inputs, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
    'parent_uuid' => $parent_uuid,
    'slot' => $slot,
  ];
}

function _restore_block_item(string $uuid, string $component_id, array $settings = [], ?string $parent_uuid = NULL, ?string $slot = NULL): array {
  $component = Component::load($component_id);
  if (!$component) {
    throw new \RuntimeException("Component '{$component_id}' not found.");
  }
  return [
    'uuid' => $uuid,
    'component_id' => $component_id,
    'component_version' => $component->getActiveVersion(),
    'inputs' => json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
    'parent_uuid' => $parent_uuid,
    'slot' => $slot,
  ];
}

// ---------------------------------------------------------------------------
// 1. Resolve the home canvas_page by alias.
// ---------------------------------------------------------------------------
$home_path = \Drupal::service('path_alias.manager')->getPathByAlias('/inicio');
if (!preg_match('#^/page/(\d+)$#', $home_path, $m)) {
  fwrite(STDERR, "✗ Could not resolve '/inicio' to a canvas_page. Got: '{$home_path}'\n");
  exit(1);
}
$home_id = (int) $m[1];
$page = \Drupal::entityTypeManager()->getStorage('canvas_page')->load($home_id);
if (!$page) {
  fwrite(STDERR, "✗ canvas_page id={$home_id} not loadable.\n");
  exit(1);
}
echo "→ Resolved home: canvas_page id={$home_id} title='{$page->label()}'\n";

// ---------------------------------------------------------------------------
// 2. Build the EN component tree (fresh UUIDs — translations are independent;
//    we don't mirror ES UUIDs because the user may have edited the ES tree
//    and the structure could have drifted).
// ---------------------------------------------------------------------------
$hero_uuid = _restore_uuid();
$marquee_uuid = _restore_uuid();
$values_uuid = _restore_uuid();
$featured_uuid = _restore_uuid();
$projects_block_uuid = _restore_uuid();
$process_uuid = _restore_uuid();
$tests_uuid = _restore_uuid();
$cta_uuid = _restore_uuid();

$value_kids = [_restore_uuid(), _restore_uuid(), _restore_uuid(), _restore_uuid()];
$step_kids  = [_restore_uuid(), _restore_uuid(), _restore_uuid(), _restore_uuid()];
$test_kids  = [_restore_uuid(), _restore_uuid()];

$tree_en = [];

// Hero
$tree_en[] = _restore_tree_item($hero_uuid, 'sdc.byte.banner-inicio', [
  'status' => 'Available · Q3 2026 · 2 slots',
  'title_a' => 'I believe a website',
  'title_accent' => 'fast',
  'title_punc' => ',',
  'title_stroke' => 'honest',
  'title_b' => ' and inclusive',
  'title_muted' => 'is a form of respect.',
  'sub' => 'I build digital experiences focused on performance, accessibility, and clarity. Sites that don\'t waste time, don\'t exclude users, and don\'t over-promise. I am John Stevans Alvarez, web developer with 15+ years building solid, scalable platforms made for people.',
  'cta_primary_label' => 'Schedule a call',
  'cta_primary_href' => '/contact',
  'cta_secondary_label' => 'See the work',
  'cta_secondary_href' => '/projects',
  'm1_value' => '15', 'm1_unit' => '+', 'm1_label' => 'years defending this',
  'm2_value' => '80', 'm2_unit' => '+', 'm2_label' => 'platforms in production',
  'm3_value' => '97', 'm3_unit' => '%', 'm3_label' => 'returning clients',
  'show_terminal' => TRUE,
  'show_portrait' => TRUE,
]);

// Marquee
$tree_en[] = _restore_tree_item($marquee_uuid, 'sdc.byte.marquee', [
  'item_1' => 'WordPress',
  'item_2' => 'Drupal 11',
  'item_3' => 'n8n',
  'item_4' => 'Headless',
  'item_5' => 'Core Web Vitals',
  'item_6' => 'WCAG 2.2',
  'item_7' => 'Multi-language',
  'item_8' => 'API Integrations',
  'item_9' => 'Performance Audits',
]);

// Cómo lo hago + 4 value cards
$tree_en[] = _restore_tree_item($values_uuid, 'sdc.byte.como-lo-hago', [
  'eyebrow_label' => 'how I work',
  'eyebrow_number' => 1,
  'title' => 'I don\'t sell features. ',
  'title_em' => 'I defend four non-negotiable disciplines.',
]);
$en_value_cards = [
  [1, 'gauge', 'performance', 'Measure before promising',
   'Every technical decision starts with a real number: LCP, CLS, page weight, bounce rate. If it isn\'t measured, it doesn\'t exist.',
   '42', '%', 'average speed gain after audit'],
  [2, 'layers', 'architecture', 'Built to last',
   'WordPress and Drupal written as if I had to maintain them five years from now. Reusable components, clean builds, near-zero technical debt.',
   '15', '+ years', 'building platforms that don\'t break'],
  [3, 'accessibility', 'accessibility', 'Respect every visitor',
   'WCAG 2.2 AA is the floor, not the ceiling. A site that excludes 15% of users is not "almost accessible": it\'s broken.',
   'AA', '', 'WCAG 2.2 conformance by default'],
  [4, 'workflow', 'automation', 'Automate the repetitive',
   'n8n flows, API integrations, funnels connected to your CRM. The site is a silent collaborator working while you sleep.',
   '∞', 'h', 'hours/month recovered for active clients'],
];
foreach ($en_value_cards as $i => [$num, $icon, $tag, $title, $body, $mv, $mu, $cap]) {
  $tree_en[] = _restore_tree_item($value_kids[$i], 'sdc.byte.value-card', [
    'number' => $num, 'icon' => $icon, 'tag' => $tag, 'title' => $title,
    'body' => $body, 'metric_value' => $mv, 'metric_unit' => $mu, 'caption' => $cap,
  ], $values_uuid, 'values');
}

// Que construyo + projects grid (block)
$tree_en[] = _restore_tree_item($featured_uuid, 'sdc.byte.que-construyo', [
  'eyebrow_label' => 'what I build',
  'eyebrow_number' => 2,
  'title' => 'And with that approach ',
  'title_em' => 'platforms like these come out.',
  'lede' => 'Music, film, wellness, real estate, creative agencies. Different industries, same principle: web that respects the time and attention of who visits.',
  'cta_label' => 'See all the work',
  'cta_href' => '/projects',
]);
$tree_en[] = _restore_block_item($projects_block_uuid, 'block.jalvarez_projects_grid', [
  'label' => '',
  'label_display' => '0',
  'only_featured' => TRUE,
  'limit' => 3,
  'wrap' => 'none',
], $featured_uuid, 'projects');

// Método + 4 process rows
$tree_en[] = _restore_tree_item($process_uuid, 'sdc.byte.metodo', [
  'eyebrow_label' => 'the method',
  'eyebrow_number' => 3,
  'title' => 'And ',
  'title_em' => 'this is how I work when we start.',
]);
$en_steps = [
  [1, 'Technical audit', 'Week 1', 'Before proposing anything, I measure. Performance, architecture, real opportunities. I deliver a document that says what hurts, what matters, and what can be safely ignored.'],
  [2, 'Architecture', 'Week 2', 'I choose the stack based on your team, budget, and growth — not on what\'s trendy. Drupal, WordPress, headless: the right answer depends on your case.'],
  [3, 'Implementation', 'Wk 3-8', 'Bi-weekly sprints with demos. Internal code reviews, performance budgets that break the build if exceeded. Zero surprises on launch day.'],
  [4, 'Launch + support', 'Ongoing', 'Zero-downtime migration, active monitoring during the first 4 weeks, and a 12-month maintenance plan. Launching is the beginning, not the end.'],
];
foreach ($en_steps as $i => [$num, $title, $tag, $body]) {
  $tree_en[] = _restore_tree_item($step_kids[$i], 'sdc.byte.process-row', [
    'number' => $num, 'title' => $title, 'tag' => $tag, 'body' => $body,
  ], $process_uuid, 'steps');
}

// Palabras cliente + 2 testimonials
$tree_en[] = _restore_tree_item($tests_uuid, 'sdc.byte.palabras-cliente', [
  'eyebrow_label' => 'client words',
  'eyebrow_number' => 4,
  'title' => 'Those who already launched tell it better than I do.',
]);
$en_tests = [
  ['John implemented our site with impeccable technical execution. He optimized performance achieving more than 40% improvement in speed and stability.',
   'Tatiana Restrepo', 'Maluma.online', 'TR'],
  ['He turned the design into a functional, optimized, and scalable platform. His ability to execute with precision made an enormous difference.',
   'Tes Pimienta', 'Royalty Films', 'TP'],
];
foreach ($en_tests as $i => [$quote, $name, $role, $initials]) {
  $tree_en[] = _restore_tree_item($test_kids[$i], 'sdc.byte.card-testimonio', [
    'quote' => $quote, 'name' => $name, 'role' => $role, 'initials' => $initials,
  ], $tests_uuid, 'testimonials');
}

// CTA final
$tree_en[] = _restore_tree_item($cta_uuid, 'sdc.byte.cta-final', [
  'title' => 'If you believe the same, ',
  'title_em' => 'let\'s build something together.',
  'sub' => 'If this way of thinking about the web is also yours — measure before promising, build to last, respect the visitor — let\'s start with a conversation. Before the first line of code, we\'ll already be aligned.',
  'primary_label' => 'Start your project',
  'primary_href' => '/contact',
  'secondary_label' => 'Review my work',
  'secondary_href' => '/projects',
]);

echo "→ Built EN tree with " . count($tree_en) . " components.\n";

// ---------------------------------------------------------------------------
// 3. Apply translation. addTranslation when missing, set + save when present.
// ---------------------------------------------------------------------------
$en_data = [
  'title' => 'Home (Canvas)',
  'description' => 'I am John Stevans Alvarez, web developer with 15+ years building solid, scalable platforms made for people. Fast, accessible, honest web.',
  'components' => $tree_en,
  'path' => ['alias' => '/home'],
];

if ($page->hasTranslation('en')) {
  $en = $page->getTranslation('en');
  $had_components = !empty($en->get('components')->getValue());
  $en->set('title', $en_data['title']);
  $en->set('description', $en_data['description']);
  $en->set('components', $en_data['components']);
  $en->set('path', $en_data['path']);
  $en->save();
  echo "✓ Updated existing EN translation (had_components=" . ($had_components ? 'yes' : 'no') . ").\n";
}
else {
  $page->addTranslation('en', $en_data)->save();
  echo "✓ Added missing EN translation.\n";
}

// ---------------------------------------------------------------------------
// 4. Flush caches so /en and /en/home resolve immediately.
// ---------------------------------------------------------------------------
drupal_flush_all_caches();
echo "✓ Caches flushed.\n";

echo "\nVerify:\n";
echo "  curl -sI https://jalvarez.tech/en | head -1       # expect 200\n";
echo "  curl -sI https://jalvarez.tech/en/home | head -1  # expect 200\n";
echo "  curl -s  https://jalvarez.tech/en | grep '<title>' | head -1  # 'Home (Canvas)'\n";
