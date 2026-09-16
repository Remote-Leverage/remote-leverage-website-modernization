#!/usr/bin/env node
/**
 * Full-page visual-parity capture: every migrated v2 page and its production homologue,
 * at desktop and mobile widths.
 *
 * Output lands in ./shots/ and is gitignored — these are large binaries that are cheap to
 * regenerate and expensive to keep in history. Run the script, don't commit the pixels.
 *
 *   node docs/visual-parity/capture.mjs                      # everything, resuming
 *   node docs/visual-parity/capture.mjs --filter=case-study  # only matching slugs
 *   node docs/visual-parity/capture.mjs --side=production    # one side only
 *   node docs/visual-parity/capture.mjs --viewport=mobile    # one viewport only
 *   node docs/visual-parity/capture.mjs --force              # re-shoot existing files
 *
 * Requires Playwright with a real Chrome channel:  npx playwright install chrome
 *
 * The capture sequence encodes the lessons in CLAUDE.md > "Verifying a migrated page".
 * Each step is there because skipping it previously produced a wrong conclusion:
 *
 *   1. channel:'chrome' at 1440px — Chromium's default font stack renders Elementor
 *      differently enough to read as drift that isn't there.
 *   2. Strip `.elementor-invisible` — production's entrance animations leave images blank
 *      if they haven't been triggered, and re-arm if you scroll back to the top.
 *   3. Force `loading='eager'` and `decoding='sync'` on every image, then scroll the full
 *      page — lazy images and lazy CSS backgrounds otherwise capture as missing.
 *   4. `await img.decode()` before shooting — `decoding="async"` lets Chrome screenshot
 *      before images rasterise, which once produced a phantom "21% different" on /blog/
 *      where all 180 image URLs were returning 200.
 *   5. Only then return to the top, re-running step 2 on the way — so the sticky site header
 *      renders once, where it belongs. CLAUDE.md warns against scrolling back to the top;
 *      that applies when `.elementor-invisible` is still on the page. See settle() for why
 *      it is safe here, and why forcing `position: static` instead is worse.
 */

import { mkdir, writeFile, access } from 'node:fs/promises';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const HERE = dirname(fileURLToPath(import.meta.url));

let chromium;
try {
  ({ chromium } = await import('playwright'));
} catch {
  console.error(
    'Playwright not found. Install it, then re-run:\n' +
      '  npm i -D playwright && npx playwright install chrome'
  );
  process.exit(1);
}

const args = Object.fromEntries(
  process.argv.slice(2).map((a) => {
    const [k, v = 'true'] = a.replace(/^--/, '').split('=');
    return [k, v];
  })
);

const manifest = JSON.parse(
  await (await import('node:fs/promises')).readFile(join(HERE, 'manifest.json'), 'utf8')
);

const SHOTS = resolve(HERE, args.out ?? 'shots');
const CONCURRENCY = Number(args.concurrency ?? 3);
const RETRIES = Number(args.retries ?? 2);
const FORCE = args.force === 'true';
const SIDES = args.side && args.side !== 'both' ? [args.side] : ['production', 'local'];
const VIEWPORTS =
  args.viewport && args.viewport !== 'both' ? [args.viewport] : ['desktop', 'mobile'];

/** Every (page, side, viewport) triple we intend to produce. */
const jobs = [];
for (const page of manifest.pages) {
  if (args.filter && !page.slug.includes(args.filter)) continue;
  for (const side of SIDES) {
    const path = side === 'production' ? page.production : page.local;
    if (!path) continue; // local-only surface: nothing to compare against
    for (const viewport of VIEWPORTS) {
      jobs.push({
        slug: page.slug,
        type: page.type,
        side,
        viewport,
        url: manifest.hosts[side] + path,
        file: join(SHOTS, viewport, `${page.slug}.${side}.png`),
      });
    }
  }
}

const exists = async (p) => access(p).then(() => true, () => false);

/** Strip entrance animations and make every image eager + synchronously decodable. */
const disarm = (page) =>
  page.evaluate(() => {
    document
      .querySelectorAll('.elementor-invisible')
      .forEach((el) => el.classList.remove('elementor-invisible'));
    document.querySelectorAll('img').forEach((img) => {
      img.loading = 'eager';
      img.decoding = 'sync';
    });
  });

