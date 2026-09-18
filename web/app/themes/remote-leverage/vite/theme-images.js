import { createHash } from 'node:crypto'
import fs from 'node:fs/promises'
import os from 'node:os'
import path from 'node:path'

import fsSync from 'node:fs'
import { globSync } from 'tinyglobby'

/**
 * Page art lives in `resources/images/pages/**` so it is tracked in git, and is optimized
 * into `public/images/**` at build time. Filenames and directory structure are preserved
 * verbatim — `BlockDefaults::pageImg()` / `homeImg()` build those URLs by hand, so hashing
 * them (the way laravel-vite-plugin treats `resources/images/*`) would break every call site.
 *
 * Every raster also gets a sibling `.webp`, which is what `BlockDefaults::preferWebp()`
 * serves. Generating them here rather than on first request keeps the container filesystem
 * read-only and stops a half-written conversion from being cached forever.
 */

const SOURCE_DIR = 'resources/images/pages'
const OUT_DIR = 'public/images'
const CACHE_FILE = 'node_modules/.cache/theme-images.json'

const RASTER = new Set(['.png', '.jpg', '.jpeg'])
const PASSTHROUGH = new Set(['.gif', '.webp'])

/**
 * Extra widths emitted next to a source. The hire-va hero is LCP on every
 * landing that uses it, and the 1366×945 original is what PSI was decoding
 * into a 412px Moto G viewport. Filenames stay unhashed (BlockDefaults
 * builds the URL by hand) and use `-{width}` before the extension:
 * `hire-va-bg.webp` → `hire-va-bg-750.webp`.
 *
 * Folded into the cache digest below so adding a variant rebuilds that
 * source even when its bytes have not changed.
 *
 * @type {Record<string, Array<{ width: number, quality?: number }>>}
 */
const RESPONSIVE_VARIANTS = {
  'hire-va-4/hire-va-bg.webp': [{ width: 750, quality: 80 }],
}

function variantTarget(relative, variant) {
  const parsed = path.parse(relative)

  return path.join(OUT_DIR, parsed.dir, `${parsed.name}-${variant.width}${parsed.ext}`)
}

async function writeResponsiveVariants(sharp, input, relative) {
  const variants = RESPONSIVE_VARIANTS[relative] ?? []
  const outputs = []

  for (const variant of variants) {
    const dest = variantTarget(relative, variant)
    const buf = await sharp(input, { failOn: 'none' })
      .resize({ width: variant.width, withoutEnlargement: true })
      .webp({ quality: variant.quality ?? 80, effort: 5 })
      .toBuffer()

    await write(dest, buf)
    outputs.push(dest)
  }

  return outputs
}

// Sharp and svgo are only needed during a build; importing them lazily keeps `vite dev`
// startup from paying for the native binding.
async function loadTooling() {
  const [{ default: sharp }, { optimize }] = await Promise.all([
    import('sharp'),
    import('svgo'),
  ])

  return { sharp, optimize }
}

function hash(buffer) {
  return createHash('sha1').update(buffer).digest('hex').slice(0, 16)
}

async function readCache() {
  try {
    return JSON.parse(await fs.readFile(CACHE_FILE, 'utf8'))
  } catch {
    return {}
  }
}

async function writeCache(cache) {
  await fs.mkdir(path.dirname(CACHE_FILE), { recursive: true })
  await fs.writeFile(CACHE_FILE, JSON.stringify(cache))
}

async function outputsExist(outputs) {
  const checks = await Promise.all(
    outputs.map((file) => fs.access(file).then(() => true, () => false)),
  )

  return checks.every(Boolean)
}

async function write(file, contents) {
  await fs.mkdir(path.dirname(file), { recursive: true })
  await fs.writeFile(file, contents)
}

/**
 * Whether another source in the same directory shares this file's stem but has a different
 * raster extension, which would make both emit the same `.webp` name.
 */
function hasStemCollision(relative) {
  const dir = path.dirname(relative)
  const stem = path.basename(relative, path.extname(relative))
  const ext = path.extname(relative).toLowerCase()

  return ['.png', '.jpg', '.jpeg']
    .filter((candidate) => candidate !== ext)
    .some((candidate) => fsSync.existsSync(path.join(SOURCE_DIR, dir, `${stem}${candidate}`)))
}

