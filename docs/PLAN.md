# PLAN — completar el sitio jalvarez.tech

> Plan en ejecución autónoma. Marcas: ✅ hecho · ⏳ en curso · ⬜ pendiente.
> Última actualización: 2026-05-02 (Pase WCAG 2.2 AA site-wide: focus-visible, prefers-reduced-motion, contraste --fg-dim, ARIA, touch targets)
>
> **Nota retrospectiva PR2 (2026-05-07):** las referencias a `scripts/X.php`
> a lo largo de este documento son históricas. La reorganización fue:
> · 49 scripts one-shot / diagnostic / duplicate borrados (ya aplicados en prod)
> · 4 supervivientes activos en `scripts/maintenance/` (con README)
> · 11 scripts SETUP en `scripts/maintenance/setup/` (audit pendiente; PR3)
> · 3 reusables ahora son Drush commands en `MaintenanceCommands.php`
>   (`drush jalvarez:audit-translations`, `pathauto-rebuild`, `test-wipe-guard`)
> · 1 mutación de field config movida a `jalvarez_site_update_10001()`
> · `lib/canvas-tree.inc.php` borrado junto con sus consumidores

## Estado actual del sitio (referencia rápida)

| URL | Render |
|---|---|
| `/`, `/es`, `/es/inicio` | **canvas_page id=1 (Inicio)** — banner-inicio + marquee + values/process/testimonials slot-driven + **`que-construyo` con `block.jalvarez_projects_grid` (only_featured=1, limit=3) en su slot** + cta-final |
| `/en`, `/en/home` | canvas_page id=1 (translation EN) |
| `/es/proyectos`, `/en/projects` | **canvas_page id=5 (Proyectos)** — phead + `block.jalvarez_projects_grid` + cta-final |
| `/es/notas`, `/en/notes` | **canvas_page id=6 (Notas)** — phead + `block.jalvarez_notes_grid` + cta-final |
| `/es/contacto`, `/en/contact` | **canvas_page id=7 (Contacto)** — phead + `block.webform_block` + `byte:canal-directo` |
| `/es/proyectos/{slug}`, `/en/projects/{slug}` | Node Project + `node--project--full.html.twig` (Twig + SDC, NO Canvas) |
| `/es/notas/{slug}`, `/en/notes/{slug}` | Node Note + `node--note--full.html.twig` |
| `/admin/*` | Gin admin theme + Drupal Navigation core sidebar |
| Top nav | Block `jalvarez_nav_glass` → SDC `byte:nav-glass` (active state per ruta + idioma) |
| Editor visual de cualquier canvas_page | `/canvas/editor/canvas_page/{id}` — accesible desde el toolbar Drupal Navigation > "Editar" |

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

### F4 — Drupal Canvas integration ✅

Migración completa a Canvas como mecanismo de composición de la home + páginas estáticas.

**SDCs registrados como Canvas Component config entities (19 total)**

Atómicos (8): `chip`, `button`, `eyebrow`, `phead`, `medidor`, `case-step-card`, `value-card`, `process-row`.
Sección molecules (5, slot-driven): `section`, `como-lo-hago` (slot `values`), `metodo` (slot `steps`), `palabras-cliente` (slot `testimonials`), `que-construyo` (slot `projects`).
Cards (3): `card-proyecto` (props planos `m1_key/m1_value`…`m3_key/m3_value`), `card-testimonio`, `row-nota`.
Hero/CTA (2): `banner-inicio` (props planos `m1_value/m1_unit/m1_label`…`m3_*`), `cta-final`.
Marquee (1): `marquee` (props planos `item_1`…`item_9`).

**SDCs marcados `noUi: true` (no aparecen en el editor Canvas, renderizados programáticamente)**

- `nav-glass` — renderizado por `jalvarez_nav_glass` block (links derivan de la ruta + idioma).
- `canal-directo` — renderizado por `ContactController` en `/contacto`.

**Razón técnica de la refactorización**

