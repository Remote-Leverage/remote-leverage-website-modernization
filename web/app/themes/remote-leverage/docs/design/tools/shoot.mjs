#!/usr/bin/env node
/**
 * Render the live page at a given width to a full-page PNG, with animation frozen and every
 * image eagerly decoded, and print the y of every h1/h2 so a section can be located.
 *
 *   node docs/design/tools/shoot.mjs --out=/tmp/mine.png --width=375 --path=/hire-va-4/
 */
import { createRequire } from 'node:module';
const require = createRequire('/Users/adriansalvatori/Documents/projects-rl/remoteleverage-v2/web/app/themes/remote-leverage/docs/visual-parity/node_modules/noop.js');
const { chromium } = require('playwright');
const args = Object.fromEntries(process.argv.slice(2).map(a => { const [k,v='true'] = a.replace(/^--/,'').split('='); return [k,v]; }));
const width = Number(args.width ?? 375);
const out = args.out ?? '/tmp/current.png';
const path = args.path ?? '/hire-va-4/';
const host = args.host ?? 'https://remoteleverage-v2.test';

const browser = await chromium.launch({ channel: 'chrome' });
const ctx = await browser.newContext({
  viewport: { width, height: 812 }, deviceScaleFactor: 1, isMobile: width < 1024, hasTouch: width < 1024,
  ignoreHTTPSErrors: true,
  ...(width < 1024 ? { userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1' } : {}),
});
const p = await ctx.newPage();
await p.goto(host + path, { waitUntil: 'load', timeout: 180000 });
await p.addStyleTag({ content: `*,*::before,*::after{animation-play-state:paused !important;animation-delay:-1s !important;transition:none !important;}` });
await p.waitForTimeout(2200);
await p.evaluate(() => document.querySelectorAll('img').forEach(i => { i.loading='eager'; i.decoding='sync'; }));
await p.evaluate(async () => { const s=Math.round(innerHeight*0.8); let l=-1;
  for (let y=0; y<document.body.scrollHeight && y!==l; y+=s) { l=y; scrollTo(0,y); await new Promise(r=>setTimeout(r,70)); }
  scrollTo(0,0); });
await p.waitForTimeout(900);
await p.evaluate(async () => { await Promise.all([...document.images].map(i => i.decode().catch(()=>{}))); });
await p.screenshot({ path: out, fullPage: true });
const marks = await p.evaluate(() => [...document.querySelectorAll('h1,h2')].map(el => {
  const r = el.getBoundingClientRect();
  return { y: Math.round(r.top + scrollY), t: (el.textContent||'').trim().replace(/\s+/g,' ').slice(0,46) };
}));
console.log(`${out}  ${width}px  height=${await p.evaluate(()=>document.body.scrollHeight)}`);
for (const m of marks) console.log(String(m.y).padStart(6), m.t);
await ctx.close(); await browser.close();
