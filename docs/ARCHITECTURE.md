# Arquitectura del sitio — jalvarez.tech

> **Documento de referencia para agentes LLM y desarrolladores.**
> Este archivo describe la arquitectura **completa y autoritativa** del sitio.
> Antes de implementar cualquier cosa, leer este documento. Antes de tomar decisiones de arquitectura, actualizar este documento.

- **Última actualización:** 2026-05-02 — Las 4 páginas principales son `canvas_page` editables visualmente
- **Stack base:** Drupal CMS 11 · Drupal Canvas · DDEV · Theme `byte`
- **Diseño visual y copy:** ver [DESIGN.md](DESIGN.md) (tokens, componentes CSS↔SDC, contenido ES/EN, composición por página).
- **Deployment:** ver [DEPLOYMENT.md](DEPLOYMENT.md) (pipeline GitHub Actions → Hostinger, SCSS compilado en CI).
- **Idiomas:** Español (default) · Inglés
- **Carpeta de trabajo activa:** `jalvarez-site/`
- **Carpeta de referencia (sitio anterior):** `../jalvarez-web-site/` — **solo referencia visual y de contenido (CSS/JS/copy/diseño)**. NO se reutiliza su configuración YML.

---

## 1. Vista general

```
┌─────────────────────────────────────────────────────────────────┐
│                      jalvarez.tech                              │
│         Drupal CMS 11 · Drupal Canvas · Bilingüe ES/EN          │
└─────────────────────────────────────────────────────────────────┘
                                │
        ┌───────────────────────┼───────────────────────┐
        │                       │                       │
        ▼                       ▼                       ▼
┌───────────────┐      ┌────────────────┐      ┌────────────────┐
│ CAPA VISUAL   │      │ CAPA CONTENIDO │      │ CAPA SISTEMA   │
│               │      │                │      │                │
│ Theme: byte   │      │ Content Types  │      │ i18n ES / EN   │
│ + SDC custom  │      │ Taxonomías     │      │ Pathauto       │
│ Drupal Canvas │      │ Paragraphs     │      │ Metatag/SEO    │
└───────────────┘      │ Media          │      │ Redirect       │
                       │ Webforms       │      │ Search API     │
                       │ Views          │      └────────────────┘
                       └────────────────┘
```

### Decisiones fundacionales

- Drupal 11 **clásico** (no decoupled).
- **Las 4 páginas principales** (Inicio, Proyectos, Notas, Contacto) son entidades **`canvas_page`** editables 100% en el editor visual de Canvas. Cada una con traducción EN.
- **Listados dinámicos** (Proyectos, Notas) usan **block plugins custom** (`jalvarez_projects_grid`, `jalvarez_notes_grid`) que cargan los nodos y los renderizan via SDC. Estos blocks viven dentro del Canvas tree de su respectiva canvas_page — el editor puede moverlos/quitarlos como cualquier otro componente.
- **Contacto** combina SDC nativos (phead, canal-directo) + el `block.webform_block` configurado con `webform_id: contact`.
- **Detalle** de Proyecto y Nota: Twig + SDC en `node--project--full.html.twig` y `node--note--full.html.twig` — son rendering per-entity (no composición editorial), no usan Canvas.
- **Front page** del sitio: `system.site.page.front = /page/<id>` (la canonical de la canvas_page Inicio). Drupal Routing es language-aware vía `/page/{id}` y Canvas resuelve la translation correcta.
- **20 SDCs Byte** registrados como Canvas Component config entities (sin `noUi: true` después de F10). Solo `nav-glass` queda `noUi: true` por su lógica de active state que requiere block plugin.
- **30+ blocks** registrados (sistema, Views, webform, custom).
- DDEV como runtime local.
- Español default; inglés como traducción de cada canvas_page (mismas UUIDs de componentes, distintos `inputs` con texto traducido).
- Prefijos URL `/es` y `/en` con negociación por URL → browser → default.
- **`/canvas/editor/*` siempre sin prefijo de idioma** — un EventSubscriber (`CanvasLangPrefixStripper`) intercepta y redirige cualquier `/{lang}/canvas/editor/...` al sin prefijo, porque el SPA router de Canvas 1.3.x no soporta prefix.
- SDCs son la fuente de verdad visual: el Canvas editor, los block plugins custom y las plantillas de nodo los reutilizan.

