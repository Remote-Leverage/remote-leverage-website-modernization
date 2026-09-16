#!/usr/bin/env node
/**
 * Iteration shooter for the 2026 homepage rebuild.
 *
 * capture.mjs answers "does this page still match production?" across the whole site. This
 * answers the narrower question the rebuild needs: "does this page match the comp?", at the
 * comp's own widths, on demand, in a couple of seconds.
 *
 *   node docs/visual-parity/shoot-homepage.mjs                       # both widths
 *   node docs/visual-parity/shoot-homepage.mjs --viewport=desktop
 *   node docs/visual-parity/shoot-homepage.mjs --path=/homepage-legacy/ --name=legacy
 *
 * Widths are the comps' own: Homepage V1/V3.png are 1366 wide, Page_v1.2.png is 376. Shooting
 * at those widths makes section order, type scale, spacing and colour compare directly against
 * the comp. Content-column width will NOT line up and that is deliberate — the theme's
 * canonical container is max-w-[1380px] (docs/design-system.md rule 1) while the comp draws a
 * 1100px column at 1366. CLAUDE.md is explicit that the container is the one measurement not
 * taken from a comp.
 *
 * The settle sequence is capture.mjs's, for the reasons documented there: eager loading,
 * sync decoding, a full scroll, `await img.decode()` before the shutter.
 */

import { mkdir } from 'node:fs/promises';
import { dirname, join, resolve } from 'node:path';
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

const HOST = args.host ?? 'https://remoteleverage-v2.test';
const PATH = args.path ?? '/';
const NAME = args.name ?? 'homepage';
const OUT = resolve(args.out ?? join(HERE, 'shots', 'rebuild'));

// The comps' own widths, so a side-by-side reads directly.
const VIEWPORTS = {
  desktop: { width: 1366, height: 1000 },
  mobile: { width: 376, height: 844 },
};

const wanted =
  args.viewport && args.viewport !== 'both' ? [args.viewport] : ['desktop', 'mobile'];

async function settle(page) {
  const disarm = () =>
    page.evaluate(() => {
      document
        .querySelectorAll('.elementor-invisible')
        .forEach((el) => el.classList.remove('elementor-invisible'));
      document.querySelectorAll('img').forEach((img) => {
        img.loading = 'eager';
        img.decoding = 'sync';
      });
    });

  await disarm();
  await page.evaluate(async () => {
    const step = Math.round(window.innerHeight * 0.8);
    let last = -1;
    for (let y = 0; y < document.body.scrollHeight && y !== last; y += step) {
      last = y;
      window.scrollTo(0, y);
      await new Promise((r) => setTimeout(r, 80));
    }
    window.scrollTo(0, document.body.scrollHeight);
    await new Promise((r) => setTimeout(r, 250));
  });
  await page.waitForTimeout(900);
  await disarm();
  await page.evaluate(() => window.scrollTo(0, 0));
  await page.waitForTimeout(500);
  await disarm();
  await page.evaluate(async () => {
    await Promise.all([...document.images].map((i) => i.decode().catch(() => {})));
  });
  await page.waitForTimeout(300);
}

await mkdir(OUT, { recursive: true });

const browser = await chromium.launch({ channel: 'chrome' });

for (const key of wanted) {
  const context = await browser.newContext({
    viewport: VIEWPORTS[key],
    deviceScaleFactor: 1,
    ignoreHTTPSErrors: true,
  });
  const page = await context.newPage();
  const url = HOST + PATH;

  await page.goto(url, { waitUntil: 'load', timeout: 90000 });
  await page.waitForTimeout(1200);
  await settle(page);

  // Geometry alongside the picture: a blank area and a correctly-rendered white card are the
  // same pixels, so record numbers that can contradict a screenshot that "looks fine".
  const diag = await page.evaluate(() => ({
    height: document.body.scrollHeight,
    images: document.images.length,
    broken: [...document.images]
      .filter((i) => i.getBoundingClientRect().width > 2 && (!i.complete || i.naturalWidth === 0))
      .map((i) => i.currentSrc || i.src),
    sections: [...document.querySelectorAll('.entry-content > *, main > *')]
      .map((el) => {
        const r = el.getBoundingClientRect();
        return `${Math.round(r.top + window.scrollY)}..${Math.round(r.bottom + window.scrollY)} ${el.tagName.toLowerCase()}.${(el.className || '').toString().split(' ')[0]}`;
      })
      .slice(0, 40),
  }));

  const file = join(OUT, `${NAME}.${key}.png`);
  await page.screenshot({ path: file, fullPage: true });

  console.log(`\n=== ${key} (${VIEWPORTS[key].width}px) -> ${file}`);
  console.log(`height ${diag.height}px, ${diag.images} images, ${diag.broken.length} broken`);
  diag.broken.forEach((b) => console.log(`  BROKEN ${b}`));
  diag.sections.forEach((s) => console.log(`  ${s}`));

  await context.close();
}

await browser.close();
