import fs from 'node:fs/promises'
import path from 'node:path'

import { globSync } from 'tinyglobby'

/**
 * Copy `resources/videos/**` into `public/videos/**` verbatim at build time.
 *
 * Why this exists: `public/` is gitignored *and* excluded from the Docker build context
 * (`.dockerignore`), and the runtime image only ever receives what the `assets` stage
 * regenerates. A video dropped straight into `public/videos/` therefore works locally and
 * 404s in every deployed environment — which is exactly what happened to the booking
 * walkthrough on `/vathankyou/` and the VSL on `/about-us/`.
 *
 * Filenames and directory structure are preserved exactly, for the same reason
 * `themeImages()` preserves them: `BlockDefaults::video()` builds these URLs by hand, so a
 * content hash would break every call site.
 *
 * **Size is the constraint on what belongs here.** This directory is tracked in git, so
 * anything put in it is paid for on every clone and every CI checkout, forever. The 1.6MB
 * walkthrough is fine. The 61MB VSL is not, and is served from EFS uploads instead —
 * `BlockDefaults::video()` looks there first and falls back to whatever this plugin emitted.
 */

const SOURCE_DIR = 'resources/videos'
const OUT_DIR = 'public/videos'

const EXTENSIONS = 'mp4,webm,mov,m4v'

/** Copy only when the target is missing or differs, so a warm rebuild does no IO. */
async function needsCopy(source, target) {
  const [from, to] = await Promise.all([
    fs.stat(source),
    fs.stat(target).catch(() => null),
  ])

  return to === null || from.size !== to.size || from.mtimeMs > to.mtimeMs
}

export function themeVideos() {
  let root = process.cwd()

  return {
    name: 'remote-leverage:theme-videos',

    configResolved(config) {
      root = config.root ?? root
    },

    async buildStart() {
      const previousCwd = process.cwd()
      process.chdir(root)

      try {
        const files = globSync([`**/*.{${EXTENSIONS}}`], { cwd: SOURCE_DIR })

        if (files.length === 0) {
          return
        }

        let copied = 0

        for (const relative of files) {
          const source = path.join(SOURCE_DIR, relative)
          const target = path.join(OUT_DIR, relative)

          if (! await needsCopy(source, target)) {
            continue
          }

          await fs.mkdir(path.dirname(target), { recursive: true })
          await fs.copyFile(source, target)
          copied++
        }

        this.info?.(`theme-videos: ${files.length} sources, ${copied} copied, ${files.length - copied} current`)
      } finally {
        process.chdir(previousCwd)
      }
    },
  }
}
