<?php

declare(strict_types=1);

namespace Drupal\jalvarez_site\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\jalvarez_site\Seo\SeoRenderer;

/**
 * Hook implementations for page_attachments_alter.
 *
 * Two responsibilities:
 *  1. Force-attach byte/global on canvas_page routes (workaround for the
 *     library aggregator dropping it when a Block-source SDC component
 *     contributes its own libraries on /contacto).
 *  2. Delegate SEO head injection to SeoRenderer.
 */
class PageAttachmentsHook {

  public function __construct(
    private readonly RouteMatchInterface $routeMatch,
    private readonly SeoRenderer $seoRenderer,
  ) {}

  #[Hook('page_attachments_alter')]
  public function alter(array &$attachments): void {
    $route_name = $this->routeMatch->getRouteName() ?? '';
    if ($route_name === 'entity.canvas_page.canonical') {
      $attachments['#attached']['library'][] = 'byte/global';
    }
    $this->seoRenderer->attachAll($attachments, $route_name);
  }

}
