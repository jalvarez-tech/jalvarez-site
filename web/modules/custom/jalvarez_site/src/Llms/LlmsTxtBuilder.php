<?php

declare(strict_types=1);

namespace Drupal\jalvarez_site\Llms;

use Drupal\canvas\Entity\Page;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\CacheableResponse;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\Entity\Node;
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
final class LlmsTxtBuilder {

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
    $lines[] = 'Contact: contacto@jalvarez.tech · +57 312 801 4078 · https://wa.link/fb2acg';
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
   * @return array<int,array{id:int,title:string,url:string,description:string,langcode:string}>
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
    $order = [8 => 0, 5 => 1, 6 => 2, 7 => 3];
    usort($rows, fn(array $a, array $b) =>
      ($order[$a['id']] ?? 99) <=> ($order[$b['id']] ?? 99)
        ?: strcmp($a['langcode'], $b['langcode'])
    );
    return $rows;
  }

  /**
   * @return array<int,array{id:int,title:string,url:string,description:string,langcode:string}>
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
      'id' => (int) $entity->id(),
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
    $line = "- [{$row['title']}]({$row['url']})";
    if ($desc !== '') {
      $line .= ': ' . $desc;
    }
    return $line;
  }

}