Canvas 1.3.x no soporta props `array of object` en su UI editor (sólo strings/integers/booleans/list_string). Decisiones aplicadas:
- Donde el array tiene 3 items fijos (KPIs hero, métricas card-proyecto): aplanar a `m1_*/m2_*/m3_*`.
- Donde el array tiene N items que pueden variar (cards en values/process/testimonials/projects grids): convertir el padre a slot, extraer el item-template a su propio SDC atómico.
- Donde el componente es de "una sola instancia" y no necesita edición visual (nav-glass, canal-directo): `noUi: true`.

**Render**

- Editor → `field_canvas` (component_tree) en nodos de tipo `page` → vista renderiza vía formatter `canvas_naive_render_sdc_tree` (configurado en `core.entity_view_display.node.page.default`).
- Front page del sitio = `/node/{nid}` (language-aware), donde nid corresponde al nodo "Inicio (Canvas)" creado por `scripts/create-canvas-home.php`.

**Scripts utilitarios (en orden de ejecución)**

1. `scripts/add-sdc-prop-titles.php` — Añade `title` a cada prop de los SDCs (Canvas lo exige).
2. `scripts/canvas-discover-sdcs.php` — Llama `ComponentSourceManager::generateComponents('sdc')` y registra los Component config entities.
3. `scripts/list-canvas-components.php` — Lista todos los Canvas Component config entities por source.
4. `scripts/create-canvas-home.php` — Crea el nodo "Inicio (Canvas)" con tree ES + traducción EN, y setea `system.site.page.front = /node/{nid}`.

**Deploy a producción**

Los scripts puntuales en prod se ejecutan vía SSH (`scp scripts/X.php hostinger:/tmp/ && ssh hostinger './vendor/bin/drush php:script /tmp/X.php'`). En el primer deploy post-migración:
1. `composer install`/`drush cim` traen los `canvas.component.sdc.byte.*.yml` y el `field_canvas`.
2. Correr `scripts/canvas-discover-sdcs.php` (idempotente, registra los Components si la sync no los trajo).
3. Correr `scripts/create-canvas-home.php` (idempotente, regenera el nodo "Inicio (Canvas)" y el page.front).

### F5 — Responsive verification ⬜

Breakpoints a respetar (de `_tokens.scss` y design CSS):
- `mobile`: < 720px (1 col)
- `tablet`: 720-1080px (2 col en grids)
- `laptop`: 1080-1240px (3 col en projects-grid, etc.)
- `desktop`: > 1240px (max-width container)

Verificación:
- ✅ Home en 375px renderiza con clamp() escalando título.
- ✅ Card-proyecto grid 1→2→3 cols (720px y 1080px breakpoints).
- ✅ Row-nota grid responde de stack a 5-col en ≥900px.
- ✅ Contact-grid: 1 col en mobile, 2 col en ≥980px.
- ✅ Phead/case/article titles scale via clamp(34-56px).
- ✅ Nav-glass: links desktop ocultos en <900px, hamburger visible.

### F6 — Mobile menu drawer (hamburger) ✅

- ✅ Template `nav-glass.twig` con `<button.nav__burger>` + `<div.nav-drawer>` portal.
- ✅ Drawer slide-in desde derecha con `transform: translateX` + cubic-bezier(.2, .7, .3, 1).
- ✅ Body scroll lock vía `body.nav-drawer-open { overflow: hidden }`.
- ✅ Drawer links con número 01/02/03/04 + arrow → animado al hover.
- ✅ JS vanilla en `nav.js`: toggle aria-expanded, escape close, backdrop click close, link click close.
- ✅ Footer del drawer: CTA "Disponible" + ES/EN toggle.

### F7 — Theme toggle (light/dark) ✅

- ✅ Botón circular `<button.theme-toggle>` con sun/moon overlapped, sun visible en light, moon visible en dark.
- ✅ JS toggle lee/escribe `localStorage.jsa-theme`, aplica `data-theme` attr.
- ✅ Inline script anti-FOUC en `byte_preprocess_html` mejorado: respeta prefers-color-scheme cuando no hay localStorage.
- ✅ Iconos sun/moon ya están en `icons.svg` sprite.
- ✅ Tokens light en `_tokens.scss` cubren bg, fg, line, accent-soft/line variantes.
- ✅ Toggle persiste tras navegación. Click directo via JS funcional.

