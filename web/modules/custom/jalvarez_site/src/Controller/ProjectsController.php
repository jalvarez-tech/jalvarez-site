<?php

declare(strict_types=1);

namespace Drupal\jalvarez_site\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;

/**
 * Renders the Proyectos listing page composed of byte SDC components.
 *
 * Mirrors DESIGN.md §4.2: phead → grid of card-proyecto → cta-final.
 */
class ProjectsController extends ControllerBase {

  public function index(): array {
    // Load published project nodes ordered by field_sort_order ASC (then nid).
    $nids = $this->entityTypeManager()
      ->getStorage('node')
      ->getQuery()
      ->condition('type', 'project')
      ->condition('status', NodeInterface::PUBLISHED)
      ->sort('field_sort_order', 'ASC')
      ->sort('nid', 'ASC')
      ->accessCheck(TRUE)
      ->execute();

    $projects = $nids ? Node::loadMultiple($nids) : [];

    $items = [];
    foreach ($projects as $node) {
      $items[] = $this->buildCardProps($node);
    }

    $build = [
      'phead' => [
        '#type' => 'component',
        '#component' => 'byte:phead',
        '#props' => [
          'eyebrow_main'  => '§ proyectos',
          'eyebrow_aside' => 'activos',
          'title'         => 'Cada proyecto es ',
          'title_em'      => 'una creencia hecha código',
          'title_dot'     => '.',
          'sub'           => 'No vendo plantillas ni paquetes. Cada plataforma aquí defiende lo mismo: que la web rápida, accesible y sostenible no es lujo — es la única forma honesta de hacerlo. Lee qué problema resolvía, qué decidí, y qué pasó después.',
        ],
      ],
    ];

    if ($items) {
      $build['grid'] = [
        '#prefix' => '<section class="section wrap"><div class="projects-grid">',
        '#suffix' => '</div></section>',
        'cards' => [],
      ];
      foreach ($items as $i => $props) {
        $build['grid']['cards'][$i] = [
          '#type' => 'component',
          '#component' => 'byte:card-proyecto',
          '#props' => $props,
        ];
      }
    }
    else {
      $build['empty'] = [
        '#markup' => '<section class="section wrap"><p class="section__lede" style="margin-top: 60px;">Aún no hay proyectos publicados.</p></section>',
      ];
    }

    $build['cta'] = [
      '#type' => 'component',
      '#component' => 'byte:cta-final',
      '#props' => [
        'title' => 'Si tu caso no aparece aquí, ',
        'title_em' => 'cuéntame el tuyo',
        'sub' => 'Migraciones complejas, plataformas corporativas, stacks headless — el método es el mismo: medir antes de prometer, construir para que dure, respetar a quien visita.',
        'primary_label' => 'Iniciar tu proyecto',
        'primary_href' => '/contacto',
        'secondary_label' => 'Ver el método',
        'secondary_href' => '/#process',
      ],
    ];

    // Cache: invalidate when any project node changes or terms change.
    $build['#cache'] = [
      'tags' => ['node_list:project', 'taxonomy_term_list'],
      'contexts' => ['languages:language_interface'],
    ];

    return $build;
  }

  /**
   * Map a Project node → card-proyecto SDC props.
   */
  private function buildCardProps(NodeInterface $node): array {
    $name = $node->label();

    // Category chip = primary technology (the design treats this as a "stack" chip).
    $category = '';
    if ($node->hasField('field_primary_technology') && !$node->get('field_primary_technology')->isEmpty()) {
      $term = $node->get('field_primary_technology')->entity;
      if ($term) {
        $category = $term->label();
      }
    }

    // Year chip.
    $year = '';
    if ($node->hasField('field_project_year') && !$node->get('field_project_year')->isEmpty()) {
      $year = (string) $node->get('field_project_year')->value;
    }

    // Description (summary).
    $description = '';
    if ($node->hasField('field_summary') && !$node->get('field_summary')->isEmpty()) {
      $description = $node->get('field_summary')->value;
    }

    // Hue: map the list_string (green/orange/etc) to OKLCH 0-360 for the placeholder.
    $hue_map = [
      'green' => 160, 'orange' => 18, 'blue' => 220,
      'purple' => 280, 'red' => 0, 'amber' => 40,
    ];
    $hue = 160;
    if ($node->hasField('field_cover_hue') && !$node->get('field_cover_hue')->isEmpty()) {
      $hue = $hue_map[$node->get('field_cover_hue')->value] ?? 160;
    }

    // Metrics: read from field_results_metrics paragraphs.
    $metrics = [];
    if ($node->hasField('field_results_metrics') && !$node->get('field_results_metrics')->isEmpty()) {
      foreach ($node->get('field_results_metrics')->referencedEntities() as $para) {
        $metrics[] = [
          'key'   => $para->get('field_metric_key')->value ?? '',
          'value' => $para->get('field_metric_value')->value ?? '',
        ];
        if (count($metrics) >= 2) {
          break;
        }
      }
    }

    return [
      'name'        => $name,
      'href'        => $node->toUrl()->toString(),
      'category'    => $category,
      'year'        => $year,
      'description' => $description,
      'hue'         => $hue,
      'metrics'     => $metrics,
    ];
  }

}