---

## 2. Mapa de URLs y enrutamiento bilingüe

```
                        ROOT  /
                          │
            ┌─────────────┴─────────────┐
            ▼                           ▼
         /es/  (Español, default)    /en/  (English)
            │                           │
   ┌────────┼────────┬────────┐  ┌─────┼─────┬──────┬────────┐
   ▼        ▼        ▼        ▼  ▼     ▼     ▼      ▼        ▼
  /        /proyectos /notas /contacto /  /projects /notes /contact
 (Inicio) (listado)(listado)(Webform) (Home)(list) (list) (form)
            │        │
            ▼        ▼
   /proyectos/{slug} /notas/{slug}
   (Project detail) (Note detail)
```

| Página | URL ES | URL EN | Render |
|---|---|---|---|
| Inicio | `/es` (alias `/inicio`) | `/en` (alias `/home`) | **canvas_page id=1** (banner + sections + cta) |
| Proyectos | `/es/proyectos` | `/en/projects` | **canvas_page id=5** (phead + `block.jalvarez_projects_grid` + cta) |
| Detalle proyecto | `/es/proyectos/{alias}` | `/en/projects/{alias}` | Node Project — Twig + SDC, NO Canvas |
| Notas | `/es/notas` | `/en/notes` | **canvas_page id=6** (phead + `block.jalvarez_notes_grid` + cta) |
| Detalle nota | `/es/notas/{alias}` | `/en/notes/{alias}` | Node Note — Twig + SDC, NO Canvas |
| Contacto | `/es/contacto` | `/en/contact` | **canvas_page id=7** (phead + `block.webform_block` + canal-directo) |
| Otras landings (futuro) | `/es/<slug>` | `/en/<slug>` | Crear nueva canvas_page desde `/admin/content/pages` |
| Editor visual de cualquier canvas_page | `/canvas/editor/canvas_page/{id}` | mismo (sin prefix) | UI Canvas (Preact SPA + Radix UI) |

Pathauto patterns:

- `node/project`: `proyectos/[node:title]` (ES) · `projects/[node:title]` (EN)
- `node/note`: `notas/[node:title]` (ES) · `notes/[node:title]` (EN)
- `taxonomy_term`: `tag/[term:name]`

---

## 3. Modelo de contenido (Content Types)

### 3.1 `project` (Proyecto)

```
┌──────────────────────── PROJECT ────────────────────────┐
│  IDENTIDAD                                              │
│  ├─ title              (texto, requerido)               │
│  ├─ field_summary      (texto largo, resumen card)      │
│  ├─ field_intro        (texto largo formateado)         │
│  └─ field_publish_date (fecha)                          │
│                                                         │
│  CLASIFICACIÓN                                          │
│  ├─ field_project_category  → taxonomy: project_cat.    │
│  ├─ field_primary_technology → taxonomy: technologies   │
│  ├─ field_stack             → taxonomy: technologies[]  │
│  ├─ field_project_year      (entero)                    │
│  ├─ field_role              (texto)                     │
│  ├─ field_duration          (texto)                     │
│  ├─ field_external_url      (link, opcional)            │
│  ├─ field_featured_home     (booleano)                  │
│  └─ field_sort_order        (entero, peso manual)       │
│                                                         │
│  PORTADA / VISUAL                                       │
│  ├─ field_cover_media       → Media: image/video        │
│  ├─ field_cover_variant     (lista: a/b/c)              │
│  ├─ field_cover_hue         (lista: paleta tema)        │
│  └─ field_gallery           → Media[] (galería)         │
│                                                         │
│  CASO DE ESTUDIO ─ Paragraphs anidados                  │
│  ├─ field_challenge_intro   (texto largo)               │
│  ├─ field_challenge_bullets (texto repetible)           │
│  ├─ field_approach_steps    → Paragraph: case_step[]    │
│  ├─ field_results_metrics   → Paragraph: metric[]       │
│  ├─ field_lesson            (texto largo)               │
│  └─ field_testimonial       → Paragraph: testimonial    │
│                                                         │
│  CTA FINAL                                              │
│  ├─ field_cta_heading       (texto)                     │
│  └─ field_cta_sub           (texto largo)               │
│                                                         │
│  SEO/META (auto vía Metatag)                            │
└─────────────────────────────────────────────────────────┘
```