### F10 — Migración completa de Proyectos, Notas y Contacto a canvas_page ✅

Tras lograr que el editor de Canvas funcione (F9), las páginas Proyectos, Notas y Contacto seguían en controllers (ProjectsController, NotesController, ContactController). Esta fase las migra a `canvas_page` entities idénticas a Inicio, manteniendo el mismo render visual.

**Estrategia: block plugins custom como puente Canvas ↔ datos dinámicos**

Las Views block (`block.views_block.projects-block_1`) renderizaban Lista plana (`<a href="...">Title</a>`), no las cards SDC. Para conservar el rendering original de los SDC `card-proyecto` y `row-nota`, creé dos block plugins que cargan los nodos y renderizan los SDCs como `'#type' => 'component'`:

- [ProjectsGridBlock](web/modules/custom/jalvarez_site/src/Plugin/Block/ProjectsGridBlock.php) — id `jalvarez_projects_grid`. Reusa el mapeo de fields del antiguo ProjectsController (category, year, hue, m1_*..m3_*).
- [NotesGridBlock](web/modules/custom/jalvarez_site/src/Plugin/Block/NotesGridBlock.php) — id `jalvarez_notes_grid`. Reusa lógica del antiguo NotesController, incluyendo cálculo de read_time a 200 wpm y formato de fecha bilingüe ("abr · 2026" / "Apr · 2026").

Canvas los registra automáticamente como `block.jalvarez_projects_grid` y `block.jalvarez_notes_grid` (block source plugin discover los detecta vía `#[Block]` attribute y `cache_clear`).

**Refactor de canal-directo (de noUi a Canvas-editable)**

`canal-directo` tenía `noUi: true` por sus props `array of object` (channels) y `array of string` (steps). Para que apareciera en el editor de Contacto, lo refactoricé:

- `channels[]` → `c1_name/c1_value/c1_href` … `c3_*` (3 canales máximo)
- `steps[]` → `step_1` … `step_5` (5 pasos máximo)
- Eliminado `noUi: true` de canal-directo.component.yml
- Twig usa `|filter(c => c.name or c.value)` para construir un array virtual sólo con los canales/pasos no vacíos

**Webform incrustado vía `block.webform_block`**

Para `Contacto`, el formulario se incrusta como Canvas component con settings `webform_id: 'contact'`, `lazy: false`. Canvas registra automáticamente `block.webform_block` cuando el módulo Webform está habilitado.

**Eliminado**

- `web/modules/custom/jalvarez_site/src/Controller/{Projects,Notes,Contact,CanvasEditController}.php`
- `web/modules/custom/jalvarez_site/jalvarez_site.links.task.yml`
- 3 rutas en `jalvarez_site.routing.yml` (queda solo `/styleguide`)
- Campo `field_canvas` de `node.page` (junto con su FieldStorage) — Canvas vive ahora en su entity nativa `canvas_page`

**Estado final del sitio**

| URL | Render | Editor visual |
|---|---|---|
| `/`, `/es`, `/es/inicio` | canvas_page id=1 (Inicio) | `/canvas/editor/canvas_page/1` |
| `/en`, `/en/home` | canvas_page id=1 (translation EN) | mismo |
| `/es/proyectos` | canvas_page id=5 → phead + `block.jalvarez_projects_grid` + cta-final | `/canvas/editor/canvas_page/5` |
| `/en/projects` | canvas_page id=5 (translation EN) | mismo |
| `/es/notas` | canvas_page id=6 → phead + `block.jalvarez_notes_grid` + cta-final | `/canvas/editor/canvas_page/6` |
| `/en/notes` | canvas_page id=6 (translation EN) | mismo |
| `/es/contacto` | canvas_page id=7 → phead + `block.webform_block` (contact) + canal-directo SDC | `/canvas/editor/canvas_page/7` |
| `/en/contact` | canvas_page id=7 (translation EN) | mismo |
| `/es/proyectos/{slug}` | Node Project + `node--project--full.html.twig` (Twig + SDC, NO Canvas) | n/a (entidades dinámicas) |
| `/es/notas/{slug}` | Node Note + `node--note--full.html.twig` | n/a |

