<?php

declare(strict_types=1);

namespace Drupal\jalvarez_site\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Renders the byte:nav-glass SDC as the site-wide top navigation.
 *
 * Each link has a per-language path so URLs match the canvas_page aliases
 * configured for each translation:
 *   ES: /es, /es/proyectos, /es/notas, /es/contacto
 *   EN: /en, /en/projects,  /en/notes, /en/contact
 */
#[Block(
  id: 'jalvarez_nav_glass',
  admin_label: new \Drupal\Core\StringTranslation\TranslatableMarkup('Byte top navigation'),
  category: new \Drupal\Core\StringTranslation\TranslatableMarkup('Jalvarez'),
)]
class NavGlassBlock extends BlockBase {

  use StringTranslationTrait;

  /**
   * Per-language nav definition. Path is the alias WITHOUT the lang prefix.
   * Drupal prepends `/{lang}/` automatically when the URL is rendered.
   */
  private const LINKS = [
    'es' => [
      ['label' => 'inicio',    'path' => '/'],
      ['label' => 'proyectos', 'path' => '/proyectos'],
      ['label' => 'notas',     'path' => '/notas'],
      ['label' => 'contacto',  'path' => '/contacto'],
    ],
    'en' => [
      ['label' => 'home',     'path' => '/'],
      ['label' => 'work',     'path' => '/projects'],
      ['label' => 'writing',  'path' => '/notes'],
      ['label' => 'contact',  'path' => '/contact'],
    ],
  ];

  public function build(): array {
    $current_lang = \Drupal::languageManager()
      ->getCurrentLanguage(LanguageInterface::TYPE_INTERFACE)
      ->getId();

    $current_path = \Drupal::service('path.current')->getPath();
    $current_alias = \Drupal::service('path_alias.manager')->getAliasByPath($current_path, $current_lang);

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

    // Contact link path matches the language (ES: /contacto · EN: /contact).
    $contact_path = $current_lang === 'en' ? '/contact' : '/contacto';

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
        'cta_href'      => '/' . $current_lang . $contact_path,
        'show_lang_toggle' => TRUE,
        'current_lang'  => $current_lang,
        'lang_es_href'  => $lang_es_href,
        'lang_en_href'  => $lang_en_href,
      ],
    ];
  }

  /**
   * Returns [es_href, en_href] for the current page. If the entity for the
   * current route has a translation, point the toggle at the translated URL.
   * Otherwise fall back to the language landing page.
   */
  private function computeLanguageSwitcherHrefs(string $current_lang): array {
    $es = '/es';
    $en = '/en';
    try {
      $route_match = \Drupal::routeMatch();
      // Iterate route parameters looking for a content entity.
      foreach ($route_match->getParameters() as $param) {
        if ($param instanceof \Drupal\Core\Entity\ContentEntityInterface) {
          $langs = array_keys($param->getTranslationLanguages());
          if (in_array('es', $langs, TRUE)) {
            $es = $param->getTranslation('es')->toUrl()->toString();
          }
          if (in_array('en', $langs, TRUE)) {
            $en = $param->getTranslation('en')->toUrl()->toString();
          }
          break;
        }
      }
    }
    catch (\Throwable) {
      // Keep defaults.
    }
    return [$es, $en];
  }

  public function getCacheContexts(): array {
    return Cache::mergeContexts(parent::getCacheContexts(), ['url.path', 'languages:language_interface']);
  }

  public function getCacheTags(): array {
    return Cache::mergeTags(parent::getCacheTags(), ['config:system.menu.main']);
  }

}
