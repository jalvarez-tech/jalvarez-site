<?php

declare(strict_types=1);

namespace Drupal\jalvarez_site\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Strips the URL language prefix from `/canvas/editor/*` requests.
 *
 * Canvas 1.3.x's SPA router ignores any URL path that doesn't match its
 * expected pattern `/canvas/editor/{entity_type}/{entity}` exactly. When
 * Drupal's URL language negotiator prepends `/es` or `/en`, the SPA
 * loads but cannot identify the entity (configuration.entity = "none",
 * isNew = true) and renders an empty editor.
 *
 * @see https://www.drupal.org/project/canvas/issues/3489775
 */
final class CanvasLangPrefixStripper implements EventSubscriberInterface {

  public function onRequest(RequestEvent $event): void {
    if (!$event->isMainRequest()) {
      return;
    }
    $request = $event->getRequest();
    $path = $request->getPathInfo();
    // Match /es/canvas/editor/... or /en/canvas/editor/...
    if (preg_match('#^/(es|en)(/canvas/editor/.+)$#', $path, $m)) {
      $stripped = $m[2];
      $qs = $request->getQueryString();
      if ($qs !== NULL && $qs !== '') {
        $stripped .= '?' . $qs;
      }
      $event->setResponse(new RedirectResponse($stripped, 302));
    }
  }

  public static function getSubscribedEvents(): array {
    // Run before Drupal's path-based language negotiation routing kicks in
    // (priority around 33 is the path processor; we run earlier).
    return [
      KernelEvents::REQUEST => ['onRequest', 100],
    ];
  }

}
