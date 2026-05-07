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
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\path_alias\AliasManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Renders the byte:nav-glass SDC as the site-wide top navigation.
 *
 * Links come from the `main` menu (system.menu.main) so they can be edited
 * at /admin/structure/menu/manage/main without touching code. Each
 * menu_link_content entity is translatable: the Spanish title is the
 * default, English is added as a translation. Drupal's URL alter pipeline
 * resolves `entity:canvas_page/{id}` and `route:<front>` URIs to the
 * per-language alias automatically.
 */
#[Block(
  id: 'jalvarez_nav_glass',
  admin_label: new TranslatableMarkup('Byte top navigation'),
  category: new TranslatableMarkup('Jalvarez'),
)]
final class NavGlassBlock extends BlockBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly LanguageManagerInterface $languageManager,
    private readonly CurrentPathStack $currentPath,
    private readonly AliasManagerInterface $aliasManager,
    private readonly RouteMatchInterface $routeMatch,
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
      $container->get('path.current'),
      $container->get('path_alias.manager'),
      $container->get('current_route_match'),
      $container->get('menu.link_tree'),
      $container->get('entity.repository'),
    );
  }

  public function build(): array {
    $current_lang = $this->languageManager
      ->getCurrentLanguage(LanguageInterface::TYPE_INTERFACE)
      ->getId();

    $current_path = $this->currentPath->getPath();
    $current_alias = $this->aliasManager->getAliasByPath($current_path, $current_lang);
    $alias_no_lang = preg_replace('#^/(es|en)#', '', $current_alias) ?: '/';

    $links = [];
    foreach ($this->loadMainMenu($current_lang) as $entry) {
      $href = $entry['href'];
      $href_no_lang = preg_replace('#^/(es|en)#', '', $href) ?: '/';
      $active = ($href_no_lang === '/' && $alias_no_lang === '/')
        || ($href_no_lang !== '/' && str_starts_with($alias_no_lang, $href_no_lang));
      $links[] = [
        'label'  => $entry['label'],
        'href'   => $href,
        'active' => $active,
      ];
    }

    // CTA "Disponible" / "Available" goes straight to WhatsApp with a
    // pre-filled message keyed to the current language. Using wa.me (the
    // official WhatsApp link format) — opens the chat with the message
    // ready to send. URL-encoded once at compile time, no runtime cost.
    $cta_messages = [
      'es' => 'Hola John, vi que estás disponible en jalvarez.tech. ¿Podemos conversar?',
      'en' => "Hi John, I saw you're available on jalvarez.tech. Can we talk?",
    ];
    $cta_message = $cta_messages[$current_lang] ?? $cta_messages['es'];
    $cta_href = 'https://wa.me/573128014078?text=' . rawurlencode($cta_message);

    // Compute the equivalent URL in each language so the language toggle
    // preserves the current page (e.g. /es/proyectos ↔ /en/projects).
    [$lang_es_href, $lang_en_href] = $this->computeLanguageSwitcherHrefs($current_lang);

    return [
      '#type' => 'component',
      '#component' => 'byte:nav-glass',
      '#props' => [
        'brand_initial' => 'J',
        'brand_name'    => 'jalvarez',
        'brand_tld'     => '.tech',
        'links'         => $links,
        'cta_label'     => $current_lang === 'en' ? 'Available' : 'Disponible',
        'cta_href'      => $cta_href,
        'show_lang_toggle' => TRUE,
        'current_lang'  => $current_lang,
        'lang_es_href'  => $lang_es_href,
        'lang_en_href'  => $lang_en_href,
        // i18n strings injected from the block plugin so the SDC stays
        // language-agnostic. The nav twig used to carry a `{% set i18n
        // = lang == 'en' ? {…} : {…} %}` map — that violated the SDC
        // contract (component.yml schema can't represent it, the strings
        // weren't extractable to .po, and a third language would have
        // required editing the twig).
        'i18n_open_menu'   => (string) $this->t('Open menu', [], ['context' => 'jalvarez_nav']),
        'i18n_close_menu'  => (string) $this->t('Close menu', [], ['context' => 'jalvarez_nav']),
        'i18n_toggle_theme' => (string) $this->t('Toggle theme', [], ['context' => 'jalvarez_nav']),
        'i18n_language'    => (string) $this->t('Language', [], ['context' => 'jalvarez_nav']),
        'i18n_primary_nav' => (string) $this->t('Primary', [], ['context' => 'jalvarez_nav']),
      ],
    ];
  }

  /**
   * Returns the `main` menu top-level links for a given language.
   *
   * Each entry is `['label' => string, 'href' => string]`. The label comes
   * from the menu link's translation in $langcode (falls back to default).
   * The href is generated via Url::toString() with the language option
   * forced — that way `entity:canvas_page/5` resolves to `/es/proyectos`
   * in Spanish or `/en/projects` in English without us hardcoding a map.
   */
  private function loadMainMenu(string $langcode): array {
    $params = (new MenuTreeParameters())
      ->setMinDepth(1)
      ->setMaxDepth(1)
      ->onlyEnabledLinks();
    $tree = $this->menuTree->load('main', $params);
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

      // Resolve title in the requested language. Menu link plugins built
      // from menu_link_content entities expose getEntity(); for the
      // Drupal-native label fallback we just use the plugin title.
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
      $url->setOption('language', $language);
      $entries[] = [
        'label' => $label,
        'href'  => $url->toString(),
      ];
    }

    return $entries;
  }

  /**
   * Returns [es_href, en_href] for the current page.
   *
   * Uses Drupal core's native language-switcher API.
   * `LanguageManager::getLanguageSwitchLinks()` invokes
   * `hook_language_switch_links_alter()` and the URL alter pipeline, so:
   *  - canvas_page entities resolve to their per-language path alias
   *    (/es/inicio ↔ /en/home, /es/proyectos ↔ /en/projects, …)
   *  - Translatable nodes resolve to their per-language alias
   *    (/es/proyectos/malumaonline ↔ /en/projects/malumaonline)
   *  - Untranslatable routes (admin, 404, etc.) keep the same path with
   *    only the language prefix swapped.
   *
   * This is the same mechanism Drupal core's `language_block:language_interface`
   * uses internally — we just consume the result and feed two hrefs into the
   * SDC instead of rendering a separate block.
   */
  private function computeLanguageSwitcherHrefs(string $current_lang): array {
    $defaults = ['es' => '/es', 'en' => '/en'];
    try {
      $url = Url::fromRouteMatch($this->routeMatch);
      $links = $this->languageManager
        ->getLanguageSwitchLinks(LanguageInterface::TYPE_INTERFACE, $url);
      $hrefs = $defaults;
      if (!empty($links->links)) {
        foreach ($links->links as $langcode => $link) {
          if (isset($link['url']) && $link['url'] instanceof Url) {
            // Force the language option so the URL generator picks the
            // alias for that language even when the current request is in
            // the other one.
            $link_url = clone $link['url'];
            $link_url->setOption('language', $this->languageManager->getLanguage($langcode));
            $hrefs[$langcode] = $link_url->toString();
          }
        }
      }
      return [$hrefs['es'], $hrefs['en']];
    }
    catch (\Throwable) {
      return [$defaults['es'], $defaults['en']];
    }
  }

  public function getCacheContexts(): array {
    return Cache::mergeContexts(parent::getCacheContexts(), ['url.path', 'languages:language_interface']);
  }

  public function getCacheTags(): array {
    return Cache::mergeTags(parent::getCacheTags(), [
      'config:system.menu.main',
      'config:menu.menu.main',
      'menu_link_content_list',
    ]);
  }

}
