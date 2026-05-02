<?php
/**
 * @file
 * Diagnostic: list canvas_page entities + check admin permissions.
 */

use Drupal\canvas\Entity\Page;
use Drupal\user\Entity\User;
use Drupal\user\Entity\Role;

echo "── canvas_page entities en la DB ──\n";
$pages = Page::loadMultiple();
if (!$pages) {
  echo "  (ninguna)\n";
}
foreach ($pages as $p) {
  echo "  · id={$p->id()} title='" . $p->label() . "' status=" . ($p->isPublished() ? 'pub' : 'draft') . "\n";
}

echo "\n── Permisos de Canvas en role 'administrator' ──\n";
$role = Role::load('administrator');
if ($role) {
  $perms = $role->getPermissions();
  $canvas_perms = array_filter($perms, fn($p) => str_contains(strtolower($p), 'canvas') || str_contains(strtolower($p), 'auto-save') || str_contains(strtolower($p), 'component'));
  if ($role->isAdmin()) {
    echo "  (administrator role tiene is_admin=TRUE → todos los permisos automáticamente)\n";
  } else {
    foreach ($canvas_perms as $p) echo "  · {$p}\n";
  }
}

echo "\n── ¿Admin user (uid=1) tiene permiso 'edit canvas_page'? ──\n";
$admin = User::load(1);
echo "  · " . ($admin->hasPermission('edit canvas_page') ? 'SÍ' : 'NO') . "\n";
echo "\n── ¿Admin tiene 'create canvas_page'? ──\n";
echo "  · " . ($admin->hasPermission('create canvas_page') ? 'SÍ' : 'NO') . "\n";
