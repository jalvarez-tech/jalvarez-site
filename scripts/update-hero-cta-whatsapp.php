<?php
/**
 * @file
 * Point the home page hero's primary CTA ("Agenda una llamada" / "Schedule
 * a call") to WhatsApp with a language-aware pre-filled message instead of
 * the contact form.
 *
 * Pattern: https://wa.me/<phone>?text=<urlencoded message> — opens the
 * user's WhatsApp chat with the message ready to send.
 *
 * Idempotent. The secondary CTA (/proyectos · /projects) is left alone.
 *
 * Run via:
 *   ddev exec ./web/vendor/bin/drush php:script scripts/update-hero-cta-whatsapp.php
 *   gh workflow run seed-content.yml --field script=scripts/update-hero-cta-whatsapp.php
 */

use Drupal\canvas\Entity\Page;

$phone = '573128014078';
$messages = [
  'es' => 'Hola John, vi tu sitio jalvarez.tech y me gustaría agendar una llamada para conversar sobre un proyecto.',
  'en' => "Hi John, I saw your site jalvarez.tech and I'd like to schedule a call to talk about a project.",
];

// Resolve the home canvas_page by ES alias (portable across envs).
$home_path = \Drupal::service('path_alias.manager')->getPathByAlias('/inicio');
if (!preg_match('#^/page/(\d+)$#', $home_path, $m)) {
  fwrite(STDERR, "✗ Could not resolve '/inicio' to a canvas_page.\n");
  exit(1);
}
$page = Page::load((int) $m[1]);
echo "→ Target: canvas_page id={$page->id()} title='{$page->label()}'\n";

// Apply to every translation.
foreach ($page->getTranslationLanguages() as $langcode => $_) {
  $message = $messages[$langcode] ?? $messages['es'];
  $wa_url = 'https://wa.me/' . $phone . '?text=' . rawurlencode($message);

  $trans = $page->getTranslation($langcode);
  $tree = $trans->get('components')->getValue();
  $touched = FALSE;
  foreach ($tree as $i => $row) {
    if ($row['component_id'] !== 'sdc.byte.banner-inicio') continue;
    $inputs = json_decode($row['inputs'], TRUE) ?? [];
    // Inputs may be flat (visual editor) or wrapped {value, sourceType, expression}.
    $old = isset($inputs['cta_primary_href'])
      ? (is_array($inputs['cta_primary_href']) ? ($inputs['cta_primary_href']['value'] ?? '<wrapped>') : $inputs['cta_primary_href'])
      : '<unset>';
    if (is_array($inputs['cta_primary_href'] ?? NULL) && array_key_exists('value', $inputs['cta_primary_href'])) {
      $inputs['cta_primary_href']['value'] = $wa_url;
    }
    else {
      $inputs['cta_primary_href'] = $wa_url;
    }
    $tree[$i]['inputs'] = json_encode($inputs, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $touched = TRUE;
    echo "  · {$langcode} banner-inicio cta_primary_href:\n";
    echo "      was: " . substr($old, 0, 80) . (strlen($old) > 80 ? '…' : '') . "\n";
    echo "      now: " . substr($wa_url, 0, 80) . (strlen($wa_url) > 80 ? '…' : '') . "\n";
    break;
  }
  if ($touched) {
    $trans->set('components', $tree);
    $trans->save();
  }
  else {
    echo "  ⚠ {$langcode}: no banner-inicio component found in tree\n";
  }
}

drupal_flush_all_caches();
echo "\n✓ Hero primary CTA wired to WhatsApp on both translations.\n";
