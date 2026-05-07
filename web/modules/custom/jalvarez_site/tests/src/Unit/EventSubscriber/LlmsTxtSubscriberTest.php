<?php

declare(strict_types=1);

namespace Drupal\Tests\jalvarez_site\Unit\EventSubscriber;

use Drupal\Core\Cache\CacheableResponse;
use Drupal\jalvarez_site\EventSubscriber\LlmsTxtSubscriber;
use Drupal\jalvarez_site\Llms\LlmsTxtBuilder;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @coversDefaultClass \Drupal\jalvarez_site\EventSubscriber\LlmsTxtSubscriber
 * @group jalvarez_site
 */
final class LlmsTxtSubscriberTest extends UnitTestCase {

  /**
   * Priority must beat the language path-prefix processor (~250).
   *
   * @covers ::getSubscribedEvents
   */
  public function testSubscribedEventsPriorityIsAbove250(): void {
    $events = LlmsTxtSubscriber::getSubscribedEvents();
    $this->assertArrayHasKey(KernelEvents::REQUEST, $events);
    $this->assertSame('onRequest', $events[KernelEvents::REQUEST][0][0]);
    $this->assertGreaterThan(250, $events[KernelEvents::REQUEST][0][1]);
  }

  /**
   * @covers ::onRequest
   */
  public function testOnRequestServesLlmsTxt(): void {
    $expected = new CacheableResponse('# llms', 200, ['Content-Type' => 'text/plain']);
    $builder = $this->createMock(LlmsTxtBuilder::class);
    $builder->expects($this->once())
      ->method('buildResponse')
      ->with($this->isInstanceOf(Request::class), FALSE)
      ->willReturn($expected);

    $event = $this->makeMainRequest('/llms.txt');
    (new LlmsTxtSubscriber($builder))->onRequest($event);

    $this->assertSame($expected, $event->getResponse());
  }

  /**
   * @covers ::onRequest
   */
  public function testOnRequestServesLlmsFullTxtWithFullFlag(): void {
    $expected = new CacheableResponse('# llms full', 200);
    $builder = $this->createMock(LlmsTxtBuilder::class);
    $builder->expects($this->once())
      ->method('buildResponse')
      ->with($this->isInstanceOf(Request::class), TRUE)
      ->willReturn($expected);

    $event = $this->makeMainRequest('/llms-full.txt');
    (new LlmsTxtSubscriber($builder))->onRequest($event);

    $this->assertSame($expected, $event->getResponse());
  }

  /**
   * @covers ::onRequest
   */
  public function testOnRequestIgnoresOtherPaths(): void {
    $builder = $this->createMock(LlmsTxtBuilder::class);
    $builder->expects($this->never())->method('buildResponse');

    $event = $this->makeMainRequest('/some/other/path');
    (new LlmsTxtSubscriber($builder))->onRequest($event);

    $this->assertNull($event->getResponse());
  }

  /**
   * @covers ::onRequest
   */
  public function testOnRequestIgnoresSubRequests(): void {
    $builder = $this->createMock(LlmsTxtBuilder::class);
    $builder->expects($this->never())->method('buildResponse');

    $kernel = $this->createMock(HttpKernelInterface::class);
    $event = new RequestEvent($kernel, Request::create('/llms.txt'), HttpKernelInterface::SUB_REQUEST);
    (new LlmsTxtSubscriber($builder))->onRequest($event);

    $this->assertNull($event->getResponse());
  }

  private function makeMainRequest(string $path): RequestEvent {
    $kernel = $this->createMock(HttpKernelInterface::class);
    return new RequestEvent($kernel, Request::create($path), HttpKernelInterface::MAIN_REQUEST);
  }

}
