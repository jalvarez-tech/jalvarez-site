# Diseño visual y contenido — jalvarez.tech

> **Documento de referencia para implementación de tema, SDC y copy.**
> Acompaña a `ARCHITECTURE.md`. Aquí vive el diseño y el contenido finales aprobados; allá vive la arquitectura Drupal.
> Fuente: handoff bundle de Claude Design (claude.ai/design) extraído a `/tmp/jalvarez-design/jalvarez-tech/` (ver sección 12).

- **Última actualización:** 2026-05-01
- **Marca:** John Stevans Alvarez · `jalvarez.tech` · monograma "JSA" · Medellín, CO
- **Estilo:** editorial-developer, dark-first, ritmo numerado por secciones (`§ 0N`).
- **Origen:** prototipo React + Babel UMD (no se reutiliza código JSX, solo el CSS y el copy).

---

## 1. Identidad y tono

### Marca

| Campo | Valor |
|---|---|
| Nombre | John Stevans Alvarez |
| Monograma | JSA (cuadro acento, esquina redondeada 7px) |
| Dominio | `jalvarez.tech` |
| Ubicación | Medellín, CO (zona horaria COT, GMT-5) |
| Email | `contacto@jalvarez.tech` |
| Teléfono | `+57 312 801 4078` |
| WhatsApp | `https://wa.link/fb2acg` |
| Posicionamiento | "Senior Web Specialist" |

### Posicionamiento principal (hero)

- **ES:** "Creo que una web *rápida*, ~~honesta~~ e inclusiva es una forma de respeto."
- **EN:** "I believe a *fast*, ~~honest~~ and inclusive web is a form of respect."

> Énfasis tipográfico: `*rápida/fast*` en cursiva acento, `~~honesta/honest~~` con `text-stroke` (outlined). Última línea en `var(--fg-muted)`.

### Voz

- Primera persona, honesta, anti-hype.
- Estructura editorial "círculo dorado": Por qué → Cómo → Qué.
- Sin features-list, sin promesas vagas. Cada sección defiende una creencia.
- Inglés y español como ciudadanos de primera clase. Toggle `ES/EN` en nav.

---

## 2. Tokens de diseño

### Colores

```css
/* Dark (default) */
--bg:           #0a0908;
--bg-elev:      #111110;
--bg-sunk:      #060605;
--fg:           #f5f2ed;
--fg-muted:     #a8a39a;
--fg-dim:       #6b6760;
--line:         #1f1d1a;
--line-strong:  #2a2724;

/* Light */
--bg:           #faf8f4;
--bg-elev:      #f2efe9;
--bg-sunk:      #ebe7df;
--fg:           #14120f;
--fg-muted:     #575249;
--fg-dim:       #8a8478;
--line:         #e3ded4;
--line-strong:  #d4cfc3;

/* Accent — sistema dual: verde estático + naranja interactivo */
--accent:        #20d958;   /* verde "deploy" — default en todos los usos del acento */
--accent-soft:   #20d95822;  /* fondos suaves (chip.accent bg, success-note bg) */
--accent-line:   #20d95855;  /* bordes de acento (chip.accent border, success-note border) */
--accent-hover:  #ff6b1a;   /* naranja editorial — solo en hover de elementos interactivos */
--accent-hover-soft: #ff6b1a22;
--accent-hover-line: #ff6b1a55;
```

#### Sistema de acento dual (decisión confirmada)

- **Estado default = verde `#20d958`** en todos los usos del acento: títulos italic, comillas de testimonios, bullets de listas, eyebrow-num, chip.accent, value-icon, line-indicators, body underlines, focus borders, success notes.
- **Estado hover = naranja `#ff6b1a`** únicamente en elementos interactivos: `.btn-primary` background, `.btn-arrow ::after`, `.nav-cta` hover, `.nav-link.active::before`, `.project-name .arrow` hover, `.plist-arr` hover, `.post:hover .post-title`, `.channel-val:hover`, `.case-link:hover`, `.art-action:hover`, `.article-back:hover`.

