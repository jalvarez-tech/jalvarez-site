<?php

declare(strict_types=1);

namespace Drupal\jalvarez_site\Llms;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\CacheableResponse;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\jalvarez_site\BrandConfig;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Builds the body and cacheable response for /llms.txt and /llms-full.txt.
 *
 * Extracted from LlmsTxtController so the controller and the early-request
 * subscriber (which serves the same routes before language path-prefix
 * processing kicks in) can share the same logic without one depending on the
 * other through the service container.
 */
class LlmsTxtBuilder {

  /**
   * Slug → weight map for the canvas_page top section ordering.
   *
   * Keyed by the last URL segment (so it survives DB reinstalls and id
   * shuffles). Both languages are listed because the rows are bilingual.
   */
  private const PAGE_ORDER = [
    'inicio' => 0,
    'home' => 0,
    'proyectos' => 1,
    'projects' => 1,
    'notas' => 2,
    'notes' => 2,
    'contacto' => 3,
    'contact' => 3,
  ];

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Returns a CacheableResponse for the requested variant.
   */
  public function buildResponse(Request $request, bool $full): CacheableResponse {
    $body = $this->renderBody($request, $full);
    $response = new CacheableResponse($body, 200, [
      'Content-Type' => 'text/plain; charset=utf-8',
      'X-Content-Type-Options' => 'nosniff',
    ]);
    $metadata = (new CacheableMetadata())
      ->addCacheTags([
        'canvas_page_list',
        'node_list:project',
        'node_list:note',
      ])
      ->addCacheContexts(['url.path']);
    $response->addCacheableDependency($metadata);
    return $response;
  }

  private function renderBody(Request $request, bool $full): string {
    $base = $request->getSchemeAndHttpHost();
    $sitemap = $base . '/sitemap.xml';

    $lines = [];
    $lines[] = '# jalvarez.tech';
    $lines[] = '';
    $lines[] = '> Portfolio of John Stevans Alvarez (JSA), Senior Web Specialist with 15+ years building solid, scalable, accessible web platforms. Based in Medellín, Colombia. Available Q3 2026 for 2 client slots.';
    $lines[] = '';
    $lines[] = 'Bilingual site (Spanish default · English alternate). Stack: Drupal 11, WordPress, Headless CMS, n8n, Core Web Vitals, WCAG 2.2. Brand voice is editorial-developer, first-person, honest, anti-features-hype.';
    $lines[] = '';
    $lines[] = sprintf(
      'Contact: %s · %s · %s',
      BrandConfig::EMAIL,
      BrandConfig::PHONE,
      BrandConfig::WHATSAPP_LINK,
    );
    $lines[] = '';

    $pages = $this->loadCanvasPages();
    if ($pages) {
      $lines[] = '## Páginas principales (ES)';
      $lines[] = '';
      foreach ($pages as $row) {
        if ($row['langcode'] !== 'es') {
          continue;
        }
        $lines[] = $this->bullet($row, $full);
      }
      $lines[] = '';
      $lines[] = '## Main pages (EN)';
      $lines[] = '';
      foreach ($pages as $row) {
        if ($row['langcode'] !== 'en') {
          continue;
        }
        $lines[] = $this->bullet($row, $full);
      }
      $lines[] = '';
    }

    $projects = $this->loadNodes('project');
    if ($projects) {
      $lines[] = '## Proyectos / Case studies (ES)';
      $lines[] = '';
      foreach ($projects as $row) {
        if ($row['langcode'] !== 'es') {
          continue;
        }
        $lines[] = $this->bullet($row, $full);
      }
      $lines[] = '';
      $lines[] = '## Projects / Case studies (EN)';
      $lines[] = '';
      foreach ($projects as $row) {
        if ($row['langcode'] !== 'en') {
          continue;
        }
        $lines[] = $this->bullet($row, $full);
      }
      $lines[] = '';
    }

    $notes = $this->loadNodes('note');
    if ($notes) {
      $lines[] = '## Notas técnicas (ES)';
      $lines[] = '';
      foreach ($notes as $row) {
        if ($row['langcode'] !== 'es') {
          continue;
        }
        $lines[] = $this->bullet($row, $full);
      }
      $lines[] = '';
      $lines[] = '## Technical notes (EN)';
      $lines[] = '';
      foreach ($notes as $row) {
        if ($row['langcode'] !== 'en') {
          continue;
        }
        $lines[] = $this->bullet($row, $full);
      }
      $lines[] = '';
    }

    $lines[] = '## Optional';
    $lines[] = '';
    $lines[] = "- [Sitemap]({$sitemap}): full machine-readable site index with hreflang for ES and EN.";
    $lines[] = "- [Full version]({$base}/llms-full.txt): same index with expanded descriptions for every project and note.";
    $lines[] = '';

    return implode("\n", $lines) . "\n";
  }

  /**
   * Returns one row per published canvas_page translation.
   *
   * @return array<int,array{title:string,url:string,description:string,langcode:string}>
   *   Rows ordered by URL slug (Inicio/Home, Proyectos/Projects, Notas/Notes,
   *   Contacto/Contact) and then by langcode. Slug-based ordering survives DB
   *   reinstalls — earlier versions used hardcoded entity IDs and broke when
   *   the canvas_page entries were re-seeded.
   */
  private function loadCanvasPages(): array {
    $rows = [];
    try {
      $pages = $this->entityTypeManager->getStorage('canvas_page')->loadMultiple();
    }
    catch (\Throwable) {
      // Canvas module not enabled (e.g. minimal test profile); skip pages.
      return [];
    }
    foreach ($pages as $page) {
      if (!$page instanceof ContentEntityInterface) {
        continue;
      }
      foreach ($page->getTranslationLanguages() as $lc => $_) {
        $trans = $page->getTranslation($lc);
        if ($trans instanceof EntityPublishedInterface && !$trans->isPublished()) {
          continue;
        }
        $rows[] = $this->rowFromEntity($trans, $lc);
      }
    }
    usort($rows, fn(array $a, array $b) =>
      $this->slugWeight($a['url']) <=> $this->slugWeight($b['url'])
        ?: strcmp($a['langcode'], $b['langcode'])
    );
    return $rows;
  }

