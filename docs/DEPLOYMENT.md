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

Hay tres mecanismos, en orden de granularidad:

**1. Revertir el commit (rollback rápido — preserva la DB):**
```
git revert <hash> && git push   # CI re-deploya el estado previo
```
Esto sólo revierte código (rsync), no toca la DB. Si el problema es código, basta con esto. Si el problema es una migración de DB rota, hace falta el siguiente paso también.

**2. Restaurar la DB desde el snapshot pre-deploy (rollback de datos):**
Cada deploy genera un dump gzipped en `~/db-snapshots/jalvarez-pre-deploy-<timestamp>.sql.gz` ANTES de correr `drush updb`. Se rotan automáticamente — quedan los últimos 5. Para restaurar:
```bash
ssh hostinger
cd $HOSTINGER_PATH/public_html
ls -1t ~/db-snapshots/jalvarez-pre-deploy-*.sql.gz | head -5  # ver opciones
SNAPSHOT=~/db-snapshots/jalvarez-pre-deploy-20260507-031500.sql.gz
gunzip < $SNAPSHOT | ./vendor/bin/drush sqlc
./vendor/bin/drush cr
```

**3. Maintenance mode manual** (cuando necesitas oscurecer el sitio fuera de un deploy):
```bash
ssh hostinger "cd \$HOSTINGER_PATH/public_html && ./vendor/bin/drush state:set system.maintenance_mode 1 --input-format=integer && ./vendor/bin/drush cr"
# … investiga / repara …
ssh hostinger "cd \$HOSTINGER_PATH/public_html && ./vendor/bin/drush state:set system.maintenance_mode 0 --input-format=integer && ./vendor/bin/drush cr"
```
El workflow ya activa/desactiva maintenance automáticamente alrededor de cada deploy — sólo necesitas estos comandos para escenarios manuales.

---

## 9b. GitHub Environment `production` (one-time setup)

`deploy.yml` declara `environment: production`. La primera vez que un push a `main` dispara el workflow, GitHub crea automáticamente el environment con ese nombre. Después, hay que ir a la UI a configurarlo:

1. **Repo → Settings → Environments → production**
2. **Required reviewers**: añadir al menos 1 (idealmente 2). Cada deploy queda en pausa hasta que un reviewer aprueba en la UI de Actions. Sin esto, cualquier merge a `main` despliega sin gate humano.
3. **Wait timer** (opcional): 5–10 minutos. Da margen para cancelar (`gh run cancel`) si te das cuenta que el merge tenía un bug.
4. **Deployment branches**: restringir a `main` solamente. Evita que alguien dispare deploy desde otra rama vía `workflow_dispatch`.
5. **Mover los secrets**: hoy `secrets.HOSTINGER_*` y `secrets.DRUPAL_*` viven a nivel repo. Idealmente se mueven al environment (Settings → Environments → production → Environment secrets) — así sólo workflows con `environment: production` pueden leerlos. Si los mueves, asegúrate de copiar TODOS antes de eliminar las versiones del repo (de lo contrario el siguiente run falla).

`docs/DEPLOYMENT.md` y este checklist son la fuente de verdad — la configuración del Environment no se versiona en YAML, vive en GitHub.

---

## 10. Estado actual

### Setup completado

- ✅ Plan Cloud Startup, PHP 8.4 activo
- ✅ DB MySQL creada (`u211065173_jalvarez_site`)
- ✅ SSH key generada, pubkey en hPanel, conexión validada (`191.101.32.187:65002`)
- ✅ 10 GitHub Secrets configurados
- ✅ Workflows: `.github/workflows/deploy.yml` + `ci.yml`
- ✅ `.deployignore` con sources excluidos
- ✅ `web/sites/default/settings.hostinger.php.template`
- ✅ Theme `byte` scaffold mínimo (info, libraries, theme PHP, package.json, scripts, scss tokens + main)
- ✅ Canvas migration: 19 SDCs registrados, home node "Inicio (Canvas)" con field_canvas tree (ES + EN)

### Post-deploy específico de Canvas (orden importa)

