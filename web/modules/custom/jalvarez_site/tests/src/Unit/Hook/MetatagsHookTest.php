<?php

declare(strict_types=1);

namespace Drupal\Tests\jalvarez_site\Unit\Hook;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\jalvarez_site\Hook\MetatagsHook;
use Drupal\jalvarez_site\Seo\SeoResolver;
use Drupal\node\NodeInterface;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\jalvarez_site\Hook\MetatagsHook
 * @group jalvarez_site
 */
final class MetatagsHookTest extends UnitTestCase {

  /**
   * @covers ::alter
   */
  public function testIgnoresNonNodeEntity(): void {
    $resolver = $this->createMock(SeoResolver::class);
    $resolver->expects($this->never())->method('resolveNode');

    $metatags = ['title' => 'untouched'];
    $context = ['entity' => $this->createMock(ContentEntityInterface::class)];
    (new MetatagsHook($resolver))->alter($metatags, $context);

    $this->assertSame(['title' => 'untouched'], $metatags);
  }

  /**
   * @covers ::alter
   */
  public function testIgnoresNodeBundleNotInList(): void {
    $node = $this->createMock(NodeInterface::class);
    $node->method('bundle')->willReturn('article');

    $resolver = $this->createMock(SeoResolver::class);
    $resolver->expects($this->never())->method('resolveNode');

    $metatags = ['title' => 'untouched'];
    $context = ['entity' => $node];
    (new MetatagsHook($resolver))->alter($metatags, $context);

    $this->assertSame(['title' => 'untouched'], $metatags);
  }

  /**
   * @covers ::alter
   */
  public function testIgnoresWhenResolverReturnsNull(): void {
    $node = $this->createMock(NodeInterface::class);
    $node->method('bundle')->willReturn('project');

    $resolver = $this->createMock(SeoResolver::class);
    $resolver->method('resolveNode')->with($node)->willReturn(NULL);

    $metatags = ['title' => 'untouched'];
    $context = ['entity' => $node];
    (new MetatagsHook($resolver))->alter($metatags, $context);

    $this->assertSame(['title' => 'untouched'], $metatags);
  }

  /**
   * @covers ::alter
   */
  public function testProjectNodeOverridesTitleDescriptionAndCanonical(): void {
    $node = $this->createMock(NodeInterface::class);
    $node->method('bundle')->willReturn('project');

    $resolver = $this->createMock(SeoResolver::class);
    $resolver->method('resolveNode')->with($node)->willReturn([
      'title' => 'Maluma.online | jalvarez.tech',
      'description' => 'A redesign in two months.',
      'image' => NULL,
      'url' => 'https://example.test/es/proyectos/maluma',
    ]);

    $metatags = [
      'title' => '[node:title]',
      'description' => '[node:summary]',
      'canonical_url' => '[node:url]',
      'robots' => 'index,follow',
    ];
    $context = ['entity' => $node];
    (new MetatagsHook($resolver))->alter($metatags, $context);

    $this->assertSame('Maluma.online | jalvarez.tech', $metatags['title']);
    $this->assertSame('A redesign in two months.', $metatags['description']);
    $this->assertSame('https://example.test/es/proyectos/maluma', $metatags['canonical_url']);
    // Other metatags are untouched.
    $this->assertSame('index,follow', $metatags['robots']);
  }

  /**
   * @covers ::alter
   */
  public function testNoteBundleAlsoOverrides(): void {
    $node = $this->createMock(NodeInterface::class);
    $node->method('bundle')->willReturn('note');

    $resolver = $this->createMock(SeoResolver::class);
    $resolver->method('resolveNode')->with($node)->willReturn([
      'title' => 'On caching | jalvarez.tech',
      'description' => 'Cache invalidation is hard.',
      'image' => NULL,
      'url' => 'https://example.test/es/notas/caching',
    ]);

    $metatags = [];
    $context = ['entity' => $node];
    (new MetatagsHook($resolver))->alter($metatags, $context);

    $this->assertSame('On caching | jalvarez.tech', $metatags['title']);
  }

}
