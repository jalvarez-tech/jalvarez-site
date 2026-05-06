<?php
/**
 * @file
 * One-shot seed: replace all sample notes with a single technical note that
 * walks programmers through how this very site is wired from GitHub to
 * Hostinger. Uses CKEditor 5's native Code Block plugin (no contrib module
 * needed in Drupal 11 — `editor_ckeditor_codetag` is the CKEditor 4
 * equivalent for older Drupal cores).
 *
 * Idempotent. Run once locally and once in prod.
 */

use Drupal\node\Entity\Node;
use Drupal\filter\Entity\FilterFormat;
use Drupal\editor\Entity\Editor;

// ---------------------------------------------------------------------------
// 1. Ensure a CKEditor 5 text format with Code Block plugin enabled.
// ---------------------------------------------------------------------------
$format_id = 'code_html';
$format = FilterFormat::load($format_id);
if (!$format) {
  $format = FilterFormat::create([
    'format' => $format_id,
    'name' => 'Code HTML (notes)',
    'weight' => 0,
    'roles' => ['authenticated', 'anonymous'],
    'filters' => [
      'filter_html' => [
        'id' => 'filter_html',
        'provider' => 'filter',
        'status' => TRUE,
        'weight' => -10,
        'settings' => [
          'allowed_html' => '<a href hreflang> <em> <strong> <cite> <blockquote cite> <code class> <ul type> <ol start type> <li> <dl> <dt> <dd> <h2 id> <h3 id> <h4 id> <h5 id> <h6 id> <p> <br> <pre> <span lang> <hr> <kbd>',
          'filter_html_help' => TRUE,
          'filter_html_nofollow' => FALSE,
        ],
      ],
      'filter_autop' => ['id' => 'filter_autop', 'provider' => 'filter', 'status' => TRUE, 'weight' => 0, 'settings' => []],
      'filter_url'   => ['id' => 'filter_url',   'provider' => 'filter', 'status' => TRUE, 'weight' => 0, 'settings' => ['filter_url_length' => 72]],
      'filter_htmlcorrector' => ['id' => 'filter_htmlcorrector', 'provider' => 'filter', 'status' => TRUE, 'weight' => 10, 'settings' => []],
    ],
  ]);
  $format->save();
  echo "✓ Created text format '{$format_id}'.\n";
}
else {
  echo "= Text format '{$format_id}' already exists.\n";
}

// CKEditor 5 with Code Block plugin in the toolbar.
$editor = Editor::load($format_id);
if (!$editor) {
  $editor = Editor::create([
    'format' => $format_id,
    'editor' => 'ckeditor5',
    'image_upload' => [],
    'settings' => [
      'toolbar' => [
        'items' => [
          'heading','|',
          'bold','italic','code','|',
          'link','|',
          'bulletedList','numberedList','|',
          'blockQuote','codeBlock','horizontalLine','|',
          'sourceEditing',
        ],
      ],
      'plugins' => [
        'ckeditor5_heading' => [
          'enabled_headings' => ['heading2','heading3','heading4'],
        ],
        'ckeditor5_codeBlock' => [
          'languages' => [
            ['language' => 'plaintext', 'label' => 'Plain text'],
            ['language' => 'bash',       'label' => 'Bash'],
            ['language' => 'yaml',       'label' => 'YAML'],
            ['language' => 'php',        'label' => 'PHP'],
            ['language' => 'json',       'label' => 'JSON'],
          ],
        ],
        'ckeditor5_sourceEditing' => [
          'allowed_tags' => ['<pre>', '<code class>', '<kbd>', '<span lang>'],
        ],
      ],
    ],
  ]);
  $editor->save();
  echo "✓ Configured CKEditor 5 for '{$format_id}' with Code Block plugin.\n";
}
else {
  echo "= CKEditor 5 already configured for '{$format_id}'.\n";
}

// ---------------------------------------------------------------------------
// 2. Delete all existing sample notes.
// ---------------------------------------------------------------------------
$nids = \Drupal::entityQuery('node')
  ->condition('type', 'note')
  ->accessCheck(FALSE)
  ->execute();
foreach (Node::loadMultiple($nids) as $n) {
  echo "  · deleting nid={$n->id()} '{$n->label()}'\n";
  $n->delete();
}

