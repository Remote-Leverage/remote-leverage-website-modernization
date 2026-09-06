# ADR-0002: Adopt Roots Bedrock + Sage 11 + Acorn 5 as the Platform Stack

- Status: Accepted (documented as-is from `README.md`)
- Date: 2026-09-06 (recorded — original decision predates this record)
- Deciders: Adrián Salvatori (original author)

## Context

Building on ADR-0001's decision to rebuild rather than patch, a concrete
technology stack was required to replace the WordPress + Elementor +
procedural-plugin foundation described in `README.md` §2.

## Decision

Adopt the "Roots enterprise stack" (`README.md` §1, §2):

- **Roots Bedrock**: 12-factor WordPress stack, `.env`-based environment
  configuration, Composer dependency management.
- **Roots Sage 11**: theme engine using Vite 6, Tailwind CSS v4, and Laravel
  Blade templating (replacing the `hello-elementor` child theme).
- **Roots Acorn 5**: brings the Laravel 11/12 service container, dependency
  injection, Eloquent ORM, and Artisan CLI into WordPress (replacing
  procedural PHP hooks scattered across 8 plugins).
- **Acorn Migrations** for version-controlled, idempotent database schema
  changes (replacing unversioned custom tables created on plugin
  activation).
- **Laravel Pint & PHPUnit** for automated linting and unit testing
  (replacing an environment with zero automated tests).

The complete target folder structure is specified in `README.md` §3
(`web/app/themes/remote-leverage/app/{Domains,Application,Infrastructure}`,
`resources/{css,js,views}`, theme-level `composer.json`/`package.json`).

## Tradeoffs & Impact on Prior Decisions

- Directly implements ADR-0001 (clean-slate rebuild) — this is the concrete
  technology choice for that strategy, not an independent decision.
- Establishes the substrate ADR-0003 (Livewire), ADR-0004 (DDD), and
  ADR-0005 (Gutenberg/ACF Composer) are all built on top of — Acorn 5 is
  what makes Laravel-style DI, Eloquent, and service providers available
  inside WordPress for those later decisions.

## Consequences

- The team must operate a Laravel-flavored WordPress stack, which requires
  Composer-driven deployments and PHP 8.3+ (per `composer.json`:
  `"php": ">=8.3"`) rather than traditional FTP/plugin-upload WordPress
  workflows.
