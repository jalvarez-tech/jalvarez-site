<?php
/**
 * @file
 * Helpers for building Canvas component trees programmatically.
 *
 * Used by:
 *  - scripts/create-canvas-home.php
 *  - scripts/create-canvas-other-pages.php
 *
 * Loaded with `require_once` from those scripts.
 */

use Drupal\canvas\Entity\Component;

if (!function_exists('canvas_tree_uuid')) {
  function canvas_tree_uuid(): string {
    return \Drupal::service('uuid')->generate();
  }
}

if (!function_exists('canvas_tree_sdc_item')) {
  /**
   * Build a tree item for an SDC Component.
   *
   * @param string $uuid Component instance UUID.
   * @param string $component_id e.g. 'sdc.byte.cta-final'.
   * @param array<string, mixed> $values Plain prop key→value map.
   * @param string|null $parent_uuid Parent UUID for items inside slots.
   * @param string|null $slot Slot name when nested.
   */
  function canvas_tree_sdc_item(string $uuid, string $component_id, array $values, ?string $parent_uuid = NULL, ?string $slot = NULL): array {
    $component = Component::load($component_id);
    if (!$component) {
      throw new \RuntimeException("Component config entity '{$component_id}' not found. Re-run scripts/canvas-discover-sdcs.php.");
    }
    $version = $component->getActiveVersion();
    $settings = $component->getSettings();
    $defs = $settings['prop_field_definitions'] ?? [];

    $inputs = [];
    foreach ($values as $prop_name => $value) {
      if (!isset($defs[$prop_name])) {
        continue;
      }
      $field_type = $defs[$prop_name]['field_type'];
      $expression = $defs[$prop_name]['expression'];
      $entry = [
        'sourceType' => "static:field_item:{$field_type}",
        'value' => $value,
        'expression' => $expression,
      ];
      if ($field_type === 'list_string') {
        $entry['sourceTypeSettings'] = [
          'storage' => ['allowed_values_function' => 'canvas_load_allowed_values_for_component_prop'],
        ];
      }
      $inputs[$prop_name] = $entry;
    }

    return [
      'uuid' => $uuid,
      'component_id' => $component_id,
      'component_version' => $version,
      'inputs' => json_encode($inputs, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
      'parent_uuid' => $parent_uuid,
      'slot' => $slot,
    ];
  }
}

if (!function_exists('canvas_tree_block_item')) {
  /**
   * Build a tree item for a Block-source Component (Views block, Webform block, etc.).
   *
   * Block components store their plugin settings flat (no sourceType wrappers) —
   * the `inputs` JSON merges into the block plugin's $configuration via
   * `BlockComponent::clientModelToInput()`.
   *
   * @param string $uuid Component instance UUID.
   * @param string $component_id e.g. 'block.webform_block'.
   * @param array<string, mixed> $settings Flat plugin settings, e.g.:
   *   ['webform_id' => 'contact', 'label' => '', 'label_display' => '0']
   */
  function canvas_tree_block_item(string $uuid, string $component_id, array $settings = [], ?string $parent_uuid = NULL, ?string $slot = NULL): array {
    $component = Component::load($component_id);
    if (!$component) {
      throw new \RuntimeException("Component config entity '{$component_id}' not found.");
    }
    $version = $component->getActiveVersion();

    return [
      'uuid' => $uuid,
      'component_id' => $component_id,
      'component_version' => $version,
      'inputs' => json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
      'parent_uuid' => $parent_uuid,
      'slot' => $slot,
    ];
  }
}