// ---------------------------------------------------------------------------
// 3. Create the new note (ES + EN) with the deploy walkthrough.
// ---------------------------------------------------------------------------
$body_es = <<<'HTML'
<p>El sitio que estás leyendo se construye en GitHub Actions y aterriza en Hostinger Cloud Startup vía SSH + rsync. Sin Composer ni Node corriendo en el servidor, sin paneles de control. Cada <code>git push</code> a <code>main</code> dispara un build en CI, compila los assets, y publica solo el artefacto final en producción.</p>

<p>Esta nota resume el setup en 7 pasos. Asume Drupal 11 + Drupal Canvas, PHP 8.4, MySQL local en Hostinger, y un repo en GitHub.</p>

<h2 id="paso-1">1 · Hostinger en hPanel</h2>

<p>Antes de tocar código, en <strong>hPanel → Hosting → Manage</strong>:</p>

<ul>
  <li><strong>Avanzado → Selector de PHP</strong>: PHP 8.4.</li>
  <li><strong>Bases de datos → MySQL</strong>: crear DB y usuario, anotar credenciales.</li>
  <li><strong>Avanzado → Acceso SSH</strong>: habilitar SSH, anotar host (IP), puerto (siempre 65002 en Hostinger Cloud) y user (<code>u&lt;id&gt;</code>).</li>
  <li><strong>Acceso SSH → Manage SSH Keys</strong>: pegar la llave pública ed25519.</li>
</ul>

<p>Generar la llave SSH localmente (sin passphrase: CI no puede teclear contraseñas):</p>

<pre><code class="language-bash">ssh-keygen -t ed25519 -f ~/.ssh/hostinger_jalvarez -C "deploy@github-actions"
chmod 600 ~/.ssh/hostinger_jalvarez
cat ~/.ssh/hostinger_jalvarez.pub | pbcopy   # pegar en hPanel</code></pre>

<h2 id="paso-2">2 · Secretos en GitHub</h2>

<p>En el repo: <strong>Settings → Secrets and variables → Actions</strong> (no Deploy keys). Diez valores:</p>

<ul>
  <li><code>HOSTINGER_HOST</code>, <code>HOSTINGER_PORT</code> (65002), <code>HOSTINGER_USER</code> (<code>u&lt;id&gt;</code>), <code>HOSTINGER_PATH</code> (<code>/home/u&lt;id&gt;/domains/&lt;site&gt;</code>).</li>
  <li><code>HOSTINGER_SSH_KEY</code>: el contenido completo de la llave privada (con newline final).</li>
  <li><code>DRUPAL_DB_NAME</code>, <code>DRUPAL_DB_USER</code>, <code>DRUPAL_DB_PASS</code>, <code>DRUPAL_DB_HOST</code> (<code>localhost</code>), <code>DRUPAL_HASH_SALT</code> (<code>openssl rand -base64 55 | tr -d '\n'</code>).</li>
</ul>

<h2 id="paso-3">3 · Composer ajustado para shared hosting</h2>

<p>Cuatro cambios en <code>composer.json</code> que ahorran horas de debugging:</p>

<pre><code class="language-json">{
  "require": {
    "drush/drush": "^13.7"
  },
  "config": {
    "platform": { "php": "8.4.0" },
    "vendor-dir": "web/vendor",
    "bin-dir": "web/vendor/bin"
  }
}</code></pre>

<ul>
  <li><strong>drush en <code>require</code>, no <code>require-dev</code></strong>: <code>composer install --no-dev</code> en CI sigue conservándolo para los hooks post-deploy.</li>
  <li><strong><code>platform.php</code></strong>: fuerza a Composer a resolver paquetes contra la versión PHP del servidor, no la del dev local.</li>
  <li><strong><code>vendor-dir = web/vendor</code></strong>: drupal-scaffold genera <code>web/autoload.php</code> con ruta <code>./vendor/autoload.php</code>, sin <code>../</code>. Necesario porque el docroot de Hostinger es <code>public_html</code> — un vendor afuera no resuelve.</li>
  <li><strong><code>bin-dir = web/vendor/bin</code></strong>: matching del anterior, mantiene <code>vendor/bin/drush</code> reachable desde el docroot.</li>
</ul>

<h2 id="paso-4">4 · settings.php desde plantilla con envsubst</h2>

<p>El truco: <code>envsubst</code> sin variable list explícita expande <strong>cada</strong> token <code>$VAR</code>, incluyendo las variables PHP del template (<code>$databases</code>, <code>$settings</code>, <code>$config</code>). Resultado: <code>settings.php</code> queda corrupto, drush boot falla con errores misteriosos.</p>

<p>La forma correcta — pasar la lista explícita:</p>

