<?php

declare(strict_types=1);

namespace Drupal\Tests\jalvarez_site\Unit\Seo;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\jalvarez_site\Seo\SeoRenderer;
use Drupal\jalvarez_site\Seo\SeoResolver;
use Drupal\node\NodeInterface;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\jalvarez_site\Seo\SeoRenderer
 * @group jalvarez_site
 */
final class SeoRendererTest extends UnitTestCase {

  /**
   * @covers ::attachAll
   */
  public function testAttachAllIsNoopWhenResolverReturnsNull(): void {
    $resolver = $this->createMock(SeoResolver::class);
    $resolver->method('resolveCurrent')->willReturn(NULL);

    $attachments = [];
    (new SeoRenderer($resolver))->attachAll($attachments, 'user.login');

    $this->assertSame([], $attachments);
  }

  /**
   * @covers ::attachAll
   */
  public function testAttachAllWritesMetaTagsAndJsonLd(): void {
    $resolver = $this->makeResolverWith($this->canvasPageInfo(image: 'https://example.test/cover.png'));

    $attachments = [];
    (new SeoRenderer($resolver))->attachAll($attachments, 'entity.canvas_page.canonical');

    $head = $attachments['#attached']['html_head'];
    // Last entry must be JSON-LD; everything before is meta.
    $jsonld = end($head);
    $this->assertSame('jalvarez_seo_jsonld', $jsonld[1]);
    $this->assertSame('script', $jsonld[0]['#tag']);
    $this->assertSame('application/ld+json', $jsonld[0]['#attributes']['type']);

    $meta_tags = array_filter(
      $head,
      fn(array $entry) => $entry[0]['#tag'] === 'meta',
    );
    $this->assertGreaterThanOrEqual(13, count($meta_tags));
  }

  /**
   * @covers ::attachAll
   */
  public function testAttachAllUsesSummaryLargeImageWhenImagePresent(): void {
    $resolver = $this->makeResolverWith($this->canvasPageInfo(image: 'https://x/i.png'));

    $attachments = [];
    (new SeoRenderer($resolver))->attachAll($attachments, 'entity.canvas_page.canonical');

    $card = $this->findMetaContent($attachments, 'twitter:card');
    $this->assertSame('summary_large_image', $card);
  }

  /**
   * @covers ::attachAll
   */
  public function testAttachAllUsesSummaryWhenNoImage(): void {
    $resolver = $this->makeResolverWith($this->canvasPageInfo(image: NULL));

    $attachments = [];
    (new SeoRenderer($resolver))->attachAll($attachments, 'entity.canvas_page.canonical');

    $card = $this->findMetaContent($attachments, 'twitter:card');
    $this->assertSame('summary', $card);
  }

  /**
   * @covers ::buildJsonLd
   */
  public function testBuildJsonLdAlwaysIncludesPersonAndWebSite(): void {
    $resolver = $this->createMock(SeoResolver::class);
    $renderer = new SeoRenderer($resolver);
    $info = $this->canvasPageInfo();

    $graph = $renderer->buildJsonLd($info, 'https://example.test')['@graph'];

    $this->assertSame('Person', $graph[0]['@type']);
    $this->assertSame('WebSite', $graph[1]['@type']);
    $this->assertSame('WebPage', $graph[2]['@type']);
  }

  /**
   * @covers ::buildJsonLd
   */
  public function testBuildJsonLdEmitsBlogPostingForNotes(): void {
    $node = $this->createMock(NodeInterface::class);
    $node->method('getCreatedTime')->willReturn(1700000000);
    $node->method('getChangedTime')->willReturn(1710000000);
    $node->method('hasField')->willReturn(FALSE);

    $info = [
      'title' => 'A note',
      'description' => 'Body.',
      'image' => NULL,
      'url' => 'https://example.test/es/notas/foo',
      'type' => 'note',
      'langcode' => 'es',
      'entity' => $node,
    ];

    $graph = (new SeoRenderer($this->createMock(SeoResolver::class)))
      ->buildJsonLd($info, 'https://example.test')['@graph'];

    $this->assertSame('BlogPosting', $graph[2]['@type']);
    $this->assertSame('https://example.test/es/notas/foo#article', $graph[2]['@id']);
  }

  /**
   * @covers ::buildJsonLd
   */
  public function testBuildJsonLdEmitsCreativeWorkForProjects(): void {
    $node = $this->createMock(NodeInterface::class);
    $node->method('getCreatedTime')->willReturn(1700000000);
    $node->method('getChangedTime')->willReturn(1710000000);
    $node->method('hasField')->willReturn(FALSE);

    $info = [
      'title' => 'A project',
      'description' => 'Did things.',
      'image' => NULL,
      'url' => 'https://example.test/es/proyectos/x',
      'type' => 'project',
      'langcode' => 'es',
      'entity' => $node,
    ];

    $graph = (new SeoRenderer($this->createMock(SeoResolver::class)))
      ->buildJsonLd($info, 'https://example.test')['@graph'];

    $this->assertSame('CreativeWork', $graph[2]['@type']);
    $this->assertSame('https://example.test/es/proyectos/x#work', $graph[2]['@id']);
  }

  /**
   * Builds a canvas-page SEO info payload with a default empty hreflang map.
   */
  private function canvasPageInfo(?string $image = NULL): array {
    $entity = $this->createMock(ContentEntityInterface::class);
    // attachHreflang() iterates getTranslationLanguages(); empty is fine for
    // tests that don't care about hreflang output.
    $entity->method('getTranslationLanguages')->willReturn([]);
    return [
      'title' => 'Inicio | jalvarez.tech',
      'description' => 'Portfolio personal.',
      'image' => $image,
      'url' => 'https://example.test/es/inicio',
      'type' => 'canvas_page',
      'langcode' => 'es',
      'entity' => $entity,
    ];
  }

  private function makeResolverWith(array $info): SeoResolver {
    $resolver = $this->createMock(SeoResolver::class);
    $resolver->method('resolveCurrent')->willReturn($info);
    $resolver->method('getBaseUrl')->willReturn('https://example.test');
    return $resolver;
  }

  /**
   * Returns the `content` attribute of the first matching meta tag.
   *
   * Scans both `name=$key` and `property=$key` and returns the first match.
   */
  private function findMetaContent(array $attachments, string $key): ?string {
    foreach ($attachments['#attached']['html_head'] ?? [] as $entry) {
      $attrs = $entry[0]['#attributes'] ?? [];
      if (($attrs['name'] ?? NULL) === $key || ($attrs['property'] ?? NULL) === $key) {
        return $attrs['content'] ?? NULL;
      }
    }
    return NULL;
  }

}
