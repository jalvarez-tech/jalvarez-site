# Deployment — GitHub → Hostinger

> Pipeline CI/CD: el repositorio en GitHub es la fuente de verdad. GitHub Actions compila assets, instala dependencias, y publica solo el artefacto final en Hostinger. El servidor de Hostinger NO ejecuta `composer`, `npm` ni `sass`.

- **Última actualización:** 2026-05-02 — Las 4 páginas principales son canvas_page
- **Repo:** `git@github.com:jalvarez-tech/jalvarez-site.git`
- **Branch productivo:** `main`
- **Hosting destino:** Hostinger (plan a confirmar — ver §6)

---

## 1. Filosofía

```
   ┌──────────────┐     push      ┌───────────────────┐    deploy    ┌────────────┐
   │  Local (dev) │ ────────────► │ GitHub + Actions  │ ────────────►│ Hostinger  │
   │  DDEV        │               │ - composer install│              │ public_html│
   │  npm run dev │               │ - npm run build   │              │ + vendor/  │
   └──────────────┘               │ - sass → css      │              │ + web/     │
                                  │ - icons sprite    │              └────────────┘
                                  │ - artifact tar    │
                                  └───────────────────┘
```

- **Local:** desarrollo con DDEV + watch mode (`npm run dev`).
- **Repo:** versiona **solo el código fuente** (`.scss`, `.jsx-templates`, `composer.json`). NO versiona `vendor/`, `node_modules/`, `dist/`, `recipes/` ni nada generado.
- **CI:** instala dependencias y compila assets en máquina limpia.
- **Deploy:** transfiere el artefacto compilado a Hostinger por SSH/SFTP.

---

## 2. Estructura del repo (qué se versiona)

```
jalvarez-site/
├── .github/
│   └── workflows/
│       ├── ci.yml              # tests + lint en PRs
│       └── deploy.yml          # build + deploy en push a main
├── .ddev/                      # entorno local
├── composer.json
├── composer.lock
├── config/
│   └── sync/                   # config exportada vía drush
├── docs/
│   ├── ARCHITECTURE.md
│   ├── DESIGN.md
│   └── DEPLOYMENT.md           # este archivo
├── web/
│   ├── modules/custom/
│   │   └── jalvarez_site/
│   ├── themes/custom/byte/
│   │   ├── byte.info.yml
│   │   ├── byte.libraries.yml
│   │   ├── byte.theme
│   │   ├── components/         # SDC (TWIG + CSS compilado a la par)
│   │   ├── scss/               # ← FUENTE: solo en repo, NO se sube a Hostinger
│   │   │   ├── tokens.scss
│   │   │   ├── base.scss
│   │   │   └── components/...
│   │   ├── scripts/
│   │   │   └── build-icons.mjs # script de sprite Lucide
│   │   ├── icons.manifest.json # lista de iconos a incluir en sprite
│   │   ├── package.json
│   │   ├── package-lock.json
│   │   └── dist/               # ← BUILD: gitignored, generado por CI
│   │       ├── css/
│   │       └── icons.svg
│   └── sites/default/
│       └── settings.hostinger.php  # settings específicos producción (sin secretos)
└── README.md
```

---

## 3. Estructura del artefacto desplegado a Hostinger

> Solo esto se transfiere. Todo lo demás se queda en GitHub Actions.

```
hostinger:~/domains/jalvarez.tech/
├── private/                    # fuera del docroot público
│   ├── vendor/                 # ← composer install --no-dev
│   ├── config/sync/
│   └── drush/
└── public_html/                # ← contenido de web/ del repo
    ├── core/                   # Drupal core (composer)
    ├── modules/
    │   ├── contrib/            # Drupal contrib (composer)
    │   └── custom/
    ├── themes/
    │   └── custom/byte/
    │       ├── byte.info.yml
    │       ├── byte.theme
    │       ├── components/
    │       ├── dist/css/       # ← CSS compilado (no SCSS)
    │       ├── icons.svg       # ← sprite generado
    │       └── byte.libraries.yml
    ├── sites/default/
    │   ├── settings.php        # generado en deploy desde settings.hostinger.php
    │   └── files/              # uploads (persistente, no se toca en deploy)
    ├── index.php
    └── .htaccess
```

