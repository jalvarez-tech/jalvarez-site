<?php
/**
 * @file
 * Three batched updates to align high-intent CTAs and the contact page:
 *
 *  1. cta-final primary_href on Inicio + Proyectos canvas_pages → wa.me
 *     deep-link with a language-aware "start a project" message. Same
 *     pattern as scripts/update-hero-cta-whatsapp.php for the hero CTA.
 *
 *  2. canal-directo on Contacto canvas_page (ES + EN): the WhatsApp
 *     channel (c2_*) renders the formatted phone number "(+57) 312 801
 *     40 78" instead of the bare wa.link short URL.
 *
 *  3. Contact webform's email_notification handler: pin to_mail to
 *     stevanswd@gmail.com instead of relying on _default (which uses
 *     system.site.mail and could drift if config:import touches it).
 *
 * Idempotent. Safe to re-run on every environment.
 */

use Drupal\canvas\Entity\Page;
use Drupal\webform\Entity\Webform;

// ─── 1. cta-final primary_href ────────────────────────────────────────────
$cta_messages = [
  'es' => 'Hola John, vi tu sitio jalvarez.tech y quiero iniciar un proyecto contigo. ¿Cuándo podemos hablar?',
  'en' => "Hi John, I saw your site jalvarez.tech and I want to start a project with you. When can we talk?",
];

$alias_manager = \Drupal::service('path_alias.manager');

foreach (['/inicio', '/proyectos'] as $alias) {
  $path = $alias_manager->getPathByAlias($alias);
  if (!preg_match('#^/page/(\d+)$#', $path, $m)) continue;
  $page = Page::load((int) $m[1]);
  foreach ($page->getTranslationLanguages() as $langcode => $_) {
    $msg = $cta_messages[$langcode] ?? $cta_messages['es'];
    $wa = 'https://wa.me/573128014078?text=' . rawurlencode($msg);
    $trans = $page->getTranslation($langcode);
    $tree = $trans->get('components')->getValue();
    $touched = FALSE;
    foreach ($tree as $i => $row) {
      if ($row['component_id'] !== 'sdc.byte.cta-final') continue;
      $inputs = json_decode($row['inputs'], TRUE) ?? [];
      if (is_array($inputs['primary_href'] ?? NULL) && array_key_exists('value', $inputs['primary_href'])) {
        $inputs['primary_href']['value'] = $wa;
      }
      else {
        $inputs['primary_href'] = $wa;
      }
      $tree[$i]['inputs'] = json_encode($inputs, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
      $touched = TRUE;
      break;
    }
    if ($touched) {
      $trans->set('components', $tree);
      $trans->save();
      echo "✓ {$alias} ({$langcode}): cta-final primary_href → wa.me with start-project message\n";
    }
  }
}

// ─── 2. canal-directo c2 on Contacto ──────────────────────────────────────
$contact_path = $alias_manager->getPathByAlias('/contacto');
if (preg_match('#^/page/(\d+)$#', $contact_path, $m)) {
  $contact_page = Page::load((int) $m[1]);
  $phone_display = '(+57) 312 801 40 78';
  $phone_wa = 'https://wa.me/573128014078';
  foreach ($contact_page->getTranslationLanguages() as $langcode => $_) {
    $trans = $contact_page->getTranslation($langcode);
    $tree = $trans->get('components')->getValue();
    $touched = FALSE;
    foreach ($tree as $i => $row) {
      if ($row['component_id'] !== 'sdc.byte.canal-directo') continue;
      $inputs = json_decode($row['inputs'], TRUE) ?? [];
      $set = function (string $key, $value) use (&$inputs) {
        if (is_array($inputs[$key] ?? NULL) && array_key_exists('value', $inputs[$key])) {
          $inputs[$key]['value'] = $value;
        }
        else {
          $inputs[$key] = $value;
        }
      };
      // c2 = WhatsApp slot. Replace the bare wa.link short URL with the
      // formatted phone number as visible text; href stays an actual
      // wa.me deep-link.
      $set('c2_name', 'WhatsApp');
      $set('c2_value', $phone_display);
      $set('c2_href',  $phone_wa);
      $tree[$i]['inputs'] = json_encode($inputs, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
      $touched = TRUE;
      break;
    }
    if ($touched) {
      $trans->set('components', $tree);
      $trans->save();
      echo "✓ /contacto ({$langcode}): canal-directo c2 → '{$phone_display}' → {$phone_wa}\n";
    }
  }
}

// ─── 3. Webform contact handler email_notification ────────────────────────
$canonical_inbox = 'stevanswd@gmail.com';
$webform = Webform::load('contact');
if ($webform) {
  $handlers = $webform->getHandlers();
  if ($handlers->has('email_notification')) {
    $handler = $handlers->get('email_notification');
    $config = $handler->getConfiguration();
    $current_to = $config['settings']['to_mail'] ?? null;
    if ($current_to !== $canonical_inbox) {
      $config['settings']['to_mail'] = $canonical_inbox;
      $handler->setConfiguration($config);
      $webform->save();
      echo "✓ webform.contact email_notification.to_mail: '{$current_to}' → '{$canonical_inbox}'\n";
    }
    else {
      echo "= webform.contact email_notification.to_mail already '{$canonical_inbox}'\n";
    }
  }
  else {
    echo "⚠ webform.contact has no 'email_notification' handler; skipping.\n";
  }
}
else {
  echo "⚠ webform 'contact' not found; skipping handler update.\n";
}

drupal_flush_all_caches();
echo "\n✓ All updates applied. Verify on /es, /en, /es/contacto, and submit a test form.\n";
