<?php

declare(strict_types=1);

namespace Drupal\jalvarez_site\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;

/**
 * Renders the full Notes list using `byte:row-nota` SDC instances.
 *
 * Replaces the legacy NotesController's list section. Loads all published
 * note nodes ordered by publish date DESC, computes read time at 200 wpm,
 * and maps each to the SDC's prop schema.
 */
#[Block(
  id: 'jalvarez_notes_grid',
  admin_label: new TranslatableMarkup('Notas — list (byte rows)'),
  category: new TranslatableMarkup('Jalvarez'),
)]
final class NotesGridBlock extends BlockBase {

  public function build(): array {
    $nids = \Drupal::entityTypeManager()->getStorage('node')->getQuery()
      ->condition('type', 'note')
      ->condition('status', NodeInterface::PUBLISHED)
      ->sort('field_publish_date', 'DESC')
      ->sort('nid', 'DESC')
      ->accessCheck(TRUE)
      ->execute();

    $rows = [];
    foreach (Node::loadMultiple($nids ?: []) as $i => $node) {
      $rows[$i] = [
        '#type' => 'component',
        '#component' => 'byte:row-nota',
        '#props' => $this->mapNodeToProps($node),
      ];
    }
    if (!$rows) {
      return [
        '#markup' => '<section class="section wrap"><p class="section__lede" style="margin-top: 60px;">Aún no hay notas publicadas.</p></section>',
      ];
    }
    return [
      '#prefix' => '<section class="section wrap"><div class="notes-list">',
      '#suffix' => '</div></section>',
      'rows' => $rows,
    ];
  }

  private function mapNodeToProps(NodeInterface $node): array {
    $excerpt = '';
    if ($node->hasField('field_excerpt') && !$node->get('field_excerpt')->isEmpty()) {
      $excerpt = $node->get('field_excerpt')->value;
    }

    $category = '';
    if ($node->hasField('field_note_topic') && !$node->get('field_note_topic')->isEmpty()) {
      $term = $node->get('field_note_topic')->entity;
      if ($term) $category = $term->label();
    }

    $date = '';
    if ($node->hasField('field_publish_date') && !$node->get('field_publish_date')->isEmpty()) {
      $raw = $node->get('field_publish_date')->value;
      if ($raw) {
        $current_lang = \Drupal::languageManager()
          ->getCurrentLanguage()
          ->getId();
        $months = $current_lang === 'en'
          ? ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
          : ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
        $parts = explode('-', $raw);
        if (count($parts) >= 3) {
          $month = $months[(int) $parts[1] - 1] ?? $parts[1];
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

    $read_time = '';
    if ($node->hasField('body') && !$node->get('body')->isEmpty()) {
      $words = str_word_count(strip_tags($node->get('body')->value ?? ''));
      $minutes = max(1, (int) ceil($words / 200));
      $read_time = "{$minutes} min";
    }

    return [
      'title'     => $node->label(),
      'excerpt'   => $excerpt,
      'href'      => $node->toUrl()->toString(),
      'date'      => $date,
      'category'  => $category,
      'read_time' => $read_time,
      'glyph'     => $glyph,
      'hue'       => $hue,
    ];
  }

  public function getCacheTags(): array {
    return Cache::mergeTags(parent::getCacheTags(), ['node_list:note', 'taxonomy_term_list']);
  }

  public function getCacheContexts(): array {
    return Cache::mergeContexts(parent::getCacheContexts(), ['languages:language_interface']);
  }

}