> **Nota Drupal:** Los pasos para que Hostinger sirva `web/` como docroot dependen del plan (ver §6). En shared hosting básico, `public_html/` es fijo; en ese caso el contenido de `web/` se publica directo en `public_html/` y `vendor/` queda en `~/private/` o `~/domains/jalvarez.tech/vendor/` referenciado por `settings.php`.

---

## 4. Workflow `deploy.yml` — esquema

```yaml
name: Deploy to Hostinger

on:
  push:
    branches: [main]

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      # PHP + Composer
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'
          tools: composer:v2
      - run: composer install --no-dev --optimize-autoloader --no-interaction

      # Node + SASS + Lucide sprite
      - uses: actions/setup-node@v4
        with:
          node-version: '20'
          cache: 'npm'
          cache-dependency-path: web/themes/custom/byte/package-lock.json
      - run: cd web/themes/custom/byte && npm ci && npm run build

      # Render production settings.php from template (deploy-time substitution).
      # Secrets are baked into the file BEFORE rsync — Hostinger PHP-FPM never sees env vars.
      - name: Render settings.php
        env:
          DRUPAL_DB_NAME: ${{ secrets.DRUPAL_DB_NAME }}
          DRUPAL_DB_USER: ${{ secrets.DRUPAL_DB_USER }}
          DRUPAL_DB_PASS: ${{ secrets.DRUPAL_DB_PASS }}
          DRUPAL_DB_HOST: ${{ secrets.DRUPAL_DB_HOST }}
          DRUPAL_HASH_SALT: ${{ secrets.DRUPAL_HASH_SALT }}
        run: |
          envsubst < web/sites/default/settings.hostinger.php.template \
            > web/sites/default/settings.php
          chmod 444 web/sites/default/settings.php

      # Deploy via rsync over SSH (or SFTP)
      - name: Deploy
        uses: burnett01/rsync-deployments@7.0.1
        with:
          switches: -avzr --delete --exclude-from='.deployignore'
          path: ./
          remote_path: ${{ secrets.HOSTINGER_PATH }}
          remote_host: ${{ secrets.HOSTINGER_HOST }}
          remote_user: ${{ secrets.HOSTINGER_USER }}
          remote_key: ${{ secrets.HOSTINGER_SSH_KEY }}

      # Post-deploy: drush updb + cim + cr
      - name: Drupal post-deploy
        uses: appleboy/ssh-action@v1.0.0
        with:
          host: ${{ secrets.HOSTINGER_HOST }}
          username: ${{ secrets.HOSTINGER_USER }}
          key: ${{ secrets.HOSTINGER_SSH_KEY }}
          script: |
            cd ${{ secrets.HOSTINGER_PATH }}
            ./vendor/bin/drush updb -y
            ./vendor/bin/drush cim -y
            ./vendor/bin/drush cr
```

### `.deployignore` (lo que NO se transfiere)

```
.git
.github
.ddev
.gitignore
.gitattributes
.editorconfig
docs/
node_modules/
**/node_modules
web/themes/custom/byte/scss/
web/themes/custom/byte/scripts/
web/themes/custom/byte/package.json
web/themes/custom/byte/package-lock.json
web/themes/custom/byte/icons.manifest.json
web/sites/default/settings.hostinger.php.template
web/sites/default/example.settings.local.php
*.log
.DS_Store
README.md
CONTRIBUTING.md
LICENSE.txt
AGENTS.md
phpunit.xml.dist
tests/
```

---

## 5. SASS y assets del theme

### Estructura `web/themes/custom/byte/`

