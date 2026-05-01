<?php

/**
 * @file
 * Phase G — Webform contact + Pathauto patterns.
 * Run: ddev exec ./web/vendor/bin/drush php:script scripts/build-phase-g.php
 */

use Drupal\webform\Entity\Webform;
use Drupal\webform\Entity\WebformOptions;
use Drupal\pathauto\Entity\PathautoPattern;
use Symfony\Component\Yaml\Yaml;

/* ─────────────────────────────────────────────────────────────────────────
 * 1. Webform options (project_types, project_budgets)
 * ───────────────────────────────────────────────────────────────────────── */
foreach ([
  'project_types' => [
    'label' => 'Tipo de proyecto',
    'options' => [
      'web' => 'Sitio corporativo',
      'ecom' => 'E-commerce',
      'custom' => 'Plataforma custom',
      'migration' => 'Migración',
      'audit' => 'Auditoría',
    ],
  ],
  'project_budgets' => [
    'label' => 'Presupuesto aproximado (USD)',
    'options' => [
      'low' => '< $5k',
      'mid' => '$5k - $15k',
      'high' => '$15k - $40k',
      'enter' => '$40k+',
      'unsure' => 'Aún no estoy seguro',
    ],
  ],
] as $id => $config) {
  if (!WebformOptions::load($id)) {
    WebformOptions::create([
      'id' => $id,
      'label' => $config['label'],
      'options' => Yaml::dump($config['options']),
    ])->save();
    echo "✓ WebformOptions: {$id}\n";
  }
}

/* ─────────────────────────────────────────────────────────────────────────
 * 2. Webform contact
 * ───────────────────────────────────────────────────────────────────────── */
$contact_elements = <<<YAML
name:
  '#type': textfield
  '#title': 'Nombre'
  '#required': true
  '#placeholder': 'John Doe'
email:
  '#type': email
  '#title': 'Correo'
  '#required': true
  '#placeholder': 'john@empresa.com'
company:
  '#type': textfield
  '#title': 'Empresa / proyecto'
  '#placeholder': 'Nombre de tu marca o startup'
project_type:
  '#type': radios
  '#title': 'Tipo de proyecto'
  '#options': project_types
  '#default_value': 'web'
budget:
  '#type': radios
  '#title': 'Presupuesto aproximado (USD)'
  '#options': project_budgets
  '#default_value': 'mid'
message:
  '#type': textarea
  '#title': 'Cuéntame'
  '#required': true
  '#description': 'Mientras más contexto, mejor.'
  '#placeholder': 'Qué intentas resolver, qué te frena hoy, qué pasaría si no lo resuelves...'
  '#rows': 6
actions:
  '#type': webform_actions
  '#submit__label': 'Enviar mensaje'
YAML;

if (!Webform::load('contact')) {
  Webform::create([
    'id' => 'contact',
    'title' => 'Contacto',
    'description' => 'Formulario de contacto para nuevos proyectos.',
    'status' => 'open',
    'elements' => $contact_elements,
    'settings' => [
      'submission_log' => TRUE,
      'wizard_progress_bar' => FALSE,
    ],
  ])->save();
  echo "✓ Webform: contact\n";
}

/* ─────────────────────────────────────────────────────────────────────────
 * 3. Pathauto patterns
 * ───────────────────────────────────────────────────────────────────────── */
$patterns = [
  [
    'id' => 'project',
    'label' => 'Proyecto',
    'type' => 'canonical_entities:node',
    'pattern' => 'proyectos/[node:title]',
    'bundles' => ['project' => 'project'],
  ],
  [
    'id' => 'note',
    'label' => 'Nota',
    'type' => 'canonical_entities:node',
    'pattern' => 'notas/[node:title]',
    'bundles' => ['note' => 'note'],
  ],
  [
    'id' => 'taxonomy_term',
    'label' => 'Términos de taxonomía',
    'type' => 'canonical_entities:taxonomy_term',
    'pattern' => '[term:vocabulary]/[term:name]',
  ],
];
foreach ($patterns as $pat) {
  if (PathautoPattern::load($pat['id'])) {
    echo "= Pathauto pattern exists: {$pat['id']} (skipping)\n";
    continue;
  }
  $config = [
    'id' => $pat['id'],
    'label' => $pat['label'],
    'type' => $pat['type'],
    'pattern' => $pat['pattern'],
    'status' => TRUE,
    'weight' => 0,
  ];
  if (!empty($pat['bundles'])) {
    $config['selection_criteria'] = [
      [
        'id' => 'entity_bundle:node',
        'bundles' => $pat['bundles'],
        'negate' => FALSE,
        'context_mapping' => ['node' => 'node'],
        'uuid' => \Drupal::service('uuid')->generate(),
      ],
    ];
  }
  PathautoPattern::create($config)->save();
  echo "✓ Pathauto pattern: {$pat['id']}\n";
}

\Drupal::service('cache.discovery')->invalidateAll();
drupal_flush_all_caches();
echo "\n✅ Phase G config built.\n";
