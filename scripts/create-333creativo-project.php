<?php
/**
 * @file
 * Seed the 333 Creativo project node — Drupal + Drupal Commerce site for
 * a creative agency in Medellín. Creates ES + EN translations, downloads
 * the agency logo as cover_media, and wires the related taxonomies +
 * paragraph references. Sets sort_order to 4 so it lands after the three
 * existing case studies on /proyectos and /projects.
 *
 * Idempotent: if a project node titled '333 Creativo' already exists, it
 * gets refreshed instead of duplicated.
 *
 * Run via:
 *   ddev exec ./web/vendor/bin/drush php:script scripts/create-333creativo-project.php
 *   gh workflow run seed-content.yml --field script=scripts/create-333creativo-project.php
 */

use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;

$external_url = 'https://www.333creativo.com';
$logo_url     = 'https://www.333creativo.com/sites/default/files/inline-images/Logo-333-creativo.png';
$cover_label  = '333 Creativo cover';

// Taxonomy refs known from the project_categories + technologies vocabularies.
$tax = [
  'category_web' => 1,
  'tech_drupal11' => 6,
];

// ─── 1. Find or create the node ──────────────────────────────────────────
$existing = \Drupal::entityQuery('node')
  ->condition('type', 'project')
  ->condition('title', '333 Creativo')
  ->accessCheck(FALSE)
  ->execute();

if ($existing) {
  $node = Node::load(reset($existing));
  echo "= Reusing existing project nid={$node->id()}\n";
}
else {
  $node = Node::create([
    'type' => 'project',
    'langcode' => 'es',
    'title' => '333 Creativo',
    'status' => 1,
  ]);
  echo "+ Creating new project node\n";
}

// ─── 2. ES content ────────────────────────────────────────────────────────
$es = [
  'title'                 => '333 Creativo',
  'field_summary'         => 'Sitio Drupal + Drupal Commerce para agencia creativa de Medellín: catálogo de servicios, portafolio, testimonios y un panel administrable que el equipo de la agencia maneja sin intervención técnica.',
  'field_intro'           => 'Plataforma web para 333 Creativo, agencia de branding, diseño y marketing digital. Implementación en Drupal con Drupal Commerce para servicios contratables, sistema editorial bilingüe y arquitectura preparada para escalar el catálogo sin tocar código. La agencia administra contenido, casos de éxito y servicios desde un panel pensado para creativos, no para developers.',
  'field_duration'        => '4 meses',
  'field_role'            => 'Senior Drupal Specialist',
  'field_project_year'    => 2024,
  'field_publish_date'    => '2024-08-01',
  'field_external_url'    => ['uri' => $external_url, 'title' => '333creativo.com'],
  'field_primary_technology' => ['target_id' => $tax['tech_drupal11']],
  'field_project_category'   => ['target_id' => $tax['category_web']],
  'field_stack'              => [['target_id' => $tax['tech_drupal11']]],
  'field_cover_hue'       => 'orange',
  'field_featured_home'   => 0,
  'field_sort_order'      => 4,
  'field_challenge_intro' => 'La agencia tenía presencia digital fragmentada: portafolio en una plataforma, formularios en otra, contenido editorial en un blog separado. El equipo quería una sola plataforma donde mostrar trabajo, vender servicios, capturar leads y escribir contenido — sin pagar a un developer cada vez que lanzaran una campaña.',
  'field_challenge_bullets' => [
    'Portafolio, blog y formularios viviendo en herramientas distintas — el equipo perdía tiempo coordinando entre plataformas y los datos no se cruzaban.',
    'Sin sistema editorial bilingüe — cada release implicaba mantener dos sitios paralelos manualmente.',
    'Servicios contratables vivían en PDFs descargables — no había forma de que un cliente cotizara y pagara desde el sitio.',
  ],
  'field_lesson'        => 'Una agencia creativa necesita un CMS que no se sienta como un CMS — campos limpios, vista previa fiel, y nada de jerga técnica en el panel. Cuando los editores entienden el sistema sin entrenamiento, el contenido fluye y la dependencia de developer desaparece.',
  'field_cta_heading'   => '¿Tu agencia opera con tres herramientas distintas que deberían ser una sola?',
  'field_cta_sub'       => 'El método aplica si tu agencia, estudio o consultora necesita unificar portafolio, contenido y servicios en una sola plataforma — administrable por gente que no es developer.',
];

