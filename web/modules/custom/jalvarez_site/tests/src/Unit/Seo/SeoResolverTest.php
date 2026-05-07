<?php

declare(strict_types=1);

namespace Drupal\Tests\jalvarez_site\Unit\Seo;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\jalvarez_site\Seo\SeoResolver;
use Drupal\node\NodeInterface;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @coversDefaultClass \Drupal\jalvarez_site\Seo\SeoResolver
 * @group jalvarez_site
 */
final class SeoResolverTest extends UnitTestCase {

  /**
   * @covers ::getBaseUrl
   */
  public function testGetBaseUrlReturnsRequestSchemeAndHost(): void {
    $stack = new RequestStack();
    $stack->push(Request::create('https://example.test/foo'));
    $resolver = $this->makeResolver(requestStack: $stack);

    $this->assertSame('https://example.test', $resolver->getBaseUrl());
  }

  /**
   * @covers ::getBaseUrl
   */
  public function testGetBaseUrlReturnsEmptyStringWhenNoRequest(): void {
    $resolver = $this->makeResolver(requestStack: new RequestStack());
    $this->assertSame('', $resolver->getBaseUrl());
  }

  /**
   * @covers ::resolveCurrent
   */
  public function testResolveCurrentReturnsNullForNonSeoRoute(): void {
    $resolver = $this->makeResolver();
    $this->assertNull($resolver->resolveCurrent('user.login'));
  }

  /**
   * @covers ::resolveCurrent
   */
  public function testResolveCurrentReturnsNullForNodeBundleNotInList(): void {
    $node = $this->makeNode(bundle: 'page');
    $route_match = $this->createMock(RouteMatchInterface::class);
    $route_match->method('getParameter')->with('node')->willReturn($node);

    $resolver = $this->makeResolver(routeMatch: $route_match);
    $this->assertNull($resolver->resolveCurrent('entity.node.canonical'));
  }

  /**
   * @covers ::resolveNode
   */
  public function testResolveNodeReturnsNullForBundleNotInList(): void {
    $resolver = $this->makeResolver();
    $node = $this->makeNode(bundle: 'page');
    $this->assertNull($resolver->resolveNode($node, 'https://example.test'));
  }

  /**
   * @covers ::resolveNode
   */
  public function testResolveNodeUsesFieldSummaryForProjects(): void {
    $node = $this->makeNode(
      bundle: 'project',
      label: 'Maluma.online',
      fieldValues: ['field_summary' => 'A redesign in two months.'],
    );
    $resolver = $this->makeResolver();
    $info = $resolver->resolveNode($node, 'https://example.test');

    $this->assertNotNull($info);
    $this->assertSame('Maluma.online | jalvarez.tech', $info['title']);
    $this->assertSame('A redesign in two months.', $info['description']);
    $this->assertSame('', $info['url']);
    $this->assertNull($info['image']);
  }

  /**
   * @covers ::resolveNode
   */
  public function testResolveNodeUsesFieldExcerptForNotes(): void {
    $node = $this->makeNode(
      bundle: 'note',
      label: 'On caching',
      fieldValues: ['field_excerpt' => 'Cache invalidation is hard.'],
    );
    $resolver = $this->makeResolver();
    $info = $resolver->resolveNode($node, 'https://example.test');

    $this->assertNotNull($info);
    $this->assertSame('On caching | jalvarez.tech', $info['title']);
    $this->assertSame('Cache invalidation is hard.', $info['description']);
  }

  /**
   * @covers ::resolveNode
   */
  public function testResolveNodeStripsHtmlAndCollapsesWhitespace(): void {
    $node = $this->makeNode(
      bundle: 'note',
      label: 'Whitespace test',
      fieldValues: ['field_excerpt' => "<p>Line one</p>\n\n<p>  Line  two</p>"],
    );
    $resolver = $this->makeResolver();
    $info = $resolver->resolveNode($node, 'https://example.test');

    $this->assertSame('Line one Line two', $info['description']);
  }

  /**
   * @covers ::resolveNode
   */
  public function testResolveNodeTruncatesLongDescriptionWithEllipsis(): void {
    $long = str_repeat('x', 350);
    $node = $this->makeNode(
      bundle: 'note',
      label: 'Long',
      fieldValues: ['field_excerpt' => $long],
    );
    $resolver = $this->makeResolver();
    $info = $resolver->resolveNode($node, 'https://example.test');

    $this->assertSame(298, mb_strlen($info['description']));
    $this->assertStringEndsWith('…', $info['description']);
  }

