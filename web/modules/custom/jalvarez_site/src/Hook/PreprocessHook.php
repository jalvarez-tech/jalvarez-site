<?php

declare(strict_types=1);

namespace Drupal\jalvarez_site\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\jalvarez_site\BrandConfig;

/**
 * Exposes the brand contact details as a `brand` variable in node templates.
 *
 * Lets node-level templates (notably node--project--full.html.twig) build
 * `tel:` and `wa.me/<phone>?text=…` links from a single source of truth
 * (BrandConfig) instead of hardcoding the phone number per template.
 *
 * Scoped to `preprocess_node` because that's where we currently need it.
 * If a block or page template starts to need the same data, add another
 * `#[Hook('preprocess_<hook>')]` here pointing at the same array.
 */
class PreprocessHook {

  #[Hook('preprocess_node')]
  public function preprocessNode(array &$variables): void {
    $variables['brand'] = BrandConfig::toArray();
  }

}