```
byte/
├── byte.info.yml
├── byte.libraries.yml
├── byte.theme
├── components/              # SDC: cada componente con su .twig + .component.yml
│   └── chip/
│       ├── chip.component.yml
│       ├── chip.twig
│       ├── chip.scss        # ← fuente
│       └── chip.css         # ← generado en build (gitignored)
├── scss/                    # estilos globales (gitignored los .css generados)
│   ├── _tokens.scss         # variables CSS (acento, fuentes, spacing)
│   ├── _reset.scss
│   ├── _base.scss           # body, html, fonts
│   ├── _layout.scss         # .wrap, .section, .hair
│   └── main.scss            # punto de entrada → dist/css/main.css
├── fonts/                   # self-hosted woff2 (Geist, JetBrains Mono, Instrument Serif)
├── icons.manifest.json      # lista de iconos Lucide a incluir
├── icons.svg                # ← sprite generado (gitignored)
├── scripts/
│   └── build-icons.mjs
├── package.json
└── dist/
    └── css/                 # ← CSS compilado (gitignored)
        ├── main.css
        └── main.css.map
```

### `package.json` — scripts

```json
{
  "name": "byte-theme",
  "private": true,
  "scripts": {
    "dev:css": "sass --watch scss/main.scss:dist/css/main.css components:components --style=expanded --source-map",
    "build:css": "sass scss/main.scss:dist/css/main.css components:components --style=compressed --no-source-map",
    "build:icons": "node scripts/build-icons.mjs",
    "dev": "npm-run-all --parallel dev:css",
    "build": "npm-run-all build:css build:icons"
  },
  "devDependencies": {
    "sass": "^1.77.0",
    "lucide-static": "^0.460.0",
    "npm-run-all": "^4.1.5"
  }
}
```

### `byte.libraries.yml` — referencia el CSS compilado

```yaml
global:
  version: 1.x
  css:
    theme:
      dist/css/main.css: {}
  js:
    js/theme-toggle.js: {}
    js/lang-toggle.js: {}
  dependencies:
    - core/drupal
```

### `.gitignore` adicional para el theme

Ya cubierto a nivel raíz (`/node_modules`, `web/themes/custom/*/node_modules`). Falta agregar:

```
web/themes/custom/*/dist/
web/themes/custom/*/icons.svg
web/themes/custom/*/components/**/*.css
web/themes/custom/*/components/**/*.css.map
web/themes/custom/*/dist/**/*.map
```

---

## 6. Entorno Hostinger — confirmado

| # | Item | Valor |
|---|---|---|
| H1 | Plan | **Cloud Startup** (incluye SSH, custom PHP, mayor RAM, recursos dedicados) |
| H2 | SSH | **Activo**. Puerto 65002. |
| H3 | Docroot | **`public_html/` fijo** (no se puede cambiar). Layout **flat**: todo dentro de `public_html/` — `vendor/`, `config/`, `private/`, core, modules, themes. `web/autoload.php` se sobreescribe vía `assets/autoload.php` (drupal-scaffold override) para que apunte a `./vendor/`. Composer `vendor-dir = web/vendor` fuerza la instalación dentro de la docroot. Drupal ships `vendor/.htaccess` + `core/.htaccess` para denegar acceso web directo. |
| H4 | Dominio | **jalvarez.tech** |
| H5 | MySQL | Credenciales de hPanel → guardar en GitHub Secrets como `DRUPAL_DB_*` |
| H6 | Drush | **Disponible vía SSH en Cloud Startup**. Se invoca por `~/domains/jalvarez.tech/vendor/bin/drush` post-deploy. Ref: https://www.hostinger.com/mx/tutoriales/tutorial-drupal |
| H7 | Node | **NO se ejecuta en Hostinger**. Solo corre en GitHub Actions (Node 20 LTS, configurado en `deploy.yml`). En el servidor de Hostinger no necesitas Node — solo PHP. Si después necesitas Node para algo runtime (raro en Drupal clásico), Cloud Startup lo permite via NVM en SSH. |
| H8 | PHP | **PHP 8.4** activo en Hostinger. CI usa misma versión. `composer.json` config.platform.php = `8.4.0`. |
| H9 | Uploads `sites/default/files` | **Persistente.** rsync usa `--exclude='public_html/sites/default/files/'` — los archivos del CMS nunca se tocan en deploy. |
| H10 | Backup | **Backup nativo Hostinger Cloud Startup activo.** No agregamos backup en CI. Si en el futuro se requiere snapshot por deploy, se añade un step antes del rsync que dispare `mysqldump` y lo guarde como artefacto del run. |

