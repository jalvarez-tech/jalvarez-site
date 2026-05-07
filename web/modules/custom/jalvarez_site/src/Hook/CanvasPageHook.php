<?php

declare(strict_types=1);

namespace Drupal\jalvarez_site\Hook;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Psr\Log\LoggerInterface;

/**
 * Defensive guard against the Canvas 1.3.x translation-wipe bug.
 *
 * Background:
 *  - Editing a translated canvas_page in the visual editor and saving while
 *    the active language is X can wipe translation Y from the entity:
 *      - Y's `components` field becomes empty in the saved revision, OR
 *      - Y's translation row disappears entirely. Root cause is the
 *        AutoSaveManager rebuilding the entity from autosave data that
 *        only carries the active language (see
 *        web/modules/contrib/canvas/src/AutoSave/AutoSaveManager.php:313).
 *  - Symptom: /<other_lang>/<aliased_path> 404s and /<other_lang> falls
 *    back to the canonical translation rendering the wrong content.
 *  - Upstream issue: https://www.drupal.org/project/canvas/issues/3569959
 *  - Upstream MR !511 is "Needs work" and not safely backportable to 1.3.x
 *    (depends on MR !494 + a JS bundle rebuild + has open regressions).
 *
 * What this hook does:
 * Just before any canvas_page save, compares the in-memory entity against
 * the version currently in storage. For each translation that previously
 * existed, ensures it still exists with its `components`, `path`, `title`,
 * `description` intact. If the save would have removed or emptied a
 * translation that wasn't actively edited in this request, the original
 * values are restored.
 *
 * Trade-off: deleting a canvas_page translation must go through drush
 * ($entity->removeTranslation($lang)->save()) or the Translate tab — NOT
 * the visual editor. The hook treats translation disappearance as a bug
 * to repair, not as user intent.
 *
 * If/when Canvas upstream fixes this and we update, the hook becomes a
 * no-op and can be removed safely. Until then it's the cheapest insurance.
 */
class CanvasPageHook {

  /**
   * Fields restored when a translation is detected as wiped.
   */
  private const RESTORE_FIELDS = ['title', 'description', 'components', 'path', 'status'];

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly LoggerInterface $logger,
  ) {}

  #[Hook('canvas_page_presave')]
  public function presave(EntityInterface $entity): void {
    if ($entity->isNew()) {
      return;
    }
    // Narrow EntityInterface (the hook signature) to the translatable +
    // fieldable shape we actually need below. Canvas pages are content
    // entities at runtime — the check exists for type safety so static
    // analysis can verify the field/translation API.
    if (!$entity instanceof ContentEntityInterface) {
      return;
    }

    $original = $this->loadOriginal($entity);
    if ($original === NULL) {
      return;
    }

    foreach ($original->getTranslationLanguages() as $langcode => $_) {
      $orig_trans = $original->getTranslation($langcode);

      if (!$entity->hasTranslation($langcode)) {
        $this->restoreMissingTranslation($entity, $orig_trans, $langcode);
        continue;
      }

      $current_trans = $entity->getTranslation($langcode);
      $this->restoreEmptiedComponents($current_trans, $orig_trans, $entity, $langcode);
    }
  }

  /**
   * Loads the unchanged original from storage, bypassing in-flight cache.
   */
  private function loadOriginal(ContentEntityInterface $entity): ?ContentEntityInterface {
    try {
      $original = $this->entityTypeManager
        ->getStorage($entity->getEntityTypeId())
        ->loadUnchanged((int) $entity->id());
    }
    catch (\Throwable) {
      return NULL;
    }
    return $original instanceof ContentEntityInterface ? $original : NULL;
  }

  /**
   * Re-adds a translation that the buggy save removed entirely.
   */
  private function restoreMissingTranslation(ContentEntityInterface $entity, ContentEntityInterface $orig_trans, string $langcode): void {
    $values = [];
    foreach (self::RESTORE_FIELDS as $field) {
      if ($orig_trans->hasField($field)) {
        $values[$field] = $orig_trans->get($field)->getValue();
      }
    }
    $entity->addTranslation($langcode, $values);
    $this->logger->warning(
      'Restored deleted canvas_page translation @lang on entity @id (translation-wipe bug guard).',
      ['@lang' => $langcode, '@id' => $entity->id()]
    );
  }

  /**
   * Restores components + path when only their values got blanked.
   *
   * We only act when components flips from non-empty to empty (the bug
   * signature). Legitimate edits change content; they don't clear it
   * wholesale.
   */
  private function restoreEmptiedComponents(ContentEntityInterface $current_trans, ContentEntityInterface $orig_trans, ContentEntityInterface $entity, string $langcode): void {
    if (!$orig_trans->hasField('components') || !$current_trans->hasField('components')) {
      return;
    }
    $orig_components = $orig_trans->get('components')->getValue();
    $current_components = $current_trans->get('components')->getValue();
    if (empty($orig_components) || !empty($current_components)) {
      return;
    }

    $current_trans->set('components', $orig_components);
    $this->logger->warning(
      'Restored emptied components on canvas_page @id translation @lang (translation-wipe bug guard).',
      ['@id' => $entity->id(), '@lang' => $langcode]
    );

    if ($orig_trans->hasField('path') && $current_trans->hasField('path')) {
      $orig_path = $orig_trans->get('path')->getValue();
      $current_path = $current_trans->get('path')->getValue();
      if (!empty($orig_path) && empty($current_path)) {
        $current_trans->set('path', $orig_path);
      }
    }
  }

}