// ─── 3. EN content ────────────────────────────────────────────────────────
$en = [
  'title'                 => '333 Creativo',
  'field_summary'         => 'Drupal + Drupal Commerce site for a Medellín-based creative agency: services catalogue, portfolio, testimonials, and an admin panel the agency team operates with no technical hand-holding.',
  'field_intro'           => 'Web platform for 333 Creativo — a branding, design and digital marketing agency. Built on Drupal with Drupal Commerce for purchasable services, a bilingual editorial system, and an architecture ready to grow the catalogue without touching code. The agency manages content, case studies and services from a panel designed for creatives, not developers.',
  'field_duration'        => '4 months',
  'field_role'            => 'Senior Drupal Specialist',
  'field_challenge_intro' => 'The agency had fragmented digital presence: portfolio on one platform, forms on another, editorial content on a separate blog. The team wanted one platform where they could showcase work, sell services, capture leads, and publish content — without paying a developer every time they launched a campaign.',
  'field_challenge_bullets' => [
    'Portfolio, blog and forms living in different tools — the team lost time coordinating between platforms and the data never crossed over.',
    'No bilingual editorial system — every release meant maintaining two parallel sites by hand.',
    'Purchasable services lived in downloadable PDFs — no way for a client to quote and pay from the site.',
  ],
  'field_lesson'        => 'A creative agency needs a CMS that doesn\'t feel like a CMS — clean fields, faithful preview, and no technical jargon in the panel. When editors understand the system without training, content flows and the dependency on a developer disappears.',
  'field_cta_heading'   => 'Is your agency running on three different tools that should be one?',
  'field_cta_sub'       => 'The method applies if your agency, studio or consultancy needs to unify portfolio, content, and services in a single platform — owned by people who aren\'t developers.',
];

// ─── 4. Apply ES (default) translation ───────────────────────────────────
foreach ($es as $field => $value) {
  if (!$node->hasField($field)) continue;
  if ($field === 'field_challenge_bullets') {
    $node->set($field, array_map(fn($t) => ['value' => $t], $value));
  }
  else {
    $node->set($field, $value);
  }
}
$node->set('langcode', 'es');
$node->setPublished()->save();
echo "✓ Saved ES content (nid={$node->id()})\n";

// ─── 5. EN translation ────────────────────────────────────────────────────
if (!$node->hasTranslation('en')) {
  $node->addTranslation('en', $en);
}
else {
  $en_trans = $node->getTranslation('en');
  foreach ($en as $field => $value) {
    if (!$en_trans->hasField($field)) continue;
    if ($field === 'field_challenge_bullets') {
      $en_trans->set($field, array_map(fn($t) => ['value' => $t], $value));
    }
    else {
      $en_trans->set($field, $value);
    }
  }
}
$node->getTranslation('en')->setPublished()->save();
echo "✓ Saved EN translation\n";

// ─── 6. Cover image (logo) ────────────────────────────────────────────────
$media_storage = \Drupal::entityTypeManager()->getStorage('media');
$existing_media = $media_storage->loadByProperties(['bundle' => 'image', 'name' => $cover_label]);
$media = $existing_media ? reset($existing_media) : NULL;

if (!$media) {
  echo "→ Downloading logo {$logo_url}…\n";
  $bytes = @file_get_contents($logo_url);
  if ($bytes !== FALSE && strlen($bytes) > 1000) {
    $directory = 'public://' . date('Y-m');
    \Drupal::service('file_system')->prepareDirectory($directory, \Drupal\Core\File\FileSystemInterface::CREATE_DIRECTORY);
    $file = \Drupal::service('file.repository')->writeData(
      $bytes,
      "{$directory}/333creativo-cover.png",
      \Drupal\Core\File\FileExists::Replace
    );
    $media = Media::create([
      'bundle' => 'image',
      'name'   => $cover_label,
      'field_media_image' => [
        'target_id' => $file->id(),
        'alt'       => '333 Creativo — agencia creativa de Medellín',
      ],
      'status' => 1,
    ]);
    $media->save();
    echo "  · media mid={$media->id()} created\n";
  }
  else {
    echo "  ⚠ Could not download logo, skipping cover\n";
  }
}
else {
  echo "= Reusing existing media mid={$media->id()}\n";
}

if ($media) {
  $node->set('field_cover_media', ['target_id' => $media->id()]);
  foreach ($node->getTranslationLanguages() as $lc => $_) {
    $node->getTranslation($lc)->setPublished()->save();
  }
  echo "✓ field_cover_media → mid={$media->id()}\n";
}

