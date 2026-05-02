<?php
/**
 * @file
 * Create a Drupal Canvas `canvas_page` entity titled "Inicio" with the home
 * component tree, plus an EN translation.
 *
 * IMPORTANT: as of Canvas 1.3.x, the visual editor (`/canvas/editor/...`)
 * only works with `canvas_page` entities — NOT with regular nodes that have
 * a `field_canvas` field. See https://drupal.org/i/3498525.
 *
 * Run via:
 *   ddev exec ./web/vendor/bin/drush php:script scripts/create-canvas-home.php
 *
 * Idempotent: deletes any existing canvas_page titled "Inicio (Canvas)" first.
 */

use Drupal\canvas\Entity\Component;
use Drupal\canvas\Entity\Page;
use Drupal\node\Entity\Node;

/**
 * Build a single tree item array, ready for $node->set('field_canvas', [...]).
 *
 * @param string $uuid Component instance UUID.
 * @param string $component_id Canvas Component config entity ID (e.g. 'sdc.byte.cta-final').
 * @param array<string, mixed> $values Plain prop key→value map.
 * @param string|null $parent_uuid Parent UUID for items inside slots.
 * @param string|null $slot Slot name when nested.
 */
function tree_item(string $uuid, string $component_id, array $values, ?string $parent_uuid = NULL, ?string $slot = NULL): array {
  $component = Component::load($component_id);
  if (!$component) {
    throw new \RuntimeException("Component config entity '{$component_id}' not found. Re-run scripts/canvas-discover-sdcs.php.");
  }
  $version = $component->getActiveVersion();
  $settings = $component->getSettings();
  $defs = $settings['prop_field_definitions'] ?? [];

  $inputs = [];
  foreach ($values as $prop_name => $value) {
    if (!isset($defs[$prop_name])) {
      // Skip props the component doesn't declare (Canvas would reject them).
      continue;
    }
    $field_type = $defs[$prop_name]['field_type'];
    $expression = $defs[$prop_name]['expression'];
    $entry = [
      'sourceType' => "static:field_item:{$field_type}",
      'value' => $value,
      'expression' => $expression,
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

function uuid(): string {
  return \Drupal::service('uuid')->generate();
}

/**
 * Build a tree item for a Block-source Component (Views block, Webform block,
 * custom Drupal Block plugin, etc.). Block items store flat plugin settings
 * (no sourceType wrappers). Used here to slot the projects grid block into
 * the `byte:que-construyo` SDC's `projects` slot.
 */
function block_tree_item(string $uuid, string $component_id, array $settings = [], ?string $parent_uuid = NULL, ?string $slot = NULL): array {
  $component = Component::load($component_id);
  if (!$component) {
    throw new \RuntimeException("Component config entity '{$component_id}' not found.");
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
// Build the Canvas tree.
// ---------------------------------------------------------------------------
$hero_uuid = uuid();
$marquee_uuid = uuid();
$values_uuid = uuid();
$featured_uuid = uuid();
$process_uuid = uuid();
$tests_uuid = uuid();
$cta_uuid = uuid();

$tree = [];

// 1. Hero
$tree[] = tree_item($hero_uuid, 'sdc.byte.banner-inicio', [
  'status' => 'Disponible · Q3 2026 · 2 cupos',
  'title_a' => 'Creo que una web',
  'title_accent' => 'rápida',
  'title_punc' => ',',
  'title_stroke' => 'honesta',
  'title_b' => ' e inclusiva',
  'title_muted' => 'es una forma de respeto.',
  'sub' => 'Construyo experiencias digitales con foco en rendimiento, accesibilidad y claridad. Sitios que no hacen perder tiempo, no excluyen usuarios y no prometen más de lo que realmente entregan.',
  'cta_primary_label' => 'Agenda una llamada',
  'cta_primary_href' => '/contacto',
  'cta_secondary_label' => 'Ver el trabajo',
  'cta_secondary_href' => '/proyectos',
  'm1_value' => '15', 'm1_unit' => '+', 'm1_label' => 'años defendiendo esto',
  'm2_value' => '80', 'm2_unit' => '+', 'm2_label' => 'plataformas en producción',
  'm3_value' => '97', 'm3_unit' => '%', 'm3_label' => 'clientes que vuelven',
  'show_terminal' => TRUE,
  'show_portrait' => TRUE,
]);

// 2. Marquee — flat item_1..item_9 props (Canvas-editable).
$tree[] = tree_item($marquee_uuid, 'sdc.byte.marquee', [
  'item_1' => 'WordPress',
  'item_2' => 'Drupal 11',
  'item_3' => 'n8n',
  'item_4' => 'Headless',
  'item_5' => 'Core Web Vitals',
  'item_6' => 'WCAG 2.2',
  'item_7' => 'Multilenguaje',
  'item_8' => 'API Integrations',
  'item_9' => 'Performance Audits',
]);

// 3. Cómo lo hago — slot-driven section.
$tree[] = tree_item($values_uuid, 'sdc.byte.como-lo-hago', [
  'eyebrow_label' => 'cómo lo hago',
  'eyebrow_number' => 1,
  'title' => 'No vendo features. ',
  'title_em' => 'Defiendo cuatro disciplinas no negociables.',
]);
foreach ([
  [1, 'gauge', 'performance', 'Mido antes de prometer',
    'Cada decisión técnica empieza con un número real: LCP, CLS, peso de página, tasa de rebote. Si no se mide, no existe.',
    '42', '%', 'mejora promedio de velocidad tras auditoría'],
  [2, 'layers', 'arquitectura', 'Construyo para que dure',
    'WordPress y Drupal escritos como si los tuviera que mantener yo dentro de cinco años. Componentes reutilizables, builds limpios, deuda técnica cercana a cero.',
    '15', '+ años', 'construyendo plataformas que no se rompen'],
  [3, 'accessibility', 'accesibilidad', 'Respeto a todos los visitantes',
    'WCAG 2.2 AA es el piso, no el techo. Un sitio que excluye al 15% de usuarios no es "casi accesible": está mal hecho.',
    'AA', '', 'conformidad WCAG 2.2 por defecto'],
  [4, 'workflow', 'automatización', 'Automatizo lo repetitivo',
    'Flujos en n8n, integraciones API, funnels conectados a tu CRM. El sitio es un colaborador silencioso que trabaja mientras duermes.',
    '∞', 'h', 'horas/mes recuperadas en clientes activos'],
] as [$num, $icon, $tag, $title, $body, $mv, $mu, $cap]) {
  $tree[] = tree_item(uuid(), 'sdc.byte.value-card', [
    'number' => $num, 'icon' => $icon, 'tag' => $tag, 'title' => $title,
    'body' => $body, 'metric_value' => $mv, 'metric_unit' => $mu, 'caption' => $cap,
  ], $values_uuid, 'values');
}

// 4. Qué construyo — slot-driven.
// El slot `projects` se llena con un único bloque (jalvarez_projects_grid)
// configurado para mostrar SOLO proyectos destacados (field_featured_home=1)
// y limitar a 3. El bloque va en modo `wrap: none` porque el SDC padre
// `byte:que-construyo` ya provee `<section><div class="projects-grid">`.
$tree[] = tree_item($featured_uuid, 'sdc.byte.que-construyo', [
  'eyebrow_label' => 'qué construyo',
  'eyebrow_number' => 2,
  'title' => 'Y resulta que con ese enfoque ',
  'title_em' => 'salen plataformas como estas.',
  'lede' => 'Música, cine, bienestar, inmobiliario, agencias creativas. Distinta industria, mismo principio: web que respeta el tiempo y la atención de quien la visita.',
  'cta_label' => 'Ver todo el trabajo',
  'cta_href' => '/proyectos',
]);
$tree[] = block_tree_item(uuid(), 'block.jalvarez_projects_grid', [
  'label' => '',
  'label_display' => '0',
  'only_featured' => TRUE,
  'limit' => 3,
  'wrap' => 'none',
], $featured_uuid, 'projects');

// 5. Método — slot-driven.
$tree[] = tree_item($process_uuid, 'sdc.byte.metodo', [
  'eyebrow_label' => 'el método',
  'eyebrow_number' => 3,
  'title' => 'Y ',
  'title_em' => 'así trabajo cuando empezamos.',
]);
foreach ([
  [1, 'Auditoría técnica', 'Semana 1', 'Antes de proponer nada, mido. Performance, arquitectura, oportunidades reales. Salgo con un documento que dice qué duele, qué importa y qué se puede ignorar sin culpa.'],
  [2, 'Arquitectura', 'Semana 2', 'Decido el stack basado en tu equipo, presupuesto y crecimiento — no en lo que está de moda. Drupal, WordPress, headless: la respuesta correcta depende de tu caso.'],
  [3, 'Implementación', 'Sem. 3-8', 'Sprints quincenales con demos. Code reviews internos, performance budgets que rompen el build si se exceden. Cero sorpresas el día del lanzamiento.'],
  [4, 'Lanzamiento + soporte', 'Ongoing', 'Migración sin downtime, monitoreo activo durante las primeras 4 semanas, y plan de mantenimiento a 12 meses. Lanzar es el comienzo, no el final.'],
] as [$num, $title, $tag, $body]) {
  $tree[] = tree_item(uuid(), 'sdc.byte.process-row', [
    'number' => $num, 'title' => $title, 'tag' => $tag, 'body' => $body,
  ], $process_uuid, 'steps');
}

// 6. Palabras de cliente — slot-driven.
$tree[] = tree_item($tests_uuid, 'sdc.byte.palabras-cliente', [
  'eyebrow_label' => 'palabra de clientes',
  'eyebrow_number' => 4,
  'title' => 'Quienes ya lanzaron lo cuentan mejor que yo.',
]);
foreach ([
  ['John implementó nuestro sitio con ejecución técnica impecable. Optimizó el rendimiento logrando una mejora superior al 40% en velocidad y estabilidad.',
   'Tatiana Restrepo', 'Maluma.online', 'TR'],
  ['Transformó el diseño en una plataforma funcional, optimizada y escalable. Su capacidad para ejecutar con precisión marcó una diferencia enorme.',
   'Tes Pimienta', 'Royalty Films', 'TP'],
] as [$quote, $name, $role, $initials]) {
  $tree[] = tree_item(uuid(), 'sdc.byte.card-testimonio', [
    'quote' => $quote, 'name' => $name, 'role' => $role, 'initials' => $initials,
  ], $tests_uuid, 'testimonials');
}

// 7. CTA final.
$tree[] = tree_item($cta_uuid, 'sdc.byte.cta-final', [
  'title' => 'Si crees lo mismo, ',
  'title_em' => 'construyamos algo juntos.',
  'sub' => 'Si esta forma de pensar la web también es la tuya — medir antes de prometer, construir para que dure, respetar a quien visita — empecemos por una conversación. Antes de la primera línea de código, ya estaremos alineados.',
  'primary_label' => 'Iniciar tu proyecto',
  'primary_href' => '/contacto',
  'secondary_label' => 'Revisar mi trabajo',
  'secondary_href' => '/proyectos',
]);

echo "Built tree with " . count($tree) . " component instances.\n";

// ---------------------------------------------------------------------------
// Cleanup obsolete entities (idempotent):
//  · canvas_page entities titled "Inicio (Canvas)"
//  · legacy node.page nodes titled "Inicio (Canvas)" (from previous attempts)
// ---------------------------------------------------------------------------
$existing_pages = \Drupal::entityTypeManager()->getStorage('canvas_page')
  ->loadByProperties(['title' => 'Inicio (Canvas)']);
foreach ($existing_pages as $p) {
  $p->delete();
  echo "  · Deleted existing canvas_page id={$p->id()}\n";
}

$nids = \Drupal::entityQuery('node')
  ->condition('type', 'page')
  ->condition('title', 'Inicio (Canvas)')
  ->accessCheck(FALSE)
  ->execute();
if ($nids) {
  foreach (Node::loadMultiple($nids) as $existing) {
    $existing->delete();
    echo "  · Deleted legacy node.page nid={$existing->id()}\n";
  }
}

// ---------------------------------------------------------------------------
// Create the new canvas_page entity (ES).
// ---------------------------------------------------------------------------
$node = Page::create([
  'title' => 'Inicio (Canvas)',
  'description' => 'Soy John Stevans Alvarez, desarrollador web con +15 años creando plataformas sólidas, escalables y pensadas para las personas. Web rápida, accesible y honesta.',
  'status' => TRUE,
  'langcode' => 'es',
  'components' => $tree,
  'path' => ['alias' => '/inicio'],
]);
$node->save();

echo "✓ Created ES canvas_page id={$node->id()} (alias /es/inicio)\n";

// ---------------------------------------------------------------------------
// Create EN translation with English copy.
// ---------------------------------------------------------------------------
$tree_en = [];

// Same UUIDs reused so the structure mirrors the ES version; only inputs change.
$tree_en[] = tree_item($hero_uuid, 'sdc.byte.banner-inicio', [
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

$tree_en[] = tree_item($marquee_uuid, 'sdc.byte.marquee', [
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

$tree_en[] = tree_item($values_uuid, 'sdc.byte.como-lo-hago', [
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
// Reuse the ES child UUIDs by collecting them from the ES tree.
$es_children_by_parent_slot = [];
foreach ($tree as $row) {
  if (!empty($row['parent_uuid'])) {
    $es_children_by_parent_slot[$row['parent_uuid']][$row['slot']][] = $row['uuid'];
  }
}
$values_kids = $es_children_by_parent_slot[$values_uuid]['values'];
foreach ($en_value_cards as $i => [$num, $icon, $tag, $title, $body, $mv, $mu, $cap]) {
  $tree_en[] = tree_item($values_kids[$i], 'sdc.byte.value-card', [
    'number' => $num, 'icon' => $icon, 'tag' => $tag, 'title' => $title,
    'body' => $body, 'metric_value' => $mv, 'metric_unit' => $mu, 'caption' => $cap,
  ], $values_uuid, 'values');
}

$tree_en[] = tree_item($featured_uuid, 'sdc.byte.que-construyo', [
  'eyebrow_label' => 'what I build',
  'eyebrow_number' => 2,
  'title' => 'And with that approach ',
  'title_em' => 'platforms like these come out.',
  'lede' => 'Music, film, wellness, real estate, creative agencies. Different industries, same principle: web that respects the time and attention of who visits.',
  'cta_label' => 'See all the work',
  'cta_href' => '/projects',
]);
// EN slot mirror: reuse the SAME block UUID as ES (block configuration is
// language-independent — the block reads project nodes which carry their
// own translations; render is per-request language).
$projects_block_uuid = $es_children_by_parent_slot[$featured_uuid]['projects'][0] ?? null;
if ($projects_block_uuid) {
  $tree_en[] = block_tree_item($projects_block_uuid, 'block.jalvarez_projects_grid', [
    'label' => '',
    'label_display' => '0',
    'only_featured' => TRUE,
    'limit' => 3,
    'wrap' => 'none',
  ], $featured_uuid, 'projects');
}

$tree_en[] = tree_item($process_uuid, 'sdc.byte.metodo', [
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
$steps_kids = $es_children_by_parent_slot[$process_uuid]['steps'];
foreach ($en_steps as $i => [$num, $title, $tag, $body]) {
  $tree_en[] = tree_item($steps_kids[$i], 'sdc.byte.process-row', [
    'number' => $num, 'title' => $title, 'tag' => $tag, 'body' => $body,
  ], $process_uuid, 'steps');
}

$tree_en[] = tree_item($tests_uuid, 'sdc.byte.palabras-cliente', [
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
$tests_kids = $es_children_by_parent_slot[$tests_uuid]['testimonials'];
foreach ($en_tests as $i => [$quote, $name, $role, $initials]) {
  $tree_en[] = tree_item($tests_kids[$i], 'sdc.byte.card-testimonio', [
    'quote' => $quote, 'name' => $name, 'role' => $role, 'initials' => $initials,
  ], $tests_uuid, 'testimonials');
}

$tree_en[] = tree_item($cta_uuid, 'sdc.byte.cta-final', [
  'title' => 'If you believe the same, ',
  'title_em' => 'let\'s build something together.',
  'sub' => 'If this way of thinking about the web is also yours — measure before promising, build to last, respect the visitor — let\'s start with a conversation. Before the first line of code, we\'ll already be aligned.',
  'primary_label' => 'Start your project',
  'primary_href' => '/contact',
  'secondary_label' => 'Review my work',
  'secondary_href' => '/projects',
]);

$node->addTranslation('en', [
  'title' => 'Home (Canvas)',
  'description' => 'I am John Stevans Alvarez, web developer with 15+ years building solid, scalable platforms made for people. Fast, accessible, honest web.',
  'components' => $tree_en,
  'path' => ['alias' => '/home'],
])->save();

echo "✓ Added EN translation\n";

// ---------------------------------------------------------------------------
// Point system.site.page.front to this canvas_page (language-aware via the
// internal route /page/<id>).
// ---------------------------------------------------------------------------
\Drupal::configFactory()
  ->getEditable('system.site')
  ->set('page.front', '/page/' . $node->id())
  ->save();
echo "✓ Set system.site.page.front = /page/{$node->id()}\n";

echo "\nRoutes:\n";
echo "  /            → front (canvas_page id={$node->id()})\n";
echo "  /es          → /es/inicio (alias of /es/page/{$node->id()})\n";
echo "  /en          → /en/home   (alias of /en/page/{$node->id()})\n";
echo "Edit (visual editor):\n";
echo "  /es/canvas/editor/canvas_page/{$node->id()} (ES)\n";
echo "  /en/canvas/editor/canvas_page/{$node->id()} (EN)\n";