<pre><code class="language-yaml">- name: Render settings.php
  env:
    DRUPAL_DB_NAME: ${{ secrets.DRUPAL_DB_NAME }}
    DRUPAL_DB_USER: ${{ secrets.DRUPAL_DB_USER }}
    DRUPAL_DB_PASS: ${{ secrets.DRUPAL_DB_PASS }}
    DRUPAL_DB_HOST: ${{ secrets.DRUPAL_DB_HOST }}
    DRUPAL_HASH_SALT: ${{ secrets.DRUPAL_HASH_SALT }}
  run: |
    envsubst '${DRUPAL_DB_NAME} ${DRUPAL_DB_USER} ${DRUPAL_DB_PASS} ${DRUPAL_DB_HOST} ${DRUPAL_HASH_SALT}' \
      &lt; web/sites/default/settings.hostinger.php.template \
      &gt; web/sites/default/settings.php
    grep -q '^\$databases' web/sites/default/settings.php || (echo "::error::envsubst stripped \$databases" && exit 1)
    php -l web/sites/default/settings.php</code></pre>

<p>El último <code>grep</code> es el guardarraíl: si vuelve a fallar la sustitución, el deploy aborta antes de pisar producción.</p>

<h2 id="paso-5">5 · Layout en Hostinger (flat + dos symlinks)</h2>

<p>Cómo queda el filesystem en el servidor:</p>

<pre><code class="language-plaintext">/home/u&lt;id&gt;/domains/&lt;site&gt;/
├── composer.json              ← deployed (RecipeHandler lo lee)
├── composer.lock              ← deployed
├── recipes/                   ← deployed (el installer escanea aquí)
├── web → public_html          ← SYMLINK (composer autoloader lo recorre)
└── public_html/               ← DOCROOT
    ├── core/
    ├── modules/
    ├── themes/
    ├── vendor/                ← composer install (vendor-dir = web/vendor)
    ├── config/sync/
    ├── private/
    ├── sites/default/
    │   ├── settings.php       ← rendered by CI envsubst
    │   └── files/
    └── index.php</code></pre>

<p>El symlink <code>web → public_html</code> vive UNA carpeta arriba del docroot, así que no es accesible por web. Existe solo para que el autoloader (que bake-eo paths como <code>web/core/...</code> al instalar) los pueda resolver en runtime.</p>

<h2 id="paso-6">6 · Workflow de deploy</h2>

<p>Una sola job en GitHub Actions ejecuta build + ship + post-deploy:</p>

<pre><code class="language-yaml">name: Deploy to Hostinger
on:
  push: { branches: [main] }
  workflow_dispatch:

jobs:
  build-and-deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with: { php-version: '8.4', tools: 'composer:v2' }
      - run: composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

      - uses: actions/setup-node@v4
        with: { node-version: '20', cache: 'npm', cache-dependency-path: web/themes/custom/byte/package-lock.json }
      - working-directory: web/themes/custom/byte
        run: npm ci && npm run build

      # Render settings.php (paso 4)
      - name: Render settings.php
        run: envsubst '...' &lt; template &gt; web/sites/default/settings.php

      - uses: shimataro/ssh-key-action@v2
        with: { key: ${{ secrets.HOSTINGER_SSH_KEY }}, name: hostinger, ... }

      - run: rsync -avz --delete --exclude-from=.deployignore -e ssh ./web/ hostinger:${{ secrets.HOSTINGER_PATH }}/public_html/
      - run: rsync -avz --delete -e ssh ./config/ hostinger:${{ secrets.HOSTINGER_PATH }}/public_html/config/
      - run: rsync -avz -e ssh ./composer.json ./composer.lock hostinger:${{ secrets.HOSTINGER_PATH }}/

      - name: Drupal post-deploy
        run: |
          ssh hostinger "
            cd ${{ secrets.HOSTINGER_PATH }}/public_html
            ./vendor/bin/drush updb -y
            ./vendor/bin/drush cim -y || true
            ./vendor/bin/drush cr
          "</code></pre>

<p>El <code>--exclude-from=.deployignore</code> deja fuera fuentes SCSS, package.json, node_modules y todo lo que CI ya compiló — solo viaja el artefacto final.</p>

<h2 id="paso-7">7 · Primera instalación (una sola vez)</h2>

<p>Tras el primer push, el código está en el servidor pero no hay tablas. Vía SSH:</p>

<pre><code class="language-bash">ssh -i ~/.ssh/hostinger_jalvarez -p 65002 u&lt;id&gt;@&lt;host&gt;
cd domains/&lt;site&gt;/public_html

