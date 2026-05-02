<?php

declare(strict_types=1);

namespace Drupal\jalvarez_site\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;

/**
 * Renders the Notas listing page composed of byte SDC components.
 *
 * Mirrors DESIGN.md §4.3: phead → list of row-nota → CTA newsletter.
 */
class NotesController extends ControllerBase {

  public function index(): array {
    $nids = $this->entityTypeManager()
      ->getStorage('node')
      ->getQuery()
      ->condition('type', 'note')
      ->condition('status', NodeInterface::PUBLISHED)
      ->sort('field_publish_date', 'DESC')
      ->sort('nid', 'DESC')
      ->accessCheck(TRUE)
      ->execute();

    $notes = $nids ? Node::loadMultiple($nids) : [];

    $build = [
      'phead' => [
        '#type' => 'component',
        '#component' => 'byte:phead',
        '#props' => [
          'eyebrow_main'  => '§ writing',
          'eyebrow_aside' => 'una al mes, sin agenda',
          'title'         => 'Lo que aprendo, ',
          'title_em'      => 'lo dejo aquí',
          'title_dot'     => '.',
          'sub'           => 'Cada proyecto me enseña algo que no sabía. Estas notas son el cuaderno público donde lo guardo — para no olvidarlo, y por si a alguien más le ahorra una semana de tropezar con lo mismo.',
        ],
      ],
    ];

    if ($notes) {
      $build['list'] = [
        '#prefix' => '<section class="section wrap"><div class="notes-list">',
        '#suffix' => '</div></section>',
      ];
      foreach ($notes as $i => $node) {
        $build['list'][$i] = [
          '#type' => 'component',
          '#component' => 'byte:row-nota',
          '#props' => $this->buildRowProps($node),
        ];
      }
    }
    else {
      $build['empty'] = [
        '#markup' => '<section class="section wrap"><p class="section__lede" style="margin-top: 60px;">Aún no hay notas publicadas.</p></section>',
      ];
    }

    $build['cta'] = [
      '#type' => 'component',
      '#component' => 'byte:cta-final',
      '#props' => [
        'title' => 'Si te sirvió algo de lo que ',
        'title_em' => 'compartí aquí',
        'sub' => 'Una vez al mes te llega un correo con la nota más útil que escribí, un par de herramientas nuevas que probé, y algún error costoso que cometí — para que tú no tengas que repetirlo.',
        'primary_label' => 'Suscribirme',
        'primary_href' => '/contacto',
        'secondary_label' => 'Ver proyectos',
        'secondary_href' => '/proyectos',
      ],
    ];

    $build['#cache'] = [
      'tags' => ['node_list:note', 'taxonomy_term_list'],
      'contexts' => ['languages:language_interface'],
    ];

    return $build;
  }

  /**
   * Map a Note node → row-nota SDC props.
   */
  private function buildRowProps(NodeInterface $node): array {
    $title = $node->label();

    $excerpt = '';
    if ($node->hasField('field_excerpt') && !$node->get('field_excerpt')->isEmpty()) {
      $excerpt = $node->get('field_excerpt')->value;
    }

    $category = '';
    if ($node->hasField('field_note_topic') && !$node->get('field_note_topic')->isEmpty()) {
      $term = $node->get('field_note_topic')->entity;
      if ($term) {
        $category = $term->label();
      }
    }

    $date = '';
    if ($node->hasField('field_publish_date') && !$node->get('field_publish_date')->isEmpty()) {
      $raw = $node->get('field_publish_date')->value; // YYYY-MM-DD
      if ($raw) {
        $months_es = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
        $parts = explode('-', $raw);
        if (count($parts) >= 3) {
          $month_idx = ((int) $parts[1]) - 1;
          $month = $months_es[$month_idx] ?? $parts[1];
          $date = "{$month} · {$parts[0]}";
        }
      }
    }

    $glyph = $node->hasField('field_thumb_glyph') && !$node->get('field_thumb_glyph')->isEmpty()
      ? $node->get('field_thumb_glyph')->value
      : 'mail';

    $hue = $node->hasField('field_thumb_hue') && !$node->get('field_thumb_hue')->isEmpty()
      ? $node->get('field_thumb_hue')->value
      : 'green';

    // Estimate read time from body length (200 words/min).
    $read_time = '';
    if ($node->hasField('body') && !$node->get('body')->isEmpty()) {
      $words = str_word_count(strip_tags($node->get('body')->value ?? ''));
      $minutes = max(1, (int) ceil($words / 200));
      $read_time = "{$minutes} min";
    }

    return [
      'title'     => $title,
      'excerpt'   => $excerpt,
      'href'      => $node->toUrl()->toString(),
      'date'      => $date,
      'category'  => $category,
      'read_time' => $read_time,
      'glyph'     => $glyph,
      'hue'       => $hue,
    ];
  }

}
