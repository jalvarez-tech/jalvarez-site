<?php

declare(strict_types=1);

namespace Drupal\jalvarez_site\EventSubscriber;

use Drupal\jalvarez_site\Controller\LlmsTxtController;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Serves /llms.txt and /llms-full.txt at the docroot, bypassing the
 * language path-prefix redirect (which would otherwise rewrite the path
 * to /es/llms.txt and break the llms.txt convention).
 *
 * Runs at priority 300 so it fires before
 * \Drupal\language\HttpKernel\PathProcessorLanguage::processInbound
 * (which would split a non-prefixed path into a redirect).
 */
final class LlmsTxtSubscriber implements EventSubscriberInterface {

  public function __construct(
    private readonly ContainerInterface $container,
  ) {}

  public static function getSubscribedEvents(): array {
    // Priority must be > 300 (Symfony Router) to intercept before routing,
    // and we need to win against language-prefix redirect (priority ~250).
    return [
      KernelEvents::REQUEST => [['onRequest', 350]],
    ];
  }

  public function onRequest(RequestEvent $event): void {
    if (!$event->isMainRequest()) {
      return;
    }
    $path = $event->getRequest()->getPathInfo();
    if ($path !== '/llms.txt' && $path !== '/llms-full.txt') {
      return;
    }

    $controller = LlmsTxtController::create($this->container);
    $response = $path === '/llms-full.txt'
      ? $controller->full($event->getRequest())
      : $controller->index($event->getRequest());

    $event->setResponse($response);
  }

}