```css
/* Patrón canónico para interactivos */
.element                  { color: var(--accent); }              /* verde estático */
.element:hover            { color: var(--accent-hover); }        /* naranja al hover */

/* Botón primario */
.btn-primary              { background: var(--accent); color: #0a0908; }
.btn-primary:hover        { background: var(--accent-hover); }

/* Chip y soft backgrounds en hover */
.chip.accent              { background: var(--accent-soft); border-color: var(--accent-line); color: var(--accent); }
.chip.accent:hover        { background: var(--accent-hover-soft); border-color: var(--accent-hover-line); color: var(--accent-hover); }
```

> Las clases que usan acento como **decoración estática** (italic en títulos, `::before/::after` de comillas, eyebrow-num, value-num .tag, case-step-num) **NO** cambian al naranja — quedan verdes siempre.

### Tipografía

```css
--f-sans:    "Geist", "Inter", ui-sans-serif, system-ui, sans-serif;
--f-display: "Geist", "Inter", ui-sans-serif, system-ui, sans-serif;
--f-mono:    "JetBrains Mono", ui-monospace, "SF Mono", Menlo, monospace;

/* Italic accent (solo en fragmentos en cursiva dentro de títulos) */
font-family: "Instrument Serif", "Geist", serif;
font-style: italic;
font-weight: 400;
letter-spacing: -0.01em;
```

Pesos disponibles: Geist 300/400/500/600/700 · Geist Mono 400/500 · JetBrains Mono 400/500 · Instrument Serif 0/1.

Cargados desde Google Fonts:
```html
<link href="https://fonts.googleapis.com/css2?family=Geist:wght@300;400;500;600;700&family=Geist+Mono:wght@400;500&family=JetBrains+Mono:wght@400;500&family=Instrument+Serif:ital@0;1&display=swap" rel="stylesheet" />
```

Aplicaciones canónicas:
- **Display headings**: `Geist 500`, `letter-spacing: -0.02em` a `-0.045em` (más negativo a mayor tamaño).
- **Italic accents en titulares**: `Instrument Serif italic 400` (clase `.accent`, `.italic`, `em`).
- **Stroke text**: `-webkit-text-stroke: 1.5px var(--fg)` con `color: transparent` (clase `.stroke`).
- **Mono metadata**: `JetBrains Mono 400/500`, `font-size: 11–13px`, `letter-spacing: 0.02em–0.06em`, frecuente uppercase.
- **Numerales tabulares**: clase `.tab` → `font-variant-numeric: tabular-nums`.

### Layout

```css
--container: 1240px;
--pad: clamp(20px, 4vw, 48px);   /* densidad regular */
/* compact: clamp(16px, 3vw, 32px) */
/* comfy:   clamp(28px, 5vw, 64px) */
```

Tres densidades configurables (`compact | regular | comfy`) que sólo cambian `--pad`.

### Bordes y radios

| Elemento | Radio |
|---|---|
| Cards (proyecto, contact card, terminal, hero portrait) | `14px` |
| Pills (chip, button, nav) | `999px` |
| Inputs/áreas pequeñas | `8–10px` |

### Sombras

```css
/* Nav liquid glass */
box-shadow:
  inset 0 1px 0 color-mix(in oklab, var(--fg) 18%, transparent),
  inset 0 -1px 0 color-mix(in oklab, var(--fg) 4%, transparent),
  0 1px 0 color-mix(in oklab, var(--bg) 60%, transparent),
  0 18px 40px -18px rgba(0,0,0,.45),
  0 2px 8px -2px rgba(0,0,0,.3);

/* Terminal card */
box-shadow: 0 30px 80px -40px #00000088;
```

### Animaciones

| Acción | Tiempo / curva |
|---|---|
| Hover en card | `transform: translateY(-1px)`, `transition .15s` |
| Hover en project media | `transform: scale(1.02)`, `transition .5s cubic-bezier(.2,.7,.3,1)` |
| Arrow → hover en `btn-arrow` | `transform: translateX(3px)`, `.2s` |
| Caret terminal | `@keyframes blink 1s steps(1) infinite` |
| Marquee strip | `@keyframes marquee` (lateral infinito) |
| Page glow | `radial-gradient` ambiental, opcional via tweak |

---

## 3. Sistema de componentes (cross-reference SDC ↔ CSS)

> Cada componente se implementará como SDC en `web/themes/custom/byte/components/` siguiendo `ARCHITECTURE.md §7`.
> Los **nombres SDC** salen de la arquitectura; las **clases CSS** salen del prototipo. Un SDC consume varias clases CSS.

