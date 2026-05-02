<?php
/**
 * @file
 * Walk every byte SDC component.yml and add a `title` to every property in
 * `props.properties` that lacks one. Canvas requires `title` on every prop.
 *
 * The title is derived from the prop key:
 *   "title_em"        -> "Title em"
 *   "cta_primary_url" -> "Cta primary url"
 *   "show_portrait"   -> "Show portrait"
 *
 * Idempotent: skips properties that already have a title.
 *
 * Run via:
 *   ddev exec ./web/vendor/bin/drush php:script scripts/add-sdc-prop-titles.php
 */

use Symfony\Component\Yaml\Yaml;

$root = '/var/www/html/web/themes/custom/byte/components';
$dirs = glob("{$root}/*", GLOB_ONLYDIR);

function titleize(string $key): string {
  return ucfirst(strtolower(str_replace('_', ' ', $key)));
}

$total_fixed = 0;
$total_files_modified = 0;

foreach ($dirs as $dir) {
  $name = basename($dir);
  $yaml_path = "{$dir}/{$name}.component.yml";
  if (!file_exists($yaml_path)) {
    continue;
  }

  // Preserve original comments/structure by parsing then re-dumping the
  // *props.properties* sub-tree only, then reading the file as text and
  // doing a careful in-place rewrite. Simpler: parse-modify-dump full file.
  $original = file_get_contents($yaml_path);
  $data = Yaml::parse($original);

  if (!isset($data['props']['properties']) || !is_array($data['props']['properties'])) {
    echo "  · {$name} — no props.properties, skipping\n";
    continue;
  }

  $modified = FALSE;
  foreach ($data['props']['properties'] as $prop_key => $prop_def) {
    if (!is_array($prop_def)) {
      continue;
    }
    if (!isset($prop_def['title']) || trim((string) $prop_def['title']) === '') {
      $data['props']['properties'][$prop_key]['title'] = titleize($prop_key);
      // Reorder so `title` appears right after `type` for readability.
      $reordered = [];
      $type = $prop_def['type'] ?? null;
      if ($type !== null) {
        $reordered['type'] = $type;
      }
      $reordered['title'] = titleize($prop_key);
      foreach ($prop_def as $k => $v) {
        if ($k === 'type' || $k === 'title') continue;
        $reordered[$k] = $v;
      }
      $data['props']['properties'][$prop_key] = $reordered;
      $modified = TRUE;
      $total_fixed++;
    }
  }

  if ($modified) {
    // Dump with reasonable inline level so simple types stay on one line.
    $new_yaml = Yaml::dump($data, 6, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
    file_put_contents($yaml_path, $new_yaml);
    echo "  ✓ {$name} — patched\n";
    $total_files_modified++;
  } else {
    echo "  = {$name} — already complete\n";
  }
}

echo "\n─────\nFiles modified: {$total_files_modified}\nProps with title added: {$total_fixed}\n";
