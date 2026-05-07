<?php

declare(strict_types=1);

namespace Drupal\jalvarez_site\Controller;

use Drupal\Core\Cache\CacheableResponse;
use Drupal\Core\Controller\ControllerBase;
use Drupal\jalvarez_site\Llms\LlmsTxtBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Serves /llms.txt and /llms-full.txt at the docroot.
 *
 * Implements the llms.txt convention (https://llmstxt.org). The actual body
 * generation lives in LlmsTxtBuilder so that the early-request subscriber
 * (which intercepts these paths before language path-prefix processing) can
 * reuse the same logic without depending on this controller.
 */
final class LlmsTxtController extends ControllerBase {

  public function __construct(
    private readonly LlmsTxtBuilder $builder,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('jalvarez_site.llms_txt_builder'),
    );
  }

  public function index(Request $request): CacheableResponse {
    return $this->builder->buildResponse($request, FALSE);
  }

  public function full(Request $request): CacheableResponse {
    return $this->builder->buildResponse($request, TRUE);
  }

}
