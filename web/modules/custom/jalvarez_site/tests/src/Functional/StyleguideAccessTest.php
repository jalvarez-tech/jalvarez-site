<?php

declare(strict_types=1);

namespace Drupal\Tests\jalvarez_site\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests access control for the /styleguide route.
 *
 * @group jalvarez_site
 */
final class StyleguideAccessTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'node', 'jalvarez_site'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Anonymous users get 403.
   *
   * Regression for the audit critical: /styleguide was previously gated by
   * 'access content' (anonymous on default installs).
   */
  public function testAnonymousIsDenied(): void {
    $this->drupalGet('/styleguide');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * A user with 'administer site configuration' is granted access.
   *
   * The styleguide controller renders byte:* SDC components which need the
   * Byte theme + compiled CSS to actually render — out of scope for this
   * test profile. We only assert the access check itself: the response is
   * NOT 403, which is the bit the audit critical fix is about.
   */
  public function testAdministratorIsGrantedAccess(): void {
    $admin = $this->drupalCreateUser(['administer site configuration']);
    $this->drupalLogin($admin);
    $this->drupalGet('/styleguide');
    $this->assertNotSame(403, $this->getSession()->getStatusCode());
  }

  /**
   * A user with only 'access content' (the old, weak gate) is denied.
   */
  public function testAccessContentAloneIsNotEnough(): void {
    $reader = $this->drupalCreateUser(['access content']);
    $this->drupalLogin($reader);
    $this->drupalGet('/styleguide');
    $this->assertSession()->statusCodeEquals(403);
  }

}
