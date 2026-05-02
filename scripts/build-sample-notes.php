<?php

/**
 * @file
 * Sample note_topics taxonomy + 5 sample Note nodes (idempotent).
 *
 * Run: ddev exec ./web/vendor/bin/drush php:script scripts/build-sample-notes.php
 */

use Drupal\taxonomy\Entity\Term;
use Drupal\node\Entity\Node;

function ensure_term_n(string $vid, string $name): Term {
  $tids = \Drupal::entityQuery('taxonomy_term')
    ->condition('vid', $vid)
    ->condition('name', $name)
    ->accessCheck(FALSE)
    ->execute();
  if (!empty($tids)) {
    return Term::load(reset($tids));
  }
  $term = Term::create(['vid' => $vid, 'name' => $name, 'langcode' => 'es']);
  $term->save();
  echo "  + term {$vid}.{$name}\n";
  return $term;
}

echo "Note topic terms\n";
$t_perf  = ensure_term_n('note_topics', 'Performance');
$t_arch  = ensure_term_n('note_topics', 'Arquitectura');
$t_drupal = ensure_term_n('note_topics', 'Drupal');
$t_a11y  = ensure_term_n('note_topics', 'Accesibilidad');
$t_auto  = ensure_term_n('note_topics', 'Automatización');

echo "\nSample notes\n";

$notes = [
  [
    'title'   => 'Lo que aprendí midiendo Core Web Vitals en 3 clientes',
    'topic'   => $t_perf,
    'excerpt' => 'LCP, CLS y peso de página: tres números que cambiaron mi forma de proponer arquitecturas. Notas honestas de campo, no de laboratorio.',
    'body'    => '<p>Los Core Web Vitals son la única forma seria de medir performance hoy. Después de auditar 3 sitios distintos llegué a 3 conclusiones contraintuitivas...</p>',
    'glyph'   => 'gauge',
    'hue'     => 'green',
    'date'    => '2026-04-15',
  ],
  [
    'title'   => 'Drupal o WordPress: la matriz que uso para decidir',
    'topic'   => $t_arch,
    'excerpt' => 'No es "cuál es mejor". Es: cuándo usar uno o el otro. Una matriz de 5 ejes que aplico antes de proponer stack al cliente.',
    'body'    => '<p>La pregunta "Drupal vs WordPress" es trampa. La pregunta correcta es: dado un cliente, equipo, presupuesto y horizonte, ¿cuál de los dos minimiza costo total a 5 años?</p>',
    'glyph'   => 'layers',
    'hue'     => 'orange',
    'date'    => '2026-03-28',
  ],
  [
    'title'   => 'El workflow de n8n que me devolvió 4 horas a la semana',
    'topic'   => $t_auto,
    'excerpt' => 'Recibí formularios de contacto, los enrichí con Clearbit, los pusheé a HubSpot, y notifiqué a Slack — todo automático. JSON exportado adentro.',
    'body'    => '<p>El cuello de botella en mi semana siempre fue el triage manual de leads. Desde que monté este flow en n8n, se redujo a casi cero...</p>',
    'glyph'   => 'workflow',
    'hue'     => 'purple',
    'date'    => '2026-03-12',
  ],
  [
    'title'   => '10 componentes accesibles que copio y pego en cada proyecto',
    'topic'   => $t_a11y,
    'excerpt' => 'Modal, dropdown, tabs, tooltip, accordion. Código listo, probado con NVDA, JAWS, VoiceOver. Sin bibliotecas pesadas.',
    'body'    => '<p>Estos 10 componentes los reescribí con accesibilidad nativa, sin frameworks externos. Cada uno cumple WCAG 2.2 AA verificado con auditoría real, no con linter...</p>',
    'glyph'   => 'accessibility',
    'hue'     => 'blue',
    'date'    => '2026-02-20',
  ],
  [
    'title'   => 'Mi stack 2026: las herramientas que de verdad uso',
    'topic'   => $t_drupal,
    'excerpt' => 'Sin afiliados, sin hype. Las herramientas que sobrevivieron 12 meses de uso real en proyectos pagados. Y por qué.',
    'body'    => '<p>Cada año reviso mi stack y elimino lo que no estoy usando. Para 2026 quedaron 8 herramientas core. Acá están con el por qué de cada una.</p>',
    'glyph'   => 'mail',
    'hue'     => 'amber',
    'date'    => '2026-01-30',
  ],
];

foreach ($notes as $n) {
  $existing = \Drupal::entityQuery('node')
    ->condition('type', 'note')
    ->condition('title', $n['title'])
    ->accessCheck(FALSE)
    ->execute();
  if (!empty($existing)) {
    echo "  = note '{$n['title']}' exists (skipping)\n";
    continue;
  }

  $node = Node::create([
    'type'     => 'note',
    'title'    => $n['title'],
    'langcode' => 'es',
    'status'   => 1,
    'body'                  => ['value' => $n['body'], 'format' => 'basic_html'],
    'field_excerpt'         => ['value' => $n['excerpt'], 'format' => 'plain_text'],
    'field_publish_date'    => $n['date'],
    'field_note_topic'      => ['target_id' => $n['topic']->id()],
    'field_thumb_glyph'     => $n['glyph'],
    'field_thumb_hue'       => $n['hue'],
  ]);
  $node->save();
  echo "  + note '{$n['title']}' (nid={$node->id()})\n";
}

\Drupal::service('cache.discovery')->invalidateAll();
drupal_flush_all_caches();
echo "\n✅ Sample notes built.\n";
