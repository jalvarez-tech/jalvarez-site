<?php

declare(strict_types=1);

namespace Drupal\Tests\jalvarez_site\Unit\Hook;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\jalvarez_site\Hook\CanvasPageHook;
use Drupal\Tests\UnitTestCase;
use Psr\Log\LoggerInterface;

/**
 * @coversDefaultClass \Drupal\jalvarez_site\Hook\CanvasPageHook
 * @group jalvarez_site
 */
final class CanvasPageHookTest extends UnitTestCase {

  /**
   * @covers ::presave
   */
  public function testNoOpForNewEntities(): void {
    $entity = $this->createMock(EntityInterface::class);
    $entity->method('isNew')->willReturn(TRUE);

    $etm = $this->createMock(EntityTypeManagerInterface::class);
    $etm->expects($this->never())->method('getStorage');

    (new CanvasPageHook($etm, $this->createMock(LoggerInterface::class)))->presave($entity);
  }

  /**
   * @covers ::presave
   */
  public function testNoOpForNonContentEntity(): void {
    // The hook signature is EntityInterface; only ContentEntityInterface
    // exposes the translation API. A config entity passed in must short
    // circuit before we touch storage.
    $entity = $this->createMock(EntityInterface::class);
    $entity->method('isNew')->willReturn(FALSE);

    $etm = $this->createMock(EntityTypeManagerInterface::class);
    $etm->expects($this->never())->method('getStorage');

    (new CanvasPageHook($etm, $this->createMock(LoggerInterface::class)))->presave($entity);
  }

  /**
   * @covers ::presave
   */
  public function testNoOpWhenStorageThrows(): void {
    $entity = $this->makeContentEntity(id: 42, type: 'canvas_page');
    $entity->method('isNew')->willReturn(FALSE);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('loadUnchanged')->willThrowException(new \RuntimeException('boom'));
    $etm = $this->createMock(EntityTypeManagerInterface::class);
    $etm->method('getStorage')->willReturn($storage);

    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($this->never())->method('warning');

    // Should swallow and return — no exception escapes, no logger calls.
    (new CanvasPageHook($etm, $logger))->presave($entity);
  }

  /**
   * @covers ::presave
   */
  public function testRestoresTranslationThatWasWipedEntirely(): void {
    // Original has EN; the in-flight entity lost it.
    $orig_en = $this->makeTranslationStub(['title' => 'Home', 'components' => ['c1']]);
    $original = $this->makeContentEntity(
      id: 5,
      type: 'canvas_page',
      translationLanguages: ['en' => NULL],
      translations: ['en' => $orig_en],
    );

    $entity = $this->makeContentEntity(id: 5, type: 'canvas_page');
    $entity->method('isNew')->willReturn(FALSE);
    $entity->method('hasTranslation')->with('en')->willReturn(FALSE);
    $entity->expects($this->once())
      ->method('addTranslation')
      ->with('en', $this->callback(fn(array $values) => isset($values['title']) && isset($values['components'])));

    $etm = $this->makeEntityTypeManagerReturning($original);
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($this->atLeastOnce())->method('warning');

    (new CanvasPageHook($etm, $logger))->presave($entity);
  }

  /**
   * @covers ::presave
   */
  public function testRestoresComponentsThatWereEmptied(): void {
    $orig_components_value = [['type' => 'header']];
    $orig_es = $this->makeTranslationStub(['components' => $orig_components_value]);
    $original = $this->makeContentEntity(
      id: 5,
      type: 'canvas_page',
      translationLanguages: ['es' => NULL],
      translations: ['es' => $orig_es],
    );

    $current_es = $this->makeTranslationStub(['components' => []], expectComponentsRestoredTo: $orig_components_value);
    $entity = $this->makeContentEntity(
      id: 5,
      type: 'canvas_page',
      translations: ['es' => $current_es],
    );
    $entity->method('isNew')->willReturn(FALSE);
    $entity->method('hasTranslation')->with('es')->willReturn(TRUE);

    $etm = $this->makeEntityTypeManagerReturning($original);
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($this->atLeastOnce())->method('warning');

    (new CanvasPageHook($etm, $logger))->presave($entity);
  }

  /**
   * @covers ::presave
   */
  public function testNoOpWhenComponentsAreStillPopulated(): void {
    // Legitimate edit: components changed but still non-empty.
    $orig_es = $this->makeTranslationStub(['components' => [['type' => 'old']]]);
    $original = $this->makeContentEntity(
      id: 5,
      type: 'canvas_page',
      translationLanguages: ['es' => NULL],
      translations: ['es' => $orig_es],
    );

    $current_es = $this->makeTranslationStub(['components' => [['type' => 'new']]]);
    $current_es->expects($this->never())->method('set');

    $entity = $this->makeContentEntity(
      id: 5,
      type: 'canvas_page',
      translations: ['es' => $current_es],
    );
    $entity->method('isNew')->willReturn(FALSE);
    $entity->method('hasTranslation')->with('es')->willReturn(TRUE);

    $etm = $this->makeEntityTypeManagerReturning($original);
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($this->never())->method('warning');

    (new CanvasPageHook($etm, $logger))->presave($entity);
  }

  /**
   * Builds a ContentEntityInterface mock with the methods the hook calls.
   *
   * @param int $id
   *   Entity ID returned by id().
   * @param string $type
   *   Entity type returned by getEntityTypeId().
   * @param array<string,mixed>|null $translationLanguages
   *   Map [langcode => stub] used as return value of getTranslationLanguages.
   * @param array<string,ContentEntityInterface> $translations
   *   Map [langcode => translation entity] returned by getTranslation().
   */
  private function makeContentEntity(
    int $id,
    string $type = 'canvas_page',
    ?array $translationLanguages = NULL,
    array $translations = [],
  ): ContentEntityInterface {
    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->method('id')->willReturn((string) $id);
    $entity->method('getEntityTypeId')->willReturn($type);
    if ($translationLanguages !== NULL) {
      $entity->method('getTranslationLanguages')->willReturn($translationLanguages);
    }
    $entity->method('getTranslation')->willReturnCallback(
      fn(string $lc) => $translations[$lc] ?? throw new \LogicException("No translation stub for $lc")
    );
    return $entity;
  }

  /**
   * Builds a translation stub with the given field values.
   *
   * @param array<string,mixed> $fields
   *   Map [field_name => raw value]. Each becomes a hasField/get pair.
   * @param mixed $expectComponentsRestoredTo
   *   When non-NULL, asserts set('components', $value) is called once.
   */
  private function makeTranslationStub(array $fields, mixed $expectComponentsRestoredTo = NULL): ContentEntityInterface {
    $stub = $this->createMock(ContentEntityInterface::class);
    $stub->method('hasField')
      ->willReturnCallback(fn(string $name) => array_key_exists($name, $fields));
    $stub->method('get')
      ->willReturnCallback(function (string $name) use ($fields) {
        $list = $this->createMock(FieldItemListInterface::class);
        $list->method('getValue')->willReturn($fields[$name] ?? []);
        return $list;
      });
    if ($expectComponentsRestoredTo !== NULL) {
      $stub->expects($this->once())
        ->method('set')
        ->with('components', $expectComponentsRestoredTo);
    }
    return $stub;
  }

  private function makeEntityTypeManagerReturning(ContentEntityInterface $original): EntityTypeManagerInterface {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('loadUnchanged')->willReturn($original);
    $etm = $this->createMock(EntityTypeManagerInterface::class);
    $etm->method('getStorage')->willReturn($storage);
    return $etm;
  }

}