**Componentes Canvas finales (registrados como Component config entities)**

- 20 SDC Byte (`sdc.byte.*`)
- 30+ blocks: sistema (`system_branding_block`, `system_menu_block.*`, etc.), Views (`featured_projects`, `featured_testimonials`, `recent_notes`, etc.), webform (`webform_block`), nav (`jalvarez_nav_glass`), grids custom (`jalvarez_projects_grid`, `jalvarez_notes_grid`)

**Scripts añadidos / modificados**

- `scripts/lib/canvas-tree.inc.php` — Helpers `canvas_tree_uuid()`, `canvas_tree_sdc_item()`, `canvas_tree_block_item()` reutilizables.
- `scripts/create-canvas-other-pages.php` — Crea las 3 canvas_pages (Proyectos + Notas + Contacto) ES+EN. Idempotente.
- `scripts/add-notes-block-display.php` — Añade `block_1` display a la View `notes` (idempotente, aunque ya no se usa porque vamos con `jalvarez_notes_grid`).
- `scripts/cleanup-node-page-field-canvas.php` — Elimina FieldConfig + FieldStorage de field_canvas del bundle node.page.
- `scripts/list-views.php`, `list-notes.php`, `test-notes-block.php` — Diagnostics.

**Deploy a producción**

Correr vía SSH (`scp scripts/X.php hostinger:/tmp/ && ssh hostinger './vendor/bin/drush php:script /tmp/X.php'`) en orden:
1. `scripts/canvas-discover-sdcs.php` (registra SDCs)
2. `scripts/create-media-image-type.php` (Canvas requirement)
3. `scripts/create-canvas-home.php` (Inicio + setea page.front)
4. `scripts/create-canvas-other-pages.php` (Proyectos + Notas + Contacto)
5. (opcional) `scripts/cleanup-node-page-field-canvas.php` (limpia field obsoleto)

### F9 — Editor visual de Canvas accesible desde la UI ✅

Diagnóstico revelado al validar `https://jalvarez-site.ddev.site/`: el botón "Editar" abría una pantalla blanca. Tres bugs encadenados de Canvas 1.3.x:

**Bug 1: Canvas 1.3.x solo edita `canvas_page` entities, NO `node.page` con `field_canvas`**

`/canvas/api/v0/layout/node/13` retornaba HTTP 500: `"For now Canvas only works if the entity is a canvas_page! Other entity types and bundles must use content templates for now, see https://drupal.org/i/3498525"`. La storage class `ComponentTreeLoader::getCanvasFieldName()` rechaza cualquier entity que no sea `canvas_page`.

**Fix:** Migrado el contenido de Inicio de `node.page` a `canvas_page` (entity nativa de Canvas con campos base `title`, `description`, `components`, `path`, `image`). El script `scripts/create-canvas-home.php` ahora hace `Page::create([...])` en lugar de `Node::create([...])`.

**Bug 2: `canvas_page` exige al menos un `media_type: image` configurado**

`/canvas/api/v0/layout/canvas_page/1` retornaba HTTP 500: `"Call to undefined method Drupal\Core\Field\BaseFieldDefinition::id()"` desde `MediaLibraryWidget::getNoMediaTypesAvailableMessage()`. Sin un media type de tipo `image` en el sitio, Canvas no puede generar el form widget para el campo base `image` de canvas_page.

**Fix:** Nuevo script `scripts/create-media-image-type.php` que crea el `media_type: image` con su `field_media_image` (storage + config) y form/view displays. Idempotente.

**Bug 3: Prefijo de idioma `/es/` o `/en/` rompe el SPA router de Canvas**

`/es/canvas/editor/canvas_page/1` cargaba todos los assets (200 OK) pero el editor renderizaba en blanco. La store de Canvas mostraba `configuration.entity = "none"` y `isNew = true` — el router interno SPA parsea `window.location.pathname` y no reconoce el patrón cuando hay prefijo de idioma. Issue upstream: https://www.drupal.org/project/canvas/issues/3489775.

