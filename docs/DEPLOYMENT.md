# Deployment — GitHub → Hostinger

> Pipeline CI/CD: el repositorio en GitHub es la fuente de verdad. GitHub Actions compila assets, instala dependencias, y publica solo el artefacto final en Hostinger. El servidor de Hostinger NO ejecuta `composer`, `npm` ni `sass`.

- **Última actualización:** 2026-05-01
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
          php-version: '8.3'
          tools: composer:v2
      - run: composer install --no-dev --optimize-autoloader --no-interaction

      # Node + SASS + Lucide sprite
      - uses: actions/setup-node@v4
        with:
          node-version: '20'
          cache: 'npm'
          cache-dependency-path: web/themes/custom/byte/package-lock.json
      - run: cd web/themes/custom/byte && npm ci && npm run build

      # Inject production settings.php
      - name: Inject settings.php
        run: |
          cp web/sites/default/settings.hostinger.php web/sites/default/settings.php
        # Secrets (DB) inyectados via env y leídos en settings.hostinger.php

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
web/sites/default/settings.hostinger.php
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
| H3 | Docroot | **`public_html/` fijo** (no se puede cambiar). Layout: contenido de `web/` se publica directo en `public_html/`; `vendor/`, `config/`, `drush/` viven un nivel arriba en `~/domains/jalvarez.tech/` (NO web-accessible). El `web/autoload.php` ya apunta a `../vendor/autoload.php`, así que el patrón funciona sin tocar core. |
| H4 | Dominio | **jalvarez.tech** |
| H5 | MySQL | Credenciales de hPanel → guardar en GitHub Secrets como `DRUPAL_DB_*` |
| H6 | Drush | **Disponible vía SSH en Cloud Startup**. Se invoca por `~/domains/jalvarez.tech/vendor/bin/drush` post-deploy. Ref: https://www.hostinger.com/mx/tutoriales/tutorial-drupal |
| H7 | Node | **NO se ejecuta en Hostinger**. Solo corre en GitHub Actions (Node 20 LTS, configurado en `deploy.yml`). En el servidor de Hostinger no necesitas Node — solo PHP. Si después necesitas Node para algo runtime (raro en Drupal clásico), Cloud Startup lo permite via NVM en SSH. |
| H8 | PHP | **PHP 8.3** recomendado. Razones: (1) Drupal 11 requiere 8.3+; (2) 8.3 es el LTS más probado con la cadena de contribs actuales; (3) 8.4 es viable pero algunos módulos contrib aún no lo declaran. Activar en hPanel → Avanzado → Selector de PHP → 8.3. |
| H9 | Uploads `sites/default/files` | **Persistente.** rsync usa `--exclude='public_html/sites/default/files/'` — los archivos del CMS nunca se tocan en deploy. |
| H10 | Backup | **Backup nativo Hostinger Cloud Startup activo.** No agregamos backup en CI. Si en el futuro se requiere snapshot por deploy, se añade un step antes del rsync que dispare `mysqldump` y lo guarde como artefacto del run. |

---

## 6b. Layout final en Hostinger (Cloud Startup, docroot fijo en public_html)

```
/home/<user>/
└── domains/jalvarez.tech/
    ├── vendor/                   ← Composer deps (NO web-accessible)
    ├── config/
    │   └── sync/                 ← config exportada via drush cex
    ├── drush/
    │   └── drush.yml             ← config drush
    ├── private/                  ← $settings['file_private_path']
    └── public_html/              ← DOCROOT (= contenido de web/ del repo)
        ├── core/
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
        ├── sites/default/
        │   ├── settings.php               ← inyectado por CI
        │   ├── services.yml
        │   └── files/                     ← PERSISTENTE, no se sobrescribe
        ├── autoload.php                   ← apunta a ../vendor/autoload.php (default Drupal)
        ├── index.php
        └── .htaccess
```

> Drupal viene preparado: el `web/autoload.php` por defecto hace `return require __DIR__ . '/../vendor/autoload.php';`. Como `web/ ≡ public_html/` y `vendor/` queda un nivel arriba, todo funciona sin parchar core.

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

## 8. `settings.hostinger.php`

Archivo plantilla en repo (sin valores reales). Lee env vars que CI inyecta antes del rsync:

```php
<?php
// settings.hostinger.php — copiado a sites/default/settings.php por CI antes del rsync.
// Sirve cuando: docroot = public_html/, vendor/ y config/ viven en ../

$databases['default']['default'] = [
  'database' => getenv('DRUPAL_DB_NAME'),
  'username' => getenv('DRUPAL_DB_USER'),
  'password' => getenv('DRUPAL_DB_PASS'),
  'host'     => getenv('DRUPAL_DB_HOST') ?: 'localhost',
  'driver'   => 'mysql',
  'prefix'   => '',
  'charset'  => 'utf8mb4',
  'collation'=> 'utf8mb4_general_ci',
];

$settings['hash_salt'] = getenv('DRUPAL_HASH_SALT');

// Rutas relativas al docroot public_html/
//   public_html/sites/default/settings.php
// + ../../../config/sync   →   ~/domains/jalvarez.tech/config/sync
// + ../../../private       →   ~/domains/jalvarez.tech/private
$settings['config_sync_directory'] = '../../../config/sync';
$settings['file_private_path']     = '../../../private';

$settings['trusted_host_patterns'] = [
  '^jalvarez\.tech$',
  '^www\.jalvarez\.tech$',
];

// Performance: agregación de CSS/JS activa en producción
$config['system.performance']['css']['preprocess'] = TRUE;
$config['system.performance']['js']['preprocess']  = TRUE;

// Bloquear instalador en producción
$settings['rebuild_access'] = FALSE;
$settings['skip_permissions_hardening'] = FALSE;
```

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

## 10. Qué falta para arrancar

### Acciones del usuario (en hPanel + local)

1. ⏳ **Activar PHP 8.3** en hPanel → Avanzado → Selector de PHP.
2. ⏳ **Crear DB MySQL** en hPanel → Bases de datos → MySQL. Anotar name/user/pass.
3. ⏳ **Generar SSH key local**:
   ```bash
   ssh-keygen -t ed25519 -f ~/.ssh/hostinger_jalvarez -C "deploy@github"
   ```
4. ⏳ **Subir pubkey a Hostinger**: hPanel → SSH Access → Manage SSH Keys → pegar contenido de `~/.ssh/hostinger_jalvarez.pub`.
5. ⏳ **Probar conexión local** una vez:
   ```bash
   ssh -i ~/.ssh/hostinger_jalvarez -p 65002 <user>@<host>
   ```
6. ⏳ **Configurar GitHub Secrets** (10 secrets de §7) en `Settings → Secrets and variables → Actions`.

### Acciones de mi parte (cuando confirmes lo anterior)

7. ⏳ Crear:
   - `.github/workflows/deploy.yml`
   - `.github/workflows/ci.yml` (lint + build dry-run en PRs)
   - `.deployignore`
   - `web/sites/default/settings.hostinger.php`
   - `web/themes/custom/byte/package.json`
   - `web/themes/custom/byte/scripts/build-icons.mjs`
   - `web/themes/custom/byte/icons.manifest.json`
8. ⏳ Validar end-to-end con un primer deploy manual (`workflow_dispatch`).

> **No se hace push automático hasta que pasos 1–6 estén completos.**