Después del primer deploy con Canvas, corra estos scripts vía SSH (ver patrón en [§ Cómo correr un script puntual en prod](#cómo-correr-un-script-puntual-en-prod) más abajo), en este orden:

1. **`scripts/maintenance/canvas-discover-sdcs.php`** (idempotente)
   Re-discover los SDCs de byte y registra los Component config entities. Útil si `drush cim` no creó automáticamente los `canvas.component.sdc.byte.*`. También útil tras añadir nuevos SDCs.

2. **`scripts/maintenance/setup/create-media-image-type.php`** (idempotente)
   Crea el `media_type: image` con su `field_media_image`. **Requisito hard de canvas_page**: sin un media type tipo image, MediaLibraryWidget crashea cuando Canvas genera el form para el campo base `image` de canvas_page. Sin esto, el editor visual queda en blanco.

3. **Initial canvas_page seed** — already in production. The original seed
   scripts (`create-canvas-home.php`, `create-canvas-other-pages.php`) were
   one-shots that ran once per environment in F8–F10. They were deleted in
   PR2 (2026-05-07) because the resulting `canvas_page` entities now live
   in production and the structural definitions sit in `config/sync`. For
   a brand-new environment, recreate the four pages by editing through the
   visual Canvas editor.

4. **`scripts/maintenance/setup/place-nav-block.php`** (solo primera vez)
   Coloca el block `jalvarez_nav_glass` en el region `byte:header`.

5. **`scripts/maintenance/setup/configure-form-displays.php`** (opcional, solo si cambian los schemas de fields en project/note)
   Configura form displays con field_group fieldsets para project, note y page bundles.

### Post-deploy específico de SEO (orden importa)

El módulo `simple_sitemap` se habilita automáticamente vía `drush cim` (está en `config/sync/core.extension.yml`) y el workflow corre `drush ssg` en cada deploy para regenerar `sitemap.xml`. Lo único que no se hace solo es:

1. **`scripts/maintenance/setup/configure-simple-sitemap.php`** (idempotente — solo correr 1ª vez por entorno)
   Registra `canvas_page`, `node:project` y `node:note` como bundles indexables en el variant `default` del sitemap. Sin esto, `drush ssg` produciría un sitemap vacío. Correr vía SSH (ver § Cómo correr un script puntual en prod).

2. **Metatags seed** — already in production. The `update-seo-metatags.php`
   one-shot script populated the `metatags` field on the 4 canvas_pages in
   F11; the values now live in DB and `config/sync`. Future SEO copy
   changes go through the visual editor or `drush config:set`. Script
   deleted in PR2.

The sitemap variant config script must run once per environment. In subsequent deploys it's not needed: the sitemap regenerates automatically via `drush ssg` and the metatags persist in DB.

`/llms.txt` y `/llms-full.txt` son endpoints dinámicos servidos por `LlmsTxtController`. No requieren generación: leen DB en cada request con `CacheableResponse` tagueada (cache tags `canvas_page_list`, `node_list:project`, `node_list:note`) — la edición de cualquier proyecto, nota o canvas_page invalida el cache automáticamente.

### Cómo correr un script puntual en prod

Para los `scripts/*.php` listados arriba (y para hotfixes one-shot), el patrón es `scp` + `drush php:script` por SSH:

```bash
SCRIPT=scripts/<name>.php
scp "$SCRIPT" hostinger:/tmp/
ssh hostinger "cd \$DRUPAL_PATH/public_html && ./vendor/bin/drush php:script /tmp/$(basename "$SCRIPT") && rm /tmp/$(basename "$SCRIPT")"
```

`hostinger` es el alias SSH definido localmente (`~/.ssh/config` con `HostName`, `Port`, `User`, `IdentityFile`); `$DRUPAL_PATH` es la ruta al proyecto en el servidor (ver `secrets.HOSTINGER_PATH` en GitHub Secrets para el valor canónico). En la máquina del autor está exportado en `~/.zshrc` para evitar repetirlo.

> Antes había un workflow `seed-content.yml` que hacía esto vía `workflow_dispatch`. Se eliminó en PR1 (2026-05-06) porque la mayoría de los scripts en `scripts/` eran one-shots ya aplicados, y PR2 (2026-05-07) terminó la limpieza: 49 scripts borrados, 4 supervivientes activos en `scripts/maintenance/`, 11 setup parked en `scripts/maintenance/setup/` (audit pendiente para borrarlos o migrarlos a recipe), 3 reusables convertidos a Drush commands en `MaintenanceCommands.php`, y 1 mutación de field config movida a `jalvarez_site_update_10001()`. Ejecutar puntualmente por SSH reduce la superficie de ataque (un workflow menos con `secrets.HOSTINGER_*`) y mantiene el flujo trivial.

### Sobre la portabilidad de IDs

`system.site.page.front = /page/<id>` referencia el ID numérico del canvas_page Inicio. Como los IDs son auto-incrementales y difieren entre entornos, el script `create-canvas-home.php` actualiza este config dinámicamente al crear la entidad. **No hay que editarlo manualmente entre entornos** — basta con correr el script.

Lo mismo para los aliases (`/inicio`, `/home`, `/proyectos`, etc.): son parte del `path` field de cada canvas_page y se crean junto con la entidad.

### Defensa contra el bug "drush cim contamina prod"

**Síntoma observado (2026-05-02):** después de un `git push`, las URLs `/`, `/es`, `/en` empezaron a devolver 404 con title `"jalvarez.tech (local)"`. El resto de URLs (`/es/proyectos`, etc.) seguían funcionando porque tienen alias propios.

**Causa raíz:** `config/sync/system.site.yml` traía valores de un export local con `name: 'jalvarez.tech (local)'`, `mail: admin@example.com` y `page.front: /page/8` (ID que existía en local pero no en prod). El step `drush cim` del workflow re-aplicó esos valores en producción, rompiendo la home.

**Fix permanente** (vive en el repo):

1. **Comando Drush custom `drush jalvarez:repair-system-site`** — implementado en `web/modules/custom/jalvarez_site/src/Drush/Commands/RepairCommands.php`:
   - Resuelve la home dinámicamente vía `path_alias.manager->getPathByAlias('/inicio')` → `/page/{id}` (sin asumir IDs entre envs).
   - Fuerza `system.site.name`, `system.site.mail`, `system.site.page.front` y `update.settings.notification.emails` a sus valores canónicos.
   - Idempotente (solo escribe si hay drift).
   - Vive dentro del módulo, viaja con el rsync — no requiere `scp` cada deploy.

2. **`.github/workflows/deploy.yml` — paso post-cim:** después de `drush updb && cim && cr`, el workflow ejecuta `drush jalvarez:repair-system-site`. Así, aunque alguien re-exporte config local sin sanitizar, el deploy lo repara automáticamente.

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
scp scripts/maintenance/restore-canvas-home-en.php hostinger:/tmp/
ssh hostinger "cd \$DRUPAL_PATH/public_html && ./vendor/bin/drush php:script /tmp/restore-canvas-home-en.php && rm /tmp/restore-canvas-home-en.php"
```

El script [`scripts/maintenance/restore-canvas-home-en.php`](../scripts/maintenance/restore-canvas-home-en.php) resuelve la canvas_page Inicio por alias `/inicio` y reaplica la translation EN canónica sin tocar el ES. Idempotente: detecta si la translation existe o falta. Tras correrlo:

```bash
# Drupal cache + page cache
ssh hostinger "cd \$DRUPAL_PATH/public_html && ./vendor/bin/drush cr"
```

Y esperar máximo 15 min a que el LiteSpeed cache de Hostinger expire (TTL `cache.page.max_age = 900`), o forzar refresh con `Cache-Control: no-cache` para verificar inmediatamente.

**Prevención automática (post-2026-05-04):** el módulo `jalvarez_site` implementa `hook_canvas_page_presave()` que detecta y revierte el wipe automáticamente — ver [`web/modules/custom/jalvarez_site/jalvarez_site.module`](../web/modules/custom/jalvarez_site/jalvarez_site.module). El hook se ejecuta antes de cada `->save()` de canvas_page y, si detecta que una translation existente quedaría con `components` vacíos o que desaparecería de la entity, restaura el value original desde storage.

Se valida con `drush jalvarez:test-wipe-guard` (portado a Drush command en PR2), que reproduce el bug por entity API y comprueba que el hook lo bloquea. Idempotente y safe en prod (revierte sus cambios al final). Para ejecutarlo: `ssh hostinger "cd \$DRUPAL_PATH/public_html && ./vendor/bin/drush jalvarez:test-wipe-guard"`.

**Trade-off:** vaciar legítimamente todos los componentes de una translation desde el editor visual ya no funciona. Si quieres hacerlo a propósito, usa drush:
```bash
drush php:eval '\Drupal\canvas\Entity\Page::load(5)->getTranslation("en")->set("components", [])->save();'
```

**Si todo falla:** el restore manual sigue disponible — ver el script en `scripts/maintenance/restore-canvas-home-en.php` y la sección § Cómo correr un script puntual en prod más abajo.

> ⚠️ **Importante para devs locales:** después de un `git pull` que toca `*.module` o `*.install`, hay que correr `ddev exec ./web/vendor/bin/drush cr` antes de probar el editor visual. Drupal cachea `module.implements` y un hook nuevo no se dispara hasta el rebuild. El deploy a prod ya lo hace automáticamente (`drush cim && cr` en el workflow), pero el entorno local depende de la disciplina del dev.
>
> **Síntoma de hook no registrado:** editas en el editor, pierdes una translation, y `drush watchdog:show --type=jalvarez_site` no muestra ningún warning del guard. La cura es `drush cr` + restore-canvas-home-en.php.

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
