<?php

/**
 * Configuration overrides for WP_ENV === 'production'
 *
 * The preview host (production.remoteleverage.com) sets DISALLOW_INDEXING=true
 * on the container so Google does not index a second copy of the live site.
 * After the apex cutover, Terraform sets that env var off and this file
 * becomes a no-op — Bedrock production stays indexable.
 */

use Roots\WPConfig\Config;

use function Env\env;

if (env('DISALLOW_INDEXING')) {
    Config::define('DISALLOW_INDEXING', true);
}