---

## 6b. Layout final en Hostinger (Cloud Startup, docroot fijo en public_html, **flat**)

```
/home/<user>/
└── domains/jalvarez.tech/
    └── public_html/              ← DOCROOT (= contenido de web/ del repo) Y project root
        ├── core/                  ← Drupal core (composer)
        ├── modules/
        │   ├── contrib/
        │   └── custom/
        ├── themes/
        │   ├── contrib/
        │   └── custom/byte/
        │       ├── byte.theme
        │       ├── components/
        │       ├── dist/css/main.css      ← compilado por CI
        │       ├── icons.svg              ← sprite generado por CI
        │       ├── fonts/
        │       └── byte.libraries.yml
        ├── vendor/                ← Composer deps (composer install vendor-dir = web/vendor)
        │                             Web-accessible PERO Drupal ship vendor/.htaccess deny
        ├── config/
        │   └── sync/              ← config exportada via drush cex
        ├── private/               ← $settings['file_private_path'], persistente
        ├── sites/default/
        │   ├── settings.php       ← renderizado por CI desde template
        │   ├── services.yml
        │   └── files/             ← PERSISTENTE, no se sobrescribe en deploy
        ├── autoload.php           ← override scaffold → require __DIR__ . '/vendor/autoload.php'
        ├── index.php
        └── .htaccess
```

> La instalación de vendor adentro de web/ se controla via `composer.json` → `config.vendor-dir = "web/vendor"` y `bin-dir = "web/vendor/bin"`. **drupal-scaffold detecta automáticamente** que vendor-dir está dentro de web-root y genera `web/autoload.php` con `__DIR__ . '/vendor/autoload.php'` (en vez del default `'/../vendor/autoload.php'`). Esto permite que `public_html/` sea la docroot Y el project root al mismo tiempo — requisito de hostings con docroot fijo como Hostinger Cloud Startup.

> **Symlink `web → public_html`.** El autoloader de Composer hardcodea rutas como `<project>/web/core/includes/bootstrap.inc` durante `composer install`. Como en CI la estructura es `<root>/web/...` pero en Hostinger es `<root>/public_html/...`, esos paths quedan rotos en server. **Solución:** `~/domains/jalvarez.tech/web → public_html` (symlink). Vive FUERA del docroot, no es web-accessible. El paso `Pre-deploy permissions` lo crea automáticamente si no existe.

---

## 7. Secrets en GitHub

A configurar en `Settings → Secrets and variables → Actions`:

| Secret | Uso | Cómo obtenerlo |
|---|---|---|
| `HOSTINGER_HOST` | Hostname SSH | hPanel → SSH Access → Host (ej: `srv123.hstgr.io`) |
| `HOSTINGER_USER` | Usuario SSH | hPanel → SSH Access → Username (ej: `u123456789`) |
| `HOSTINGER_PORT` | Puerto SSH | **65002** en Cloud Startup |
| `HOSTINGER_PATH` | Ruta absoluta destino | `/home/u123456789/domains/jalvarez.tech` (sin `/public_html`) |
| `HOSTINGER_SSH_KEY` | Llave privada SSH | Generar local: `ssh-keygen -t ed25519 -f ~/.ssh/hostinger_jalvarez -C "deploy@github"`. Subir la pública (`.pub`) a hPanel → SSH Access → Manage SSH Keys. Pegar la privada en GitHub Secret. |
| `DRUPAL_DB_NAME` | DB MySQL | hPanel → Bases de datos → MySQL → DB name |
| `DRUPAL_DB_USER` | DB user | hPanel → Bases de datos → MySQL → user |
| `DRUPAL_DB_PASS` | DB password | hPanel → Bases de datos → MySQL → password |
| `DRUPAL_DB_HOST` | `localhost` | En Hostinger Cloud Startup la DB es local al servidor PHP |
| `DRUPAL_HASH_SALT` | Salt Drupal | Generar local: `openssl rand -base64 55` o `php -r "echo bin2hex(random_bytes(32));"` |