# Apuntar al PHP CLI correcto (Hostinger usa /usr/bin/php por defecto, viejo)
echo 'export PATH=/opt/alt/php84/usr/bin:$PATH' &gt;&gt; ~/.bashrc
source ~/.bashrc

./vendor/bin/drush site:install minimal \
  --site-name='jalvarez.tech' \
  --account-name=admin \
  --account-mail=stevanswd@gmail.com \
  --account-pass='&lt;strong-password&gt;' \
  -y</code></pre>

<p>De ahí en adelante cada <code>git push origin main</code> hace el ciclo completo solo: build, transfer, <code>updb + cim + cr</code>. Si Drupal ya está bootstrap-eable, el workflow sigue corriendo. Si todavía no, se salta los hooks y avisa.</p>

<h2 id="bonus">Bonus — endurecer permisos al final</h2>

<p>Hostinger se queja si <code>settings.php</code> queda 644 después del deploy. El último step revierte permisos al estado seguro:</p>

<pre><code class="language-bash">chmod 555 sites/default
chmod 444 sites/default/settings.php</code></pre>

<p>Y el <code>chmod u+w</code> al inicio del próximo deploy se encarga de re-abrirlos cuando rsync necesite escribir.</p>

<p>El stack completo está documentado en el skill <code>drupal-hostinger-deploy</code>: 28 traps específicos que muerden la primera vez (envsubst, symlink, autoloader path, language prefix de Canvas, translation wipes…) y patterns reutilizables para footer bilingüe, cards con cover image, y scripts de mantenimiento. Cada trap es una hora de debug ahorrada.</p>
HTML;

$body_en = <<<'HTML'
<p>The site you're reading builds in GitHub Actions and lands on Hostinger Cloud Startup over SSH + rsync. No Composer or Node running on the server, no control panels. Every <code>git push</code> to <code>main</code> kicks off a CI build, compiles the assets, and ships only the final artefact.</p>

<p>This note walks the setup in 7 steps. Assumes Drupal 11 + Drupal Canvas, PHP 8.4, MySQL on Hostinger, and a GitHub repo.</p>

<h2 id="step-1">1 · Hostinger via hPanel</h2>

<p>Before touching code, in <strong>hPanel → Hosting → Manage</strong>:</p>

<ul>
  <li><strong>Advanced → PHP Selector</strong>: PHP 8.4.</li>
  <li><strong>Databases → MySQL</strong>: create DB + user, note the credentials.</li>
  <li><strong>Advanced → SSH Access</strong>: enable SSH, note host (IP), port (always 65002 on Hostinger Cloud), user (<code>u&lt;id&gt;</code>).</li>
  <li><strong>SSH Access → Manage SSH Keys</strong>: paste the ed25519 public key.</li>
</ul>