- Traducible: ✅
- Form modes: `default`
- View modes: `default`, `teaser`

### 3.2 `note` (Nota)

```
┌────────────────────────── NOTE ──────────────────────────┐
│  IDENTIDAD                                               │
│  ├─ title                (texto)                         │
│  ├─ body                 (texto largo formateado)        │
│  ├─ field_excerpt        (texto largo, resumen)          │
│  └─ field_publish_date   (fecha)                         │
│                                                          │
│  CLASIFICACIÓN                                           │
│  ├─ field_note_topic     → taxonomy: note_topics         │
│  └─ field_note_tags      → taxonomy: note_topics[]       │
│                                                          │
│  VISUAL                                                  │
│  ├─ field_featured_media → Media: image                  │
│  ├─ field_thumb_glyph    (lista: ícono)                  │
│  └─ field_thumb_hue      (lista: paleta tema)            │
│                                                          │
│  SEO/META (auto vía Metatag)                             │
└──────────────────────────────────────────────────────────┘
```

- Traducible: ✅
- View modes: `default`, `teaser`, `card`

### 3.3 `testimonial` (Testimonio)

```
┌─────────────────────── TESTIMONIAL ───────────────────────┐
│  Decisión: standalone CT (no solo Paragraph)              │
│  Permite reutilizarlo en Views y desde múltiples páginas. │
│                                                           │
│  ├─ title                (referencia interna)             │
│  ├─ field_quote          (texto largo)                    │
│  ├─ field_author_name    (texto)                          │
│  ├─ field_author_role    (texto)                          │
│  ├─ field_author_initials (texto, fallback avatar)        │
│  ├─ field_author_avatar  → Media: image (opcional)        │
│  ├─ field_related_project → reference: node project       │
│  ├─ field_featured       (booleano)                       │
│  └─ field_weight         (entero)                         │
└───────────────────────────────────────────────────────────┘
```

- Traducible: ✅

### 3.4 `page` (Canvas)

```
┌──────────────────────── PAGE (Canvas) ────────────────────────┐
│  Para Inicio, Proyectos (índice), Notas (índice),             │
│  Contacto, Sobre mí, y futuras landing pages.                 │
│                                                               │
│  ├─ title                                                     │
│  ├─ body          (texto largo, opcional)                     │
│  └─ field_canvas  → Drupal Canvas (composición visual)        │
└───────────────────────────────────────────────────────────────┘
```

- Traducible: ✅

---

## 4. Taxonomías

| Vocabulario | VID | Uso |
|---|---|---|
| Categorías de proyecto | `project_categories` | Web · Branding · Producto · IA · Otros |
| Tecnologías | `technologies` | Stack y tecnología principal de proyectos |
| Temas de notas | `note_topics` | Temas de blog: Drupal · Frontend · Diseño · Carrera |

Todas traducibles. Términos con Pathauto opcional.

---

## 5. Paragraphs

Estructuras anidadas usadas **solo dentro** de nodos (no en Canvas).

| Bundle | Campos | Usado en |
|---|---|---|
| `case_step` | `field_step_tag`, `field_step_title`, `field_step_body` | `project.field_approach_steps[]` |
| `metric` | `field_metric_key`, `field_metric_value`, `field_metric_note` | `project.field_results_metrics[]` |
| `testimonial` | `field_quote`, `field_author_name`, `field_author_role`, `field_author_initials` | `project.field_testimonial` |