**Fix:** Implementado `Drupal\jalvarez_site\EventSubscriber\CanvasLangPrefixStripper` que intercepta requests a `/es/canvas/editor/...` o `/en/canvas/editor/...` y redirige (302) al equivalente sin prefijo. Registrado vía `jalvarez_site.services.yml` con tag `event_subscriber`. Funciona transparente para el usuario: clicar "Editar" en el toolbar desde `/es/inicio` lleva al editor visual sin romper.

**Estado final del flow editorial**

1. Admin navega a `/`, `/es`, `/en`, `/es/inicio`, `/en/home` o `/page/1` — todas resuelven a la canvas_page.
2. En el toolbar Drupal Navigation aparece el botón "Editar" (entity.canvas_page.edit_form) que apunta a `/canvas/editor/canvas_page/1`.
3. Si el admin estaba en `/es/...`, Drupal genera el link con prefijo (`/es/canvas/editor/...`); el EventSubscriber lo redirige al sin prefijo.
4. Canvas SPA bootstrap, monta el editor visual con preview en vivo + sidebar de Page data + paneles de Add/Layers/Code/Pages/Library.

**Scripts añadidos esta fase**

- `scripts/create-media-image-type.php` — Crea `media_type: image` (idempotente).
- `scripts/list-media-types.php` — Diagnóstico de media types existentes.
- `scripts/check-canvas-page.php` — Diagnóstico de canvas_page entities y permisos del admin.

**Configs nuevos en `config/sync`**

- `media.type.image.yml`
- `field.storage.media.field_media_image.yml`
- `field.field.media.image.field_media_image.yml`
- `core.entity_form_display.media.image.default.yml`
- `core.entity_view_display.media.image.default.yml`
- `system.site.yml` (page.front actualizado a `/page/1`)

### F8 — Form displays user-friendly + Canvas UI access ✅

Hasta esta fase los nodos `project` y `note` no exponían sus campos en el form de edición (todos quedaban en `hidden:`), y el editor visual de Canvas no era accesible vía UI.

**Form displays con field_group (vertical tabs)**

- ✅ `node.project` — 25 campos en 6 vertical tabs:
  - Identidad (resumen, intro, fecha)
  - Clasificación (categoría, tecnología, stack, año, rol, duración, URL externa, destacado, peso)
  - Visual (cover media + variant + hue, galería)
  - Caso de estudio (challenge intro/bullets, approach steps paragraphs, results metrics paragraphs, lesson, testimonial embed)
  - CTA final (heading, sub)
  - Publicación (estado, autor, fecha, URL alias, redirects, langcode, translation)
- ✅ `node.note` — 9 campos en 4 vertical tabs:
  - Identidad (excerpt, body, fecha)
  - Clasificación (topic, tags)
  - Visual (featured media, glyph, hue)
  - Publicación
- ✅ `node.page` — 2 vertical tabs (`field_canvas` permanece en `hidden:` por diseño — Canvas no expone widget para `component_tree`):
  - Básicos (body como fallback opcional)
  - Publicación

**Local task “Editor visual (Canvas)”**

- ✅ Ruta wrapper `jalvarez_site.node.canvas_edit` (`/node/{node}/canvas-edit`) → redirige a `/canvas/editor/node/{nid}` (la app de Canvas).
- ✅ Local task tab declarado en `jalvarez_site.links.task.yml` con `base_route: entity.node.canonical`.
- ✅ Access check `CanvasEditController::access` solo permite el tab cuando el bundle del nodo expone un campo `component_tree` (actualmente `node.page`).
- ✅ Visible en el toolbar dropdown de Drupal Navigation (junto a Edit / Delete / Translations) cuando un admin visita `/node/13`.

**Script de configuración**

- `scripts/configure-form-displays.php` — Idempotente. Borra y recrea form displays + grupos en project, note y page. Correr una vez después de deploy o tras cambios en field schemas.

