<?php

declare(strict_types=1);

namespace Drupal\Tests\jalvarez_site\Unit\Hook;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\jalvarez_site\Hook\PageAttachmentsHook;
use Drupal\jalvarez_site\Seo\SeoRenderer;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\jalvarez_site\Hook\PageAttachmentsHook
 * @group jalvarez_site
 */
final class PageAttachmentsHookTest extends UnitTestCase {

  /**
   * @covers ::alter
   */
  public function testAttachesByteGlobalLibraryOnCanvasPageRoute(): void {
    $route_match = $this->createMock(RouteMatchInterface::class);
    $route_match->method('getRouteName')->willReturn('entity.canvas_page.canonical');

    $renderer = $this->createMock(SeoRenderer::class);
    $renderer->expects($this->once())
      ->method('attachAll')
      ->with(
        $this->isType('array'),
        'entity.canvas_page.canonical',
      );

    $attachments = [];
    (new PageAttachmentsHook($route_match, $renderer))->alter($attachments);

    $this->assertContains('byte/global', $attachments['#attached']['library'] ?? []);
  }

  /**
   * @covers ::alter
   */
  public function testDoesNotAttachLibraryOnNonCanvasRoutes(): void {
    $route_match = $this->createMock(RouteMatchInterface::class);
    $route_match->method('getRouteName')->willReturn('entity.node.canonical');

    $renderer = $this->createMock(SeoRenderer::class);
    $renderer->expects($this->once())->method('attachAll');

    $attachments = [];
    (new PageAttachmentsHook($route_match, $renderer))->alter($attachments);

    $this->assertArrayNotHasKey('library', $attachments['#attached'] ?? []);
  }

  /**
   * @covers ::alter
   */
  public function testAlwaysCallsSeoRendererRegardlessOfRoute(): void {
    $route_match = $this->createMock(RouteMatchInterface::class);
    $route_match->method('getRouteName')->willReturn('user.login');

    $renderer = $this->createMock(SeoRenderer::class);
    $renderer->expects($this->once())
      ->method('attachAll')
      ->with($this->isType('array'), 'user.login');

    $attachments = [];
    (new PageAttachmentsHook($route_match, $renderer))->alter($attachments);
  }

  /**
   * @covers ::alter
   */
  public function testSurvivesNullRouteName(): void {
    $route_match = $this->createMock(RouteMatchInterface::class);
    $route_match->method('getRouteName')->willReturn(NULL);

    $renderer = $this->createMock(SeoRenderer::class);
    $renderer->expects($this->once())
      ->method('attachAll')
      ->with($this->isType('array'), '');

    $attachments = [];
    (new PageAttachmentsHook($route_match, $renderer))->alter($attachments);

    $this->assertArrayNotHasKey('library', $attachments['#attached'] ?? []);
  }

}