> **Regla:** Paragraphs sirven a contenido editorial estructurado dentro de un nodo. Para componentes visuales reutilizables en Canvas → usar SDC.

---

## 6. Vistas (Views)

| View | Filtros | Display | Ubicación |
|---|---|---|---|
| `featured_projects` | `featured_home = 1` | block | Inicio |
| `projects` | `project_category`, `technology`, `year` (expuestos) | page `/proyectos` + block | Listado de proyectos |
| `related_projects` | misma categoría, excluye actual, límite 3 | block | Detalle de proyecto |
| `notes` | `note_topic` (expuesto) | page `/notas` | Listado de notas |
| `recent_notes` | orden `publish_date DESC`, límite 3 | block | Inicio |
| `related_notes` | mismo topic | block | Detalle de nota |
| `featured_testimonials` _(nuevo)_ | `featured = 1` | block | Inicio |

Todas las vistas con labels traducibles vía Configuration Translation.

---

## 7. SDC Components (theme `byte`)

```
┌──── SINGLE DIRECTORY COMPONENTS (web/themes/custom/byte/components/) ──────┐
│                                                                             │
│  ATOMS — props simples, totalmente Canvas-editables                         │
│    ├─ chip              (pill tag/label, variants default/accent)           │
│    ├─ button            (primary/ghost, optional arrow)                     │
│    ├─ eyebrow           (§ 0N + label, mono uppercase)                      │
│    ├─ phead             (page hero header con eyebrow + título + sub)       │
│    ├─ medidor           (big metric value/unit/key/note, variants)          │
│    ├─ case-step-card    (numbered step inside project case study)           │
│    ├─ value-card        (atom inside como-lo-hago)                          │
│    ├─ process-row       (atom inside metodo)                                │
│    ├─ card-proyecto     (project teaser, m1/m2/m3 metrics planas)           │
│    ├─ card-testimonio   (testimonial card)                                  │
│    └─ row-nota          (note row inside listings)                          │
│                                                                             │
│  HERO / CTA / MARQUEE — props planos                                        │
│    ├─ banner-inicio     (Home hero, KPIs aplanados m1_*..m3_*)              │
│    ├─ cta-final         (closing CTA band)                                  │
│    └─ marquee           (stack badges, items aplanados item_1..item_9)      │
│                                                                             │
│  MOLECULES — slot-driven (compose hijos en Canvas tree)                     │
│    ├─ section           (slot `default`)                                    │
│    ├─ como-lo-hago      (slot `values` ← value-card)                        │
│    ├─ metodo            (slot `steps` ← process-row)                        │
│    ├─ palabras-cliente  (slot `testimonials` ← card-testimonio)             │
│    └─ que-construyo     (slot `projects` ← card-proyecto)                   │
│                                                                             │
│  PROGRAMÁTICOS — `noUi: true`, NO aparecen en Canvas picker                 │
│    └─ nav-glass         (renderizado por jalvarez_nav_glass block)          │
│                         (canal-directo dejó de ser noUi tras aplanarse en F10) │
└─────────────────────────────────────────────────────────────────────────────┘

BLOCK PLUGINS CUSTOM (registrados como Canvas Component blocks):
  ├─ block.jalvarez_nav_glass         (top nav, language-aware active state)
  ├─ block.jalvarez_projects_grid     (carga node.project + renderiza SDC card-proyecto)
  └─ block.jalvarez_notes_grid        (carga node.note + renderiza SDC row-nota + read_time bilingüe)
```

### 7.1 Restricciones que impone Canvas a los SDCs

Para que un SDC sea registrable como Canvas Component config entity:

1. **Cada prop necesita `title`** — sin `title` Canvas lanza `ComponentDoesNotMeetRequirementsException`. Aplicado por `scripts/add-sdc-prop-titles.php`.
2. **Props no pueden ser `array of object`** — Canvas no tiene field type/widget que mapee a `[{name,value,…}]`. Soluciones:
   - Si el array tiene tamaño fijo (≤ 3): aplanar a `m1_*`, `m2_*`, `m3_*`.
   - Si el array tiene tamaño variable: convertir a slot, extraer el item-template a un SDC atómico.