### F11 — Home: sección "Qué construyo" reemplazada por block dinámico ✅

Antes la sección §02 del home tenía 3 `card-proyecto` SDCs **hardcoded en el Canvas tree** dentro del slot `projects` de `que-construyo`. Si cambiaba un proyecto (título, métricas, cover_hue) había que reeditar el Canvas tree.

**Solución:** un único bloque dinámico que carga proyectos desde la base de datos.

**Cambios al `ProjectsGridBlock`** ([src/Plugin/Block/ProjectsGridBlock.php](web/modules/custom/jalvarez_site/src/Plugin/Block/ProjectsGridBlock.php))

3 nuevos settings configurables vía `blockForm()`:

| Setting | Tipo | Default | Uso |
|---|---|---|---|
| `only_featured` | bool | FALSE | Filtra `field_featured_home = 1` (home). En `/proyectos` queda en FALSE para mostrar todos. |
| `limit` | int | 0 | `0` = sin límite. En el home = `3`. |
| `wrap` | string | `'section'` | `section` (uso autónomo en `/proyectos`), `grid` (parent provee section), `none` (parent provee section + grid; usado en el slot del home). |

**Config schema declarado** ([config/schema/jalvarez_site.schema.yml](web/modules/custom/jalvarez_site/config/schema/jalvarez_site.schema.yml))

`block.settings.jalvarez_projects_grid` con typed config schema. Sin esto, Canvas `validateComponentInput` rechaza los keys con `'X' is not a supported key`.

**Composición final del home §02 ("qué construyo")**

```
canvas_page id=1 (Inicio)
  …
  ┌─ que-construyo (SDC, slot-driven)
  │    eyebrow: "qué construyo §02"
  │    title: "Y resulta que con ese enfoque salen plataformas como estas."
  │    cta_label: "Ver todo el trabajo" → /proyectos
  │    └─ slot `projects`:
  │         block.jalvarez_projects_grid
  │           only_featured: 1
  │           limit: 3
  │           wrap: 'none'   ← parent (que-construyo) provee section.wrap + .projects-grid
  │           → carga node.project WHERE field_featured_home=1 ORDER BY field_sort_order ASC LIMIT 3
  │           → renderiza cada uno como SDC byte:card-proyecto
  └─ …
```

Para `/proyectos` (canvas_page id=5), el mismo block plugin se usa con `only_featured: 0, limit: 0, wrap: 'section'` (default) — uso autónomo.

**Bug visual encontrado y resuelto: nested-grid breakage**

Canvas envuelve cada componente en `<div id="block-{uuid}">` para selección en editor. Al slottear el block dentro de `que-construyo.projects` (que ya tiene `.projects-grid` como wrapper), el grid CSS quedaba con un solo hijo (el wrapper) y las cards se apilaban verticalmente.

**Fix** en [scss/main.scss](web/themes/custom/byte/scss/main.scss): `display: contents` para los wrappers Canvas dentro de los containers del theme:

```scss
.projects-grid > div[id^="block-"],
.notes-list > div[id^="block-"],
.values > div[id^="block-"],
.process-list > div[id^="block-"],
.tests > div[id^="block-"] {
  display: contents;
}
```

`display: contents` hace que el wrapper "desaparezca" del flujo de layout — sus hijos se convierten en hijos directos del padre (el grid container).

**Scripts añadidos / modificados**

- [`scripts/regenerate-canvas-blocks.php`](scripts/regenerate-canvas-blocks.php) — Fuerza `ComponentSourceManager::generateComponents()` para que Canvas re-discover los nuevos settings de los block plugins. Necesario después de modificar `defaultConfiguration()` de cualquier Block plugin custom (sin esto Canvas valida contra el schema viejo cacheado).
- [`scripts/mark-featured-projects.php`](scripts/mark-featured-projects.php) — Marca los 3 proyectos sample como `field_featured_home=1` con `field_sort_order` 1/2/3. Idempotente.
- [`scripts/create-canvas-home.php`](scripts/create-canvas-home.php) — Actualizado: añadido helper inline `block_tree_item()`, removidas las 3 `card-proyecto` SDCs hardcoded del slot `projects`, reemplazadas por una única instancia de `block.jalvarez_projects_grid`. Idempotente.