---

## 8. `settings.hostinger.php.template`

Plantilla en repo con placeholders `${VAR}`. **`envsubst` los reemplaza en CI** antes del rsync — el `settings.php` final que llega a Hostinger contiene los valores hardcoded, NO `getenv()`. Esto es necesario porque Hostinger PHP-FPM no expone las env vars de GitHub Actions al runtime.

```php
<?php
// settings.hostinger.php.template — fuente versionada (con placeholders ${...}).
// CI ejecuta `envsubst` para producir sites/default/settings.php con valores reales.
// Layout esperado en Hostinger:
//   docroot       = public_html/
//   vendor/       = ../vendor/
//   config/sync   = ../../../config/sync (relativo a este archivo)
//   private/      = ../../../private

$databases['default']['default'] = [
  'database' => '${DRUPAL_DB_NAME}',
  'username' => '${DRUPAL_DB_USER}',
  'password' => '${DRUPAL_DB_PASS}',
  'host'     => '${DRUPAL_DB_HOST}',
  'driver'   => 'mysql',
  'prefix'   => '',
  'charset'  => 'utf8mb4',
  'collation'=> 'utf8mb4_general_ci',
];

$settings['hash_salt'] = '${DRUPAL_HASH_SALT}';

$settings['config_sync_directory'] = '../../../config/sync';
$settings['file_private_path']     = '../../../private';

$settings['trusted_host_patterns'] = [
  '^jalvarez\.tech$',
  '^www\.jalvarez\.tech$',
];

$config['system.performance']['css']['preprocess'] = TRUE;
$config['system.performance']['js']['preprocess']  = TRUE;

$settings['rebuild_access'] = FALSE;
$settings['skip_permissions_hardening'] = FALSE;
```

> El archivo `settings.hostinger.php.template` está en `.deployignore` — solo se publica el `settings.php` ya renderizado.

---

## 9. Flujo de cambios

### Para feature pequeño (estilos, copy)

```
local:  git checkout -b feat/foo
        edit scss/, edit twig
        npm run dev (preview)
        ddev drush cr
        git commit + push
github: PR → CI runs (lint, build verifies)
        merge to main → deploy.yml runs
        - composer install --no-dev
        - npm ci + npm run build
        - rsync to Hostinger
        - drush updb + cim + cr en producción
hostinger: cambios visibles en jalvarez.tech
```

### Para cambios estructurales (content type, view, módulo)

```
local:  ddev drush cex      # exporta config nueva a config/sync
        git commit -A
github: deploy → drush cim -y aplica la config en producción
```

### Rollback

```
git revert <hash> && git push   # CI re-deploya el estado previo
```

---

## 10. Estado actual

### Setup completado

- ✅ Plan Cloud Startup, PHP 8.4 activo
- ✅ DB MySQL creada (`u211065173_jalvarez_site`)
- ✅ SSH key generada, pubkey en hPanel, conexión validada (`191.101.32.187:65002`)
- ✅ 10 GitHub Secrets configurados
- ✅ Workflows: `.github/workflows/deploy.yml` + `ci.yml` + `seed-content.yml`
- ✅ `.deployignore` con sources excluidos
- ✅ `web/sites/default/settings.hostinger.php.template`
- ✅ Theme `byte` scaffold mínimo (info, libraries, theme PHP, package.json, scripts, scss tokens + main)
- ✅ Canvas migration: 19 SDCs registrados, home node "Inicio (Canvas)" con field_canvas tree (ES + EN)

### Post-deploy específico de Canvas (orden importa)

