<?php

declare(strict_types=1);

namespace Drupal\jalvarez_site\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Rewrites the language prefix of generated aliases for translated content.
 *
 * EN translations get the right URL segment without relying on Pathauto's
 * per-language patterns (which have buggy context resolution):
 *
 *   ES (default): proyectos/* · notas/*
 *   EN:           projects/*  · notes/*
 */
class PathautoHook {

  private const REWRITES = [
    '/proyectos/' => '/projects/',
    '/notas/'     => '/notes/',
    'proyectos/'  => 'projects/',
    'notas/'      => 'notes/',
  ];

  #[Hook('pathauto_alias_alter')]
  public function alter(string &$alias, array &$context): void {
    // PathautoGenerator builds context with key 'language' (a reference to
    // $langcode). @see Drupal\pathauto\PathautoGenerator::createEntityAlias()
    $lang = $context['language'] ?? '';
    if ($lang !== 'en') {
      return;
    }
    foreach (self::REWRITES as $from => $to) {
      if (str_starts_with($alias, $from)) {
        $alias = $to . substr($alias, strlen($from));
        return;
      }
    }
  }

}
