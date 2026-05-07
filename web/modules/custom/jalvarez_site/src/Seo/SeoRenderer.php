<?php

declare(strict_types=1);

namespace Drupal\jalvarez_site\Seo;

use Drupal\Core\Entity\TranslatableInterface;
use Drupal\jalvarez_site\BrandConfig;
use Drupal\node\NodeInterface;

/**
 * Builds the head attachments (meta, hreflang, JSON-LD) for SEO-eligible routes.
 *
 * Pure transformer: receives a payload from SeoResolver and writes into the
 * `#attached` array passed by reference. No Drupal global state.
 */
class SeoRenderer {

  private const LOCALE_MAP = ['es' => 'es_CO', 'en' => 'en_US'];

  public function __construct(
    private readonly SeoResolver $resolver,
  ) {}

  /**
   * Resolves the current route and writes meta + hreflang + JSON-LD if any.
   */
  public function attachAll(array &$attachments, string $route_name): void {
    $info = $this->resolver->resolveCurrent($route_name);
    if ($info === NULL) {
      return;
    }
    $base = $this->resolver->getBaseUrl();

    $this->attachMetaTags($attachments, $info);
    $this->attachHreflang($attachments, $info['entity']);
    $this->attachJsonLd($attachments, $info, $base);
  }

  private function attachMetaTags(array &$attachments, array $info): void {
    $entity = $info['entity'];
    $langcode = $info['langcode'];

    $og_locale = self::LOCALE_MAP[$langcode] ?? 'es_CO';
    $og_alt = $langcode === 'es' ? 'en_US' : 'es_CO';

    $og_type = match ($info['type']) {
      'note', 'project' => 'article',
      default => 'website',
    };

    $tags = [
      ['name' => 'author', 'content' => 'John Stevans Alvarez'],
      ['name' => 'theme-color', 'content' => '#0f0f0f'],
      ['property' => 'og:type', 'content' => $og_type],
      ['property' => 'og:title', 'content' => $info['title']],
      ['property' => 'og:description', 'content' => $info['description']],
      ['property' => 'og:url', 'content' => $info['url']],
      ['property' => 'og:site_name', 'content' => 'jalvarez.tech'],
      ['property' => 'og:locale', 'content' => $og_locale],
      ['property' => 'og:locale:alternate', 'content' => $og_alt],
      ['name' => 'twitter:card', 'content' => $info['image'] ? 'summary_large_image' : 'summary'],
      ['name' => 'twitter:title', 'content' => $info['title']],
      ['name' => 'twitter:description', 'content' => $info['description']],
      ['name' => 'twitter:creator', 'content' => '@jalvareztech'],
    ];
    if ($info['image']) {
      $tags[] = ['property' => 'og:image', 'content' => $info['image']];
      $tags[] = ['property' => 'og:image:alt', 'content' => $info['title']];
      $tags[] = ['name' => 'twitter:image', 'content' => $info['image']];
    }

    if ($og_type === 'article' && $entity instanceof NodeInterface) {
      $tags[] = ['property' => 'article:author', 'content' => 'John Stevans Alvarez'];
      if ($entity->bundle() === 'note' && $entity->hasField('field_publish_date') && !$entity->get('field_publish_date')->isEmpty()) {
        $published = (string) $entity->get('field_publish_date')->value;
        if ($published !== '') {
          $tags[] = ['property' => 'article:published_time', 'content' => $published];
        }
      }
      $tags[] = ['property' => 'article:modified_time', 'content' => date('c', (int) $entity->getChangedTime())];
    }

    $i = 0;
    foreach ($tags as $tag) {
      if (empty($tag['content'])) {
        continue;
      }
      $attachments['#attached']['html_head'][] = [
        ['#tag' => 'meta', '#attributes' => $tag],
        'jalvarez_seo_' . $i++,
      ];
    }
  }

  private function attachHreflang(array &$attachments, $entity): void {
    if (!$entity instanceof TranslatableInterface) {
      return;
    }
    foreach ($entity->getTranslationLanguages() as $lc => $_) {
      try {
        $alt_url = $entity->getTranslation($lc)->toUrl('canonical', ['absolute' => TRUE])->toString();
      }
      catch (\Throwable) {
        continue;
      }
      $attachments['#attached']['html_head_link'][] = [
        ['rel' => 'alternate', 'hreflang' => $lc, 'href' => $alt_url],
        TRUE,
      ];
      // x-default points at the site's default language (es).
      if ($lc === 'es') {
        $attachments['#attached']['html_head_link'][] = [
          ['rel' => 'alternate', 'hreflang' => 'x-default', 'href' => $alt_url],
          TRUE,
        ];
      }
    }
  }

