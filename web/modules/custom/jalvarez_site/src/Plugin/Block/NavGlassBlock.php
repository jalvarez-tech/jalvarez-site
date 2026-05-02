<?php

declare(strict_types=1);

namespace Drupal\jalvarez_site\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;

/**
 * Renders the byte:nav-glass SDC as the site-wide top navigation.
 */
#[Block(
  id: 'jalvarez_nav_glass',
  admin_label: new \Drupal\Core\StringTranslation\TranslatableMarkup('Byte top navigation'),
  category: new \Drupal\Core\StringTranslation\TranslatableMarkup('Jalvarez'),
)]
class NavGlassBlock extends BlockBase {

  use StringTranslationTrait;

  public function build(): array {
    $current_lang = \Drupal::languageManager()
      ->getCurrentLanguage(LanguageInterface::TYPE_INTERFACE)
      ->getId();

    $current_path = \Drupal::service('path.current')->getPath();
    $current_alias = \Drupal::service('path_alias.manager')->getAliasByPath($current_path, $current_lang);

    // Strip language prefix to compare with link href.
    $alias_no_lang = preg_replace('#^/(es|en)#', '', $current_alias) ?: '/';

    $links_def = [
      ['label' => $current_lang === 'en' ? 'home'      : 'inicio',    'path' => '/'],
      ['label' => $current_lang === 'en' ? 'work'      : 'proyectos', 'path' => '/proyectos'],
      ['label' => $current_lang === 'en' ? 'writing'   : 'notas',     'path' => '/notas'],
      ['label' => $current_lang === 'en' ? 'contact'   : 'contacto',  'path' => '/contacto'],
    ];

    $links = [];
    foreach ($links_def as $l) {
      $href = '/' . $current_lang . ($l['path'] === '/' ? '' : $l['path']);
      $active = ($l['path'] === '/' && $alias_no_lang === '/')
        || ($l['path'] !== '/' && str_starts_with($alias_no_lang, $l['path']));
      $links[] = ['label' => $l['label'], 'href' => $href, 'active' => $active];
    }

    return [
      '#type' => 'component',
      '#component' => 'byte:nav-glass',
      '#props' => [
        'brand_initial' => 'J',
        'brand_name'    => 'jalvarez',
        'brand_tld'     => '.tech',
        'links'         => $links,
        'cta_label'     => $current_lang === 'en' ? 'Available' : 'Disponible',
        'cta_href'      => '/' . $current_lang . '/contacto',
        'show_lang_toggle' => TRUE,
        'current_lang'  => $current_lang,
      ],
    ];
  }

  public function getCacheContexts(): array {
    return Cache::mergeContexts(parent::getCacheContexts(), ['url.path', 'languages:language_interface']);
  }

  public function getCacheTags(): array {
    return Cache::mergeTags(parent::getCacheTags(), ['config:system.menu.main']);
  }

}
