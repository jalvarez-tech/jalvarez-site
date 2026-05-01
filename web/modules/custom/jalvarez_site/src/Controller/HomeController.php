<?php

declare(strict_types=1);

namespace Drupal\jalvarez_site\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Renders the home page composed of byte SDC components.
 *
 * Mirrors the design from docs/DESIGN.md §4.1.
 */
class HomeController extends ControllerBase {

  public function index(): array {
    return [
      // 1. Hero
      'hero' => [
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
          'sub' => 'Construyo experiencias digitales con foco en rendimiento, accesibilidad y claridad. Sitios que no hacen perder tiempo, no excluyen usuarios y no prometen más de lo que realmente entregan. Soy John Stevans Alvarez, desarrollador web con más de 15 años creando plataformas sólidas, escalables y pensadas para las personas.',
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
      ],

      // 2. Marquee de stack
      'marquee' => [
        '#type' => 'component',
        '#component' => 'byte:marquee',
        '#props' => [
          'items' => [
            'WordPress', 'Drupal 11', 'n8n', 'Headless',
            'Core Web Vitals', 'WCAG 2.2', 'Multilenguaje',
            'API Integrations', 'Performance Audits',
          ],
        ],
      ],

      // 3. Como lo hago (§ 01)
      'values' => [
        '#type' => 'component',
        '#component' => 'byte:como-lo-hago',
        '#props' => [
          'eyebrow_label' => 'cómo lo hago',
          'eyebrow_number' => 1,
          'title' => 'No vendo features. ',
          'title_em' => 'Defiendo cuatro disciplinas no negociables.',
          'items' => [
            [
              'icon' => 'gauge', 'tag' => 'performance', 'title' => 'Mido antes de prometer',
              'body' => 'Cada decisión técnica empieza con un número real: LCP, CLS, peso de página, tasa de rebote. Si no se mide, no existe.',
              'metric_value' => '42', 'metric_unit' => '%',
              'caption' => 'mejora promedio de velocidad tras auditoría',
            ],
            [
              'icon' => 'layers', 'tag' => 'arquitectura', 'title' => 'Construyo para que dure',
              'body' => 'WordPress y Drupal escritos como si los tuviera que mantener yo dentro de cinco años. Componentes reutilizables, builds limpios, deuda técnica cercana a cero.',
              'metric_value' => '15', 'metric_unit' => '+ años',
              'caption' => 'construyendo plataformas que no se rompen',
            ],
            [
              'icon' => 'accessibility', 'tag' => 'accesibilidad', 'title' => 'Respeto a todos los visitantes',
              'body' => 'WCAG 2.2 AA es el piso, no el techo. Un sitio que excluye al 15% de usuarios no es "casi accesible": está mal hecho.',
              'metric_value' => 'AA',
              'caption' => 'conformidad WCAG 2.2 por defecto',
            ],
            [
              'icon' => 'workflow', 'tag' => 'automatización', 'title' => 'Automatizo lo repetitivo',
              'body' => 'Flujos en n8n, integraciones API, funnels conectados a tu CRM. El sitio es un colaborador silencioso que trabaja mientras duermes.',
              'metric_value' => '∞', 'metric_unit' => 'h',
              'caption' => 'horas/mes recuperadas en clientes activos',
            ],
          ],
        ],
      ],

      // 4. Qué construyo (§ 02) - mock projects
      'featured' => [
        '#type' => 'component',
        '#component' => 'byte:que-construyo',
        '#props' => [
          'eyebrow_label' => 'qué construyo',
          'eyebrow_number' => 2,
          'title' => 'Y resulta que con ese enfoque ',
          'title_em' => 'salen plataformas como estas.',
          'lede' => 'Música, cine, bienestar, inmobiliario, agencias creativas. Distinta industria, mismo principio: web que respeta el tiempo y la atención de quien la visita.',
          'items' => [
            [
              'name' => 'Maluma.online', 'category' => 'WordPress', 'year' => '2025', 'hue' => 18,
              'description' => 'Sitio multilenguaje para el artista, optimizado con mejora superior al 40% en velocidad.',
              'metrics' => [['key' => 'LCP', 'value' => '1.4s'], ['key' => 'Peso', 'value' => '−38%']],
            ],
            [
              'name' => 'Royalty Films', 'category' => 'WordPress', 'year' => '2025', 'hue' => 0,
              'description' => 'Arquitectura multilenguaje para la productora, preparada para expansión internacional.',
              'metrics' => [['key' => 'Idiomas', 'value' => '3'], ['key' => 'Perf', 'value' => '94']],
            ],
            [
              'name' => '333 Creativo', 'category' => 'Drupal 11', 'year' => '2024', 'hue' => 280,
              'description' => 'Plataforma escalable para agencia de marca personal con diseño visual fuerte.',
              'metrics' => [['key' => 'Módulos', 'value' => 'Custom'], ['key' => 'A11y', 'value' => 'AA']],
            ],
          ],
          'cta_label' => 'Ver todo el trabajo',
          'cta_href' => '/proyectos',
        ],
      ],

      // 5. Método (§ 03)
      'process' => [
        '#type' => 'component',
        '#component' => 'byte:metodo',
        '#props' => [
          'eyebrow_label' => 'el método',
          'eyebrow_number' => 3,
          'title' => 'Y ',
          'title_em' => 'así trabajo cuando empezamos.',
          'items' => [
            ['title' => 'Auditoría técnica', 'tag' => 'Semana 1', 'body' => 'Antes de proponer nada, mido. Performance, arquitectura, oportunidades reales. Salgo con un documento que dice qué duele, qué importa y qué se puede ignorar sin culpa.'],
            ['title' => 'Arquitectura', 'tag' => 'Semana 2', 'body' => 'Decido el stack basado en tu equipo, presupuesto y crecimiento — no en lo que está de moda. Drupal, WordPress, headless: la respuesta correcta depende de tu caso.'],
            ['title' => 'Implementación', 'tag' => 'Sem. 3-8', 'body' => 'Sprints quincenales con demos. Code reviews internos, performance budgets que rompen el build si se exceden. Cero sorpresas el día del lanzamiento.'],
            ['title' => 'Lanzamiento + soporte', 'tag' => 'Ongoing', 'body' => 'Migración sin downtime, monitoreo activo durante las primeras 4 semanas, y plan de mantenimiento a 12 meses. Lanzar es el comienzo, no el final.'],
          ],
        ],
      ],

      // 6. Palabras de cliente (§ 04)
      'tests' => [
        '#type' => 'component',
        '#component' => 'byte:palabras-cliente',
        '#props' => [
          'eyebrow_label' => 'palabra de clientes',
          'eyebrow_number' => 4,
          'title' => 'Quienes ya lanzaron lo cuentan mejor que yo.',
          'items' => [
            [
              'quote' => 'John implementó nuestro sitio con ejecución técnica impecable. Optimizó el rendimiento logrando una mejora superior al 40% en velocidad y estabilidad.',
              'name' => 'Tatiana Restrepo', 'role' => 'Maluma.online', 'initials' => 'TR',
            ],
            [
              'quote' => 'Transformó el diseño en una plataforma funcional, optimizada y escalable. Su capacidad para ejecutar con precisión marcó una diferencia enorme.',
              'name' => 'Tes Pimienta', 'role' => 'Royalty Films', 'initials' => 'TP',
            ],
          ],
        ],
      ],

      // 7. CTA Final
      'cta' => [
        '#type' => 'component',
        '#component' => 'byte:cta-final',
        '#props' => [
          'title' => 'Si crees lo mismo, ',
          'title_em' => 'construyamos algo juntos.',
          'sub' => 'Si esta forma de pensar la web también es la tuya — medir antes de prometer, construir para que dure, respetar a quien visita — empecemos por una conversación. Antes de la primera línea de código, ya estaremos alineados.',
          'primary_label' => 'Iniciar tu proyecto',
          'primary_href' => '/contacto',
          'secondary_label' => 'Revisar mi trabajo',
          'secondary_href' => '/proyectos',
        ],
      ],
    ];
  }

}