**Beneficio editorial**

Ahora cuando el editor crea/edita un proyecto y marca el checkbox "Destacado en home" (`field_featured_home`), automáticamente:
- Aparece en el §02 del home si está dentro del top 3 por `field_sort_order`
- Reemplaza al que estaba antes
- El editor NO toca el Canvas tree del home — solo edita el proyecto en `/admin/content`

### F12 — Accesibilidad WCAG 2.2 AA site-wide ✅

Auditoría completa + fixes aplicados en commit `a5ea40f`. Producción verificada (CSS agregado contiene los nuevos tokens, HTML renderiza los nuevos atributos ARIA en `/es` y `/en`).

**Indicadores de foco** ([scss/main.scss](web/themes/custom/byte/scss/main.scss))

- `:focus-visible` ring global (3px `var(--accent)`, offset 2px) — usa `:focus-visible` para que mouse users no lo vean.
- Variantes:
  - `.btn`, `.nav__cta`, `.lang-toggle__btn`, `.theme-toggle`, `.chip`, `.nav__link` → `border-radius: var(--r-pill)` + offset 3px.
  - `.project`, `.post`, `.case-step`, `.contact-card` → `border-radius: var(--r-card)` + offset 4px.
- Skip-link de Drupal core (`.visually-hidden.focusable:focus`) ahora visible al focus con estilo byte (`background: var(--accent)`, `border-radius: var(--r-pill)`).
- `.visually-hidden` redeclarada en main.scss porque el theme depende solo de `core/drupal` (no de `system/base`).

**Reduced motion** ([scss/main.scss](web/themes/custom/byte/scss/main.scss))

```scss
@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after {
    animation-duration: 0.01ms !important;
    transition-duration: 0.01ms !important;
    scroll-behavior: auto !important;
  }
  .marquee__track { animation: none !important; }
  .project:hover .project__media, .post:hover { transform: none !important; }
}
```

**Jerarquía de headings** ([components/canal-directo/canal-directo.twig](web/themes/custom/byte/components/canal-directo/canal-directo.twig))

`<h4>` → `<h2 class="contact-card__title">` en las 3 cards. La página Contacto (canvas_page id=7) no tiene h3 padre en el árbol, así que h4 era heading huérfano. SCSS migrado de selector `h4` a `&__title`.

**Newsletter form** ([templates/node--note--full.html.twig](web/themes/custom/byte/templates/node--note--full.html.twig))

`<input type="email">` con solo `placeholder` → ahora con `<label class="visually-hidden">` real + `autocomplete="email"`. Strings nuevos `newsletter_label` en ambos i18n maps (ES/EN).

**Contraste de color** ([scss/_tokens.scss](web/themes/custom/byte/scss/_tokens.scss))

Calculado vía fórmula WCAG (luminance relativa) sobre los backgrounds reales del theme:

| Token | Antes | Después | Tema |
|---|---|---|---|
| `--fg-dim` | `#6b6760` (3.49:1 ❌) | `#80796f` (4.68:1 ✓ AA) | Dark sobre `#0a0908` |
| `--fg-dim` | `#8a8478` (3.48:1 ❌) | `#6e6a60` (5.03:1 ✓ AA) | Light sobre `#faf8f4` |

`--fg-muted` ya pasaba AAA en ambos temas (7.80:1 dark, 7.27:1 light).

**Touch targets (WCAG 2.2 SC 2.5.5/2.5.8)** ([scss/components/_nav-glass.scss](web/themes/custom/byte/scss/components/_nav-glass.scss))

| Elemento | Antes | Después |
|---|---|---|
| `.nav__burger` (mobile) | 36×36 | **44×44** |
| `.lang-toggle__btn` (desktop) | ~21×30 (fail AA min) | `min-height: 26px` + flex centering ≥ 24×24 |
| `.lang-toggle__btn` (drawer mobile) | ~21×30 | **44×44** (`min-width` + `min-height`) |

