<?php

declare(strict_types=1);

namespace Drupal\Tests\jalvarez_site\Unit\Hook;

use Drupal\jalvarez_site\Hook\PathautoHook;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\jalvarez_site\Hook\PathautoHook
 * @group jalvarez_site
 */
final class PathautoHookTest extends UnitTestCase {

  /**
   * @covers ::alter
   */
  public function testEnglishProyectosBecomesProjects(): void {
    $alias = '/proyectos/maluma-online';
    $context = ['language' => 'en'];

    (new PathautoHook())->alter($alias, $context);

    $this->assertSame('/projects/maluma-online', $alias);
  }

  /**
   * @covers ::alter
   */
  public function testEnglishNotasBecomesNotes(): void {
    $alias = '/notas/cache-invalidation';
    $context = ['language' => 'en'];

    (new PathautoHook())->alter($alias, $context);

    $this->assertSame('/notes/cache-invalidation', $alias);
  }

  /**
   * @covers ::alter
   */
  public function testWorksWithoutLeadingSlash(): void {
    $alias = 'proyectos/x';
    $context = ['language' => 'en'];

    (new PathautoHook())->alter($alias, $context);

    $this->assertSame('projects/x', $alias);
  }

  /**
   * @covers ::alter
   */
  public function testSpanishAliasIsLeftUntouched(): void {
    $alias = '/proyectos/x';
    $context = ['language' => 'es'];

    (new PathautoHook())->alter($alias, $context);

    $this->assertSame('/proyectos/x', $alias);
  }

  /**
   * @covers ::alter
   */
  public function testNonMatchingPrefixIsLeftUntouched(): void {
    $alias = '/blog/something';
    $context = ['language' => 'en'];

    (new PathautoHook())->alter($alias, $context);

    $this->assertSame('/blog/something', $alias);
  }

  /**
   * @covers ::alter
   */
  public function testMissingLanguageContextDoesNotRewrite(): void {
    $alias = '/proyectos/x';
    $context = [];

    (new PathautoHook())->alter($alias, $context);

    $this->assertSame('/proyectos/x', $alias);
  }

}
