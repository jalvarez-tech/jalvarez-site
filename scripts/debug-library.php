<?php
$lib = \Drupal::service('library.discovery')->getLibraryByName('byte', 'global');
echo "byte/global library:\n";
print_r($lib);
echo "\nAttached to active theme? Default theme: " . \Drupal::config('system.theme')->get('default') . "\n";
