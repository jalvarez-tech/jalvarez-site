<?php

declare(strict_types=1);

namespace Drupal\jalvarez_site\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;

/**
 * Renders a grid of project nodes as `byte:card-proyecto` SDC instances.
 *
 * Configurable:
 *  - `only_featured` (bool, default FALSE) — filter by `field_featured_home`.
 *  - `limit` (int, default 0 = all).
 *  - `wrap` (string, default 'section'):
 *     · 'section' — full standalone wrapper `<section class="section wrap"><div class="projects-grid">…</div></section>`
 *     · 'grid'    — only the grid wrapper `<div class="projects-grid">…</div>`
 *     · 'none'    — bare cards (use when the parent SDC slot already provides
 *                   both the section and the grid wrapper, e.g. inside
 *                   `byte:que-construyo`'s `projects` slot).
 */
#[Block(
  id: 'jalvarez_projects_grid',
  admin_label: new TranslatableMarkup('Proyectos — grid (byte cards)'),
  category: new TranslatableMarkup('Jalvarez'),
)]
final class ProjectsGridBlock extends BlockBase {

  public function defaultConfiguration(): array {
    return [
      'only_featured' => FALSE,
      'limit' => 0,
      'wrap' => 'section',
    ] + parent::defaultConfiguration();
  }

  public function blockForm($form, FormStateInterface $form_state): array {
    $form['only_featured'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Mostrar solo proyectos destacados (field_featured_home)'),
      '#default_value' => $this->configuration['only_featured'],
    ];
    $form['limit'] = [
      '#type' => 'number',
      '#title' => $this->t('Límite de proyectos a mostrar (0 = sin límite)'),
      '#default_value' => $this->configuration['limit'],
      '#min' => 0,
      '#max' => 50,
    ];
    $form['wrap'] = [
      '#type' => 'select',
      '#title' => $this->t('Wrapper HTML'),
      '#options' => [
        'section' => $this->t('Section + grid (uso autónomo en una página)'),
        'grid' => $this->t('Solo grid (la sección la provee el padre)'),
        'none' => $this->t('Sin wrapper (cards crudas, para slots con grid propio)'),
      ],
      '#default_value' => $this->configuration['wrap'],
    ];
    return $form;
  }

  public function blockSubmit($form, FormStateInterface $form_state): void {
    $this->configuration['only_featured'] = (bool) $form_state->getValue('only_featured');
    $this->configuration['limit'] = (int) $form_state->getValue('limit');
    $this->configuration['wrap'] = $form_state->getValue('wrap');
  }

  public function build(): array {
    $query = \Drupal::entityTypeManager()->getStorage('node')->getQuery()
      ->condition('type', 'project')
      ->condition('status', NodeInterface::PUBLISHED)
      ->sort('field_sort_order', 'ASC')
      ->sort('nid', 'ASC')
      ->accessCheck(TRUE);

    if (!empty($this->configuration['only_featured'])) {
      $query->condition('field_featured_home', 1);
    }
    $limit = (int) ($this->configuration['limit'] ?? 0);
    if ($limit > 0) {
      $query->range(0, $limit);
    }

    $nids = $query->execute();
    $cards = [];
    foreach (Node::loadMultiple($nids ?: []) as $i => $node) {
      $cards[$i] = [
        '#type' => 'component',
        '#component' => 'byte:card-proyecto',
        '#props' => $this->mapNodeToProps($node),
      ];
    }
    if (!$cards) {
      return [
        '#markup' => '<section class="section wrap"><p class="section__lede" style="margin-top: 60px;">Aún no hay proyectos publicados.</p></section>',
      ];
    }

    $wrap = $this->configuration['wrap'] ?? 'section';
    return match ($wrap) {
      'none' => ['cards' => $cards],
      'grid' => ['#prefix' => '<div class="projects-grid">', '#suffix' => '</div>', 'cards' => $cards],
      default => ['#prefix' => '<section class="section wrap"><div class="projects-grid">', '#suffix' => '</div></section>', 'cards' => $cards],
    };
  }

  private function mapNodeToProps(NodeInterface $node): array {
    $category = '';
    if ($node->hasField('field_primary_technology') && !$node->get('field_primary_technology')->isEmpty()) {
      $term = $node->get('field_primary_technology')->entity;
      if ($term) $category = $term->label();
    }

    $year = '';
    if ($node->hasField('field_project_year') && !$node->get('field_project_year')->isEmpty()) {
      $year = (string) $node->get('field_project_year')->value;
    }

    $description = '';
    if ($node->hasField('field_summary') && !$node->get('field_summary')->isEmpty()) {
      $description = $node->get('field_summary')->value;
    }

    $hue_map = ['green' => 160, 'orange' => 18, 'blue' => 220, 'purple' => 280, 'red' => 0, 'amber' => 40];
    $hue = 160;
    if ($node->hasField('field_cover_hue') && !$node->get('field_cover_hue')->isEmpty()) {
      $hue = $hue_map[$node->get('field_cover_hue')->value] ?? 160;
    }

    // Resolve cover_media → file URL. The card-proyecto twig uses cover_url
    // when present and falls back to the procedural browser-mock otherwise.
    $cover_url = '';
    $cover_alt = '';
    if ($node->hasField('field_cover_media') && !$node->get('field_cover_media')->isEmpty()) {
      $media = $node->get('field_cover_media')->entity;
      if ($media && $media->hasField('field_media_image') && !$media->get('field_media_image')->isEmpty()) {
        $file = $media->get('field_media_image')->entity;
        if ($file) {
          $cover_url = $file->createFileUrl(FALSE);
          // Media library widget stores alt on the field item itself.
          $cover_alt = (string) ($media->get('field_media_image')->alt ?? $media->label());
        }
      }
    }

    $metric_props = [];
    if ($node->hasField('field_results_metrics') && !$node->get('field_results_metrics')->isEmpty()) {
      $i = 1;
      foreach ($node->get('field_results_metrics')->referencedEntities() as $para) {
        if ($i > 3) break;
        $metric_props["m{$i}_key"]   = $para->get('field_metric_key')->value ?? '';
        $metric_props["m{$i}_value"] = $para->get('field_metric_value')->value ?? '';
        $i++;
      }
    }

    return [
      'name'        => $node->label(),
      'href'        => $node->toUrl()->toString(),
      'category'    => $category,
      'year'        => $year,
      'description' => $description,
      'hue'         => $hue,
      'cover_url'   => $cover_url,
      'cover_alt'   => $cover_alt,
    ] + $metric_props;
  }

  public function getCacheTags(): array {
    return Cache::mergeTags(parent::getCacheTags(), ['node_list:project']);
  }

  public function getCacheContexts(): array {
    return Cache::mergeContexts(parent::getCacheContexts(), ['languages:language_interface']);
  }

}
