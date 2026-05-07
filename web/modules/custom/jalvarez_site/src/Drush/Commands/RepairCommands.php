<?php

declare(strict_types=1);

namespace Drupal\jalvarez_site\Drush\Commands;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\path_alias\AliasManagerInterface;
use Drush\Attributes\Command;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Maintenance commands wired into the deploy pipeline.
 *
 * Idempotent — designed to run on every `drush deploy` after `cim`. Replaces
 * the legacy scripts/fix-prod-system-site.php pattern (which had to be `scp`'d
 * to /tmp on every deploy and invoked via `drush php:script`).
 */
final class RepairCommands extends DrushCommands {

  /**
   * Canonical site name (must match what we want in production).
   */
  private const string CANONICAL_NAME = 'jalvarez.tech';

  /**
   * Canonical admin/contact email.
   */
  private const string CANONICAL_MAIL = 'stevanswd@gmail.com';

  /**
   * ES path alias of the home canvas_page (seed-defined).
   *
   * Resolved dynamically to /page/{id} so we don't hardcode the entity id —
   * the same alias maps to different ids across environments.
   */
  private const string HOME_ALIAS = '/inicio';

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly AliasManagerInterface $aliasManager,
    private readonly CacheTagsInvalidatorInterface $cacheTagsInvalidator,
  ) {
    parent::__construct();
  }

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory'),
      $container->get('path_alias.manager'),
      $container->get('cache_tags.invalidator'),
    );
  }

  /**
   * Repair `system.site` after `drush cim` reapplies stale local values.
   *
   * Three values drift back from `config/sync/system.site.yml`:
   *  - name      → `jalvarez.tech (local)` (or whatever the dev machine has)
   *  - mail      → `admin@example.com`
   *  - page.front → `/page/<local-id>` which doesn't exist on prod.
   *
   * Plus `update.settings.notification.emails` from update.settings.yml.
   *
   * Algorithm:
   *  - Resolve `/inicio` to its current `/page/{id}` via path_alias.manager.
   *  - Compare each value to the canonical one; only `set()` + save when
   *    they actually differ (idempotent — re-running is a no-op).
   *  - Flush caches when a write happened so the new front page resolves
   *    immediately (otherwise the menu cache + router cache keep serving
   *    the old `/page/<wrong-id>` for up to TTL).
   */
  #[Command(name: 'jalvarez:repair-system-site')]
  public function repairSystemSite(): int {
    $home_path = $this->resolveHomeInternalPath();

    $changes = [];
    $config = $this->configFactory->getEditable('system.site');

    if ($config->get('name') !== self::CANONICAL_NAME) {
      $config->set('name', self::CANONICAL_NAME);
      $changes[] = "name → '" . self::CANONICAL_NAME . "'";
    }
    if ($config->get('mail') !== self::CANONICAL_MAIL) {
      $config->set('mail', self::CANONICAL_MAIL);
      $changes[] = "mail → '" . self::CANONICAL_MAIL . "'";
    }
    if ($home_path !== NULL && $config->get('page.front') !== $home_path) {
      $config->set('page.front', $home_path);
      $changes[] = "page.front → '{$home_path}' (resolved from alias '" . self::HOME_ALIAS . "')";
    }

    if ($changes) {
      $config->save();
      $this->logger()->success('system.site repaired:');
      foreach ($changes as $change) {
        $this->logger()->success('  - ' . $change);
      }
      // Front page change requires router rebuild — invalidate the relevant
      // tags rather than nuking everything. (`drupal_flush_all_caches` would
      // also work but is overkill on a deploy where `drush cr` already ran.)
      $this->cacheTagsInvalidator->invalidateTags(['config:system.site', 'router']);
    }
    else {
      $this->logger()->notice('system.site already canonical, nothing to do.');
    }

    $this->repairUpdateNotificationEmails();

    return DrushCommands::EXIT_SUCCESS;
  }

  /**
   * Resolves the home alias to /page/{id} or returns NULL on miss.
   */
  private function resolveHomeInternalPath(): ?string {
    $resolved = $this->aliasManager->getPathByAlias(self::HOME_ALIAS);
    if ($resolved === self::HOME_ALIAS) {
      $this->logger()->warning(sprintf(
        "Could not resolve alias '%s' to an internal path — leaving page.front untouched.",
        self::HOME_ALIAS,
      ));
      return NULL;
    }
    if (!preg_match('#^/page/\d+$#', $resolved)) {
      $this->logger()->warning(sprintf(
        "Alias '%s' resolved to '%s' which is not a /page/{id} path; refusing to set as front.",
        self::HOME_ALIAS,
        $resolved,
      ));
      return NULL;
    }
    return $resolved;
  }

  /**
   * Repairs the update.settings notification email list.
   *
   * Drops any leaked-from-local placeholder (admin@example.com) and ensures
   * the canonical mail is the single recipient.
   */
  private function repairUpdateNotificationEmails(): void {
    $config = $this->configFactory->getEditable('update.settings');
    $current = $config->get('notification.emails') ?? [];
    $needs_repair = !in_array(self::CANONICAL_MAIL, $current, TRUE)
      || in_array('admin@example.com', $current, TRUE);
    if (!$needs_repair) {
      return;
    }
    $config->set('notification.emails', [self::CANONICAL_MAIL])->save();
    $this->logger()->success(sprintf(
      "update.settings.notification.emails → ['%s']",
      self::CANONICAL_MAIL,
    ));
  }

}