3. **`array of string`** se acepta solo aplanado a posiciones fijas (`item_1`, `item_2`, …).
4. **Slots** se declaran con `slots:` en el YAML; en el Twig se renderizan con `{% block <slot> %}{{ <slot> }}{% endblock %}`.
5. Para SDCs que no necesitan UI (renderizados programáticamente desde controllers/blocks): añadir `noUi: true` al YAML — siguen funcionando como includes Twig pero no se ofrecen al editor.

### 7.2 Convenciones del directorio

```
web/themes/custom/byte/components/<nombre>/
├── <nombre>.component.yml   ← schema + slots + noUi flag
├── <nombre>.twig             ← markup (incluye slot blocks si aplica)
└── (opcional) README.md
```

Estilos viven en `web/themes/custom/byte/scss/components/_<nombre>.scss` (un archivo por SDC) y se importan desde `main.scss`.

---

## 8. Composición Canvas por página

Cada canvas_page se crea programáticamente la primera vez (idempotente, scripts en `scripts/`). Después del seed inicial el editor modifica libremente desde `/canvas/editor/canvas_page/{id}`. Las traducciones EN comparten UUIDs con la ES (mismo árbol estructural), solo cambian los inputs (textos).

### Inicio (canvas_page id=1) — `scripts/create-canvas-home.php`

20 instancias de componentes:

```
banner-inicio        (hero, props planos m1_*..m3_*)
marquee              (item_1..item_9)
como-lo-hago         (slot values)
  └─ value-card × 4  ← children en slot `values`
que-construyo        (slot projects)
  └─ card-proyecto × 3 ← children en slot `projects`
metodo               (slot steps)
  └─ process-row × 4 ← children en slot `steps`
palabras-cliente     (slot testimonials)
  └─ card-testimonio × 2 ← children en slot `testimonials`
cta-final
```

### Proyectos (canvas_page id=5) — `scripts/create-canvas-other-pages.php`

```
phead                                    (eyebrow + título + sub)
block.jalvarez_projects_grid             (carga Node::loadMultiple + renderiza card-proyecto SDCs)
cta-final
```

El listado dinámico vive dentro del Canvas tree como un block plugin custom. El editor puede mover/quitar el block; los project nodes se siguen administrando en `/admin/content`.

### Notas (canvas_page id=6)

```
phead                                    (eyebrow + título + sub)
block.jalvarez_notes_grid                (carga Node::loadMultiple + renderiza row-nota SDCs)
cta-final
```

`NotesGridBlock::build()` calcula `read_time` a 200 wpm y formatea fechas bilingüe (`abr · 2026` / `Apr · 2026`) según la language context.

### Contacto (canvas_page id=7)

```
phead                                    (eyebrow + título + sub)
block.webform_block { webform_id: contact, lazy: false }
canal-directo                            (3 canales aplanados c1_* c2_* c3_* + 5 steps step_1..step_5)
```

El Webform se renderiza vía el block standard de Drupal Webform module. canal-directo dejó de ser `noUi: true` (F10) tras aplanarse.

### Detalle de Proyecto (`node--project--full.html.twig`)

Twig template per-entity, NO Canvas. Compone:
- Cover (gradient OKLCH + browser mock)
- chips (category, year)
- Title + brief
- Meta line
- §01 Reto (challenge_intro + bullets)
- §02 Enfoque (case-step-card × N desde `field_approach_steps`)
- §03 Resultados (medidor × N desde `field_results_metrics`)
- §04 Lección (lesson + testimonial_embed)
- CTA final

### Detalle de Nota (`node--note--full.html.twig`)

Twig template per-entity, NO Canvas. Compone:
- Back link
- Tags + title display
- Sub
- Byline + 3 action buttons (share/bookmark/copy)
- Hero glyph fallback
- Body rich text (h2/h3/blockquote/code/pre)
- Divider + newsletter CTA

