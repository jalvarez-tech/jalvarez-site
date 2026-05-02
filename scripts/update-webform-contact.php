<?php
/**
 * @file
 * Update webform.webform.contact with Spanish labels and the full
 * project_type/budget structure from DESIGN.md §9. Idempotent.
 */

use Drupal\webform\Entity\Webform;

$webform = Webform::load('contact');
if (!$webform) {
  echo "✗ webform 'contact' not found\n";
  return;
}

$elements = <<<YAML
name:
  '#title': 'Nombre'
  '#type': textfield
  '#required': true
  '#placeholder': 'John Doe'
email:
  '#title': 'Correo'
  '#type': email
  '#required': true
  '#placeholder': 'john@empresa.com'
company:
  '#title': 'Empresa / proyecto'
  '#type': textfield
  '#placeholder': 'Nombre de tu marca o startup'
project_type:
  '#title': 'Tipo de proyecto'
  '#type': radios
  '#options': project_types
  '#default_value': 'web'
budget:
  '#title': 'Presupuesto aproximado (USD)'
  '#type': radios
  '#options': project_budgets
  '#default_value': 'mid'
message:
  '#title': 'Cuéntame'
  '#type': textarea
  '#required': true
  '#description': 'Mientras más contexto, mejor.'
  '#placeholder': "Qué intentas resolver, qué te frena hoy, qué pasaría si no lo resuelves. El cómo lo discutimos después..."
  '#rows': 6
actions:
  '#type': webform_actions
  '#submit__label': 'Enviar mensaje'
YAML;

$webform->set('title', 'Contacto');
$webform->set('description', 'Formulario de contacto para nuevos proyectos.');
$webform->set('elements', $elements);
$webform->save();
echo "✓ webform 'contact' updated with Spanish labels\n";
