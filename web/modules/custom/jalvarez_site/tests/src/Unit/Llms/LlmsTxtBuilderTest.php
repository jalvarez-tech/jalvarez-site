<?php

declare(strict_types=1);

namespace Drupal\Tests\jalvarez_site\Unit\Llms;

use Drupal\Core\Cache\CacheableResponse;
use Drupal\Core\Cache\Context\CacheContextsManager;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\jalvarez_site\Llms\LlmsTxtBuilder;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * @coversDefaultClass \Drupal\jalvarez_site\Llms\LlmsTxtBuilder
 * @group jalvarez_site
 */
final class LlmsTxtBuilderTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // CacheableMetadata::addCacheContexts() validates tokens via
    // cache_contexts_manager. Give it a permissive stub.
    $contexts_manager = $this->createMock(CacheContextsManager::class);
    $contexts_manager->method('assertValidTokens')->willReturn(TRUE);
    $container = new ContainerBuilder();
    $container->set('cache_contexts_manager', $contexts_manager);
    \Drupal::setContainer($container);
  }

  /**
   * @covers ::buildResponse
   */
  public function testBuildResponseReturnsTextPlainWith200(): void {
    $builder = new LlmsTxtBuilder($this->stubEntityTypeManagerWithEmptyNodes());
    $response = $builder->buildResponse(Request::create('https://example.com/llms.txt'), FALSE);

    $this->assertInstanceOf(CacheableResponse::class, $response);
    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame('text/plain; charset=utf-8', $response->headers->get('Content-Type'));
    $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
  }

  /**
   * Response must advertise list cache tags so writes invalidate llms.txt.
   *
   * Covers canvas_page + project + note list tags.
   *
   * @covers ::buildResponse
   */
  public function testBuildResponseAdvertisesEntityListCacheTags(): void {
    $builder = new LlmsTxtBuilder($this->stubEntityTypeManagerWithEmptyNodes());
    $response = $builder->buildResponse(Request::create('https://example.com/llms.txt'), FALSE);

    $tags = $response->getCacheableMetadata()->getCacheTags();
    $this->assertContains('canvas_page_list', $tags);
    $this->assertContains('node_list:project', $tags);
    $this->assertContains('node_list:note', $tags);
  }

  /**
   * @covers ::buildResponse
   */
  public function testBuildResponseBodyMentionsBrandHeader(): void {
    $builder = new LlmsTxtBuilder($this->stubEntityTypeManagerWithEmptyNodes());
    $response = $builder->buildResponse(Request::create('https://example.com/llms.txt'), FALSE);

    $body = (string) $response->getContent();
    $this->assertStringContainsString('# jalvarez.tech', $body);
    $this->assertStringContainsString('contacto@jalvarez.tech', $body);
    $this->assertStringContainsString('https://example.com/sitemap.xml', $body);
    $this->assertStringContainsString('https://example.com/llms-full.txt', $body);
  }

  /**
   * Stub that returns no nodes / no canvas pages.
   *
   * Lets the builder render the static brand header + Optional section
   * without touching real Drupal entity storage.
   */
  private function stubEntityTypeManagerWithEmptyNodes(): EntityTypeManagerInterface {
    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('sort')->willReturnSelf();
    $query->method('execute')->willReturn([]);

    $node_storage = $this->createMock(EntityStorageInterface::class);
    $node_storage->method('getQuery')->willReturn($query);
    $node_storage->method('loadMultiple')->willReturn([]);

    $canvas_storage = $this->createMock(EntityStorageInterface::class);
    $canvas_storage->method('loadMultiple')->willReturn([]);

    $etm = $this->createMock(EntityTypeManagerInterface::class);
    $etm->method('getStorage')->willReturnMap([
      ['node', $node_storage],
      ['canvas_page', $canvas_storage],
    ]);
    return $etm;
  }

}