async function processFile({ sharp, optimize }, relative) {
  const source = path.join(SOURCE_DIR, relative)
  const target = path.join(OUT_DIR, relative)
  const ext = path.extname(relative).toLowerCase()
  const input = await fs.readFile(source)

  if (ext === '.svg') {
    const { data } = optimize(input.toString('utf8'), {
      path: source,
      multipass: true,
      // Production markup targets some of these by id, and `viewBox` has to survive for
      // the SVGs that are sized with CSS rather than width/height attributes.
      plugins: [{ name: 'preset-default', params: { overrides: { cleanupIds: false, removeViewBox: false } } }],
    })

    await write(target, data)

    return [target]
  }

  if (PASSTHROUGH.has(ext)) {
    await write(target, input)

    return [target, ...await writeResponsiveVariants(sharp, input, relative)]
  }

  if (! RASTER.has(ext)) {
    return []
  }

  const pipeline = sharp(input, { failOn: 'none' })

  // The source raster is only ever the fallback — `preferWebp()` hands out the sibling below —
  // so it is re-encoded losslessly or not at all. Re-compressing an already-lossy JPEG at a
  // fixed quality compounds its artifacts: at q82 the contractor-management cards lost 6x their
  // bytes and dropped to ~28dB PSNR, which is visible banding on skin tones for no real gain.
  const recompressed = ext === '.png'
    ? await pipeline.clone().png({ compressionLevel: 9, effort: 10, palette: false }).toBuffer()
    : input

  // Never let "optimization" grow a file: some already-crushed PNGs come back larger.
  await write(target, recompressed.length < input.length ? recompressed : input)

  // A PNG here almost always means flat colour, hard edges or transparency — flags, icons,
  // logos — and lossy WebP shreds those (the country flags came back at ~17dB). Encode PNG
  // sources losslessly, and only drop to lossy for the handful of photographs that happen to
  // have been saved as PNG, which is exactly the case where lossless comes out oversized.
  // Two sources that differ only by extension (Frame-76-5.jpg and Frame-76-5.png are both
  // real, distinct images on the ecommerce page) would otherwise both write Frame-76-5.webp,
  // and whichever ran second silently won — one card then rendered the other card's picture.
  // On a collision, keep the original extension in the name: Frame-76-5.jpg.webp.
  // BlockDefaults::preferWebp() looks for that appended form before the swapped one.
  const webpTarget = hasStemCollision(relative)
    ? `${target}.webp`
    : target.replace(/\.(png|jpe?g)$/i, '.webp')
  let webp = ext === '.png'
    ? await pipeline.clone().webp({ lossless: true, effort: 5 }).toBuffer()
    : await pipeline.clone().webp({ quality: 82, effort: 5 }).toBuffer()

  if (ext === '.png' && webp.length > recompressed.length) {
    webp = await pipeline.clone().webp({ quality: 90, effort: 5 }).toBuffer()
  }

  await write(webpTarget, webp)

  return [target, webpTarget]
}

export function themeImages() {
  let root = process.cwd()

  return {
    name: 'remote-leverage:theme-images',

    configResolved(config) {
      root = config.root ?? root
    },

    async buildStart() {
      const previousCwd = process.cwd()
      process.chdir(root)

      try {
        const files = globSync(['**/*.{png,jpg,jpeg,svg,gif,webp}'], { cwd: SOURCE_DIR })

        if (files.length === 0) {
          return
        }

        const tooling = await loadTooling()
        const cache = await readCache()
        const next = {}
        let built = 0

        // Lossless WebP is expensive enough that doing 383 of them in series added ~50s to
        // every cold build (and every Docker layer rebuild). Sharp releases the event loop
        // during encoding, so a small worker pool scales this with the machine.
        const queue = [...files]
        const workers = Array.from(
          { length: Math.min(queue.length, Math.max(1, os.cpus().length)) },
          async () => {
            let relative
            while ((relative = queue.shift()) !== undefined) {
              const input = await fs.readFile(path.join(SOURCE_DIR, relative))
              // Variants are part of the identity of a source: adding hire-va-bg-750
              // must rebuild even though hire-va-bg.webp itself did not change.
              const digest = hash(Buffer.concat([
                input,
                Buffer.from('\0' + JSON.stringify(RESPONSIVE_VARIANTS[relative] ?? [])),
              ]))
              const cached = cache[relative]

              if (cached?.hash === digest && await outputsExist(cached.outputs)) {
                next[relative] = cached
                continue
              }

              next[relative] = { hash: digest, outputs: await processFile(tooling, relative) }
              built++
            }
          },
        )

        await Promise.all(workers)
        await writeCache(next)

        this.info?.(`theme-images: ${files.length} sources, ${built} rebuilt, ${files.length - built} cached`)
      } finally {
        process.chdir(previousCwd)
      }
    },
  }
}
