<?php
/**
 * @file
 * Create canvas_page entities for Proyectos, Notas, Contacto (ES + EN).
 *
 * Listings (Proyectos, Notas) embed the corresponding Views block.
 * Contacto uses the Webform block + canal-directo SDC sidebar.
 *
 * Idempotent: deletes any existing entries with these titles first.
 *
 * Run via:
 *   ddev exec ./web/vendor/bin/drush php:script scripts/create-canvas-other-pages.php
 */

use Drupal\canvas\Entity\Page;
use Drupal\canvas\Entity\Component;

// Helpers inlined so this script runs standalone via seed-content.yml
// (the workflow scp's only this single file to /tmp/, so the lib/
// include path doesn't resolve in production).

if (!function_exists('canvas_tree_uuid')) {
  function canvas_tree_uuid(): string {
    return \Drupal::service('uuid')->generate();
  }
}

if (!function_exists('canvas_tree_sdc_item')) {
  function canvas_tree_sdc_item(string $uuid, string $component_id, array $values, ?string $parent_uuid = NULL, ?string $slot = NULL): array {
    $component = Component::load($component_id);
    if (!$component) {
      throw new \RuntimeException("Component config entity '{$component_id}' not found. Re-run scripts/canvas-discover-sdcs.php.");
    }
    $version = $component->getActiveVersion();
    $defs = ($component->getSettings()['prop_field_definitions'] ?? []);
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
        $entry['sourceTypeSettings'] = ['storage' => ['allowed_values_function' => 'canvas_load_allowed_values_for_component_prop']];
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
}

if (!function_exists('canvas_tree_block_item')) {
  function canvas_tree_block_item(string $uuid, string $component_id, array $settings = [], ?string $parent_uuid = NULL, ?string $slot = NULL): array {
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
}

/**
 * Delete any existing canvas_page with the given title (idempotent helper).
 */
function delete_existing_pages_by_title(string $title): void {
  $pages = \Drupal::entityTypeManager()->getStorage('canvas_page')
    ->loadByProperties(['title' => $title]);
  foreach ($pages as $p) {
    $p->delete();
    echo "  · Deleted existing canvas_page id={$p->id()} ({$title})\n";
  }
}

// ============================================================================
// PROYECTOS / PROJECTS
// ============================================================================
echo "── Proyectos / Projects ──\n";
delete_existing_pages_by_title('Proyectos');
delete_existing_pages_by_title('Projects');

$phead_uuid = canvas_tree_uuid();
$grid_uuid = canvas_tree_uuid();
$cta_uuid = canvas_tree_uuid();

$proyectos_es = [
  canvas_tree_sdc_item($phead_uuid, 'sdc.byte.phead', [
    'eyebrow_main'  => '§ proyectos',
    'eyebrow_aside' => 'activos',
    'title'         => 'Cada proyecto es ',
    'title_em'      => 'una creencia hecha código',
    'title_dot'     => '.',
    'sub'           => 'No vendo plantillas ni paquetes. Cada plataforma aquí defiende lo mismo: que la web rápida, accesible y sostenible no es lujo — es la única forma honesta de hacerlo. Lee qué problema resolvía, qué decidí, y qué pasó después.',
  ]),
  canvas_tree_block_item($grid_uuid, 'block.jalvarez_projects_grid', [
    'label' => '',
    'label_display' => '0',
  ]),
  canvas_tree_sdc_item($cta_uuid, 'sdc.byte.cta-final', [
    'title' => 'Si tu caso no aparece aquí, ',
    'title_em' => 'cuéntame el tuyo',
    'sub' => 'Migraciones complejas, plataformas corporativas, stacks headless — el método es el mismo: medir antes de prometer, construir para que dure, respetar a quien visita.',
    'primary_label' => 'Iniciar tu proyecto',
    'primary_href' => '/contacto',
    'secondary_label' => 'Ver el método',
    'secondary_href' => '/#process',
  ]),
];

$proyectos_en = [
  canvas_tree_sdc_item($phead_uuid, 'sdc.byte.phead', [
    'eyebrow_main'  => '§ projects',
    'eyebrow_aside' => 'active',
    'title'         => 'Each project is ',
    'title_em'      => 'a belief made code',
    'title_dot'     => '.',
    'sub'           => 'I don\'t sell templates or packages. Each platform here defends the same idea: that fast, accessible, sustainable web is not luxury — it\'s the only honest way to do it. Read what problem it solved, what I decided, and what happened next.',
  ]),
  canvas_tree_block_item($grid_uuid, 'block.jalvarez_projects_grid', [
    'label' => '',
    'label_display' => '0',
  ]),
  canvas_tree_sdc_item($cta_uuid, 'sdc.byte.cta-final', [
    'title' => 'If your case isn\'t shown here, ',
    'title_em' => 'tell me yours',
    'sub' => 'Complex migrations, corporate platforms, headless stacks — the method is the same: measure before promising, build to last, respect the visitor.',
    'primary_label' => 'Start your project',
    'primary_href' => '/contact',
    'secondary_label' => 'See the method',
    'secondary_href' => '/#process',
  ]),
];

$proyectos = Page::create([
  'title' => 'Proyectos',
  'description' => 'Plataformas, sitios y migraciones construidos con foco en rendimiento, accesibilidad y mantenibilidad.',
  'status' => TRUE,
  'langcode' => 'es',
  'components' => $proyectos_es,
  'path' => ['alias' => '/proyectos'],
]);
$proyectos->save();
echo "  ✓ Created ES canvas_page id={$proyectos->id()} (alias /es/proyectos)\n";

$proyectos->addTranslation('en', [
  'title' => 'Projects',
  'description' => 'Platforms, sites and migrations built with a focus on performance, accessibility and maintainability.',
  'components' => $proyectos_en,
  'path' => ['alias' => '/projects'],
])->save();
echo "  ✓ Added EN translation (alias /en/projects)\n";

// ============================================================================
// NOTAS / NOTES
// ============================================================================
echo "\n── Notas / Notes ──\n";
delete_existing_pages_by_title('Notas');
delete_existing_pages_by_title('Notes');

$phead_uuid = canvas_tree_uuid();
$grid_uuid = canvas_tree_uuid();
$cta_uuid = canvas_tree_uuid();

$notas_es = [
  canvas_tree_sdc_item($phead_uuid, 'sdc.byte.phead', [
    'eyebrow_main'  => '§ notas',
    'eyebrow_aside' => 'campo · taller · prácticas',
    'title'         => 'Lo que aprendo ',
    'title_em'      => 'mientras construyo',
    'title_dot'     => '.',
    'sub'           => 'Notas técnicas, decisiones de arquitectura y opiniones formadas con fricción real. No tutoriales — apuntes de campo de alguien que sigue el código en producción.',
  ]),
  canvas_tree_block_item($grid_uuid, 'block.jalvarez_notes_grid', [
    'label' => '',
    'label_display' => '0',
  ]),
  canvas_tree_sdc_item($cta_uuid, 'sdc.byte.cta-final', [
    'title' => '¿Te suena alguna de estas ideas? ',
    'title_em' => 'hablemos',
    'sub' => 'Si esto resuena con tu forma de pensar la web, podemos llevar la conversación a algo concreto.',
    'primary_label' => 'Iniciar tu proyecto',
    'primary_href' => '/contacto',
    'secondary_label' => 'Ver el trabajo',
    'secondary_href' => '/proyectos',
  ]),
];

$notas_en = [
  canvas_tree_sdc_item($phead_uuid, 'sdc.byte.phead', [
    'eyebrow_main'  => '§ writing',
    'eyebrow_aside' => 'field · workshop · practices',
    'title'         => 'What I learn ',
    'title_em'      => 'while building',
    'title_dot'     => '.',
    'sub'           => 'Technical notes, architecture decisions and opinions formed with real friction. Not tutorials — field notes from someone who keeps code in production.',
  ]),
  canvas_tree_block_item($grid_uuid, 'block.jalvarez_notes_grid', [
    'label' => '',
    'label_display' => '0',
  ]),
  canvas_tree_sdc_item($cta_uuid, 'sdc.byte.cta-final', [
    'title' => 'Do any of these ideas resonate? ',
    'title_em' => 'let\'s talk',
    'sub' => 'If this aligns with how you think about the web, we can take the conversation to something concrete.',
    'primary_label' => 'Start your project',
    'primary_href' => '/contact',
    'secondary_label' => 'See the work',
    'secondary_href' => '/projects',
  ]),
];

$notas = Page::create([
  'title' => 'Notas',
  'description' => 'Notas técnicas, decisiones de arquitectura y opiniones formadas con fricción real.',
  'status' => TRUE,
  'langcode' => 'es',
  'components' => $notas_es,
  'path' => ['alias' => '/notas'],
]);
$notas->save();
echo "  ✓ Created ES canvas_page id={$notas->id()} (alias /es/notas)\n";

$notas->addTranslation('en', [
  'title' => 'Notes',
  'description' => 'Technical notes, architecture decisions and opinions formed with real friction.',
  'components' => $notas_en,
  'path' => ['alias' => '/notes'],
])->save();
echo "  ✓ Added EN translation (alias /en/notes)\n";

// ============================================================================
// CONTACTO / CONTACT
// ============================================================================
echo "\n── Contacto / Contact ──\n";
delete_existing_pages_by_title('Contacto');
delete_existing_pages_by_title('Contact');

$phead_uuid = canvas_tree_uuid();
$form_uuid = canvas_tree_uuid();
$side_uuid = canvas_tree_uuid();

$contacto_es = [
  canvas_tree_sdc_item($phead_uuid, 'sdc.byte.phead', [
    'eyebrow_main'  => '§ contacto',
    'eyebrow_aside' => 'respuesta en < 24h · zona horaria COT (GMT-5)',
    'title'         => 'Antes del código, ',
    'title_em'      => 'una conversación honesta',
    'title_dot'     => '.',
    'sub'           => 'Creo que los mejores proyectos empiezan con preguntas, no con propuestas. Cuéntame qué intentas resolver — no qué quieres construir. Si lo que defendemos coincide, el resto se diseña solo.',
  ]),
  canvas_tree_block_item($form_uuid, 'block.webform_block', [
    'webform_id' => 'contact',
    'default_data' => '',
    'redirect' => FALSE,
    'lazy' => FALSE,
    'label' => '',
    'label_display' => '0',
  ]),
  canvas_tree_sdc_item($side_uuid, 'sdc.byte.canal-directo', [
    'channels_label' => 'Canales directos',
    'c1_name' => 'Email',    'c1_value' => 'contacto@jalvarez.tech', 'c1_href' => 'mailto:contacto@jalvarez.tech',
    'c2_name' => 'WhatsApp', 'c2_value' => 'wa.link/fb2acg',         'c2_href' => 'https://wa.link/fb2acg',
    'c3_name' => 'LinkedIn', 'c3_value' => 'in/stevansalvarez ↗',    'c3_href' => 'https://www.linkedin.com/in/stevansalvarez/',
    'availability_label' => 'Disponibilidad',
    'free_value' => '2',
    'free_label' => 'cupos libres en Q3 2026',
    'response_label' => 'Tiempo de respuesta',
    'response_value' => '< 24h lun-vie',
    'expect_label' => 'Cómo va a ser',
    'step_1' => 'Leo tu mensaje completo y respondo con preguntas — no con un PDF de servicios.',
    'step_2' => 'Si las respuestas alinean, agendamos 30 min para ver si la química sostiene un proyecto.',
    'step_3' => 'Solo entonces escribo una propuesta con alcance, timeline y costo. 3-5 días.',
    'step_4' => 'Si avanzamos: firma, anticipo del 40%, y arrancamos la semana siguiente. Sin sorpresas.',
  ]),
];

$contacto_en = [
  canvas_tree_sdc_item($phead_uuid, 'sdc.byte.phead', [
    'eyebrow_main'  => '§ contact',
    'eyebrow_aside' => 'reply within < 24h · COT timezone (GMT-5)',
    'title'         => 'Before the code, ',
    'title_em'      => 'an honest conversation',
    'title_dot'     => '.',
    'sub'           => 'I believe the best projects start with questions, not proposals. Tell me what you\'re trying to solve — not what you want to build. If we share what we defend, the rest designs itself.',
  ]),
  canvas_tree_block_item($form_uuid, 'block.webform_block', [
    'webform_id' => 'contact',
    'default_data' => '',
    'redirect' => FALSE,
    'lazy' => FALSE,
    'label' => '',
    'label_display' => '0',
  ]),
  canvas_tree_sdc_item($side_uuid, 'sdc.byte.canal-directo', [
    'channels_label' => 'Direct channels',
    'c1_name' => 'Email',    'c1_value' => 'contacto@jalvarez.tech', 'c1_href' => 'mailto:contacto@jalvarez.tech',
    'c2_name' => 'WhatsApp', 'c2_value' => 'wa.link/fb2acg',         'c2_href' => 'https://wa.link/fb2acg',
    'c3_name' => 'LinkedIn', 'c3_value' => 'in/stevansalvarez ↗',    'c3_href' => 'https://www.linkedin.com/in/stevansalvarez/',
    'availability_label' => 'Availability',
    'free_value' => '2',
    'free_label' => 'open slots in Q3 2026',
    'response_label' => 'Response time',
    'response_value' => '< 24h Mon-Fri',
    'expect_label' => 'How it will go',
    'step_1' => 'I read your full message and reply with questions — not a PDF of services.',
    'step_2' => 'If the answers align, we book 30 min to see if the chemistry can sustain a project.',
    'step_3' => 'Only then I write a proposal with scope, timeline and cost. 3-5 days.',
    'step_4' => 'If we move forward: contract, 40% upfront, and we start the following week. No surprises.',
  ]),
];

$contacto = Page::create([
  'title' => 'Contacto',
  'description' => 'Hablemos de tu proyecto. Respuesta en menos de 24 horas. Email, WhatsApp y formulario directo.',
  'status' => TRUE,
  'langcode' => 'es',
  'components' => $contacto_es,
  'path' => ['alias' => '/contacto'],
]);
$contacto->save();
echo "  ✓ Created ES canvas_page id={$contacto->id()} (alias /es/contacto)\n";

$contacto->addTranslation('en', [
  'title' => 'Contact',
  'description' => 'Let\'s talk about your project. Reply within 24 hours. Email, WhatsApp and direct form.',
  'components' => $contacto_en,
  'path' => ['alias' => '/contact'],
])->save();
echo "  ✓ Added EN translation (alias /en/contact)\n";

drupal_flush_all_caches();
echo "\n✅ Done. Canvas pages:\n";
echo "  Proyectos (es+en): id={$proyectos->id()} → /es/proyectos · /en/projects\n";
echo "  Notas     (es+en): id={$notas->id()} → /es/notas · /en/notes\n";
echo "  Contacto  (es+en): id={$contacto->id()} → /es/contacto · /en/contact\n";
echo "Visual editor:\n";
echo "  /canvas/editor/canvas_page/{$proyectos->id()}\n";
echo "  /canvas/editor/canvas_page/{$notas->id()}\n";
echo "  /canvas/editor/canvas_page/{$contacto->id()}\n";
