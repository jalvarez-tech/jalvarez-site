<?php

declare(strict_types=1);

namespace Drupal\jalvarez_site\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\jalvarez_site\Seo\SeoResolver;
use Drupal\node\NodeInterface;

/**
 * Hook implementations for metatags_alter.
 *
 * Backfills title/description/canonical_url for project + note nodes which
 * don't have a metatag field attached — without this the metatag.metatag
 * defaults emit `[node:summary]` (always empty) as the description.
 *
 * canvas_page entities own their tags via their `metatags` JSON field, so
 * they're left untouched here.
 */
class MetatagsHook {

  public function __construct(
    private readonly SeoResolver $resolver,
  ) {}

  #[Hook('metatags_alter')]
  public function alter(array &$metatags, array &$context): void {
    $entity = $context['entity'] ?? NULL;
    if (!$entity instanceof NodeInterface) {
      return;
    }
    if (!in_array($entity->bundle(), ['project', 'note'], TRUE)) {
      return;
    }
    $info = $this->resolver->resolveNode($entity);
    if (!$info) {
      return;
    }
    $metatags['title'] = $info['title'];
    $metatags['description'] = $info['description'];
    $metatags['canonical_url'] = $info['url'];
  }

}
