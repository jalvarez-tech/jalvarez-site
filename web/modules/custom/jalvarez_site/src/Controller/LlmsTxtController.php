<?php

declare(strict_types=1);

namespace Drupal\jalvarez_site\Controller;

use Drupal\canvas\Entity\Page;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\CacheableResponse;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\TranslatableInterface;
use Drupal\node\Entity\Node;
use Symfony\Component\HttpFoundation\Request;

/**
 * Serves /llms.txt and /llms-full.txt at the docroot.
 *
 * Implements the llms.txt convention (https://llmstxt.org) so that LLM
 * crawlers and AI assistants can discover the site's structure and the
 * canonical URLs of its primary content without parsing the HTML.
 *
 * The output is dynamic — it reflects the current canvas_pages, projects
 * and notes in the database, in both ES and EN. The full variant additionally
 * inlines each item's description (field_summary / field_excerpt).
 */
final class LlmsTxtController extends ControllerBase {

  /**
   * Short index — links + one-line descriptions.
   */
  public function index(Request $request): CacheableResponse {
    return $this->respond($this->render($request, FALSE));
  }

  /**
   * Full index — adds expanded descriptions for projects + notes.
   */
  public function full(Request $request): CacheableResponse {
    return $this->respond($this->render($request, TRUE));
  }

  /**
   * Returns a CacheableResponse tagged with the entity-list tags that
   * affect the body. Drupal's page_cache + dynamic_page_cache will store
   * the response for anonymous users and invalidate it automatically the
   * moment any canvas_page, project or note is created/edited/deleted.
   *
   * No HTTP max-age is set: Drupal's PageCache middleware applies the
   * site-wide system.performance:cache.page.max_age, and tag invalidation
   * runs ahead of that ceiling on edits.
   */
  private function respond(string $body): CacheableResponse {
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

  private function render(Request $request, bool $full): string {
    $base = $request->getSchemeAndHttpHost();
    $sitemap = $base . '/sitemap.xml';

    $lines = [];
    $lines[] = '# jalvarez.tech';
    $lines[] = '';
    $lines[] = '> Portfolio of John Stevans Alvarez (JSA), Senior Web Specialist with 15+ years building solid, scalable, accessible web platforms. Based in Medellín, Colombia. Available Q3 2026 for 2 client slots.';
    $lines[] = '';
    $lines[] = 'Bilingual site (Spanish default · English alternate). Stack: Drupal 11, WordPress, Headless CMS, n8n, Core Web Vitals, WCAG 2.2. Brand voice is editorial-developer, first-person, honest, anti-features-hype.';
    $lines[] = '';
    $lines[] = 'Contact: contacto@jalvarez.tech · +57 312 801 4078 · https://wa.link/fb2acg';
    $lines[] = '';

    // Canvas pages.
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

    // Projects.
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

    // Notes.
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

    // Resources.
    $lines[] = '## Optional';
    $lines[] = '';
    $lines[] = "- [Sitemap]({$sitemap}): full machine-readable site index with hreflang for ES and EN.";
    $lines[] = "- [Full version]({$base}/llms-full.txt): same index with expanded descriptions for every project and note.";
    $lines[] = '';

    return implode("\n", $lines) . "\n";
  }

  /**
   * @return array<int,array{title:string,url:string,description:string,langcode:string}>
   */
  private function loadCanvasPages(): array {
    $rows = [];
    $pages = Page::loadMultiple();
    foreach ($pages as $page) {
      foreach ($page->getTranslationLanguages() as $lc => $_) {
        $trans = $page->getTranslation($lc);
        if (!$trans->isPublished()) {
          continue;
        }
        $rows[] = $this->rowFromEntity($trans, $lc);
      }
    }
    // Order: Inicio (id=8) first, then Proyectos, Notas, Contacto.
    $order = [8 => 0, 5 => 1, 6 => 2, 7 => 3];
    usort($rows, fn(array $a, array $b) =>
      ($order[$a['id']] ?? 99) <=> ($order[$b['id']] ?? 99)
        ?: strcmp($a['langcode'], $b['langcode'])
    );
    return $rows;
  }

  /**
   * @return array<int,array{title:string,url:string,description:string,langcode:string}>
   */
  private function loadNodes(string $bundle): array {
    $rows = [];
    $nids = $this->entityTypeManager()->getStorage('node')->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', $bundle)
      ->condition('status', 1)
      ->sort('created', 'DESC')
      ->execute();
    if (!$nids) {
      return [];
    }
    /** @var \Drupal\node\NodeInterface[] $nodes */
    $nodes = Node::loadMultiple($nids);
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
   * @return array{id:int,title:string,url:string,description:string,langcode:string}
   */
  private function rowFromEntity(EntityInterface $entity, string $langcode): array {
    $url = '';
    try {
      $url = $entity->toUrl('canonical', ['absolute' => TRUE])->toString();
    }
    catch (\Throwable) {}

    $description = '';
    if ($entity instanceof \Drupal\node\NodeInterface) {
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
      'id' => (int) $entity->id(),
      'title' => (string) $entity->label(),
      'url' => $url,
      'description' => $description,
      'langcode' => $langcode,
    ];
  }

  /**
   * Markdown bullet line. Truncates description for the short variant.
   */
  private function bullet(array $row, bool $full): string {
    $desc = $row['description'];
    if (!$full && mb_strlen($desc) > 180) {
      $desc = mb_substr($desc, 0, 177) . '…';
    }
    $line = "- [{$row['title']}]({$row['url']})";
    if ($desc !== '') {
      $line .= ': ' . $desc;
    }
    return $line;
  }

}