**ARIA states en navegación** ([components/nav-glass/nav-glass.twig](web/themes/custom/byte/components/nav-glass/nav-glass.twig) + [js/nav.js](web/themes/custom/byte/js/nav.js))

- `aria-current="page"` en link activo (desktop nav + drawer).
- `aria-current="true"` + `lang/hreflang` en `.lang-toggle__btn.is-on`.
- `<nav aria-label="Principal/Primary">` en desktop y drawer.
- `<div role="group" aria-label="Idioma/Language">` envolviendo lang-toggle.
- Burger: `aria-controls="nav-drawer"` + `aria-label` dinámico Open/Close vía JS (`data-label-open` + `data-label-close` para i18n sin tocar JS).
- Drawer: atributo `inert` cuando está oculto (skip total de tab-focus en lugar de solo `aria-hidden`). JS sincroniza `aria-expanded`, `aria-label`, `aria-hidden` e `inert` en cada toggle.
- SVGs con `aria-hidden="true"` + `focusable="false"` (los íconos del theme-toggle).

**Verificación post-deploy**

```bash
# CSS agregado en producción
curl -s https://jalvarez.tech/es | grep css_ | sed 's/&amp;/\&/g'
# → grep --fg-dim, focus-visible, nav__burger 44px

# ARIA en HTML
curl -s https://jalvarez.tech/es | grep -E 'aria-current|aria-controls|inert|data-label-'
# → 2× aria-current="page", 2× aria-current="true", inert, data-label-open/close
```

### F13 — SEO discoverable (metatags, OG/Twitter/JSON-LD, sitemap, llms.txt) ✅

**Qué se entregó:**

- 4 canvas_pages con `metatags` JSON poblado (ES + EN) — title + description SEO-optimizados via `scripts/update-seo-metatags.php`.
- `jalvarez_site_metatags_alter()` — backfill description en project/note desde `field_summary`/`field_excerpt`.
- `jalvarez_site_page_attachments_alter()` — inyecta OG, Twitter Card, hreflang, author, theme-color, JSON-LD (`Person + WebSite + WebPage|BlogPosting|CreativeWork`) en canvas_page + project + note.
- `simple_sitemap` + `simple_sitemap_engines` habilitados vía config; `/sitemap.xml` con 18 URLs y hreflang. Bundle settings sembrados con `scripts/configure-simple-sitemap.php`.
- `LlmsTxtController` + `LlmsTxtSubscriber` → `/llms.txt` y `/llms-full.txt` bilingües, `CacheableResponse` con tags que invalidan al editar.
- `web/robots.txt` con `Sitemap:` + comentario llms; excluido del scaffold de `drupal/core` para no perderse en `composer install`.
- `deploy.yml`: paso `drush ssg -y` post-cim. `DEPLOYMENT.md` y `ARCHITECTURE.md` documentan el sistema.

**Verificado vía `curl`:**
```bash
curl -sI https://jalvarez.tech/llms.txt        # → 200 text/plain (sin redirect a /es/)
curl -s  https://jalvarez.tech/sitemap.xml | grep -c '<url>'  # → 18
curl -s  https://jalvarez.tech/inicio | grep -E '<(title|meta property="og:|link rel="alternate")'
```

## Cierre

- ✅ Commit final + push → deploy a producción.
- ✅ Actualizar `docs/DESIGN.md` y `docs/PLAN.md` con cambios finales (incluida sección a11y).
- ⏳ Update skill `drupal-hostinger-deploy` con aprendizajes (UUID sync, library cache, drush cim por entity).

## Convenciones operativas durante este plan

- Cada fase: trabajo local → build CSS → verificación visual → commit + push → seed prod si requiere data.
- Para scripts puntuales en prod: `scp scripts/X.php hostinger:/tmp/ && ssh hostinger './vendor/bin/drush php:script /tmp/X.php && rm /tmp/X.php'`.
- Si un cambio no aplica vía `drush cim` (UUID mismatch), se ejecuta script directo en prod.
- Mantener idempotencia en todos los scripts.
