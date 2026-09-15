import { defineConfig } from 'vite'
import tailwindcss from '@tailwindcss/vite';
import laravel from 'laravel-vite-plugin'
import { wordpressPlugin, wordpressThemeJson } from '@roots/vite-plugin';

import { themeImages } from './vite/theme-images.js'

// Set APP_URL if it doesn't exist for Laravel Vite plugin
if (! process.env.APP_URL) {
  process.env.APP_URL = 'http://example.test';
}

export default defineConfig({
  base: '/app/themes/remote-leverage/public/build/',
  plugins: [
    tailwindcss(),
    laravel({
      input: [
        'resources/css/app.css',
        'resources/js/app.js',
        'resources/css/editor.css',
        'resources/js/editor.js',
        // Social Media Kit — the ported rl-social-kit dashboard. Its CSS and JS are
        // carried over verbatim from the plugin, so they stay separate entries rather
        // than being folded into app.css/app.js.
        'resources/css/social-kit.css',
        'resources/js/social-kit.js',
      ],
      refresh: true,
      // Only the flat files here go through Vite's hashed pipeline. `resources/images/pages/**`
      // is handled by themeImages() below, which has to preserve filenames.
      assets: ['resources/images/*.{png,jpg,jpeg,svg,gif,webp}', 'resources/fonts/**'],
    }),

    themeImages(),

    wordpressPlugin(),

    // Generate the theme.json file in the public/build/assets directory
    // based on the Tailwind config and the theme.json file from base theme folder
    wordpressThemeJson({
      disableTailwindColors: false,
      disableTailwindFonts: false,
      disableTailwindFontSizes: false,
      disableTailwindBorderRadius: false,
    }),
  ],
  resolve: {
    alias: {
      '@scripts': '/resources/js',
      '@styles': '/resources/css',
      '@fonts': '/resources/fonts',
      '@images': '/resources/images',
    },
  },
  build: {
    // intl-tel-input, Sentry, and PostHog are dynamic imports. Vite's default
    // modulepreload would fetch them on every page even when they never run.
    modulePreload: false,
  },
})
