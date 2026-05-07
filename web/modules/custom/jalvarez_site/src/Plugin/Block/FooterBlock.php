<?php

declare(strict_types=1);

namespace Drupal\jalvarez_site\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Renders the byte:footer SDC as the site-wide footer.
 *
 * Strings are dispatched per language inline (same pattern as
 * NavGlassBlock). Anything text-facing here lives in the LANG array — no
 * external i18n config is needed and the editor doesn't have to manage
 * footer copy through Canvas.
 *
 * Contact details (email, phone, WhatsApp link) come from the BRAND
 * constants — they're identical in both languages and rarely change, so
 * keeping them as PHP constants is simpler than wiring them through config.
 */
#[Block(
  id: 'jalvarez_footer',
  admin_label: new TranslatableMarkup('Byte site footer'),
  category: new TranslatableMarkup('Jalvarez'),
)]
class FooterBlock extends BlockBase {

  /**
   * Brand contact info (language-independent — values, not labels).
   *
   * If these ever change, update here AND in canal-directo.twig defaults.
   */
  private const BRAND = [
    'email' => 'contacto@jalvarez.tech',
    'phone' => '+57 312 801 4078',
    'phone_tel' => '+573128014078',
    'whatsapp' => 'https://wa.link/fb2acg',
  ];

  /**
   * Per-language footer copy.
   *
   * Path values are bare aliases — Drupal prepends the language prefix at
   * render time via the URL generator (see href construction in build()).
   */
  private const LANG = [
    'es' => [
      'pitch_a' => 'Construyo plataformas web que ',
      'pitch_em' => 'generan resultados medibles.',
      'availability' => 'Aceptando 2 proyectos en Q3 2026',
      'nav_label' => 'Navegación',
      'nav_items' => [
        ['label' => 'Inicio', 'path' => '/'],
        ['label' => 'Proyectos', 'path' => '/proyectos'],
        ['label' => 'Notas', 'path' => '/notas'],
        ['label' => 'Contacto', 'path' => '/contacto'],
      ],
      'contact_label' => 'Contacto',
      'whatsapp_label' => 'WhatsApp →',
      'elsewhere_label' => 'En otros lugares',
      'else_items' => [
        ['label' => 'LinkedIn ↗', 'href' => 'https://www.linkedin.com/in/jalvarez-tech/'],
        ['label' => 'GitHub ↗', 'href' => 'https://github.com/jalvarez-tech'],
      ],
      'copyright' => '© 2026 John Stevans Alvarez · Senior Web Specialist',
      'version' => 'v4.0 · Actualizado may 2026',
    ],
    'en' => [
      'pitch_a' => 'I build web platforms that ',
      'pitch_em' => 'drive measurable results.',
      'availability' => 'Accepting 2 projects for Q3 2026',
      'nav_label' => 'Navigation',
      'nav_items' => [
        ['label' => 'Home', 'path' => '/'],
        ['label' => 'Projects', 'path' => '/projects'],
        ['label' => 'Writing', 'path' => '/notes'],
        ['label' => 'Contact', 'path' => '/contact'],
      ],
      'contact_label' => 'Contact',
      'whatsapp_label' => 'WhatsApp →',
      'elsewhere_label' => 'Elsewhere',
      'else_items' => [
        ['label' => 'LinkedIn ↗', 'href' => 'https://www.linkedin.com/in/jalvarez-tech/'],
        ['label' => 'GitHub ↗', 'href' => 'https://github.com/jalvarez-tech'],
      ],
      'copyright' => '© 2026 John Stevans Alvarez · Senior Web Specialist',
      'version' => 'v4.0 · Updated May 2026',
    ],
  ];

  public function build(): array {
    $lang = \Drupal::languageManager()
      ->getCurrentLanguage(LanguageInterface::TYPE_INTERFACE)
      ->getId();
    $lang_data = self::LANG[$lang] ?? self::LANG['es'];

    $props = [
      // Col 1.
      'pitch_a' => $lang_data['pitch_a'],
      'pitch_em' => $lang_data['pitch_em'],
      'availability' => $lang_data['availability'],

      // Col 2 — site nav (per-language paths, language-prefixed at render)
      'nav_label' => $lang_data['nav_label'],

      // Col 3 — contact (labels translated, values from BRAND)
      'contact_label' => $lang_data['contact_label'],
      'contact_1_label' => self::BRAND['email'],
      'contact_1_href'  => 'mailto:' . self::BRAND['email'],
      'contact_2_label' => self::BRAND['phone'],
      'contact_2_href'  => 'tel:' . self::BRAND['phone_tel'],
      'contact_3_label' => $lang_data['whatsapp_label'],
      'contact_3_href'  => self::BRAND['whatsapp'],

      // Col 4 — elsewhere.
      'elsewhere_label' => $lang_data['elsewhere_label'],

      // Bottom.
      'copyright' => $lang_data['copyright'],
      'version'   => $lang_data['version'],
    ];

    // Map nav items into nav_1..nav_6 slots (twig filters empties out).
    foreach ($lang_data['nav_items'] as $i => $item) {
      $n = $i + 1;
      $href = '/' . $lang . ($item['path'] === '/' ? '' : $item['path']);
      $props["nav_{$n}_label"] = $item['label'];
      $props["nav_{$n}_href"]  = $href;
    }

    // Map elsewhere items into else_1..else_4 slots.
    foreach ($lang_data['else_items'] as $i => $item) {
      $n = $i + 1;
      $props["else_{$n}_label"] = $item['label'];
      $props["else_{$n}_href"]  = $item['href'];
    }

    return [
      '#type' => 'component',
      '#component' => 'byte:footer',
      '#props' => $props,
    ];
  }

  public function getCacheContexts(): array {
    return Cache::mergeContexts(parent::getCacheContexts(), ['languages:language_interface']);
  }

}
