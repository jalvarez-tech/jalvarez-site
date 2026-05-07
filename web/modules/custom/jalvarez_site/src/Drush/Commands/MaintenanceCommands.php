<?php

declare(strict_types=1);

namespace Drupal\jalvarez_site\Drush\Commands;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Site\Settings;
use Drupal\language\ConfigurableLanguageManagerInterface;
use Drupal\node\NodeInterface;
use Drupal\path_alias\AliasManagerInterface;
use Drupal\path_alias\PathAliasInterface;
use Drupal\pathauto\PathautoGeneratorInterface;
use Drupal\pathauto\PathautoPatternInterface;
use Drupal\taxonomy\TermInterface;
use Drupal\taxonomy\VocabularyInterface;
use Drupal\webform\WebformInterface;
use Drush\Attributes\Command;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * On-demand maintenance commands extracted from former scripts/*.php helpers.
 *
 * These were the three scripts in `scripts/` that survived the PR2 cleanup —
 * the rest were either one-shots already applied to production or duplicates
 * of native Drush commands. Lifting them into Drush gives them DI, attribute
 * routing, and `drush help` discoverability.
 */
final class MaintenanceCommands extends DrushCommands {

  /**
   * Bundles whose pathauto patterns and aliases this module owns.
   */
  private const array OWNED_BUNDLES = ['project', 'note'];

  /**
   * Per-language pattern ids the team experimented with and abandoned.
   *
   * Re-running pathauto-rebuild deletes these to revert any leftover state.
   */
  private const array ROGUE_PATTERN_IDS = [
    'project_es',
    'project_en',
    'note_es',
    'note_en',
  ];