  private function attachJsonLd(array &$attachments, array $info, string $base): void {
    $jsonld = $this->buildJsonLd($info, $base);
    $attachments['#attached']['html_head'][] = [
      [
        '#tag' => 'script',
        '#attributes' => ['type' => 'application/ld+json'],
        '#value' => json_encode($jsonld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
      ],
      'jalvarez_seo_jsonld',
    ];
  }

  /**
   * Builds the schema.org @graph (Person + WebSite + page-specific node).
   */
  public function buildJsonLd(array $info, string $base): array {
    return [
      '@context' => 'https://schema.org',
      '@graph' => [
        $this->jsonLdPerson($base),
        $this->jsonLdWebSite($base),
        $this->jsonLdPageNode($info, $base),
      ],
    ];
  }

  private function jsonLdPerson(string $base): array {
    return [
      '@type' => 'Person',
      '@id' => $base . '/#person',
      'name' => 'John Stevans Alvarez',
      'alternateName' => 'JSA',
      'url' => $base . '/',
      'jobTitle' => 'Senior Web Specialist',
      'email' => 'mailto:' . BrandConfig::EMAIL,
      'telephone' => BrandConfig::PHONE,
      'address' => [
        '@type' => 'PostalAddress',
        'addressLocality' => 'Medellín',
        'addressCountry' => 'CO',
      ],
      'knowsAbout' => [
        'Drupal',
        'WordPress',
        'Headless CMS',
        'n8n',
        'Core Web Vitals',
        'WCAG 2.2',
        'Web Performance',
        'Web Accessibility',
      ],
    ];
  }

  private function jsonLdWebSite(string $base): array {
    return [
      '@type' => 'WebSite',
      '@id' => $base . '/#website',
      'name' => 'jalvarez.tech',
      'url' => $base . '/',
      'inLanguage' => ['es-CO', 'en-US'],
      'publisher' => ['@id' => $base . '/#person'],
    ];
  }

  private function jsonLdPageNode(array $info, string $base): array {
    $page_lang = $info['langcode'] === 'en' ? 'en-US' : 'es-CO';
    $entity = $info['entity'];

    if ($info['type'] === 'note' && $entity instanceof NodeInterface) {
      return $this->jsonLdBlogPosting($entity, $info, $base, $page_lang);
    }
    if ($info['type'] === 'project' && $entity instanceof NodeInterface) {
      return $this->jsonLdCreativeWork($entity, $info, $base, $page_lang);
    }
    return $this->jsonLdWebPage($info, $base, $page_lang);
  }

  private function jsonLdWebPage(array $info, string $base, string $page_lang): array {
    $node = [
      '@type' => 'WebPage',
      '@id' => $info['url'] . '#webpage',
      'url' => $info['url'],
      'name' => $info['title'],
      'description' => $info['description'],
      'inLanguage' => $page_lang,
      'isPartOf' => ['@id' => $base . '/#website'],
      'about' => ['@id' => $base . '/#person'],
    ];
    if ($info['image']) {
      $node['primaryImageOfPage'] = ['@type' => 'ImageObject', 'url' => $info['image']];
    }
    return $node;
  }

  private function jsonLdBlogPosting(NodeInterface $node, array $info, string $base, string $page_lang): array {
    $published = NULL;
    if ($node->hasField('field_publish_date') && !$node->get('field_publish_date')->isEmpty()) {
      $published = (string) $node->get('field_publish_date')->value;
    }
    $out = [
      '@type' => 'BlogPosting',
      '@id' => $info['url'] . '#article',
      'url' => $info['url'],
      'headline' => $info['title'],
      'description' => $info['description'],
      'inLanguage' => $page_lang,
      'isPartOf' => ['@id' => $base . '/#website'],
      'author' => ['@id' => $base . '/#person'],
      'publisher' => ['@id' => $base . '/#person'],
      'mainEntityOfPage' => $info['url'],
      'datePublished' => $published ?: date('c', (int) $node->getCreatedTime()),
      'dateModified' => date('c', (int) $node->getChangedTime()),
    ];
    if ($info['image']) {
      $out['image'] = ['@type' => 'ImageObject', 'url' => $info['image']];
    }
    return $out;
  }

  private function jsonLdCreativeWork(NodeInterface $node, array $info, string $base, string $page_lang): array {
    $year = NULL;
    if ($node->hasField('field_project_year') && !$node->get('field_project_year')->isEmpty()) {
      $year = (string) $node->get('field_project_year')->value;
    }
    $out = [
      '@type' => 'CreativeWork',
      '@id' => $info['url'] . '#work',
      'url' => $info['url'],
      'name' => $info['title'],
      'description' => $info['description'],
      'inLanguage' => $page_lang,
      'isPartOf' => ['@id' => $base . '/#website'],
      'creator' => ['@id' => $base . '/#person'],
      'author' => ['@id' => $base . '/#person'],
      'dateCreated' => $year ?: date('c', (int) $node->getCreatedTime()),
      'dateModified' => date('c', (int) $node->getChangedTime()),
    ];
    if ($info['image']) {
      $out['image'] = ['@type' => 'ImageObject', 'url' => $info['image']];
    }
    return $out;
  }

}
