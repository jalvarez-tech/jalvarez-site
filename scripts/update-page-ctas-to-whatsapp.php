<?php
/**
 * @file
 * Point every "Iniciar tu proyecto" / "Start your project" CTA on the
 * canvas_page entities to a WhatsApp deep link, with a message that
 * names the page the visitor was just reading. Lead arrives with
 * context (which page, which language) instead of a generic ping.
 *
 * Targets:
 *   • Home (id=8)        — banner-inicio CTA + cta-final
 *   • Proyectos (id=5)   — cta-final
 *   • Notas (id=6)       — cta-final
 *
 * Each canvas_page is matched by its title (per language) so the script
 * is portable — it does not hardcode pids that may differ across envs.
 *
 * Idempotent end-to-end: re-runs leave the same final state.
 */

const PHONE = '573128014078';

/**
 * Per-page WhatsApp message library — keyed by canvas_page title slug
 * + active language. The slug is derived from the ES title since both
 * translations share the same canvas_page id.
 */
const MESSAGES = [
  // Home — generic project conversation.
  'inicio (canvas)' => [
    'es' => 'Hola John, vi tu sitio jalvarez.tech y me gustaría agendar una llamada para conversar sobre un proyecto.',
    'en' => 'Hi John, I saw your site jalvarez.tech and I\'d like to schedule a call to talk about a project.',
  ],
  // Project list — they came from seeing the work.
  'proyectos' => [
    'es' => 'Hola John, vi los proyectos en jalvarez.tech y me gustaría conversar sobre el mío.',
    'en' => 'Hi John, I saw the projects on jalvarez.tech and I\'d like to talk about mine.',
  ],
  // Notes/blog — they came from reading the writing.
  'notas' => [
    'es' => 'Hola John, leí tus notas en jalvarez.tech y me gustaría conversar.',
    'en' => 'Hi John, I read your notes on jalvarez.tech and I\'d like to talk.',
  ],
];

function wa_url(string $message): string {
  return 'https://wa.me/' . PHONE . '?text=' . rawurlencode($message);
}

function find_message_for(string $title): ?array {
  $slug = strtolower(trim($title));
  // Map ES titles → message bucket. EN titles fall through via id.
  $aliases = [
    'inicio (canvas)'  => 'inicio (canvas)',
    'home (canvas)'    => 'inicio (canvas)',
    'proyectos'        => 'proyectos',
    'projects'         => 'proyectos',
    'notas'            => 'notas',
    'notes'            => 'notas',
  ];
  $bucket = $aliases[$slug] ?? null;
  return $bucket !== null ? MESSAGES[$bucket] : null;
}

$storage = \Drupal::entityTypeManager()->getStorage('canvas_page');
$ids     = \Drupal::entityQuery('canvas_page')->accessCheck(FALSE)->execute();

foreach ($storage->loadMultiple($ids) as $page) {
  $bucket = null;
  foreach ($page->getTranslationLanguages() as $lc => $_) {
    $bucket ??= find_message_for($page->getTranslation($lc)->label());
  }
  if (!$bucket) {
    echo "= page id={$page->id()} skipped (no message bucket)\n";
    continue;
  }

  $changed_any = false;
  foreach ($page->getTranslationLanguages() as $lc => $_) {
    if (!isset($bucket[$lc])) continue;
    $t        = $page->getTranslation($lc);
    $msg      = $bucket[$lc];
    $new_href = wa_url($msg);
    $components = $t->get('components')->getValue();

    foreach ($components as $i => &$comp) {
      $cid = $comp['component_id'] ?? '';
      if (!str_contains($cid, 'cta-final') && !str_contains($cid, 'banner-inicio')) {
        continue;
      }
      $inputs = json_decode($comp['inputs'] ?? '{}', true);
      if (!is_array($inputs)) continue;

      // cta-final uses primary_href; banner-inicio uses cta_primary_href.
      $key = isset($inputs['primary_href'])
        ? 'primary_href'
        : (isset($inputs['cta_primary_href']) ? 'cta_primary_href' : null);
      if ($key === null) continue;

      if ($inputs[$key] === $new_href) continue;
      $inputs[$key] = $new_href;
      $comp['inputs'] = json_encode($inputs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
      $changed_any = true;
      echo "  → page id={$page->id()} ({$lc}) delta={$i} {$cid} {$key} updated\n";
    }
    unset($comp);

    if ($changed_any) {
      $t->set('components', $components);
      $t->setNewRevision(false);
      $t->save();
    }
  }

  if (!$changed_any) {
    echo "= page id={$page->id()} '{$page->label()}' already up to date\n";
  }
}

drupal_flush_all_caches();
echo "\n✓ Page CTAs aligned to per-page WhatsApp deep links.\n";