Después del primer deploy con Canvas, corra estos scripts vía `seed-content.yml` desde GitHub Actions → Run workflow, en este orden:

1. **`scripts/canvas-discover-sdcs.php`** (idempotente)
   Re-discover los SDCs de byte y registra los Component config entities. Útil si `drush cim` no creó automáticamente los `canvas.component.sdc.byte.*`. También útil tras añadir nuevos SDCs.

2. **`scripts/create-media-image-type.php`** (idempotente)
   Crea el `media_type: image` con su `field_media_image`. **Requisito hard de canvas_page**: sin un media type tipo image, MediaLibraryWidget crashea cuando Canvas genera el form para el campo base `image` de canvas_page. Sin esto, el editor visual queda en blanco.

3. **`scripts/create-canvas-home.php`** (idempotente — borra y recrea la canvas_page "Inicio (Canvas)")
   Crea el `canvas_page` con `components` field tree en ES + traducción EN, y setea `system.site.page.front` al `/page/<id>` correcto del entorno (los IDs de canvas_page difieren entre local y prod).

4. **`scripts/create-canvas-other-pages.php`** (idempotente)
   Crea las 3 canvas_pages restantes: Proyectos, Notas, Contacto. Cada una con traducción EN. Las páginas de listados embeben los block plugins custom (`block.jalvarez_projects_grid`, `block.jalvarez_notes_grid`); Contacto embebe `block.webform_block` con `webform_id: contact`.

5. **`scripts/place-nav-block.php`** (solo primera vez)
   Coloca el block `jalvarez_nav_glass` en el region `byte:header`.

6. **`scripts/configure-form-displays.php`** (opcional, solo si cambian los schemas de fields en project/note)
   Configura form displays con field_group fieldsets para project, note y page bundles.

### Sobre la portabilidad de IDs

`system.site.page.front = /page/<id>` referencia el ID numérico del canvas_page Inicio. Como los IDs son auto-incrementales y difieren entre entornos, el script `create-canvas-home.php` actualiza este config dinámicamente al crear la entidad. **No hay que editarlo manualmente entre entornos** — basta con correr el script.

Lo mismo para los aliases (`/inicio`, `/home`, `/proyectos`, etc.): son parte del `path` field de cada canvas_page y se crean junto con la entidad.

### Defensa contra el bug "drush cim contamina prod"

**Síntoma observado (2026-05-02):** después de un `git push`, las URLs `/`, `/es`, `/en` empezaron a devolver 404 con title `"jalvarez.tech (local)"`. El resto de URLs (`/es/proyectos`, etc.) seguían funcionando porque tienen alias propios.

**Causa raíz:** `config/sync/system.site.yml` traía valores de un export local con `name: 'jalvarez.tech (local)'`, `mail: admin@example.com` y `page.front: /page/8` (ID que existía en local pero no en prod). El step `drush cim` del workflow re-aplicó esos valores en producción, rompiendo la home.

**Fix permanente** (vive en el repo):

1. **`scripts/fix-prod-system-site.php`** — script defensivo que:
   - Resuelve la home dinámicamente vía `path_alias.manager->getPathByAlias('/inicio')` → `/page/{id}` (sin asumir IDs entre envs).
   - Fuerza `system.site.name`, `system.site.mail`, `system.site.page.front` y `update.settings.notification.emails` a sus valores canónicos.
   - Idempotente (solo escribe si hay drift).

2. **`.github/workflows/deploy.yml` — paso post-cim:** después de `drush updb && cim && cr`, el workflow hace `scp` del script a `/tmp` del server y lo ejecuta con `drush php:script`. Así, aunque alguien re-exporte config local sin sanitizar, el deploy lo repara automáticamente.

3. **`config/sync/system.site.yml` y `update.settings.yml`** — los valores `name`, `mail`, `front`, `notification.emails` quedaron con los valores canónicos de prod (no de local), y un comentario YAML pide explícitamente restaurarlos antes de hacer `drush cex && git push`.