  /**
   * @covers ::resolveCanvasPage
   */
  public function testResolveCanvasPageUsesMetatagsJsonWhenPresent(): void {
    $page = $this->makeContentEntity(
      label: 'Inicio',
      fieldValues: [
        'metatags' => json_encode(['title' => 'Custom T', 'description' => 'Custom D']),
      ],
    );
    $resolver = $this->makeResolver();
    $info = $resolver->resolveCanvasPage($page, 'https://example.test');

    $this->assertNotNull($info);
    $this->assertSame('Custom T', $info['title']);
    $this->assertSame('Custom D', $info['description']);
  }

  /**
   * @covers ::resolveCanvasPage
   */
  public function testResolveCanvasPageFallsBackToLabelPlusBrandSuffix(): void {
    $page = $this->makeContentEntity(label: 'Contacto', fieldValues: []);
    $resolver = $this->makeResolver();
    $info = $resolver->resolveCanvasPage($page, 'https://example.test');

    $this->assertSame('Contacto | jalvarez.tech', $info['title']);
    $this->assertSame('', $info['description']);
  }

  /**
   * @covers ::resolveCanvasPage
   */
  public function testResolveCanvasPageFallsBackToFieldDescriptionWhenMetatagsEmpty(): void {
    $page = $this->makeContentEntity(
      label: 'Página',
      fieldValues: ['description' => 'Field-level fallback.'],
    );
    $resolver = $this->makeResolver();
    $info = $resolver->resolveCanvasPage($page, 'https://example.test');

    $this->assertSame('Field-level fallback.', $info['description']);
  }

  /**
   * Builds a SeoResolver with sensible defaults for each dependency.
   */
  private function makeResolver(
    ?RouteMatchInterface $routeMatch = NULL,
    ?LanguageManagerInterface $languageManager = NULL,
    ?RequestStack $requestStack = NULL,
    ?FileUrlGeneratorInterface $fileUrlGenerator = NULL,
  ): SeoResolver {
    $language = $this->createMock(LanguageInterface::class);
    $language->method('getId')->willReturn('es');
    $language_manager = $languageManager ?? $this->createMock(LanguageManagerInterface::class);
    $language_manager->method('getCurrentLanguage')->willReturn($language);

    return new SeoResolver(
      $routeMatch ?? $this->createMock(RouteMatchInterface::class),
      $language_manager,
      $requestStack ?? new RequestStack(),
      $fileUrlGenerator ?? $this->createMock(FileUrlGeneratorInterface::class),
    );
  }

  private function makeNode(string $bundle, string $label = 'X', array $fieldValues = []): NodeInterface {
    $node = $this->createMock(NodeInterface::class);
    $node->method('bundle')->willReturn($bundle);
    $node->method('label')->willReturn($label);
    $this->stubFieldAccess($node, $fieldValues);

    // resolveNode wraps toUrl() in try/catch; let it throw to cover that path.
    $node->method('toUrl')->willThrowException(new \LogicException('no route'));
    return $node;
  }

  private function makeContentEntity(string $label = 'X', array $fieldValues = []): ContentEntityInterface {
    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->method('label')->willReturn($label);
    $this->stubFieldAccess($entity, $fieldValues);
    $entity->method('toUrl')->willThrowException(new \LogicException('no route'));
    return $entity;
  }

  /**
   * Stubs hasField()/get()/isEmpty() so the resolver sees the given values.
   *
   * Uses an anonymous concrete stub for the field list because PHPUnit mocks
   * can't impersonate the `->value` magic property exposed by FieldItemList.
   */
  private function stubFieldAccess(object $entity, array $fieldValues): void {
    $entity->method('hasField')
      ->willReturnCallback(fn(string $name) => array_key_exists($name, $fieldValues));
    $entity->method('get')
      ->willReturnCallback(fn(string $name) => new FakeFieldList($fieldValues[$name] ?? NULL));
  }

}

/**
 * Minimal FieldItemList stand-in: exposes ->value and isEmpty(), nothing else.
 */
final class FakeFieldList {

  public function __construct(public readonly mixed $value) {}

  public function isEmpty(): bool {
    return $this->value === NULL || $this->value === '';
  }

}