| SDC (en byte) | Clases CSS del diseño | Notas |
|---|---|---|
| `section` | `.section`, `.section-head`, `.section-title`, `.section-lede`, `.eyebrow`, `.eyebrow-num` | Wrapper editorial con eyebrow numerado `§ 0N`. |
| `container` | `.wrap` | `max-width: 1240px`, padding `var(--pad)`. |
| `nav-glass` | `.nav`, `.nav-inner`, `.brand`, `.brand-mark`, `.nav-links`, `.nav-link`, `.nav-cta`, `.nav-dot`, `.lang-toggle` | Liquid glass fixed top, blur 26px sat 180%. |
| `banner-inicio` (Hero) | `.hero`, `.hero-grid`, `.hero-status`, `.hero-title`, `.hero-sub`, `.hero-ctas`, `.hero-meta` | Grid 1.35fr/.9fr en ≥980px. Tiene companion: `.hero-portrait` + `.term`. |
| `terminal-card` | `.term`, `.term-hd`, `.term-dots`, `.term-path`, `.term-body`, `.term-line`, `.term-prompt`, `.term-cmd`, `.term-out`, `.term-comment`, `.term-caret` | Decorativo en hero. |
| `hero-portrait` | `.hero-portrait`, `.hp-frame`, `.hp-caption`, `.hp-badge` | SVG placeholder + slot para imagen real. |
| `marquee` | `.marquee`, `.marquee-track` | Stack badges en loop con dots de acento. |
| `medible` (KPIs) | `.hero-meta`, `.hero-meta-item`, `.value-metric`, `.value-metric .unit`, `.caption` | Reusable; en hero (3 KPIs) y en values (4). |
| `como-lo-hago` (Values) | `.values`, `.value`, `.value-num`, `.value-icon`, `.value-title`, `.value-body`, `.value-metric` | Grid 2col ≥900px. Iconos line-art (gauge/layers/access/flow) embebidos. |
| `que-construyo` (Featured) | wrap con `.section-head` + `.projects-grid` (View embebida) | Solo título+lede; el grid lo pone la View. |
| `card-proyecto` | `.project`, `.project-media`, `.project-info`, `.project-tags`, `.chip`, `.chip.accent`, `.project-name`, `.project-desc`, `.project-metrics` | Variante featured (con métricas). Hover: border + scale media. |
| `project-media-mock` | `.project-media-browser`, `.pm-dots`, `.pm-url`, `.project-media-canvas`, `.pm-nav`, `.pm-brand`, `.pm-links`, `.pm-hero`, `.pm-headline`, `.pm-l1/2/3`, `.pm-btn`, `.pm-tiles`, `.pm-tile` | Mock-up de browser; usar como fallback cuando no hay screenshot real. Acepta `hue` y `theme`. |
| `metodo` (Process) | `.process-list`, `.process-row`, `.process-num`, `.process-title`, `.process-body`, `.process-tag` | Grid 100/2fr/3fr/1fr en ≥900px. |
| `card-proceso` / `row-metodo` | `.process-row` (variante) | Mismo átomo, distinto contexto. |
| `palabras-cliente` (Testimonials wrapper) | `.tests` | Grid 2col, separador hairline. |
| `card-testimonio` | `.test`, `.test-quote`, `.test-attr`, `.test-avatar`, `.test-name`, `.test-role` | Comilla pre/post con `::before/::after` color acento. |
| `cta-final` (CTA band) | `.cta-band`, `.cta-title`, `.cta-title .italic`, `.cta-sub`, `.cta-actions` | Centered, con borde top. |
| `phead` (Page header) | `.phead`, `.phead-eyebrow`, `.phead-title`, `.phead-sub` | Hero alternativo para Proyectos/Notas/Contacto. |
| `row-nota` (Note row) | `.post`, `.post-thumb`, `.post-date`, `.post-title`, `.post-excerpt`, `.post-cat`, `.post-read` | Grid 60/110/1fr/160/80 en ≥900px. |
| `blog-filter` | `.blog-filter`, `.blog-chip`, `.blog-chip.on` | Pills con estado activo. |
| `plist-row` (Projects index) | `.plist`, `.plist-row`, `.plist-num`, `.plist-name`, `.plist-desc`, `.plist-tech`, `.plist-year`, `.plist-arr` | Tabla densa de proyectos. |
| `contacto` (Form) | `.contact-grid`, `.field`, `.radio-group`, `.radio`, `.radio.on`, `.success-note` | Inputs sin background, sólo border-bottom; focus → accent. |
| `canal-directo` | `.contact-side`, `.contact-card`, `.contact-channels`, `.channel`, `.channel-name`, `.channel-val`, `.availability` | Card lateral con email/teléfono/WhatsApp. |
| `article` (post detail) | `.article`, `.article-wrap`, `.article-back`, `.article-head`, `.article-tags`, `.article-meta-item`, `.article-meta-dot`, `.article-title`, `.article-sub`, `.article-byline`, `.article-author`, `.article-avatar`, `.article-actions`, `.art-action`, `.article-hero`, `.article-body`, `.article-lede`, `.article-divider`, `.article-footer`, `.article-fauthor`, `.article-news`, `.article-related`, `.art-rel-cat`, `.art-rel-title`, `.art-rel-date` | Plantilla Twig de detalle de Nota. |
| `case` (project detail) | `.case`, `.case-head`, `.case-tags`, `.case-title`, `.case-brief`, `.case-meta`, `.case-meta-k`, `.case-meta-v`, `.case-link`, `.case-hero`, `.case-section`, `.case-step-num`, `.case-h2`, `.case-p`, `.case-p-lg`, `.case-bullets` | Plantilla Twig de detalle de Proyecto. |
| `chip` | `.chip`, `.chip.accent` | Variantes default y accent. |
| `button` | `.btn`, `.btn-primary`, `.btn-ghost`, `.btn-arrow`, `.btn-sm` | Pill button con flecha animada. |
| `eyebrow` | `.eyebrow`, `.eyebrow-num` | "§ 0N · texto-mono". |
| `rule` | `.hair`, `.rule-dashed` | Hairlines y rule punteado. |
| `lang-toggle` | `.lang-toggle`, `.lang-toggle button.on` | Botón ES/EN persistido en localStorage. |
| `availability-strip` | `.availability` | Dot pulsante + texto mono. |

