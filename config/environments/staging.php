<?php

/**
 * Configuration overrides for WP_ENV === 'staging'
 */

use Roots\WPConfig\Config;

use function Env\env;

/**
 * Staging should stay as close to production as possible, with one deliberate exception: it has
 * to say what went wrong.
 *
 * Staging exists to catch what CI cannot. CI never opens an admin screen, so a fatal in a
 * dashboard widget or a settings page deploys green and is discovered by a person clicking. With
 * the fatal error handler on, that person sees "There has been a critical error on this website"
 * and nothing else -- no file, no line, no trace -- and the error log lives in CloudWatch, which
 * the people who test staging cannot read. On 2026-09-20 that turned a one-line diagnosis into
 * two wrong guesses and a production revert.
 *
 * So the handler is off and errors are displayed. The cost is that a fatal on staging is ugly
 * rather than tidy, which is the correct trade for an environment nobody outside the company
 * sees: DISALLOW_INDEXING is set below and the site is not public.
 *
 * Production keeps the handler and shows nobody anything, as it must.
 */
Config::define('WP_DEBUG', true);
Config::define('WP_DEBUG_DISPLAY', true);
Config::define('WP_DEBUG_LOG', env('WP_DEBUG_LOG') ?? true);
Config::define('WP_DISABLE_FATAL_ERROR_HANDLER', true);

ini_set('display_errors', '1');

/*
 * SCRIPT_DEBUG and SAVEQUERIES are deliberately *not* copied from development. They change what
 * is served and how queries run, which is exactly the drift from production that staging exists
 * to avoid. Only the reporting of errors changes here, not the behaviour that produces them.
 */
Config::define('DISALLOW_INDEXING', true);
