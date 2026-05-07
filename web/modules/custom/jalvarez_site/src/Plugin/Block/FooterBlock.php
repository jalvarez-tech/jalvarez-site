<?php

declare(strict_types=1);

namespace Drupal\jalvarez_site\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\jalvarez_site\BrandConfig;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
final class FooterBlock extends BlockBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly LanguageManagerInterface $languageManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('language_manager'),
    );
  }

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
    $lang = $this->languageManager
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
      'contact_1_label' => BrandConfig::EMAIL,
      'contact_1_href'  => 'mailto:' . BrandConfig::EMAIL,
      'contact_2_label' => BrandConfig::PHONE,
      'contact_2_href'  => 'tel:' . BrandConfig::PHONE_TEL,
      'contact_3_label' => $lang_data['whatsapp_label'],
      'contact_3_href'  => BrandConfig::WHATSAPP_LINK,

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