**Prevención manual**: si haces `drush cex` desde local con un dump de DB diferente, **revisa el diff** de `config/sync/system.site.yml` antes de commitear y restaura `name/mail/front` a sus valores prod-canon.

### Bug Canvas 1.3.x: editar canvas_page en `/es` borra la translation EN

**Síntoma observado (2026-05-02):** después de editar el Inicio en el editor visual de Canvas mientras el idioma activo era ES:
- `/en/home` → 404 (el alias `/home` se había evaporado).
- `/en` → 200 pero renderizaba el contenido ES con `<html lang="en">` (Drupal hace fallback a la canonical cuando la translation EN no existe).
- Resto de páginas EN intactas (`/en/projects`, `/en/notes`, `/en/contact`).

**Causa probable:** Canvas 1.3.x sobrescribe el campo `components` y `path` de la translation EN al guardar la versión ES desde el editor visual. La translation EN queda con `components` vacío o eliminada por completo.

**Fix inmediato:**

```bash
gh workflow run seed-content.yml --field script=scripts/restore-canvas-home-en.php
```

El script [`scripts/restore-canvas-home-en.php`](../scripts/restore-canvas-home-en.php) resuelve la canvas_page Inicio por alias `/inicio` y reaplica la translation EN canónica (mismo árbol de componentes que `create-canvas-home.php`) sin tocar el ES. Idempotente: detecta si la translation existe o falta. Tras correrlo:

```bash
# Drupal cache + page cache
gh workflow run seed-content.yml --field script=scripts/clear-page-cache.php
```

Y esperar máximo 15 min a que el LiteSpeed cache de Hostinger expire (TTL `cache.page.max_age = 900`), o forzar refresh con `Cache-Control: no-cache` para verificar inmediatamente.

**Prevención**: hasta que Canvas upstream corrija el bug, **siempre editar la canvas_page del Inicio activando el toggle de idioma del editor visual** antes de cambiar nada — no editar via URL directa `/es/canvas/editor/...` y luego switchear idiomas. Si dudas, después de editar revisa que `/en` muestre contenido en inglés con `curl -s -H 'Cache-Control: no-cache' https://jalvarez.tech/en | grep '<title>'`.

### Primer deploy — flujo automático

`git push origin main` dispara `deploy.yml`:

1. **Build:** composer install (sin dev) + npm ci + sass → CSS + sprite Lucide.
2. **Render:** `envsubst` reemplaza placeholders en `settings.hostinger.php.template` → `settings.php` con secrets.
3. **Rsync (3 pasadas):**
   - `web/` → `~/domains/jalvarez.tech/public_html/` (excluye sources y `sites/default/files/`)
   - `vendor/` → `~/domains/jalvarez.tech/vendor/`
   - `config/` → `~/domains/jalvarez.tech/config/`
4. **Post-deploy via SSH:**
   - Si Drupal está instalado: `drush updb && cim && cr`.
   - Si NO está instalado (primer deploy): warn y skip.

### Primer deploy — `drush site:install` manual (una sola vez)

Después del primer push exitoso (que mete el código), el sitio aún no tiene tablas en DB. Vía SSH:

```bash
ssh hostinger-jalvarez   # o el comando largo si no creaste el alias
cd domains/jalvarez.tech/public_html
./vendor/bin/drush site:install drupal_cms_installer \
  --site-name="jalvarez.tech" \
  --account-name=admin \
  --account-mail=contacto@jalvarez.tech \
  -y
exit
```

> Validar el profile correcto con `ls profiles/contrib/`. Si el recipe inicial usa `minimal` o `standard`, cambiá ese argumento.

A partir de ahí cada `git push` despliega solo.

### Activar el theme `byte` (post site:install)

```bash
ssh hostinger-jalvarez
cd domains/jalvarez.tech/public_html
./vendor/bin/drush theme:install byte -y
./vendor/bin/drush config:set system.theme default byte -y
./vendor/bin/drush cr
```

O via `/admin/appearance` → "Install and set as default".
