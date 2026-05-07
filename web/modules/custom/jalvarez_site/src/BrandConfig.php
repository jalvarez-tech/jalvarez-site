<?php

declare(strict_types=1);

namespace Drupal\jalvarez_site;

/**
 * Single source of truth for brand contact details.
 *
 * Centralizes the values previously duplicated across FooterBlock,
 * node--project--full.html.twig, llms.txt brand header, and the JSON-LD
 * Person node. Anything that names "John's email / phone / WhatsApp"
 * should pull from here.
 */
final class BrandConfig {

  /**
   * Display + send-to email.
   */
  public const string EMAIL = 'contacto@jalvarez.tech';

  /**
   * Human-readable phone (use in <dd> labels, NOT in href).
   */
  public const string PHONE = '+57 312 801 4078';

  /**
   * E.164-formatted phone for `tel:` href construction.
   */
  public const string PHONE_TEL = '+573128014078';

  /**
   * Bare digits for wa.me deep links: `https://wa.me/<digits>?text=…`.
   *
   * Don't use for tel: hrefs — use PHONE_TEL instead.
   */
  public const string WHATSAPP_PHONE = '573128014078';

  /**
   * Wa.link short URL for "click to chat" without a pre-filled message.
   */
  public const string WHATSAPP_LINK = 'https://wa.link/fb2acg';

  /**
   * Returns the brand info as an array shape consumable by Twig.
   *
   * @return array{email:string,phone:string,phone_tel:string,whatsapp_phone:string,whatsapp_link:string}
   *   Keyed by the same names exposed to Twig via PreprocessHook.
   */
  public static function toArray(): array {
    return [
      'email' => self::EMAIL,
      'phone' => self::PHONE,
      'phone_tel' => self::PHONE_TEL,
      'whatsapp_phone' => self::WHATSAPP_PHONE,
      'whatsapp_link' => self::WHATSAPP_LINK,
    ];
  }

}
