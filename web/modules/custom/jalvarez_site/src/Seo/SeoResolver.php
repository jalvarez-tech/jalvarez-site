<?php

declare(strict_types=1);

namespace Drupal\jalvarez_site\Seo;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Entity\TranslatableInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\file\FileInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Resolves the SEO payload (title, description, image, url, type) per route.
 *
 * Extracted from the procedural _jalvarez_site_seo_resolve_* helpers in the
 * .module so the logic is testable in isolation and the dependency graph
 * (route_match, language_manager, request_stack, file_url_generator) is
 * explicit instead of grabbed via \Drupal::xxx().
 */
class SeoResolver {

  public function __construct(
    private readonly RouteMatchInterface $routeMatch,
    private readonly LanguageManagerInterface $languageManager,
    private readonly RequestStack $requestStack,
    private readonly FileUrlGeneratorInterface $fileUrlGenerator,
  ) {}

  /**
   * Resolves the SEO payload for the route currently being served.
   *
   * @return array{title:string,description:string,image:?string,url:string,type:string,langcode:string,entity:\Drupal\Core\Entity\ContentEntityInterface}|null
   *   NULL if the route is not a SEO-eligible page.
   */
  public function resolveCurrent(string $route_name): ?array {
    [$entity, $type] = $this->extractEntityFromRoute($route_name);
    if (!$entity instanceof ContentEntityInterface) {
      return NULL;
    }

    $langcode = $this->languageManager->getCurrentLanguage()->getId();
    if ($entity instanceof TranslatableInterface && $entity->hasTranslation($langcode)) {
      $translated = $entity->getTranslation($langcode);
      if ($translated instanceof ContentEntityInterface) {
        $entity = $translated;
      }
    }
    else {
      $langcode = $entity->language()->getId();
    }

    $base = $this->getBaseUrl();
    $info = $type === 'canvas_page'
      ? $this->resolveCanvasPage($entity, $base)
      : ($entity instanceof NodeInterface ? $this->resolveNode($entity, $base) : NULL);
    if ($info === NULL) {
      return NULL;
    }
    $info['type'] = $type;
    $info['langcode'] = $langcode;
    $info['entity'] = $entity;
    return $info;
  }

  /**
   * Builds the SEO payload for a canvas_page translation.
   *
   * @return array{title:string,description:string,image:?string,url:string}|null
   *   Always returns a non-null payload — falls back to label + brand suffix
   *   when the page has no metatags JSON.
   */
  public function resolveCanvasPage(ContentEntityInterface $page, string $base): ?array {
    $raw = $page->hasField('metatags') ? ($page->get('metatags')->value ?? '') : '';
    $tags = is_string($raw) && $raw !== '' ? (json_decode($raw, TRUE) ?: []) : [];

    $title = $tags['title'] ?? ((string) $page->label() . ' | jalvarez.tech');
    $description = $tags['description'] ?? '';
    if ($description === '' && $page->hasField('description')) {
      $description = (string) $page->get('description')->value;
    }

    $url = $this->canonicalUrl($page);
    $image = $this->extractImageFromMediaField($page, ['image']);

    return [
      'title' => (string) $title,
      'description' => (string) $description,
      'image' => $image,
      'url' => $url,
    ];
  }

  /**
   * Builds the SEO payload for a project/note node translation.
   *
   * @return array{title:string,description:string,image:?string,url:string}|null
   *   NULL if the node bundle isn't project/note.
   */
  public function resolveNode(NodeInterface $node, ?string $base = NULL): ?array {
    $bundle = $node->bundle();
    if (!in_array($bundle, ['project', 'note'], TRUE)) {
      return NULL;
    }
    $base ??= $this->getBaseUrl();

    // Description: project uses field_summary, note uses field_excerpt.
    $description = '';
    if ($bundle === 'project' && $node->hasField('field_summary') && !$node->get('field_summary')->isEmpty()) {
      $description = (string) $node->get('field_summary')->value;
    }
    elseif ($bundle === 'note' && $node->hasField('field_excerpt') && !$node->get('field_excerpt')->isEmpty()) {
      $description = (string) $node->get('field_excerpt')->value;
    }
    $description = trim(preg_replace('/\s+/', ' ', strip_tags($description)) ?? '');
    if (mb_strlen($description) > 300) {
      $description = mb_substr($description, 0, 297) . '…';
    }

    // Brand suffix appended once so the same value flows into <title>,
    // og:title, twitter:title and JSON-LD without double-suffixing.
    $title = (string) $node->label() . ' | jalvarez.tech';

    return [
      'title' => $title,
      'description' => $description,
      'image' => $this->extractImageFromMediaField($node, ['field_featured_media', 'field_cover_media', 'field_gallery']),
      'url' => $this->canonicalUrl($node),
    ];
  }

  /**
   * Returns the scheme + host of the current request (no trailing slash).
   */
  public function getBaseUrl(): string {
    $request = $this->requestStack->getCurrentRequest();
    return $request !== NULL ? $request->getSchemeAndHttpHost() : '';
  }

  /**
   * Extracts (entity, type) from a SEO-eligible route, or [NULL, NULL].
   *
   * @return array{0:?\Drupal\Core\Entity\ContentEntityInterface,1:?string}
   *   The entity + the SEO type bucket (canvas_page / project / note).
   */
  private function extractEntityFromRoute(string $route_name): array {
    if ($route_name === 'entity.canvas_page.canonical') {
      $entity = $this->routeMatch->getParameter('canvas_page');
      return [$entity instanceof ContentEntityInterface ? $entity : NULL, 'canvas_page'];
    }
    if ($route_name === 'entity.node.canonical') {
      $node = $this->routeMatch->getParameter('node');
      if ($node instanceof NodeInterface && in_array($node->bundle(), ['project', 'note'], TRUE)) {
        return [$node, $node->bundle()];
      }
    }
    return [NULL, NULL];
  }

  /**
   * Walks a list of media reference fields, returns the first usable URL.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   Entity carrying the media reference fields.
   * @param string[] $fields
   *   Media reference field names to try, in priority order.
   */
  private function extractImageFromMediaField(ContentEntityInterface $entity, array $fields): ?string {
    foreach ($fields as $field) {
      if (!$entity->hasField($field) || $entity->get($field)->isEmpty()) {
        continue;
      }
      $media = $entity->get($field)->entity;
      if (!$media instanceof FieldableEntityInterface || !$media->hasField('field_media_image') || $media->get('field_media_image')->isEmpty()) {
        continue;
      }
      $file = $media->get('field_media_image')->entity;
      if ($file instanceof FileInterface) {
        return $this->fileUrlGenerator->generateAbsoluteString($file->getFileUri());
      }
    }
    return NULL;
  }

  /**
   * Resolves an entity's canonical URL or returns '' if it has no route.
   */
  private function canonicalUrl(ContentEntityInterface $entity): string {
    try {
      return $entity->toUrl('canonical', ['absolute' => TRUE])->toString();
    }
    catch (\Throwable) {
      return '';
    }
  }

}