---

## 4. Composición por página

### 4.1 Inicio (Canvas Page)

| Bloque | SDC | Copy ES (resumen) |
|---|---|---|
| 1 | `banner-inicio` (Hero) | "Disponible · Q3 2026 · 2 cupos" → Título 3 líneas con stroke + accent → sub editorial → CTAs (primary/ghost) → meta (15+ años · 80+ plataformas · 97% retención) |
| 2 | `marquee` | WordPress · Drupal 11 · n8n · Headless · Core Web Vitals · WCAG 2.2 · Multilenguaje · API Integrations · Performance Audits |
| 3 | `como-lo-hago` (Values §01) | "No vendo features. *Defiendo cuatro disciplinas no negociables.*" → 4 cards (Mido / Construyo / Respeto / Automatizo) |
| 4 | `que-construyo` + `featured_projects` View (§02) | "Y resulta que con ese enfoque *salen plataformas como estas.*" |
| 5 | `metodo` (Process §03) | "Y *así trabajo cuando empezamos.*" → 4 pasos (Auditoría / Arquitectura / Implementación / Lanzamiento+soporte) |
| 6 | `palabras-cliente` (Testimonials §04) | "Quienes ya lanzaron lo cuentan mejor que yo." → 2 testimonios |
| 7 | `cta-final` | "Si crees lo mismo, *construyamos algo juntos.*" |

### 4.2 Proyectos (Canvas Page + View)

| Bloque | SDC | Copy ES |
|---|---|---|
| 1 | `phead` | Eyebrow "§ proyectos · activos" · Título "Cada proyecto es *una creencia hecha código.*" · sub editorial |
| 2 | `blog-filter` (filtros) | Todos · WordPress · Drupal · E-commerce · Automatización |
| 3 | `plist-row` × N (View) | tabla densa con num, name, tech, year, arrow |
| 4 | `cta-final` (variante outro) | "Si tu caso no aparece aquí…" → "Cuéntame el tuyo" |

### 4.3 Notas (Canvas Page + View)

