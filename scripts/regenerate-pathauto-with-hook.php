<?php
/**
 * @file
 * Cleanup any rogue per-language pathauto patterns and regenerate all node
 * aliases so the new hook_pathauto_alias_alter() takes effect.
 */

use Drupal\node\Entity\Node;
use Drupal\pathauto\Entity\PathautoPattern;
use Drupal\Core\Path\AliasStorageInterface;

// Delete experimental per-language patterns if they exist.
foreach (['project_es', 'project_en', 'note_es', 'note_en'] as $id) {
  $p = PathautoPattern::load($id);
  if ($p) {
    $p->delete();
    echo "  · pattern '{$id}' eliminado\n";
  }
}

// Re-import original ES patterns from config/sync if missing.
foreach (['project', 'note'] as $bundle) {
  if (!PathautoPattern::load($bundle)) {
    $config_file = '/var/www/html/config/sync/pathauto.pattern.' . $bundle . '.yml';
    if (file_exists($config_file)) {
      $data = \Symfony\Component\Yaml\Yaml::parse(file_get_contents($config_file));
      // Strip readonly properties pathauto may complain about.
      unset($data['_core']);
      $pattern = PathautoPattern::create($data);
      $pattern->save();
      echo "  ✓ pattern '{$bundle}' restaurado desde config/sync\n";
    }
  }
}

drupal_flush_all_caches();

echo "\n── Wiping existing node aliases ──\n";
$alias_storage = \Drupal::entityTypeManager()->getStorage('path_alias');
$count = 0;
foreach ($alias_storage->loadMultiple() as $a) {
  if (preg_match('#^/node/\d+$#', $a->getPath())) {
    $a->delete();
    $count++;
  }
}
echo "  · {$count} aliases de nodos eliminados\n";

echo "\n── Regenerating with hook_pathauto_alias_alter() ──\n";
$pathauto = \Drupal::service('pathauto.generator');
foreach (['project', 'note'] as $bundle) {
  $nids = \Drupal::entityQuery('node')->condition('type', $bundle)->accessCheck(FALSE)->execute();
  foreach (Node::loadMultiple($nids) as $n) {
    foreach ($n->getTranslationLanguages() as $lang_code => $lang) {
      $translation = $n->getTranslation($lang_code);
      $pathauto->updateEntityAlias($translation, 'bulkupdate', ['language' => $lang_code]);
    }
  }
}

echo "\n── Aliases finales ──\n";
foreach ($alias_storage->loadMultiple() as $a) {
  if (preg_match('#^/node/\d+$#', $a->getPath())) {
    echo sprintf("  · [%s] %s → %s\n", $a->language()->getId(), $a->getPath(), $a->getAlias());
  }
}
echo "\n✅ Done.\n";
