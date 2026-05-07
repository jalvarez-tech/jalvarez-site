<?php

declare(strict_types=1);

namespace Drupal\jalvarez_site\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Renders a styleguide of byte SDC components for visual QA.
 */
class StyleguideController extends ControllerBase {

  public function index(): array {
    $build = [];

    // Header.
    $build['header'] = [
      '#markup' => '<div class="wrap" style="padding-top:56px;padding-bottom:24px;"><h1 style="font-family:var(--f-display);font-size:42px;font-weight:500;letter-spacing:-0.03em;margin:0 0 8px;">Byte styleguide</h1><p style="font-family:var(--f-mono);font-size:13px;color:var(--fg-muted);">Phase E components — visual QA only.</p></div>',
    ];

    // Hero (banner-inicio)
    $build['hero'] = [
      '#type' => 'component',
      '#component' => 'byte:banner-inicio',
      '#props' => [
        'status' => 'Disponible · Q3 2026 · 2 cupos',
        'title_a' => 'Creo que una web',
        'title_accent' => 'rápida',
        'title_punc' => ',',
        'title_stroke' => 'honesta',
        'title_b' => ' e inclusiva',
        'title_muted' => 'es una forma de respeto.',
        'sub' => 'Construyo experiencias digitales con foco en rendimiento, accesibilidad y claridad. Sitios que no hacen perder tiempo, no excluyen usuarios y no prometen más de lo que entregan.',
        'cta_primary_label' => 'Agenda una llamada',
        'cta_primary_href' => '/contacto',
        'cta_secondary_label' => 'Ver el trabajo',
        'cta_secondary_href' => '/proyectos',
        'meta' => [
          ['value' => '15', 'unit' => '+', 'label' => 'años defendiendo esto'],
          ['value' => '80', 'unit' => '+', 'label' => 'plataformas en producción'],
          ['value' => '97', 'unit' => '%', 'label' => 'clientes que vuelven'],
        ],
      ],
    ];

    // Section + chip + button samples.
    $build['primitives'] = [
      '#type' => 'component',
      '#component' => 'byte:section',
      '#props' => [
        'eyebrow_label' => 'primitivos',
        'eyebrow_number' => 1,
        'title' => 'Chips, botones, tipografía. ',
        'title_em' => 'Las piezas más pequeñas.',
        'lede' => 'Los chips usan el sistema dual: verde estático en variant accent, naranja al hover en interactivos. Botones primary y ghost.',
      ],
      'samples' => [
        '#prefix' => '<div class="wrap" style="display:flex;gap:14px;flex-wrap:wrap;align-items:center;margin-top:24px;">',
        '#suffix' => '</div>',
        'chip1' => [
          '#type' => 'component',
          '#component' => 'byte:chip',
          '#props' => ['label' => 'WordPress', 'variant' => 'accent'],
        ],
        'chip2' => [
          '#type' => 'component',
          '#component' => 'byte:chip',
          '#props' => ['label' => '2025'],
        ],
        'chip3' => [
          '#type' => 'component',
          '#component' => 'byte:chip',
          '#props' => ['label' => 'Drupal 11', 'variant' => 'accent'],
        ],
        'chip4' => [
          '#type' => 'component',
          '#component' => 'byte:chip',
          '#props' => ['label' => 'Performance'],
        ],
        'btn1' => [
          '#type' => 'component',
          '#component' => 'byte:button',
          '#props' => [
            'label' => 'Iniciar tu proyecto',
            'variant' => 'primary',
            'arrow' => TRUE,
            'href' => '/contacto',
          ],
        ],
        'btn2' => [
          '#type' => 'component',
          '#component' => 'byte:button',
          '#props' => ['label' => 'Ver el trabajo', 'variant' => 'ghost'],
        ],
      ],
    ];

    // CTA final.
    $build['cta'] = [
      '#type' => 'component',
      '#component' => 'byte:cta-final',
      '#props' => [
        'title' => 'Si crees lo mismo,',
        'title_em' => 'construyamos algo juntos.',
        'sub' => 'Si esta forma de pensar la web también es la tuya — medir antes de prometer, construir para que dure, respetar a quien visita — empecemos por una conversación.',
        'primary_label' => 'Iniciar tu proyecto',
        'primary_href' => '/contacto',
        'secondary_label' => 'Revisar mi trabajo',
        'secondary_href' => '/proyectos',
      ],
    ];

    return $build;
  }

}