| Bloque | SDC | Copy ES |
|---|---|---|
| 1 | `phead` | "§ writing · notas · una al mes, sin agenda" · "Lo que aprendo, *lo dejo aquí.*" |
| 2 | `blog-filter` | Todos · Performance · Arquitectura · Drupal · Automatización · A11y |
| 3 | `row-nota` × N (View) | thumb + título + excerpt + categoría + fecha + tiempo de lectura |
| 4 | `article-news` | Newsletter ("Si te sirvió algo de lo que *compartí aquí*...") |

### 4.4 Contacto (Canvas Page + Webform)

| Bloque | SDC | Copy ES |
|---|---|---|
| 1 | `phead` | "§ contacto · respuesta en < 24h · zona horaria COT (GMT-5)" · "Antes del código, *una conversación honesta.*" |
| 2 | `contacto` (Webform) | name · email · company · project_type (5) · budget (5) · message |
| 3 | `canal-directo` (sidebar) | Email · Teléfono · WhatsApp + Disponibilidad + "Cómo va a ser" (4 pasos) |

### 4.5 Detalle de Proyecto (Twig template)

```
[case-back]
[case-tags]      chip accent (categoría) + chip (año) + chip (rol)
[case-title]
[case-brief]
[case-meta]      Cliente · Año · Rol · Duración · Stack · Live ↗
[case-hero]      cover/screenshot
[case-section §01 Desafío]
  [case-h2] · [case-p] · [case-bullets]
[case-section §02 Enfoque]
  [case-step-num] × 4 (case_step paragraphs)
[case-section §03 Resultados]
  [case-h2] · [metric-grid] (metric paragraphs)
  [case-p-lg]  (testimonial paragraph)
[case-section §04 Aprendizaje]
  [case-h2] · [case-p]
[next-project link]
[cta-final]
```

### 4.6 Detalle de Nota (Twig template)

```
[article-back]
[article-head]
  [article-tags]   categoría + fecha + tiempo de lectura
  [article-title]
  [article-sub]
[article-byline]
  [article-avatar] + nombre + rol
  [article-actions] (compartir · guardar)
[article-hero]
[article-body]
  [article-lede] · h2 · h3 · p · ul/ol · blockquote · img
[article-divider]
[article-footer]
  [article-fauthor]
[article-news]   newsletter
[article-related]  3 notas relacionadas (View)
```

---

## 5. Iconografía

