<?php
/**
 * @file
 * Download the official Royalty Films cover (og:image of royaltyfilms.co)
 * and attach it as field_cover_media on the project node nid=2.
 *
 * field_cover_media was made non-translatable on commit 728b101, so a
 * single Media reference covers ES + EN automatically.
 *
 * Idempotent: if a Media entity with the same source URL already exists,
 * reuses it instead of creating a new one.
 *
 * Run via:
 *   ddev exec ./web/vendor/bin/drush php:script scripts/add-royalty-cover.php
 *   gh workflow run seed-content.yml --field script=scripts/add-royalty-cover.php
 */

use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\node\Entity\Node;

$source_url     = 'https://royaltyfilms.co/wp-content/uploads/2024/07/RF.jpg';
$file_basename  = 'royalty-films-cover.jpg';
$media_alt      = 'Royalty Films — production company cover';
$media_label    = 'Royalty Films cover';
$node_id        = 2;

$node = Node::load($node_id);
if (!$node || $node->bundle() !== 'project' || stripos($node->label(), 'royalty') === FALSE) {
  fwrite(STDERR, "✗ Node nid={$node_id} is not Royalty Films. Aborting.\n");
  exit(1);
}

// 1. Reuse existing Media if we already attached one with this label.
$media_storage = \Drupal::entityTypeManager()->getStorage('media');
$existing = $media_storage->loadByProperties([
  'bundle' => 'image',
  'name'   => $media_label,
]);
$media = $existing ? reset($existing) : NULL;

if (!$media) {
  // 2. Download the source image.
  echo "→ Downloading {$source_url}…\n";
  $bytes = @file_get_contents($source_url);
  if ($bytes === FALSE || strlen($bytes) < 1000) {
    fwrite(STDERR, "✗ Could not download {$source_url} (got " . (is_string($bytes) ? strlen($bytes) : 'false') . " bytes).\n");
    exit(1);
  }
  echo "  · downloaded " . number_format(strlen($bytes)) . " bytes\n";

  // 3. Save the bytes as a managed File in public://.
  $year_month = date('Y-m');
  $directory = "public://{$year_month}";
  \Drupal::service('file_system')->prepareDirectory($directory, \Drupal\Core\File\FileSystemInterface::CREATE_DIRECTORY);
  $file = \Drupal::service('file.repository')->writeData(
    $bytes,
    "{$directory}/{$file_basename}",
    \Drupal\Core\File\FileExists::Replace
  );
  echo "  · saved as " . $file->getFileUri() . " (fid={$file->id()})\n";

  // 4. Wrap the File in a Media entity (bundle: image).
  $media = Media::create([
    'bundle' => 'image',
    'name'   => $media_label,
    'field_media_image' => [
      'target_id' => $file->id(),
      'alt'       => $media_alt,
    ],
    'status' => 1,
  ]);
  $media->save();
  echo "  · media entity created (mid={$media->id()})\n";
}
else {
  echo "= Reusing existing media: {$media->label()} (mid={$media->id()})\n";
}

// 5. Attach to the node. Since field_cover_media is non-translatable, the
// single value applies to both ES and EN translations automatically.
$node->set('field_cover_media', ['target_id' => $media->id()]);
foreach ($node->getTranslationLanguages() as $langcode => $_) {
  $node->getTranslation($langcode)->setPublished()->save();
}
echo "✓ field_cover_media on Royalty Films now → media mid={$media->id()}\n";

drupal_flush_all_caches();
echo "✓ Caches flushed. Verify at /es/proyectos and /es/proyectos/royalty-films.\n";
