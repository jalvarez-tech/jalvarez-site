#!/usr/bin/env node
/**
 * build-icons.mjs
 *
 * Generates `icons.svg` (sprite) from `icons.manifest.json` + `lucide-static`.
 * Each icon becomes a <symbol id="i-{name}"> in a single hidden <svg>.
 * Consumed from Twig with: <svg><use href="…icons.svg#i-{name}"/></svg>
 */

import fs from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname  = path.dirname(fileURLToPath(import.meta.url));
const themeRoot  = path.resolve(__dirname, '..');
const manifest   = path.join(themeRoot, 'icons.manifest.json');
const lucideDir  = path.resolve(themeRoot, 'node_modules', 'lucide-static', 'icons');
const outputPath = path.join(themeRoot, 'icons.svg');

const cfg = JSON.parse(await fs.readFile(manifest, 'utf-8'));
const icons = Array.isArray(cfg.lucide) ? cfg.lucide : [];

if (icons.length === 0) {
  console.error('icons.manifest.json: no icons listed under "lucide".');
  process.exit(1);
}

const symbols = await Promise.all(
  icons.map(async (name) => {
    const svgPath = path.join(lucideDir, `${name}.svg`);
    let svg;
    try {
      svg = await fs.readFile(svgPath, 'utf-8');
    } catch (err) {
      throw new Error(`Lucide icon not found: ${name}.svg (${svgPath})`);
    }

    // Lucide source: <svg xmlns="…" width="24" height="24" viewBox="0 0 24 24" …>…</svg>
    // Convert <svg …> → <symbol id="i-{name}" viewBox="…">  and </svg> → </symbol>
    const viewBoxMatch = svg.match(/viewBox="([^"]+)"/);
    const viewBox = viewBoxMatch ? viewBoxMatch[1] : '0 0 24 24';
    const inner = svg
      .replace(/^[\s\S]*?<svg[^>]*>/, '')
      .replace(/<\/svg>\s*$/, '')
      .trim();
    return `  <symbol id="i-${name}" viewBox="${viewBox}">\n${indent(inner, 4)}\n  </symbol>`;
  })
);

const sprite = `<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" style="display:none" aria-hidden="true">
${symbols.join('\n')}
</svg>
`;

await fs.writeFile(outputPath, sprite);
console.log(`✓ Generated ${path.relative(themeRoot, outputPath)} with ${icons.length} icons.`);

function indent(s, n) {
  const pad = ' '.repeat(n);
  return s.split('\n').map((l) => pad + l).join('\n');
}
