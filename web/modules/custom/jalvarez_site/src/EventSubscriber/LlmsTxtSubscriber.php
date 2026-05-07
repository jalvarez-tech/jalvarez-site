<?php

declare(strict_types=1);

namespace Drupal\jalvarez_site\EventSubscriber;

use Drupal\jalvarez_site\Llms\LlmsTxtBuilder;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Serves /llms.txt and /llms-full.txt at the docroot, bypassing the
 * language path-prefix redirect (which would otherwise rewrite the path
 * to /es/llms.txt and break the llms.txt convention).
 *
 * Runs at priority 350 so it fires before
 * \Drupal\language\HttpKernel\PathProcessorLanguage::processInbound
 * (which would split a non-prefixed path into a redirect).
 */
final class LlmsTxtSubscriber implements EventSubscriberInterface {

  public function __construct(
    private readonly LlmsTxtBuilder $builder,
  ) {}

  public static function getSubscribedEvents(): array {
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

    $event->setResponse(
      $this->builder->buildResponse($event->getRequest(), $path === '/llms-full.txt'),
    );
  }

}