async function settle(page) {
  await disarm(page);

  // walk the whole page so lazy images and lazy CSS backgrounds commit
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
  await page.waitForTimeout(1200);

  // anything that mounted during the scroll needs the same treatment
  await disarm(page);

  // Return to the top so the sticky site header renders once, in its natural place.
  //
  // CLAUDE.md warns against scrolling back to the top, because that re-arms Elementor's
  // `.elementor-invisible` entrance animation and images that had loaded read as blank.
  // That warning is about scrolling back with the class still on the page — we removed it
  // above and remove it again below, so there is nothing left to re-arm. Verified on
  // /case-study/chick-fil-a/ at 390px: 0 `.elementor-invisible` nodes remain and every
  // content image still rasterises.
  //
  // The alternative — forcing fixed/sticky elements to `position: static` — is worse. It
  // drops the header out of fixed positioning into its DOM position, which is partway down
  // the document, stamping a second header band across the middle of the stitched image.
  await page.evaluate(() => window.scrollTo(0, 0));
  await page.waitForTimeout(600);
  await disarm(page);

  // rasterise before the shutter
  await page.evaluate(async () => {
    await Promise.all([...document.images].map((i) => i.decode().catch(() => {})));
  });
  await page.waitForTimeout(400);
}

async function shoot(context, job) {
  const page = await context.newPage();
  try {
    await page.goto(job.url, { waitUntil: 'load', timeout: 90000 });
    await page.waitForTimeout(2500);
    await settle(page);

    // Geometry is the check that catches what the picture hides: a blank area and a
    // correctly-rendered white card are the same pixels, so record the numbers too.
    const diag = await page.evaluate(() => ({
      height: document.body.scrollHeight,
      images: document.images.length,
      brokenImages: [...document.images].filter(
        (i) => i.getBoundingClientRect().width > 2 && (!i.complete || i.naturalWidth === 0)
      ).length,
      title: document.title,
    }));

    await mkdir(dirname(job.file), { recursive: true });
    await page.screenshot({ path: job.file, fullPage: true });
    return { ...job, ok: true, ...diag };
  } finally {
    await page.close();
  }
}

const results = [];
let done = 0;

async function worker(queue, browser) {
  const contexts = {};
  for (const viewport of VIEWPORTS) {
    const v = manifest[viewport];
    contexts[viewport] = await browser.newContext({
      viewport: { width: v.width, height: v.height },
      deviceScaleFactor: 1,
      isMobile: viewport === 'mobile',
      hasTouch: viewport === 'mobile',
      ignoreHTTPSErrors: true,
      userAgent:
        viewport === 'mobile'
          ? 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1'
          : undefined,
    });
  }

  for (;;) {
    const job = queue.shift();
    if (!job) break;

    if (!FORCE && (await exists(job.file))) {
      results.push({ ...job, ok: true, skipped: true });
      done++;
      continue;
    }

    let last;
    for (let attempt = 0; attempt <= RETRIES; attempt++) {
      try {
        results.push(await shoot(contexts[job.viewport], job));
        last = null;
        break;
      } catch (err) {
        last = err;
        await new Promise((r) => setTimeout(r, 2000 * (attempt + 1)));
      }
    }
    if (last) results.push({ ...job, ok: false, error: last.message.slice(0, 160) });

    done++;
    const tag = last ? 'FAIL' : ' ok ';
    console.log(`[${String(done).padStart(3)}/${jobs.length}] ${tag} ${job.viewport.padEnd(7)} ${job.side.padEnd(10)} ${job.slug}`);
  }

  for (const c of Object.values(contexts)) await c.close();
}

console.log(`${jobs.length} captures · concurrency ${CONCURRENCY} · out ${SHOTS}`);

await mkdir(SHOTS, { recursive: true });

const browser = await chromium.launch({ channel: 'chrome' });
const queue = [...jobs];
await Promise.all(
  Array.from({ length: CONCURRENCY }, () => worker(queue, browser))
);
await browser.close();

const failed = results.filter((r) => !r.ok);
const capturedAt = new Date().toISOString();

await writeFile(
  join(SHOTS, 'results.json'),
  JSON.stringify({ capturedAt, results }, null, 2)
);

// Data for index.html. Written as a plain script rather than JSON because the viewer is
// opened over file://, where fetch() of a sibling file is blocked but <script src> is not.
await writeFile(
  join(SHOTS, 'data.js'),
  'window.PARITY = ' +
    JSON.stringify(
      {
        capturedAt,
        viewports: { desktop: manifest.desktop, mobile: manifest.mobile },
        pages: manifest.pages.map((p) => ({
          ...p,
          shots: Object.fromEntries(
            results
              .filter((r) => r.slug === p.slug && r.ok)
              .map((r) => [`${r.viewport}.${r.side}`, { height: r.height, brokenImages: r.brokenImages }])
          ),
        })),
      },
      null,
      2
    ) +
    ';\n'
);

console.log(`\ndone: ${results.filter((r) => r.ok).length} ok, ${failed.length} failed`);
for (const f of failed) console.log(`  FAIL ${f.viewport} ${f.side} ${f.slug} — ${f.error}`);
