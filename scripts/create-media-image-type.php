<?php
/**
 * @file
 * Create the standard "Image" media type so canvas_page's `image` base field
 * (and other media-library widgets) can render without crashing.
 *
 * Idempotent.
 */

use Drupal\media\Entity\MediaType;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;

if (MediaType::load('image')) {
  echo "  = media_type 'image' ya existe — nada que hacer.\n";
  return;
}

// 1. Create the media type.
$media_type = MediaType::create([
  'id' => 'image',
  'label' => 'Image',
  'description' => 'Use local images for reusable content.',
  'source' => 'image',
  'queue_thumbnail_downloads' => FALSE,
  'new_revision' => FALSE,
  'source_configuration' => [
    'source_field' => 'field_media_image',
  ],
]);
$media_type->save();
echo "  ✓ media_type 'image' creado\n";

// 2. Create the source field storage if it doesn't exist.
if (!FieldStorageConfig::loadByName('media', 'field_media_image')) {
  FieldStorageConfig::create([
    'field_name' => 'field_media_image',
    'entity_type' => 'media',
    'type' => 'image',
    'cardinality' => 1,
    'settings' => [
      'target_type' => 'file',
      'display_field' => FALSE,
      'display_default' => FALSE,
      'uri_scheme' => 'public',
      'default_image' => [
        'uuid' => '',
        'alt' => '',
        'title' => '',
        'width' => NULL,
        'height' => NULL,
      ],
    ],
  ])->save();
  echo "  ✓ field_storage 'media.field_media_image' creado\n";
}

// 3. Field instance bound to this media type.
if (!FieldConfig::loadByName('media', 'image', 'field_media_image')) {
  FieldConfig::create([
    'field_name' => 'field_media_image',
    'entity_type' => 'media',
    'bundle' => 'image',
    'label' => 'Image',
    'required' => TRUE,
    'translatable' => FALSE,
    'settings' => [
      'file_extensions' => 'png gif jpg jpeg webp avif',
      'file_directory' => '[date:custom:Y]-[date:custom:m]',
      'max_filesize' => '',
      'max_resolution' => '',
      'min_resolution' => '',
      'alt_field' => TRUE,
      'alt_field_required' => TRUE,
      'title_field' => FALSE,
      'title_field_required' => FALSE,
      'default_image' => [
        'uuid' => '',
        'alt' => '',
        'title' => '',
        'width' => NULL,
        'height' => NULL,
      ],
      'handler' => 'default:file',
      'handler_settings' => [],
    ],
  ])->save();
  echo "  ✓ field 'media.image.field_media_image' creado\n";
}

// 4. Form display: image field uses image_image widget.
$form = EntityFormDisplay::load('media.image.default');
if (!$form) {
  $form = EntityFormDisplay::create([
    'targetEntityType' => 'media',
    'bundle' => 'image',
    'mode' => 'default',
    'status' => TRUE,
  ]);
}
$form->setComponent('field_media_image', [
  'type' => 'image_image',
  'weight' => 1,
  'settings' => ['preview_image_style' => 'medium', 'progress_indicator' => 'throbber'],
]);
$form->setComponent('name', [
  'type' => 'string_textfield',
  'weight' => 0,
  'settings' => ['size' => 60, 'placeholder' => ''],
]);
$form->save();
echo "  ✓ form display media.image.default actualizado\n";

// 5. View display: image field uses image formatter.
$view = EntityViewDisplay::load('media.image.default');
if (!$view) {
  $view = EntityViewDisplay::create([
    'targetEntityType' => 'media',
    'bundle' => 'image',
    'mode' => 'default',
    'status' => TRUE,
  ]);
}
$view->setComponent('field_media_image', [
  'type' => 'image',
  'weight' => 1,
  'label' => 'hidden',
  'settings' => ['image_style' => '', 'image_link' => ''],
]);
$view->save();
echo "  ✓ view display media.image.default actualizado\n";

// 6. Set the source field on the media type now that it exists.
$media_type = MediaType::load('image');
$media_type->set('source_configuration', ['source_field' => 'field_media_image'])->save();

drupal_flush_all_caches();
echo "\n✅ Media type 'image' listo. Canvas MediaLibraryWidget ya no debería crashear.\n";
