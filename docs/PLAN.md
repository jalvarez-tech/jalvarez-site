# PLAN — completar el sitio jalvarez.tech

> Plan en ejecución autónoma. Marcas: ✅ hecho · ⏳ en curso · ⬜ pendiente.
> Última actualización: 2026-05-02

## Estado actual del sitio (referencia rápida)

| URL | Status |
|---|---|
| `/` (home) | ✅ Hero + values + featured + process + tests + CTA |
| `/es/proyectos` | ✅ phead + 3 cards + CTA |
| `/es/notas` | ✅ phead + 5 rows + CTA |
| `/es/contacto` | ✅ phead + form ES + canal-directo |
| `/admin/*` | ✅ Gin + Navigation core sidebar |
| Top nav (`byte:nav-glass`) | ✅ Liquid glass, brand + 4 links + CTA + ES/EN |

## Plan de cierre — 7 fases en orden

### F1 — Form bullets polish ✅

- ✅ Selectores corregidos a `.js-webform-radios`, `.webform-type-radio`, `.js-form-type-radio`.
- ✅ Native radio hidden con opacity:0 + position:absolute (sigue keyboard-accessible).
- ✅ Pill labels: border outline → bg verde + text dark cuando checked.
- ✅ focus-visible outline para a11y.

### F2 — Detalle de Proyecto (case study) ✅

- ✅ SDC `byte:case-step-card` (number + tag + title + body).
- ✅ SDC `byte:medidor` (value + unit + key + note, variant flat/card).
- ✅ Template `node--project--full.html.twig` con back link + tags + title + brief + meta + §01-04 + CTA.
- ✅ `_case.scss` con todos los estilos editoriales del case study.
- ✅ Script `enrich-sample-projects.php` para popular Maluma con challenge_intro + bullets + 4 case_step paragraphs + lesson + testimonial_embed + CTA.
- ✅ Pathauto auto-genera `/es/proyectos/malumaonline`.

### F3 — Detalle de Nota (article) ✅

- ✅ Template `node--note--full.html.twig` con back, tags, title display, sub, byline + 3 action buttons (Lucide share/bookmark/copy), hero glyph fallback, body rich text con h2/h3/blockquote serif italic/code/pre, divider, newsletter CTA con italic acento.
- ✅ Read time computado server-side (200 wpm).
- ✅ Date formatting bilingüe ("abr 15, 2026" / "Apr 15, 2026").
- ✅ `_article.scss` con `.article__*` BEM completo.
- ✅ Pathauto auto-genera `/es/notas/<slug>`.

### F4 — Drupal Canvas integration ⬜

- ⬜ Agregar `field_canvas` al CT `page` (drupal/canvas field type).
- ⬜ Verificar que SDCs creadas (chip, button, banner-inicio, etc.) aparecen disponibles en el editor de Canvas.
- ⬜ Crear página Canvas "Sobre mí" como prueba de concepto.

### F5 — Responsive verification ⬜

Breakpoints a respetar (de `_tokens.scss` y design CSS):
- `mobile`: < 720px (1 col)
- `tablet`: 720-1080px (2 col en grids)
- `laptop`: 1080-1240px (3 col en projects-grid, etc.)
- `desktop`: > 1240px (max-width container)

Verificación:
- ⬜ Home en 375px, 768px, 1024px, 1440px
- ⬜ /proyectos card grid responde 1→2→3 columnas
- ⬜ /notas row layout: stack en mobile, 5-col en desktop
- ⬜ /contacto grid: 1 col en mobile, 2 col en desktop
- ⬜ phead title font sizes scale con clamp()
- ⬜ nav-glass: links → hamburger en mobile

### F6 — Mobile menu drawer (hamburger) ⬜

- ⬜ Actualizar `byte:nav-glass` template con botón hamburger visible solo en < 900px.
- ⬜ Drawer portal a `<body>` con animación slide-in desde derecha.
- ⬜ Lock body scroll cuando drawer abierto.
- ⬜ Link items con número (01, 02, 03, 04) según design.
- ⬜ JS vanilla mínimo: toggle, escape key close, click backdrop close.
- ⬜ Footer del drawer: CTA + lang toggle.

### F7 — Theme toggle (light/dark) ⬜

- ⬜ Agregar botón sun/moon en `byte:nav-glass` (junto a lang-toggle).
- ⬜ JS para toggle: lee/escribe `localStorage.jsa-theme`, set `data-theme` attr.
- ⬜ Inline script en `<head>` para evitar FOUC (ya existe en `byte_preprocess_html`, mejorar).
- ⬜ Iconos sun/moon de Lucide ya están en sprite.
- ⬜ CSS `[data-theme="light"]` ya existe en tokens — verificar que cubre todos los componentes.
- ⬜ Test en /, /proyectos, /notas, /contacto que el toggle persiste y no flickea.

## Cierre

- ⬜ Commit final + push → deploy a producción.
- ⬜ Actualizar `docs/DESIGN.md` y `docs/ARCHITECTURE.md` con cambios finales.
- ⬜ Update skill `drupal-hostinger-deploy` con cualquier nuevo aprendizaje.

## Convenciones operativas durante este plan

- Cada fase: trabajo local → build CSS → verificación visual → commit + push → seed prod si requiere data.
- Workflow `seed-content.yml` reusable para cualquier `.php` script en producción.
- Si un cambio no aplica vía `drush cim` (UUID mismatch), se ejecuta script directo en prod.
- Mantener idempotencia en todos los scripts.