### Detalle de Proyecto (Twig template, NO Canvas)

`node--project--full.html.twig`:

```
[SDC] project-cover     (cover + meta)
[SDC] section           (challenge intro + bullets)
[SDC] approach-steps    (loop case_step paragraphs)
[SDC] medidor           (loop metric paragraphs)
[SDC] gallery
[SDC] card-testimonio   (testimonial paragraph)
[SDC] section           (lesson)
[VIEW] related_projects
[SDC] cta-final
```

### Detalle de Nota (Twig template)

`node--note--full.html.twig`:

```
[SDC] note-header   (título + meta + featured_media)
[body]              (texto formateado)
[VIEW] related_notes
[SDC] cta-final
```

---

## 9. Webforms

| Webform | Campos | Notas |
|---|---|---|
| `contact` | `name`, `email`, `company`, `project_type`, `budget`, `message` | Handler: email + log |

Opciones reutilizables (Webform Options):

- `project_types` — web, app, branding, IA, otros.
- `project_budgets` — rangos USD.

Traducible: ✅ vía Configuration Translation.

---

## 10. Capa multilingüe

```
┌──────────────── LANGUAGE STACK ────────────────┐
│  Idiomas:    🇪🇸 Español (default)  🇺🇸 English │
│                                                │
│  Negociación:                                  │
│   1. URL prefix  (/es, /en)                    │
│   2. Browser language                          │
│   3. Default                                   │
└────────────────────────────────────────────────┘
```

| Tipo de contenido | Mecanismo |
|---|---|
| Nodos | Content Translation |
| Términos de taxonomía | Content Translation |
| Paragraphs | Content Translation |
| Menús | Configuration Translation |
| Vistas (labels) | Configuration Translation |
| Bloques | Configuration Translation |
| Webforms | Configuration Translation |
| Strings UI / Twig `{% trans %}` | Interface Translation |
| SDC props (labels) | Configuration Translation |

---

## 11. Stack de módulos

### Core y estructura

- Drupal Core 11
- Drupal CMS recipes (starter)
- Drupal Canvas
- Paragraphs
- Single Directory Components

### i18n

- Language
- Content Translation
- Configuration Translation
- Interface Translation

### Contenido / UX

- Views (core)
- Webform
- Media + Media Library
- Pathauto + Redirect
- Metatag (core)
- Search API + DB backend

### SEO y discoverability

- **Metatag** (core) — defaults globales por preset (front, node, taxonomy_term, user, 403/404).
- **Simple XML Sitemap** + **Simple XML Sitemap Engines** — `/sitemap.xml` con hreflang ES/EN para canvas_page, node:project y node:note. Bundle settings exportados a `config/sync/`. Regenera vía `drush ssg` en cada deploy.
- **Custom** en `jalvarez_site.module`:
  - `hook_metatags_alter()` — backfill description en project/note desde `field_summary`/`field_excerpt` (el default `[node:summary]` está vacío para esos bundles).
  - `hook_page_attachments_alter()` — inyecta OG, Twitter Card, `<link rel="alternate" hreflang>`, author, theme-color y JSON-LD (`@graph`: `Person` + `WebSite` + `WebPage|BlogPosting|CreativeWork`) para canvas_page + project + note en la traducción activa.