<p>Generate the key locally (no passphrase: CI can't type passwords):</p>

<pre><code class="language-bash">ssh-keygen -t ed25519 -f ~/.ssh/hostinger_jalvarez -C "deploy@github-actions"
chmod 600 ~/.ssh/hostinger_jalvarez
cat ~/.ssh/hostinger_jalvarez.pub | pbcopy   # paste in hPanel</code></pre>

<h2 id="step-2">2 · GitHub secrets</h2>

<p>In the repo: <strong>Settings → Secrets and variables → Actions</strong> (not Deploy keys). Ten values:</p>

<ul>
  <li><code>HOSTINGER_HOST</code>, <code>HOSTINGER_PORT</code> (65002), <code>HOSTINGER_USER</code> (<code>u&lt;id&gt;</code>), <code>HOSTINGER_PATH</code> (<code>/home/u&lt;id&gt;/domains/&lt;site&gt;</code>).</li>
  <li><code>HOSTINGER_SSH_KEY</code>: full contents of the private key (with trailing newline).</li>
  <li><code>DRUPAL_DB_NAME</code>, <code>DRUPAL_DB_USER</code>, <code>DRUPAL_DB_PASS</code>, <code>DRUPAL_DB_HOST</code> (<code>localhost</code>), <code>DRUPAL_HASH_SALT</code> (<code>openssl rand -base64 55 | tr -d '\n'</code>).</li>
</ul>

<h2 id="step-3">3 · Composer adjusted for shared hosting</h2>

<p>Four edits to <code>composer.json</code> that save hours of debugging:</p>

<pre><code class="language-json">{
  "require": {
    "drush/drush": "^13.7"
  },
  "config": {
    "platform": { "php": "8.4.0" },
    "vendor-dir": "web/vendor",
    "bin-dir": "web/vendor/bin"
  }
}</code></pre>

<ul>
  <li><strong>drush in <code>require</code>, not <code>require-dev</code></strong>: <code>composer install --no-dev</code> in CI keeps it for post-deploy hooks.</li>
  <li><strong><code>platform.php</code></strong>: forces Composer to resolve packages against the server's PHP, not the dev's local PHP.</li>
  <li><strong><code>vendor-dir = web/vendor</code></strong>: drupal-scaffold generates <code>web/autoload.php</code> pointing at <code>./vendor/autoload.php</code>, no <code>../</code>. Required because Hostinger's docroot is <code>public_html</code> — a vendor outside it doesn't resolve.</li>
  <li><strong><code>bin-dir = web/vendor/bin</code></strong>: matches the previous, keeps <code>vendor/bin/drush</code> reachable from the docroot.</li>
</ul>

<h2 id="step-4">4 · settings.php from a template via envsubst</h2>

<p>The trap: <code>envsubst</code> with no explicit variable list expands <strong>every</strong> <code>$VAR</code>-looking token, including PHP variables in the template (<code>$databases</code>, <code>$settings</code>, <code>$config</code>). Result: <code>settings.php</code> ends corrupted, drush bootstrap fails with cryptic errors.</p>

<p>The right way — pass the explicit list:</p>

<pre><code class="language-yaml">- name: Render settings.php
  env:
    DRUPAL_DB_NAME: ${{ secrets.DRUPAL_DB_NAME }}
    DRUPAL_DB_USER: ${{ secrets.DRUPAL_DB_USER }}
    DRUPAL_DB_PASS: ${{ secrets.DRUPAL_DB_PASS }}
    DRUPAL_DB_HOST: ${{ secrets.DRUPAL_DB_HOST }}
    DRUPAL_HASH_SALT: ${{ secrets.DRUPAL_HASH_SALT }}
  run: |
    envsubst '${DRUPAL_DB_NAME} ${DRUPAL_DB_USER} ${DRUPAL_DB_PASS} ${DRUPAL_DB_HOST} ${DRUPAL_HASH_SALT}' \
      &lt; web/sites/default/settings.hostinger.php.template \
      &gt; web/sites/default/settings.php
    grep -q '^\$databases' web/sites/default/settings.php || (echo "::error::envsubst stripped \$databases" && exit 1)
    php -l web/sites/default/settings.php</code></pre>

<p>The final <code>grep</code> is the guardrail: if substitution ever breaks again, the deploy aborts before touching production.</p>

<h2 id="step-5">5 · Layout on Hostinger (flat + two symlinks)</h2>

<p>How the filesystem ends up on the server:</p>

<pre><code class="language-plaintext">/home/u&lt;id&gt;/domains/&lt;site&gt;/
├── composer.json              ← deployed (RecipeHandler reads it)
├── composer.lock              ← deployed
├── recipes/                   ← deployed (installer scans here)
├── web → public_html          ← SYMLINK (composer autoloader walks it)
└── public_html/               ← DOCROOT
    ├── core/
    ├── modules/
    ├── themes/
    ├── vendor/                ← composer install (vendor-dir = web/vendor)
    ├── config/sync/
    ├── private/
    ├── sites/default/
    │   ├── settings.php       ← rendered by CI envsubst
    │   └── files/
    └── index.php</code></pre>

<p>The <code>web → public_html</code> symlink lives ONE level above the docroot, so it isn't web-accessible. It exists only so the autoloader (which bakes paths like <code>web/core/...</code> at install time) can resolve them at runtime.</p>

<h2 id="step-6">6 · Deploy workflow</h2>

<p>One GitHub Actions job runs build + ship + post-deploy:</p>

<pre><code class="language-yaml">name: Deploy to Hostinger
on:
  push: { branches: [main] }
  workflow_dispatch:

jobs:
  build-and-deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with: { php-version: '8.4', tools: 'composer:v2' }
      - run: composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

      - uses: actions/setup-node@v4
        with: { node-version: '20', cache: 'npm', cache-dependency-path: web/themes/custom/byte/package-lock.json }
      - working-directory: web/themes/custom/byte
        run: npm ci && npm run build

      # Render settings.php (step 4)
      - name: Render settings.php
        run: envsubst '...' &lt; template &gt; web/sites/default/settings.php

      - uses: shimataro/ssh-key-action@v2
        with: { key: ${{ secrets.HOSTINGER_SSH_KEY }}, name: hostinger, ... }

      - run: rsync -avz --delete --exclude-from=.deployignore -e ssh ./web/ hostinger:${{ secrets.HOSTINGER_PATH }}/public_html/
      - run: rsync -avz --delete -e ssh ./config/ hostinger:${{ secrets.HOSTINGER_PATH }}/public_html/config/
      - run: rsync -avz -e ssh ./composer.json ./composer.lock hostinger:${{ secrets.HOSTINGER_PATH }}/

      - name: Drupal post-deploy
        run: |
          ssh hostinger "
            cd ${{ secrets.HOSTINGER_PATH }}/public_html
            ./vendor/bin/drush updb -y
            ./vendor/bin/drush cim -y || true
            ./vendor/bin/drush cr
          "</code></pre>

<p>The <code>--exclude-from=.deployignore</code> filters out SCSS sources, package.json, node_modules, anything CI already compiled — only the final artefact ships.</p>

<h2 id="step-7">7 · First install (once)</h2>

<p>After the first push, the code is on the server but no tables exist yet. Via SSH:</p>

<pre><code class="language-bash">ssh -i ~/.ssh/hostinger_jalvarez -p 65002 u&lt;id&gt;@&lt;host&gt;
cd domains/&lt;site&gt;/public_html

# Point at the right PHP CLI (Hostinger defaults to old /usr/bin/php)
echo 'export PATH=/opt/alt/php84/usr/bin:$PATH' &gt;&gt; ~/.bashrc
source ~/.bashrc

./vendor/bin/drush site:install minimal \
  --site-name='jalvarez.tech' \
  --account-name=admin \
  --account-mail=stevanswd@gmail.com \
  --account-pass='&lt;strong-password&gt;' \
  -y</code></pre>

<p>From there on every <code>git push origin main</code> runs the full cycle on its own: build, transfer, <code>updb + cim + cr</code>. If Drupal can bootstrap, the workflow keeps going. If it can't yet, it skips the hooks and warns.</p>

<h2 id="bonus">Bonus — re-harden permissions at the end</h2>

<p>Hostinger complains if <code>settings.php</code> stays 644 after deploy. The last step reverts to a safe state:</p>

<pre><code class="language-bash">chmod 555 sites/default
chmod 444 sites/default/settings.php</code></pre>

<p>And the <code>chmod u+w</code> at the start of the next deploy reopens them when rsync needs to write.</p>

<p>The full stack is documented in the <code>drupal-hostinger-deploy</code> skill: 28 specific traps that bite first-time setups (envsubst, symlink, autoloader path, Canvas's URL language prefix, translation wipes…) and reusable patterns for bilingual footer, cards with cover image, and maintenance scripts. Each trap is an hour of debugging saved.</p>
HTML;

// Find or create a 'How I work' / 'DevOps' topic term to link the note.
// Skip if vocabulary doesn't exist.
$topic_id = NULL;
$topic_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
$existing = $topic_storage->loadByProperties(['name' => 'DevOps', 'vid' => 'note_topics']);
if ($existing) {
  $topic_id = (int) reset($existing)->id();
}

$node = Node::create([
  'type' => 'note',
  'langcode' => 'es',
  'status' => TRUE,
  'title' => 'Cómo conecté el sitio desde GitHub a Hostinger',
  'field_publish_date' => '2026-05-06',
  'field_excerpt' => 'El paso a paso del CI/CD que mueve este sitio desde mi repo a producción: build en GitHub Actions, rsync por SSH, drush en post-deploy. Sin Composer ni Node corriendo en el servidor.',
  'field_thumb_glyph' => 'workflow',
  'body' => [
    'value' => $body_es,
    'format' => $format_id,
  ],
]);
if ($topic_id) {
  $node->set('field_note_topic', ['target_id' => $topic_id]);
}
$node->save();
echo "✓ Created ES note nid={$node->id()}\n";

// EN translation.
$node->addTranslation('en', [
  'title' => 'How I wired this site from GitHub to Hostinger',
  'field_excerpt' => 'The CI/CD playbook that moves this site from repo to production: build on GitHub Actions, rsync over SSH, drush in post-deploy. No Composer or Node running on the server.',
  'body' => [
    'value' => $body_en,
    'format' => $format_id,
  ],
])->save();
echo "✓ Added EN translation\n";

drupal_flush_all_caches();
echo "\n✓ Done. Note URL: /es/notas/" . $node->toUrl()->toString() . "\n";
