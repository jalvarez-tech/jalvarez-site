<?php

declare(strict_types=1);

namespace Drupal\jalvarez_site\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\webform\Entity\Webform;

/**
 * Renders the Contacto page composed of byte SDC components + Webform.
 *
 * Mirrors DESIGN.md §4.4: phead → 2-col (webform + canal-directo).
 */
class ContactController extends ControllerBase {

  public function index(): array {
    $build = [
      'phead' => [
        '#type' => 'component',
        '#component' => 'byte:phead',
        '#props' => [
          'eyebrow_main'  => '§ contacto',
          'eyebrow_aside' => 'respuesta en < 24h · zona horaria COT (GMT-5)',
          'title'         => 'Antes del código, ',
          'title_em'      => 'una conversación honesta',
          'title_dot'     => '.',
          'sub'           => 'Creo que los mejores proyectos empiezan con preguntas, no con propuestas. Cuéntame qué intentas resolver — no qué quieres construir. Si lo que defendemos coincide, el resto se diseña solo.',
        ],
      ],
    ];

    $webform = Webform::load('contact');
    $form = $webform
      ? $this->entityTypeManager()->getViewBuilder('webform')->view($webform)
      : ['#markup' => '<p>Webform no disponible.</p>'];

    $build['grid'] = [
      '#prefix' => '<section class="section"><div class="contact-grid">',
      '#suffix' => '</div></section>',
      'form' => $form,
      'side' => [
        '#type' => 'component',
        '#component' => 'byte:canal-directo',
        '#props' => [
          'channels_label' => 'Canales directos',
          'channels' => [
            ['name' => 'Email',    'value' => 'contacto@jalvarez.tech',   'href' => 'mailto:contacto@jalvarez.tech'],
            ['name' => 'Teléfono', 'value' => '+57 312 801 4078',         'href' => 'tel:+573128014078'],
            ['name' => 'WhatsApp', 'value' => 'wa.link/fb2acg',           'href' => 'https://wa.link/fb2acg'],
            ['name' => 'LinkedIn', 'value' => 'in/stevansalvarez ↗',      'href' => 'https://www.linkedin.com/in/stevansalvarez/'],
          ],
          'free_value' => '2',
          'free_label' => 'cupos libres en Q3 2026',
          'response_label' => 'Tiempo de respuesta',
          'response_value' => '< 24h lun-vie',
          'expect_label' => 'Cómo va a ser',
          'steps' => [
            'Leo tu mensaje completo y respondo con preguntas — no con un PDF de servicios.',
            'Si las respuestas alinean, agendamos 30 min para ver si la química sostiene un proyecto.',
            'Solo entonces escribo una propuesta con alcance, timeline y costo. 3-5 días.',
            'Si avanzamos: firma, anticipo del 40%, y arrancamos la semana siguiente. Sin sorpresas.',
          ],
        ],
      ],
    ];

    $build['#cache'] = [
      'tags' => ['config:webform.webform.contact'],
      'contexts' => ['languages:language_interface'],
    ];

    return $build;
  }

}