// ─── 7. Approach steps (paragraphs) ──────────────────────────────────────
$approach_steps_data = [
  [
    'es' => ['title' => 'Auditoría + arquitectura', 'tag' => 'Mes 1', 'body' => 'Levanté el inventario de contenido vivo en las 3 plataformas anteriores. Definí el modelo de contenido en Drupal con tipos para servicio, caso de éxito, testimonio y artículo. Modelo bilingüe simétrico desde el día uno.'],
    'en' => ['title' => 'Audit + architecture', 'tag' => 'Month 1', 'body' => 'I inventoried live content across the 3 previous platforms. Defined the content model in Drupal with types for service, case study, testimonial and article. Symmetric bilingual model from day one.'],
  ],
  [
    'es' => ['title' => 'Drupal Commerce', 'tag' => 'Mes 2', 'body' => 'Configuración de Drupal Commerce para servicios contratables: variantes por alcance, checkout en pasos cortos, integración con pasarela local. El equipo administra precios y disponibilidad sin tocar código.'],
    'en' => ['title' => 'Drupal Commerce', 'tag' => 'Month 2', 'body' => 'Drupal Commerce setup for purchasable services: variants by scope, short-step checkout, local payment gateway integration. The team manages prices and availability without touching code.'],
  ],
  [
    'es' => ['title' => 'Editor + previews', 'tag' => 'Mes 3', 'body' => 'Capa de edición pulida: vistas previas fieles, campos agrupados por contexto, sin jerga técnica en el panel. Layout Builder configurado con bloques que el equipo creativo puede reordenar visualmente.'],
    'en' => ['title' => 'Editor + previews', 'tag' => 'Month 3', 'body' => 'Polished editing layer: faithful previews, fields grouped by context, no technical jargon in the panel. Layout Builder configured with blocks the creative team can rearrange visually.'],
  ],
  [
    'es' => ['title' => 'Migración + lanzamiento', 'tag' => 'Mes 4', 'body' => 'Migración del contenido legacy con Migrate API, redirects 301 desde las URLs antiguas. Lanzamiento sin downtime, monitoreo de Core Web Vitals durante las primeras 4 semanas, y plan de soporte editorial para el equipo.'],
    'en' => ['title' => 'Migration + launch', 'tag' => 'Month 4', 'body' => 'Legacy content migrated via Migrate API, 301 redirects from the old URLs. Zero-downtime launch, Core Web Vitals monitoring through the first 4 weeks, and an editorial support plan for the team.'],
  ],
];

// Replace existing approach_steps if any (we don't merge — fresh list).
foreach ($node->get('field_approach_steps')->referencedEntities() as $p) {
  $p->delete();
}
$step_refs = [];
foreach ($approach_steps_data as $data) {
  $step = Paragraph::create([
    'type' => 'case_step',
    'langcode' => 'es',
    'field_step_title' => $data['es']['title'],
    'field_step_tag'   => $data['es']['tag'],
    'field_step_body'  => $data['es']['body'],
  ]);
  $step->save();
  if (!empty($data['en'])) {
    $step->addTranslation('en', [
      'field_step_title' => $data['en']['title'],
      'field_step_tag'   => $data['en']['tag'],
      'field_step_body'  => $data['en']['body'],
    ])->save();
  }
  $step_refs[] = ['target_id' => $step->id(), 'target_revision_id' => $step->getRevisionId()];
}
$node->set('field_approach_steps', $step_refs);

// ─── 8. Result metrics (paragraphs) ──────────────────────────────────────
$metrics_data = [
  [
    'es' => ['key' => 'Idiomas', 'value' => '2', 'note' => 'ES + EN'],
    'en' => ['key' => 'Languages', 'value' => '2', 'note' => 'ES + EN'],
  ],
  [
    'es' => ['key' => 'Plataformas', 'value' => '3→1', 'note' => 'unificadas en una sola'],
    'en' => ['key' => 'Platforms', 'value' => '3→1', 'note' => 'unified into one'],
  ],
];

foreach ($node->get('field_results_metrics')->referencedEntities() as $p) {
  $p->delete();
}
$metric_refs = [];
foreach ($metrics_data as $data) {
  $metric = Paragraph::create([
    'type' => 'metric',
    'langcode' => 'es',
    'field_metric_key'   => $data['es']['key'],
    'field_metric_value' => $data['es']['value'],
    'field_metric_note'  => $data['es']['note'],
  ]);
  $metric->save();
  $metric->addTranslation('en', [
    'field_metric_key'   => $data['en']['key'],
    'field_metric_value' => $data['en']['value'],
    'field_metric_note'  => $data['en']['note'],
  ])->save();
  $metric_refs[] = ['target_id' => $metric->id(), 'target_revision_id' => $metric->getRevisionId()];
}
$node->set('field_results_metrics', $metric_refs);

foreach ($node->getTranslationLanguages() as $lc => $_) {
  $node->getTranslation($lc)->setPublished()->save();
}
echo "✓ Approach steps (4) + result metrics (2) attached\n";

drupal_flush_all_caches();
echo "\n✓ 333 Creativo project ready in ES + EN.\n";
echo "  Verify: /es/proyectos · /es/proyectos/333-creativo · /en/projects/333-creativo\n";
