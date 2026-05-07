<?php

declare(strict_types=1);

namespace Drupal\Tests\jalvarez_site\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests the /llms.txt and /llms-full.txt endpoints.
 *
 * @group jalvarez_site
 */
final class LlmsTxtRoutesTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'node', 'jalvarez_site'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Confirms /llms.txt returns 200 with the right content-type and brand header.
   */
  public function testLlmsTxtIsServedAtDocrootWithCorrectHeaders(): void {
    $this->drupalGet('/llms.txt');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseHeaderEquals('Content-Type', 'text/plain; charset=utf-8');
    $this->assertSession()->responseHeaderEquals('X-Content-Type-Options', 'nosniff');
    $this->assertSession()->pageTextContains('# jalvarez.tech');
    $this->assertSession()->pageTextContains('contacto@jalvarez.tech');
  }

  /**
   * Confirms /llms-full.txt returns 200 and references the full variant.
   */
  public function testLlmsFullTxtIsServedAtDocroot(): void {
    $this->drupalGet('/llms-full.txt');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseHeaderEquals('Content-Type', 'text/plain; charset=utf-8');
    $this->assertSession()->pageTextContains('# jalvarez.tech');
  }

  /**
   * Confirms that the page-cache invalidation contract is in place.
   */
  public function testResponseAdvertisesEntityListCacheTags(): void {
    $this->drupalGet('/llms.txt');
    $this->assertSession()->statusCodeEquals(200);
    $tags = $this->getSession()->getResponseHeader('X-Drupal-Cache-Tags') ?? '';
    $this->assertStringContainsString('node_list:project', $tags);
    $this->assertStringContainsString('node_list:note', $tags);
  }

}
