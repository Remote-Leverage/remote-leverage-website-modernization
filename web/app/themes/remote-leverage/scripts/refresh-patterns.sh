#!/usr/bin/env bash
# Drop WordPress's cached list of theme pattern files, then clear Acorn's caches.
#
# WP stores the pattern-file listing in a site transient keyed by theme
# (_site_transient_wp_theme_files_patterns-<hash>). Adding a patterns/*.php file means the new
# pattern is not registered until the transient expires; DELETING one is worse — WP keeps trying
# to register the missing file and _register_theme_block_patterns raises a doing_it_wrong, which
# this theme's error handling turns into a 500 on every page.
#
# Run after adding, renaming or deleting anything in patterns/.
set -euo pipefail
cd "$(dirname "$0")/../../../../.."
wp db query "DELETE FROM wp_options WHERE option_name LIKE '%wp_theme_files_patterns%'" --skip-plugins --skip-themes
wp acorn optimize:clear
