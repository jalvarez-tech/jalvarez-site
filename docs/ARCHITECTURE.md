# Arquitectura del sitio — jalvarez.tech

> **Documento de referencia para agentes LLM y desarrolladores.**
> Este archivo describe la arquitectura **completa y autoritativa** del sitio.
> Antes de implementar cualquier cosa, leer este documento. Antes de tomar decisiones de arquitectura, actualizar este documento.

- **Última actualización:** 2026-05-01
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
- Drupal Canvas **solo** para páginas de marketing flexibles (Inicio, Proyectos, Notas, Contacto, futuras landings).
- Detalle de Proyecto y Nota usan **Twig + SDC**, no Canvas.
- DDEV como runtime local.
- Español default; inglés como traducción.
- Prefijos URL `/es` y `/en` con negociación por URL → browser → default.
- Reutilizar componentes SDC entre Canvas y plantillas de nodo.

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

| Página | URL ES | URL EN | Tipo |
|---|---|---|---|
| Inicio | `/es` | `/en` | Canvas Page |
| Proyectos (listado) | `/es/proyectos` | `/en/projects` | Canvas Page + View |
| Detalle proyecto | `/es/proyectos/{alias}` | `/en/projects/{alias}` | Node Project (SDC + Twig) |
| Notas (listado) | `/es/notas` | `/en/notes` | Canvas Page + View |
| Detalle nota | `/es/notas/{alias}` | `/en/notes/{alias}` | Node Note (SDC + Twig) |
| Contacto | `/es/contacto` | `/en/contact` | Canvas Page + Webform |
| Sobre mí (futuro) | `/es/sobre-mi` | `/en/about` | Canvas Page |

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
┌──── SINGLE DIRECTORY COMPONENTS ─────────────────────┐
│                                                      │
│  📐 PRIMITIVOS / LAYOUT                              │
│     ├─ section          (envoltura semántica)        │
│     ├─ container        (max-width + padding)        │
│     ├─ grid             (utility grid)               │
│     └─ stack            (vertical rhythm)            │
│                                                      │
│  🎨 BLOQUES DE INICIO                                │
│     ├─ banner-inicio    (Hero principal)             │
│     ├─ medible          (KPIs grandes)               │
│     ├─ como-lo-hago     (sección proceso)            │
│     ├─ que-construyo    (servicios/áreas)            │
│     ├─ metodo           (sección método)             │
│     └─ cta-final        (cierre + botón)             │
│                                                      │
│  🃏 CARDS                                            │
│     ├─ card-proyecto    (teaser proyecto)            │
│     ├─ card-proceso     (paso proceso)               │
│     ├─ card-testimonio  (testimonio)                 │
│     └─ row-nota         (nota en lista)              │
│                                                      │
│  📊 UI DATA                                          │
│     ├─ medidor          (barra/donut métrica)        │
│     ├─ row-metodo       (fila de método)             │
│     └─ palabras-cliente (wrapper testimonios)        │
│                                                      │
│  📩 CONTACTO                                         │
│     ├─ contacto         (formulario wrapper)         │
│     └─ canal-directo    (Email · WhatsApp · LinkedIn)│
└──────────────────────────────────────────────────────┘
```

Cada SDC tiene `*.component.yml` + `*.twig` + `*.css` con props traducibles vía Configuration Translation.

Estructura esperada en `web/themes/custom/byte/components/<nombre>/`:

```
<nombre>/
├── <nombre>.component.yml
├── <nombre>.twig
├── <nombre>.css
└── README.md (opcional)
```

---

## 8. Composición Canvas por página

### Inicio

```
[SDC] banner-inicio
[SDC] medible             (3-4 KPIs)
[SDC] que-construyo
[VIEW] featured_projects  (embebida)
[SDC] como-lo-hago
[SDC] palabras-cliente
[VIEW] featured_testimonials
[VIEW] recent_notes
[SDC] cta-final
```

### Proyectos (listado)

```
[SDC] hero-page
[VIEW] projects   (con filtros expuestos)
[SDC] cta-final
```

### Notas (listado)

```
[SDC] hero-page
[VIEW] notes      (con filtros expuestos)
[SDC] cta-final
```

### Contacto

```
[SDC] hero-page
[WEBFORM] contact
[SDC] canal-directo
```

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
- Metatag + Schema.org
- Search API + DB backend

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
9. ⏳ Pathauto + Metatag + Redirect.
10. ⏳ Pruebas de traducción y QA.

---

## 15. Referencias internas

> ⚠️ El sitio anterior `../jalvarez-web-site/` se consulta **solo como referencia visual y de contenido**: CSS, JS, paleta, tipografía, layout, animaciones, copy editorial y nombres de campos a nivel conceptual. **No se importa configuración YML.**

- Sitio anterior (referencia visual/contenido): `../jalvarez-web-site/`
- Theme y assets del sitio anterior (CSS/JS de referencia): `../jalvarez-web-site/web/themes/custom/`
- Templates Twig del sitio anterior (referencia de diseño): `../jalvarez-web-site/web/themes/custom/*/templates/`
- Plan de arquitectura previo (referencia conceptual): `../jalvarez-web-site/docs/plans/2026-04-29-jalvarez-site-architecture-design.md`
- Guía de agentes (DDEV/Drush): [AGENTS.md](../AGENTS.md)