- **Custom** `LlmsTxtController` + `LlmsTxtSubscriber` — sirve `/llms.txt` y `/llms-full.txt` siguiendo la convención [llmstxt.org](https://llmstxt.org). Bilingüe (secciones ES + EN). Subscriber prioridad 350 evita el redirect del language path-prefix. `CacheableResponse` con tags `canvas_page_list`, `node_list:project`, `node_list:note` → invalidación instantánea en cada edit.
- `web/robots.txt` versionado con `Sitemap:` + comentario `llms.txt`. Excluido del scaffold de `drupal/core` vía `composer.json` para no perderse en cada `composer install`.

### Custom

- Theme: `byte` (en `web/themes/custom/byte/`) — extender con SDCs.
- Module: `jalvarez_site` (en `web/modules/custom/jalvarez_site/`) — config + lógica.

---

## 12. Decisiones arquitectónicas

| # | Decisión | Estado | Notas |
|---|---|---|---|
| 1 | Idioma default `es`, `en` secundario | ✅ aprobado | Match con audiencia primaria |
| 2 | Detalle Proyecto/Nota usa Twig + SDC, NO Canvas | ✅ aprobado | Más control, mejor SEO, más rápido |
| 3 | Testimonios como CT standalone + Paragraph embebido | ✅ aprobado | Reutilización en Views |
| 4 | Paragraphs solo dentro de nodos; SDC para Canvas | ✅ aprobado | Separación clara |
| 5 | NO reutilizar YML de `jalvarez-web-site`; solo referencia visual (CSS/JS/diseño/copy) | ✅ aprobado | Arquitectura limpia desde cero, sin deuda técnica |
| 6 | Mantener tema `byte` y extender con SDCs | ✅ aprobado | Ya existe base |
| 7 | Migración de datos del sitio anterior | ⏳ pendiente | Empezar limpio o migrar |

---

## 13. Convenciones para agentes LLM

Cuando trabajes en este sitio:

1. **Antes de implementar:** lee este archivo y verifica que el cambio sigue el modelo aquí descrito.
2. **Si el cambio modifica la arquitectura:** actualiza este archivo en el mismo PR.
3. **Configuración:** vive en `web/modules/custom/jalvarez_site/config/install/` para defaults, y se exporta a `config/sync/` para cambios runtime.
4. **Custom code:** módulos en `web/modules/custom/`, theme en `web/themes/custom/byte/`.
5. **No editar Drupal core ni contrib in-place**.
6. **Comandos DDEV:** `ddev start`, `ddev composer`, `ddev drush`, `ddev launch`.
7. **NO importar config del sitio anterior.** Toda la configuración (content types, fields, paragraphs, views, taxonomías, webforms, SDC) se crea desde cero según este documento. El sitio anterior `../jalvarez-web-site/` se consulta **solo** como referencia visual y de contenido (CSS, JS, paleta, tipografía, layout, copy).
8. **Idioma de etiquetas y descripciones:** UI admin en español por default.
9. **Traducciones:** todo nuevo content type, taxonomy, paragraph, view, webform y SDC debe ser traducible.

---

## 14. Roadmap de implementación

1. ✅ Validar este mapa.
2. ⏳ Configurar idiomas (es/en) y prefijos URL.
3. ⏳ Crear vocabularios y content types desde cero según las secciones 3 y 4 de este documento.
4. ⏳ Crear Paragraphs y conectar a `project`.
5. ⏳ Crear Vistas con displays page+block.
6. ⏳ Scaffold de SDCs en `byte` (estructura mínima viable primero).
7. ⏳ Crear Canvas Pages una por una empezando por Inicio.
8. ⏳ Webform de contacto + opciones reutilizables.
9. ✅ Pathauto + Metatag + Redirect.
10. ✅ SEO (sitemap, llms.txt, OG/Twitter/JSON-LD, hreflang).
11. ⏳ Pruebas de traducción y QA.

---

## 15. Referencias internas

> ⚠️ El sitio anterior `../jalvarez-web-site/` se consulta **solo como referencia visual y de contenido**: CSS, JS, paleta, tipografía, layout, animaciones, copy editorial y nombres de campos a nivel conceptual. **No se importa configuración YML.**

- Sitio anterior (referencia visual/contenido): `../jalvarez-web-site/`
- Theme y assets del sitio anterior (CSS/JS de referencia): `../jalvarez-web-site/web/themes/custom/`
- Templates Twig del sitio anterior (referencia de diseño): `../jalvarez-web-site/web/themes/custom/*/templates/`
- Plan de arquitectura previo (referencia conceptual): `../jalvarez-web-site/docs/plans/2026-04-29-jalvarez-site-architecture-design.md`
- Guía de agentes (DDEV/Drush): [AGENTS.md](../AGENTS.md)
