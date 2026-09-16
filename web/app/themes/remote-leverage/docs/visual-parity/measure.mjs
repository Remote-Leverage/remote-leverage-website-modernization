#!/usr/bin/env node
/**
 * Report the page's real element geometry, so a section can be compared against the comp by
 * numbers instead of by eye.
 *
 * CLAUDE.md's rule — "sanity-check the geometry, not just the picture" — cuts both ways. It
 * catches a screenshot that looks right and isn't, and it also settles "is this box 8px low?"
 * far faster than another round of cropping and squinting.
 *
 *   node docs/visual-parity/measure.mjs --sel="h1,section,.wp-block-group"
 *   node docs/visual-parity/measure.mjs --width=376 --sel="h1"
 *
 * Coordinates are document-absolute (getBoundingClientRect + scrollY), matching how the comp
 * was measured off the PNG.
 */

import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { createRequire } from 'node:module';

const HERE = dirname(fileURLToPath(import.meta.url));
const require = createRequire(join(HERE, 'node_modules', 'noop.js'));
const { chromium } = require('playwright');

const args = Object.fromEntries(
  process.argv.slice(2).map((a) => {
    const [k, v = 'true'] = a.replace(/^--/, '').split('=');
    return [k, v];
  })
);

const url = (args.host ?? 'https://remoteleverage-v2.test') + (args.path ?? '/');
const width = Number(args.width ?? 1366);
const selectors = (args.sel ?? 'h1').split(',').map((s) => s.trim()).filter(Boolean);

const browser = await chromium.launch({ channel: 'chrome' });
const context = await browser.newContext({
  viewport: { width, height: 1000 },
  deviceScaleFactor: 1,
  ignoreHTTPSErrors: true,
});
const page = await context.newPage();
await page.goto(url, { waitUntil: 'load', timeout: 90000 });
await page.waitForTimeout(1500);

const rows = await page.evaluate((sels) => {
  const out = [];
  for (const sel of sels) {
    document.querySelectorAll(sel).forEach((el, i) => {
      const r = el.getBoundingClientRect();
      if (r.width < 1 && r.height < 1) return;
      const cs = getComputedStyle(el);
      out.push({
        sel: `${sel}[${i}]`,
        x: Math.round(r.left + window.scrollX),
        right: Math.round(r.right + window.scrollX),
        y: Math.round(r.top + window.scrollY),
        bottom: Math.round(r.bottom + window.scrollY),
        w: Math.round(r.width),
        h: Math.round(r.height),
        font: `${cs.fontSize}/${cs.lineHeight}`,
        color: cs.color,
        bg: cs.backgroundColor,
        text: (el.textContent || '').trim().replace(/\s+/g, ' ').slice(0, 42),
      });
    });
  }
  return out;
}, selectors);

console.log(`${url} @ ${width}px\n`);
for (const r of rows) {
  console.log(
    `${r.sel.padEnd(26)} x ${String(r.x).padStart(4)}..${String(r.right).padEnd(5)} ` +
      `y ${String(r.y).padStart(4)}..${String(r.bottom).padEnd(5)} ` +
      `${String(r.w).padStart(4)}x${String(r.h).padEnd(4)} ${r.font.padEnd(14)} ${r.text}`
  );
}

await browser.close();