  /**
   * Returns the order weight for a URL based on its last path segment.
   *
   * Unknown slugs sort last (99). The map covers both languages.
   */
  private function slugWeight(string $url): int {
    $path = parse_url($url, PHP_URL_PATH);
    if (!is_string($path) || $path === '') {
      return 99;
    }
    $segments = array_values(array_filter(explode('/', $path), fn(string $s) => $s !== ''));
    $slug = end($segments) ?: '';
    return self::PAGE_ORDER[$slug] ?? 99;
  }

  /**
   * Returns one row per published translation of nodes in $bundle.
   *
   * @return array<int,array{title:string,url:string,description:string,langcode:string}>
   *   Newest-first by created date.
   */
  private function loadNodes(string $bundle): array {
    $rows = [];
    $nids = $this->entityTypeManager->getStorage('node')->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', $bundle)
      ->condition('status', 1)
      ->sort('created', 'DESC')
      ->execute();
    if (!$nids) {
      return [];
    }
    /** @var \Drupal\node\NodeInterface[] $nodes */
    $nodes = $this->entityTypeManager->getStorage('node')->loadMultiple($nids);
    foreach ($nodes as $node) {
      foreach ($node->getTranslationLanguages() as $lc => $_) {
        $trans = $node->getTranslation($lc);
        if (!$trans->isPublished()) {
          continue;
        }
        $rows[] = $this->rowFromEntity($trans, $lc);
      }
    }
    return $rows;
  }

  /**
   * Projects an entity translation into the row shape used by render().
   *
   * @return array{title:string,url:string,description:string,langcode:string}
   *   Description is plain text (HTML stripped, whitespace collapsed).
   */
  private function rowFromEntity(ContentEntityInterface $entity, string $langcode): array {
    $url = '';
    try {
      $url = $entity->toUrl('canonical', ['absolute' => TRUE])->toString();
    }
    catch (\Throwable) {
    }

    $description = '';
    if ($entity instanceof NodeInterface) {
      $bundle = $entity->bundle();
      if ($bundle === 'project' && $entity->hasField('field_summary') && !$entity->get('field_summary')->isEmpty()) {
        $description = (string) $entity->get('field_summary')->value;
      }
      elseif ($bundle === 'note' && $entity->hasField('field_excerpt') && !$entity->get('field_excerpt')->isEmpty()) {
        $description = (string) $entity->get('field_excerpt')->value;
      }
    }
    elseif ($entity->hasField('description') && !$entity->get('description')->isEmpty()) {
      $description = (string) $entity->get('description')->value;
    }
    $description = trim(preg_replace('/\s+/', ' ', strip_tags($description)) ?? '');

    return [
      'title' => (string) $entity->label(),
      'url' => $url,
      'description' => $description,
      'langcode' => $langcode,
    ];
  }

  private function bullet(array $row, bool $full): string {
    $desc = $row['description'];
    if (!$full && mb_strlen($desc) > 180) {
      $desc = mb_substr($desc, 0, 177) . '…';
    }
    $title = $this->escapeMarkdownText($row['title']);
    $url = $this->escapeMarkdownUrl($row['url']);
    // Without a valid http(s) URL we still want the title in the index, but
    // as plain text — a markdown link with an empty/invalid target is worse
    // than no link at all (some llms.txt parsers follow `(javascript:…)`).
    $line = $url === '' ? "- {$title}" : "- [{$title}]({$url})";
    if ($desc !== '') {
      $line .= ': ' . $this->escapeMarkdownText($desc);
    }
    return $line;
  }

  /**
   * Sanitises arbitrary user input for inclusion in a markdown bullet.
   *
   * Editors with permission to create projects/notes/canvas pages can put any
   * character in a title or summary. Without escaping, a `]` in a title or a
   * raw newline in a description silently breaks the markdown structure —
   * crawlers that parse llms.txt as markdown then misread later rows. This
   * helper collapses whitespace into single spaces and backslash-escapes the
   * characters that would terminate a link label early.
   */
  private function escapeMarkdownText(string $value): string {
    $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');
    return strtr($value, [
      '\\' => '\\\\',
      '[' => '\\[',
      ']' => '\\]',
    ]);
  }

  /**
   * Validates and prepares a URL for a markdown link target.
   *
   * Only `http` and `https` URLs are accepted — anything else (`javascript:`,
   * `data:`, `mailto:`, malformed, empty) returns '' so `bullet()` falls back
   * to a plain text entry. Surviving URLs get their `(` and `)` percent-
   * encoded so they cannot terminate the markdown link target.
   */
  private function escapeMarkdownUrl(string $url): string {
    if ($url === '') {
      return '';
    }
    $scheme = parse_url($url, PHP_URL_SCHEME);
    if ($scheme !== 'http' && $scheme !== 'https') {
      return '';
    }
    return strtr($url, ['(' => '%28', ')' => '%29']);
  }

}