**Librería:** [Lucide](https://lucide.dev) — ISC license, ~1500+ iconos line-art con `stroke-width: 2`. Encaja con la estética editorial-developer del diseño y reemplaza los 4 SVG inline del prototipo.

### Iconos canónicos (mapeo prototipo → Lucide)

| Uso | Custom (prototipo) | Lucide |
|---|---|---|
| Mido antes de prometer | `gauge` (velocímetro) | [`gauge`](https://lucide.dev/icons/gauge) |
| Construyo para que dure | `layers` | [`layers`](https://lucide.dev/icons/layers) |
| Respeto a todos los visitantes | `access` | [`accessibility`](https://lucide.dev/icons/accessibility) |
| Automatizo lo repetitivo | `flow` | [`workflow`](https://lucide.dev/icons/workflow) |

### Inventario de iconos esperados en el sitio

| Slot | Lucide | Notas |
|---|---|---|
| Disponibilidad (dot pulsante decorativo) | — | CSS puro, sin icono |
| Flecha CTA buttons | [`arrow-right`](https://lucide.dev/icons/arrow-right) | Reemplaza el `→` mono unicode si se prefiere icono. Mantener unicode `→` también es válido. |
| Flecha externa cards proyecto | [`arrow-up-right`](https://lucide.dev/icons/arrow-up-right) | Reemplaza el `↗` |
| Email (footer/contact) | [`mail`](https://lucide.dev/icons/mail) | |
| Teléfono | [`phone`](https://lucide.dev/icons/phone) | |
| WhatsApp | [`message-circle`](https://lucide.dev/icons/message-circle) | Lucide no tiene logo de marca; usar genérico |
| LinkedIn / GitHub | — | Logos de marca: usar SVG oficiales (no Lucide). |
| Compartir (article actions) | [`share-2`](https://lucide.dev/icons/share-2) | |
| Guardar (article actions) | [`bookmark`](https://lucide.dev/icons/bookmark) | |
| Copiar (article actions) | [`copy`](https://lucide.dev/icons/copy) | |
| Calendar (book a call) | [`calendar`](https://lucide.dev/icons/calendar) | |
| Sun / Moon (theme toggle) | [`sun`](https://lucide.dev/icons/sun), [`moon`](https://lucide.dev/icons/moon) | Para el toggle de modo |
| Menu (mobile) | [`menu`](https://lucide.dev/icons/menu), [`x`](https://lucide.dev/icons/x) | Reemplaza las 3 barras CSS del prototipo si se prefiere |
| Filtros (chips) | — | CSS puro |
| Éxito (success-note) | [`check-circle-2`](https://lucide.dev/icons/check-circle-2) | |
| Error / required | — | Asterisco `*` con `color: var(--accent)` |

### Estrategia de integración en Drupal

**Opción A — Sprite SVG generado (recomendado):**

1. Instalar via Composer / NPM:
   ```bash
   ddev npm install lucide-static --save-dev   # ~6.5 MB de SVGs
   ```
2. Script de build en `web/themes/custom/byte/scripts/build-icons.mjs` que:
   - Lee un manifest `web/themes/custom/byte/icons.manifest.json` con la lista de iconos usados.
   - Toma los `.svg` correspondientes de `node_modules/lucide-static/icons/`.
   - Aplica `stroke="currentColor"` y los wrappea en `<symbol id="i-name">`.
   - Escribe un sprite consolidado en `web/themes/custom/byte/icons.svg`.
3. SDC `icon` con prop `name` y `size`:
   ```twig
   {# components/icon/icon.twig #}
   <svg class="icon icon-{{ name }}" width="{{ size|default(24) }}" height="{{ size|default(24) }}" aria-hidden="true">
     <use href="/{{ active_theme_path() }}/icons.svg#i-{{ name }}"></use>
   </svg>
   ```
4. CSS:
   ```css
   .icon { stroke: currentColor; fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
   ```

**Opción B — Inline por archivo (más simple, más overhead HTTP):**

Copiar SVGs individuales a `web/themes/custom/byte/icons/` y embeberlos via `{{ source('@theme/icons/' ~ name ~ '.svg') }}` en el SDC. Útil si el sitio usa pocos iconos.

> **Recomendación:** Opción A. Una sola request, cacheable, y el sprite final pesa <10KB para los ~15 iconos del sitio.

### Logos de marca (no-Lucide)

Para LinkedIn, GitHub y otros logos de marca, mantener SVG oficiales en `web/themes/custom/byte/icons/brands/`. No usar Lucide para logos (no tiene y mezclarlos rompería consistencia visual).

---

## 6. Imágenes y media (placeholders)

El prototipo usa placeholders SVG generativos:

| Placeholder | Estructura |
|---|---|
| `project-media-mock` | Browser frame + nav mock + headline lines + tile grid. Configurable via `hue` (0–360) y `theme` (`dark`/`film`/`creative`). |
| `hero-portrait` | SVG con gradient + diagonal stripes + circle (avatar) + arc (silueta). Slot para JPG real. |
| `test-avatar` | Linear-gradient `--accent → #f0a16b` + initials. Bg cuando no hay avatar real. |
| `article-avatar` | Linear-gradient acento → mix con `--bg`. Initials. |

**Decisión de implementación:** mantener placeholders SVG como fallback en SDCs cuando el campo Media esté vacío. Reemplazar por screenshots reales cuando estén disponibles.

---

## 7. Copy completo (ES + EN)

> El copy completo de las 4 páginas principales vive en `i18n.jsx` (216 líneas, ES y EN paralelos). Es el "source of truth" del texto.

Ubicación canónica del copy: `/tmp/jalvarez-design/jalvarez-tech/project/i18n.jsx`.

Cuando se cree la traducción Drupal:
- Copy de páginas Canvas → strings translatables vía Configuration Translation o directo en el campo del bloque.
- Copy del Webform → vía Webform translation.
- Copy de UI (nav, footer, botones genéricos, mensajes de éxito) → archivo `.po` en `web/themes/custom/byte/translations/` o módulo `jalvarez_site` con strings `t()`.

### Strings de UI fijos

| ID | ES | EN |
|---|---|---|
| `nav.work` | proyectos | work |
| `nav.writing` | notas | writing |
| `nav.contact` | contacto | contact |
| `nav.available` | Disponible | Available |
| `cta.primary` (footer/CTA) | Iniciar tu proyecto | Start your project |
| `cta.secondary` | Revisar mi trabajo | Browse my work |
| `form.send` | Enviar mensaje | Send message |
| `form.success` | Mensaje recibido. Te escribo en menos de 24h. | Message received. I'll write back within 24h. |

---

## 8. Patrones de interacción

- **Mode toggle (dark/light):** botón en nav (junto al `lang-toggle`). Atributo `data-theme="dark|light"` en `<html>`. CSS adapta vars. Persiste en `localStorage.jsa-theme`. Inicialización: lee `localStorage` → si vacío, usa `matchMedia('(prefers-color-scheme: dark)')` → fallback `dark`. Aplicar antes de pintar (script inline en `<head>` para evitar FOUC).
- **Lang toggle:** botones ES/EN, persiste en `localStorage.jsa-lang`. Atributo `lang` en `<html>`.
- **Nav móvil:** hamburger → drawer portaled a `body`, `position: fixed`, scroll del body bloqueado.
- **Form:** inputs sin background, solo border-bottom. Focus → border-bottom acento. Radios pill.
- **Project card click:** navega a detalle (`onNavigate("case:" + idx)`).
- **Marquee:** loop infinito de stack badges, sin pausa en hover.
- **Page glow:** `radial-gradient` ambiental, opcional (tweak); detrás del contenido con `position: fixed; inset:0; z-index:-1;`.

---

## 9. Decisiones

| # | Decisión | Resolución | Estado |
|---|---|---|---|
| D1 | Color de acento | **Verde `#20d958` estático** + **Naranja `#ff6b1a` solo en hover de elementos interactivos** (ver §2). | ✅ confirmado |
| D2 | Terminal card en hero | **Sí, conservar.** SDC `terminal-card` con props `prompt`, `lines[]`. Es firma "developer". | ✅ confirmado |
| D3 | `hero-portrait` con SVG placeholder | **Sí, conservar el SVG generativo** (gradient + diagonal stripes + circle + arc). Cuando exista foto real, reemplazar via campo Media; el SVG queda como fallback automático. | ✅ confirmado |
| D4 | Librería de iconos | **[Lucide](https://lucide.dev)** (ISC license, ~1500+ iconos line-art). Ver §5 para integración. | ✅ confirmado |
| D5 | Tweaks panel | NO se implementa en producción. Era para iterar diseño. | ✅ descartado |
| D6 | Glow ambiental | **Activo por defecto** en producción. `<div class="page-glow">` fixed detrás del contenido con `radial-gradient` ambiental color acento (verde, baja opacidad). No expuesto al usuario — siempre ON. | ✅ confirmado |
| D7 | Fuentes | **Self-hosted en `web/themes/custom/byte/fonts/`**. No depender de Google Fonts CDN. Subset latin + latin-ext, formato `woff2`, `font-display: swap`. | ✅ confirmado |
| D8 | Light/dark mode | **Toggle expuesto al usuario** en nav. Default = `prefers-color-scheme` o `dark`. Persistir en `localStorage.jsa-theme`. Atributo `data-theme="dark|light"` en `<html>`. | ✅ confirmado |

---

## 10. Mapeo a roadmap de implementación

Coordinar con `ARCHITECTURE.md §14`:

| Paso roadmap | Acción de diseño |
|---|---|
| 6. Scaffold de SDCs en `byte` | Crear estructura por componente con CSS extraído de `styles.css` (ver §3 cross-reference). |
| 7. Crear Canvas Pages | Componer cada página con los SDCs en el orden de §4 de este doc. |
| — | Cargar fuentes (Google Fonts o self-hosted) en `byte.libraries.yml`. |
| — | Crear sprite SVG de iconos (§5). |
| — | Definir tokens CSS (§2) en `byte/css/tokens.css`. |

---

## 11. Restricciones y guardrails

- **No copiar JSX.** El prototipo usa React + Babel UMD. La implementación es Drupal Twig + CSS estático.
- **No depender de tweaks-panel.jsx.** Es una herramienta de diseño, no parte del producto.
- **Conservar tokens CSS, no los nombres de variantes.** Los nombres `featured` / `compact` etc. del prototipo son sugerencias; los nombres SDC los define `ARCHITECTURE.md`.
- **Bilingüe estricto.** Ningún string puede quedar hard-coded. Todo pasa por `t()`, Webform translation o Config translation.
- **Sin bibliotecas JS pesadas.** El prototipo usa React solo para iterar. La implementación produce HTML server-side; cualquier interactividad (lang toggle, mobile drawer, marquee) se hace con JS vanilla minimal en el theme.

---

## 12. Origen del bundle

El handoff bundle está extraído en:

```
/tmp/jalvarez-design/jalvarez-tech/
├── README.md                 # guidance de coding agent (claude.ai/design)
├── chats/
│   ├── chat1.md              # 1238 líneas — sesión completa de diseño
│   └── chat2.md              # 42 líneas — pedido de copy actualizado
└── project/
    ├── index.html            # bootstrap React + tweaks defaults
    ├── styles.css            # 1387 líneas — tokens y todos los componentes
    ├── i18n.jsx              # ES/EN copy completo
    ├── shared.jsx            # Nav, Footer, BRAND
    ├── home.jsx              # Hero, Marquee, Values, Featured, Process, Tests, CTA
    ├── projects.jsx          # listado proyectos
    ├── blog.jsx              # listado notas
    ├── contact.jsx           # form + side
    ├── post.jsx              # detalle nota
    ├── case.jsx              # detalle proyecto
    ├── tweaks-panel.jsx      # NO se implementa (dev-only)
    └── docs/
        └── content-model.md  # primera intención del modelo de contenido
```

> ⚠️ El directorio `/tmp/` es volátil. Si necesitas re-extraer, descarga el bundle desde la URL de Anthropic Design original y descomprime con `tar -xzf`.

---

## 13. Discrepancias detectadas

| Item | Origen A | Origen B | Resolución |
|---|---|---|---|
| Color acento | `styles.css :root` → `#ff6b1a` (naranja) | `index.html TWEAK_DEFAULTS.accent` → `#20d958` (verde) | Usar naranja `#ff6b1a` por consistencia. Pendiente confirmación. |
| Color acento (doc) | `content-model.md` → `#E07A5F` (warm coral) | Otros archivos → `#ff6b1a` | El CSS y los componentes están con `#ff6b1a`. Doc desactualizado. |
| Identidad usuario | `content-model.md` → "Jorge Álvarez" | `shared.jsx BRAND.name` → "John Stevans Alvarez" | Usar **John Stevans Alvarez** (canónico en código y copy ES/EN). |

---

## 14. Próximos pasos sugeridos

1. ⏳ Descargar `woff2` de Geist (300/400/500/600/700), Geist Mono (400/500), JetBrains Mono (400/500), Instrument Serif (regular + italic) → `web/themes/custom/byte/fonts/`.
2. ⏳ Crear `web/themes/custom/byte/css/tokens.css` con los tokens de §2 (incluye sistema dual de acento).
3. ⏳ Crear `web/themes/custom/byte/css/fonts.css` con `@font-face` self-hosted (`font-display: swap`).
4. ⏳ Crear `web/themes/custom/byte/css/base.css` con reset, body, theme attr.
5. ⏳ Inline script en `<head>` (`html.html.twig` preprocess) para aplicar `data-theme` antes del primer paint.
6. ⏳ Crear SDC `lang-toggle` y SDC `theme-toggle` en `nav-glass`.
7. ⏳ Scaffold de SDCs en `web/themes/custom/byte/components/` por orden:
   - `chip`, `button`, `eyebrow`, `section` (primitivos)
   - `nav-glass`, `cta-final`, `phead` (layout)
   - `banner-inicio` (hero)
   - `como-lo-hago`, `metodo`, `palabras-cliente`
   - `card-proyecto`, `row-nota`, `card-testimonio`
   - `contacto`, `canal-directo`
   - Resto.
8. ⏳ Cargar copy de `i18n.jsx` en Drupal (Webform, Config translation, theme `t()`).
9. ⏳ Instalar `lucide-static` y generar `icons.svg` sprite con los iconos de §5 (build script `scripts/build-icons.mjs`).
10. ⏳ Crear SDC `icon` consumiendo el sprite via `<use href="...icons.svg#i-name">`.

> Ver `ARCHITECTURE.md §14` para el roadmap completo de Drupal.
