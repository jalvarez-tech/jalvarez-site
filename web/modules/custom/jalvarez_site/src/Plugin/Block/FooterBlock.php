<?php

declare(strict_types=1);

namespace Drupal\jalvarez_site\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Renders the byte:footer SDC as the site-wide footer.
 *
 * Link items live in 3 Drupal menus, one per column:
 *   - system.menu.footer          → Navegación / Navigation (4 links, ES+EN)
 *   - system.menu.footer_contact  → Contacto / Contact     (3 links)
 *   - system.menu.footer_social   → En otros lugares / Elsewhere (2 links)
 *
 * Column headings (nav_label, contact_label, elsewhere_label), pitch and
 * copy strings stay in the LANG constant — they're block-level chrome
 * around the menus, not menu items themselves.
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
    private readonly MenuLinkTreeInterface $menuTree,
    private readonly EntityRepositoryInterface $entityRepository,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('language_manager'),
      $container->get('menu.link_tree'),
      $container->get('entity.repository'),
    );
  }

  /**
   * Per-language column headings + pitch + copyright.
   *
   * Items themselves come from menus; this constant only carries the
   * surrounding chrome (column titles, pitch, availability, footer).
   */
  private const LANG = [
    'es' => [
      'pitch_a' => 'Construyo plataformas web que ',
      'pitch_em' => 'generan resultados medibles.',
      'availability' => 'Aceptando 2 proyectos en Q3 2026',
      'nav_label' => 'Navegación',
      'contact_label' => 'Contacto',
      'elsewhere_label' => 'En otros lugares',
      'copyright' => '© 2026 John Stevans Alvarez · Senior Web Specialist',
      'version' => 'v4.0 · Actualizado may 2026',
    ],
    'en' => [
      'pitch_a' => 'I build web platforms that ',
      'pitch_em' => 'drive measurable results.',
      'availability' => 'Accepting 2 projects for Q3 2026',
      'nav_label' => 'Navigation',
      'contact_label' => 'Contact',
      'elsewhere_label' => 'Elsewhere',
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

      // Column headings.
      'nav_label' => $lang_data['nav_label'],
      'contact_label' => $lang_data['contact_label'],
      'elsewhere_label' => $lang_data['elsewhere_label'],

      // Bottom.
      'copyright' => $lang_data['copyright'],
      'version'   => $lang_data['version'],
    ];

    // Map each menu's items into the SDC's flattened slots. The SDC
    // exposes nav_1..nav_6, contact_1..contact_4, else_1..else_4 so we
    // cap each column at its slot count — extra menu items are dropped
    // silently rather than overflowing into another column.
    $this->mapLinks($props, $this->loadMenu('footer', $lang), 'nav_', 6);
    $this->mapLinks($props, $this->loadMenu('footer_contact', $lang), 'contact_', 4);
    $this->mapLinks($props, $this->loadMenu('footer_social', $lang), 'else_', 4);

    return [
      '#type' => 'component',
      '#component' => 'byte:footer',
      '#props' => $props,
    ];
  }

  /**
   * Loads enabled top-level links of a menu in the requested language.
   *
   * Returns `[['label' => string, 'href' => string], …]` ordered by the
   * menu tree's natural sort. Labels resolve through the menu_link_content
   * entity's translation when present (falls back to default langcode).
   */
  private function loadMenu(string $menu_name, string $langcode): array {
    $params = (new MenuTreeParameters())
      ->setMinDepth(1)
      ->setMaxDepth(1)
      ->onlyEnabledLinks();
    $tree = $this->menuTree->load($menu_name, $params);
    $tree = $this->menuTree->transform($tree, [
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ]);

    $language = $this->languageManager->getLanguage($langcode);
    $entries = [];
    foreach ($tree as $element) {
      if (!$element->link->isEnabled()) {
        continue;
      }
      $label = (string) $element->link->getTitle();
      $metadata = $element->link->getMetaData();
      if (!empty($metadata['entity_id'])) {
        $entity = $this->entityRepository->getActive('menu_link_content', $metadata['entity_id']);
        if ($entity !== NULL) {
          $translated = $this->entityRepository->getTranslationFromContext($entity, $langcode);
          $label = (string) $translated->label();
        }
      }
      $url = $element->link->getUrlObject();
      // External URLs (mailto:, tel:, https://) ignore the language
      // option; internal/route URIs use it to pick the alias for the
      // requested language.
      if (!$url->isExternal()) {
        $url->setOption('language', $language);
      }
      $entries[] = [
        'label' => $label,
        'href'  => $url->toString(),
      ];
    }
    return $entries;
  }

  /**
   * Writes link entries into flattened `{prefix}{n}_label/_href` props.
   */
  private function mapLinks(array &$props, array $entries, string $prefix, int $max): void {
    foreach (array_values($entries) as $i => $entry) {
      $n = $i + 1;
      if ($n > $max) {
        break;
      }
      $props["{$prefix}{$n}_label"] = $entry['label'];
      $props["{$prefix}{$n}_href"]  = $entry['href'];
    }
  }

  public function getCacheContexts(): array {
    return Cache::mergeContexts(parent::getCacheContexts(), ['languages:language_interface']);
  }

  public function getCacheTags(): array {
    return Cache::mergeTags(parent::getCacheTags(), [
      'config:system.menu.footer',
      'config:system.menu.footer_contact',
      'config:system.menu.footer_social',
      'menu_link_content_list',
    ]);
  }

}
