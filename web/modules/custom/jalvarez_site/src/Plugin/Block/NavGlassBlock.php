<?php

declare(strict_types=1);

namespace Drupal\jalvarez_site\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
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
 * Each link has a per-language path so URLs match the canvas_page aliases
 * configured for each translation:
 *   ES: /es, /es/proyectos, /es/notas, /es/contacto
 *   EN: /en, /en/projects,  /en/notes, /en/contact.
 */
#[Block(
  id: 'jalvarez_nav_glass',
  admin_label: new TranslatableMarkup('Byte top navigation'),
  category: new TranslatableMarkup('Jalvarez'),
)]
class NavGlassBlock extends BlockBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly LanguageManagerInterface $languageManager,
    private readonly CurrentPathStack $currentPath,
    private readonly AliasManagerInterface $aliasManager,
    private readonly RouteMatchInterface $routeMatch,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('language_manager'),
      $container->get('path.current'),
      $container->get('path_alias.manager'),
      $container->get('current_route_match'),
    );
  }

  /**
   * Per-language nav definition.
   *
   * Path is the alias WITHOUT the lang prefix. Drupal prepends `/{lang}/`
   * automatically when the URL is rendered.
   */
  private const LINKS = [
    'es' => [
      ['label' => 'inicio', 'path' => '/'],
      ['label' => 'proyectos', 'path' => '/proyectos'],
      ['label' => 'notas', 'path' => '/notas'],
      ['label' => 'contacto', 'path' => '/contacto'],
    ],
    'en' => [
      ['label' => 'home', 'path' => '/'],
      ['label' => 'work', 'path' => '/projects'],
      ['label' => 'writing', 'path' => '/notes'],
      ['label' => 'contact', 'path' => '/contact'],
    ],
  ];

  public function build(): array {
    $current_lang = $this->languageManager
      ->getCurrentLanguage(LanguageInterface::TYPE_INTERFACE)
      ->getId();

    $current_path = $this->currentPath->getPath();
    $current_alias = $this->aliasManager->getAliasByPath($current_path, $current_lang);

    // Strip language prefix to compare with link path.
    $alias_no_lang = preg_replace('#^/(es|en)#', '', $current_alias) ?: '/';

    $links_def = self::LINKS[$current_lang] ?? self::LINKS['es'];

    $links = [];
    foreach ($links_def as $l) {
      $href = '/' . $current_lang . ($l['path'] === '/' ? '' : $l['path']);
      $active = ($l['path'] === '/' && $alias_no_lang === '/')
        || ($l['path'] !== '/' && str_starts_with($alias_no_lang, $l['path']));
      $links[] = ['label' => $l['label'], 'href' => $href, 'active' => $active];
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
      ],
    ];
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
    return Cache::mergeTags(parent::getCacheTags(), ['config:system.menu.main']);
  }

}