  /**
   * ES path alias of the home canvas_page (must exist for the wipe-guard test).
   */
  private const string HOME_ALIAS = '/inicio';

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly LanguageManagerInterface $languageManager,
    private readonly AliasManagerInterface $aliasManager,
    private readonly PathautoGeneratorInterface $pathautoGenerator,
  ) {
    parent::__construct();
  }

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('language_manager'),
      $container->get('path_alias.manager'),
      $container->get('pathauto.generator'),
    );
  }

  /**
   * Audit translation coverage across all entity types we manage.
   *
   * Lists `canvas_page`, `node:project`, `node:note`, every taxonomy term and
   * every webform with the language codes that have translations. Useful for
   * spotting regressions after a content migration or after enabling a new
   * language.
   */
  #[Command(name: 'jalvarez:audit-translations')]
  public function auditTranslations(): int {
    $this->output()->writeln('── canvas_page entities ──');
    $alias_storage = $this->entityTypeManager->getStorage('path_alias');
    foreach ($this->entityTypeManager->getStorage('canvas_page')->loadMultiple() as $page) {
      if (!$page instanceof ContentEntityInterface) {
        continue;
      }
      $langs = implode(',', array_keys($page->getTranslationLanguages()));
      $aliases = [];
      foreach ($alias_storage->loadByProperties(['path' => '/page/' . $page->id()]) as $alias) {
        if (!$alias instanceof PathAliasInterface) {
          continue;
        }
        $aliases[] = sprintf('[%s] %s', $alias->language()->getId(), $alias->getAlias());
      }
      $this->output()->writeln(sprintf(
        '  · id=%s title=%-22s langs=%s · aliases: %s',
        (string) $page->id(),
        '"' . $page->label() . '"',
        $langs,
        implode(' ', $aliases),
      ));
    }

    $node_storage = $this->entityTypeManager->getStorage('node');
    foreach (self::OWNED_BUNDLES as $bundle) {
      $this->output()->writeln('');
      $this->output()->writeln("── node.{$bundle} ──");
      $nids = $node_storage->getQuery()
        ->condition('type', $bundle)
        ->accessCheck(FALSE)
        ->execute();
      foreach ($node_storage->loadMultiple($nids) as $node) {
        if (!$node instanceof NodeInterface) {
          continue;
        }
        $langs = implode(',', array_keys($node->getTranslationLanguages()));
        $this->output()->writeln(sprintf(
          '  · nid=%-3s title=%-60s langs=%s',
          (string) $node->id(),
          '"' . substr((string) $node->label(), 0, 55) . '"',
          $langs,
        ));
      }
    }

    $this->output()->writeln('');
    $this->output()->writeln('── Taxonomy terms ──');
    $vocab_storage = $this->entityTypeManager->getStorage('taxonomy_vocabulary');
    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
    foreach ($vocab_storage->loadMultiple() as $vid => $vocab) {
      if (!$vocab instanceof VocabularyInterface) {
        continue;
      }
      $this->output()->writeln("  vocab '{$vid}':");
      $tids = $term_storage->getQuery()
        ->condition('vid', $vid)
        ->accessCheck(FALSE)
        ->execute();
      foreach ($term_storage->loadMultiple($tids) as $term) {
        if (!$term instanceof TermInterface) {
          continue;
        }
        $langs = implode(',', array_keys($term->getTranslationLanguages()));
        $this->output()->writeln(sprintf(
          '    · tid=%-3s %-30s langs=%s',
          (string) $term->id(),
          '"' . $term->label() . '"',
          $langs,
        ));
      }
    }

    $this->output()->writeln('');
    $this->output()->writeln('── Webforms ──');
    $webform_storage = $this->entityTypeManager->getStorage('webform');
    foreach ($webform_storage->loadMultiple() as $webform) {
      if (!$webform instanceof WebformInterface) {
        continue;
      }
      $this->output()->writeln("  · {$webform->id()}: '{$webform->label()}'");
      if (!$this->languageManager->isMultilingual()) {
        continue;
      }
      // Config-translation overrides are only available when the `language`
      // module is enabled — its service decorates `language_manager` with
      // ConfigurableLanguageManager. Skip silently when running on a
      // monolingual install (e.g. the minimal test profile).
      if (!$this->languageManager instanceof ConfigurableLanguageManagerInterface) {
        $this->output()->writeln('    · (language module disabled — translation overrides unavailable)');
        continue;
      }
      $config_name = 'webform.webform.' . $webform->id();
      foreach ($this->languageManager->getLanguages() as $lang_code => $_) {
        if ($lang_code === $webform->language()->getId()) {
          continue;
        }
        $override = $this->languageManager->getLanguageConfigOverride($lang_code, $config_name);
        $has = !empty($override->get());
        $this->output()->writeln("    · translation [{$lang_code}]: " . ($has ? 'YES' : 'NO'));
      }
    }

    return DrushCommands::EXIT_SUCCESS;
  }

  /**
   * Drop rogue per-language pathauto patterns and regenerate node aliases.
   *
   * Idempotent — safe to re-run. Performs:
   *  1. Delete experimental `<bundle>_<lang>` patterns if present.
   *  2. Restore the canonical `<bundle>` pattern from `config/sync` if the
   *     active config is missing it.
   *  3. Wipe every `/node/N` alias.
   *  4. Regenerate aliases for every translation of every project + note,
   *     letting the pathauto alter hook (in PathautoHook) rewrite the EN
   *     prefix from `proyectos/notas` to `projects/notes`.
   */
  #[Command(name: 'jalvarez:pathauto-rebuild')]
  public function pathautoRebuild(): int {
    $pattern_storage = $this->entityTypeManager->getStorage('pathauto_pattern');
    foreach (self::ROGUE_PATTERN_IDS as $pattern_id) {
      $pattern = $pattern_storage->load($pattern_id);
      if ($pattern instanceof PathautoPatternInterface) {
        $pattern->delete();
        $this->logger()->success("Pattern '{$pattern_id}' deleted.");
      }
    }

    $sync_dir = Settings::get('config_sync_directory') ?: (DRUPAL_ROOT . '/../config/sync');
    foreach (self::OWNED_BUNDLES as $bundle) {
      if ($pattern_storage->load($bundle) !== NULL) {
        continue;
      }
      $config_file = rtrim((string) $sync_dir, '/') . '/pathauto.pattern.' . $bundle . '.yml';
      if (!file_exists($config_file)) {
        $this->logger()->warning("Pattern '{$bundle}' missing and no config/sync source found at {$config_file}.");
        continue;
      }
      $data = Yaml::parse((string) file_get_contents($config_file));
      // `_core` carries an environment-specific UUID suffix that
      // PathautoPattern::create() rejects.
      unset($data['_core']);
      $pattern_storage->create($data)->save();
      $this->logger()->success("Pattern '{$bundle}' restored from config/sync.");
    }

    drupal_flush_all_caches();

    $this->output()->writeln('');
    $this->output()->writeln('── Wiping existing node aliases ──');
    $alias_storage = $this->entityTypeManager->getStorage('path_alias');
    $deleted = 0;
    foreach ($alias_storage->loadMultiple() as $alias) {
      if (!$alias instanceof PathAliasInterface) {
        continue;
      }
      if (preg_match('#^/node/\d+$#', $alias->getPath())) {
        $alias->delete();
        $deleted++;
      }
    }
    $this->output()->writeln("  · {$deleted} node aliases deleted.");

    $this->output()->writeln('');
    $this->output()->writeln('── Regenerating with hook_pathauto_alias_alter() ──');
    $node_storage = $this->entityTypeManager->getStorage('node');
    foreach (self::OWNED_BUNDLES as $bundle) {
      $nids = $node_storage->getQuery()
        ->condition('type', $bundle)
        ->accessCheck(FALSE)
        ->execute();
      foreach ($node_storage->loadMultiple($nids) as $node) {
        if (!$node instanceof NodeInterface) {
          continue;
        }
        foreach ($node->getTranslationLanguages() as $lang_code => $_) {
          $translation = $node->getTranslation($lang_code);
          $this->pathautoGenerator->updateEntityAlias(
            $translation,
            'bulkupdate',
            ['language' => $lang_code],
          );
        }
      }
    }

    $this->output()->writeln('');
    $this->output()->writeln('── Final aliases ──');
    foreach ($alias_storage->loadMultiple() as $alias) {
      if (!$alias instanceof PathAliasInterface) {
        continue;
      }
      if (preg_match('#^/node/\d+$#', $alias->getPath())) {
        $this->output()->writeln(sprintf(
          '  · [%s] %s → %s',
          $alias->language()->getId(),
          $alias->getPath(),
          $alias->getAlias(),
        ));
      }
    }

    return DrushCommands::EXIT_SUCCESS;
  }

  /**
   * Smoke-test the canvas_page translation-wipe guard against a real save.
   *
   * Reproduces the Canvas 1.3.x bug scenario via the entity API: edits the ES
   * translation's title AND blanks the EN translation's `components` field
   * (mimicking what the buggy AutoSaveManager flow used to do), saves, then
   * asserts that the `CanvasPageHook::presave` defence reverted the wipe.
   *
   * Side effects: the test ES title change is reverted at the end. Safe to
   * run on production. Exit code 1 on guard regression — wire to monitoring
   * if you care.
   */
  #[Command(name: 'jalvarez:test-wipe-guard')]
  public function testWipeGuard(): int {
    $home_path = $this->aliasManager->getPathByAlias(self::HOME_ALIAS);
    if (!preg_match('#^/page/(\d+)$#', $home_path, $matches)) {
      $this->logger()->error("Could not resolve '" . self::HOME_ALIAS . "' to /page/{id}. Got '{$home_path}'.");
      return DrushCommands::EXIT_FAILURE;
    }
    $page_id = (int) $matches[1];
    $page_storage = $this->entityTypeManager->getStorage('canvas_page');
    $page = $page_storage->load($page_id);
    if (!$page instanceof ContentEntityInterface) {
      $this->logger()->error("canvas_page id={$page_id} not loadable.");
      return DrushCommands::EXIT_FAILURE;
    }
    $this->output()->writeln("→ Target: canvas_page id={$page_id} title='{$page->label()}'");

    if (!$page->hasTranslation('en')) {
      $this->logger()->error('EN translation missing; nothing to protect. Restore it first.');
      return DrushCommands::EXIT_FAILURE;
    }

    $en = $page->getTranslation('en');
    $en_components_before = count($en->get('components')->getValue());
    $en_alias_before = $en->get('path')->first()?->getValue()['alias'] ?? NULL;
    $en_title_before = (string) $en->label();
    $this->output()->writeln(sprintf(
      "→ EN snapshot: title='%s', components=%d, alias='%s'",
      $en_title_before,
      $en_components_before,
      (string) $en_alias_before,
    ));

    $es = $page->getTranslation('es');
    $es_title_before = (string) $es->label();

    $this->output()->writeln('→ Simulating buggy save: edit ES title + blank EN components…');
    $es->set('title', $es_title_before . ' [TEST]');
    $en->set('components', []);
    $page->save();
    $this->output()->writeln('→ Save completed.');

    $reloaded = $page_storage->loadUnchanged($page_id);
    if (!$reloaded instanceof ContentEntityInterface) {
      $this->logger()->error("canvas_page id={$page_id} disappeared after save.");
      return DrushCommands::EXIT_FAILURE;
    }
    $failed = [];
    if (!$reloaded->hasTranslation('en')) {
      $failed[] = 'EN translation was DELETED';
    }
    else {
      $en_after = $reloaded->getTranslation('en');
      $components_after = count($en_after->get('components')->getValue());
      $alias_after = $en_after->get('path')->first()?->getValue()['alias'] ?? NULL;
      if ($components_after !== $en_components_before) {
        $failed[] = "EN components count changed: {$en_components_before} → {$components_after}";
      }
      if ($alias_after !== $en_alias_before) {
        $failed[] = "EN alias changed: '{$en_alias_before}' → '" . (string) $alias_after . "'";
      }
    }

    // Always revert the ES title, even when the assertion fails — we never
    // want this command to leave production with " [TEST]" appended.
    $this->output()->writeln('→ Reverting test ES title change…');
    $reloaded->getTranslation('es')->set('title', $es_title_before);
    $reloaded->save();

    if ($failed) {
      $this->logger()->error('GUARD FAILED:');
      foreach ($failed as $message) {
        $this->logger()->error('  - ' . $message);
      }
      return DrushCommands::EXIT_FAILURE;
    }

    $this->logger()->success('GUARD WORKING: EN translation survived a simulated wipe.');
    $this->output()->writeln(sprintf('  - components: %d preserved', $en_components_before));
    $this->output()->writeln(sprintf("  - alias: '%s' preserved", (string) $en_alias_before));

    return DrushCommands::EXIT_SUCCESS;
  }

}
