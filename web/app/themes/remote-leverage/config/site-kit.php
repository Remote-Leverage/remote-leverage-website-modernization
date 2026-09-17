<?php

/**
 * Google Tag Manager delivery and Site Kit settings, as code.
 *
 * Site Kit keeps its configuration in the database (`googlesitekit_*` options), set through
 * wp-admin after an OAuth handshake with Google. That does not survive this stack: the
 * application container is immutable and rebuilt on every deploy, and staging's database is
 * periodically refreshed from elsewhere, so anything connected by hand is one release away from
 * being gone. The same reasoning as `config/wordfence.php`.
 *
 * Unlike WordFence, nothing here is *written* to the database. Site Kit reads every setting
 * through `get_option()` (`Core\Storage\Options::get`), so `SiteKitHooks` serves these values
 * from `pre_option_*` filters instead. That is strictly safer for this particular plugin: a
 * deploy-time write leaves a window in which someone who connects Tag Manager in wp-admin gets
 * a second GTM snippet on every page, and nothing would correct it until the next release.
 * Filtered at read time, that cannot happen at all.
 *
 * **This file is the source of truth.** Tag Manager settings changed in wp-admin are ignored.
 *
 * Verified against Site Kit 1.187.0:
 *   - `Modules\Tag_Manager\Tag_Guard::can_activate()` needs only `useSnippet` and a container
 *     id, so no Google account connection is required for a tag to fire.
 *   - `Modules\Tag_Manager::register_tag()` builds `new Web_Tag($settings['containerID'])` —
 *     exactly one web container. `ampContainerID` is a separate AMP-only render path, not a
 *     second container on the same page. That single-container limit is why delivery lives in
 *     the theme; see `emit_snippet` below.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Containers
    |--------------------------------------------------------------------------
    |
    | Every GTM container to load, in order.
    |
    | One, not two. Decision 32 recorded that production serves both
    | GTM-53JDTQCZ and GTM-P4KZNJWL, read off its HTML on 2026-09-15. Audited
    | 2026-09-17 and that is wrong twice over:
    |
    |   - Production's GTM-P4KZNJWL snippet sits inside an HTML comment reading
    |     "Deprecated: unused Google Tag Manager script tag". It has never
    |     loaded. Only Site Kit's GTM-53JDTQCZ snippet is live.
    |   - GTM-P4KZNJWL is an empty container regardless: its published payload
    |     is version 1 with "tags":[], "predicates":[], "rules":[].
    |
    | Loading it would cost a request and a round trip to run nothing at all.
    |
    | The list stays plural because the delivery decision does not depend on the
    | count: Site Kit holds one container id, so the moment a second is wanted
    | this is the only place that can express it.
    |
    | Comma-separated in the environment, so an environment can carry a
    | different set without a deploy of this file.
    */
    'containers' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('GTM_CONTAINER_IDS', 'GTM-53JDTQCZ')),
    ))),

    /*
    |--------------------------------------------------------------------------
    | Snippet delivery
    |--------------------------------------------------------------------------
    |
    | Whether the theme renders the container snippets itself. When true,
    | Site Kit's own `useSnippet` is forced off so the two can never both emit.
    |
    | Set false to hand delivery back to Site Kit entirely — which also means
    | back to one container.
    */
    'emit_snippet' => filter_var(env('GTM_EMIT_SNIPPET', true), FILTER_VALIDATE_BOOLEAN),

    /*
    |--------------------------------------------------------------------------
    | Environments
    |--------------------------------------------------------------------------
    |
    | Which `wp_get_environment_type()` values load the containers. Production
    | only by default, matching Site Kit's own `Tag_Environment_Type_Guard`:
    | a container firing on staging sends test bookings to the same Meta and
    | LinkedIn pixels as real ones, which corrupts the conversion data the ad
    | spend is optimised against.
    |
    | Set GTM_ENVIRONMENTS=production,staging to debug a tag on staging.
    */
    'environments' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('GTM_ENVIRONMENTS', 'production')),
    ))),

    /*
    |--------------------------------------------------------------------------
    | Site Kit module settings
    |--------------------------------------------------------------------------
    |
    | Served to Site Kit in place of whatever `googlesitekit_tagmanager_settings`
    | holds. Keys are Site Kit's own, from
    | `Modules\Tag_Manager\Settings::get_default()`.
    |
    | `containerID` names the primary container so Site Kit's admin screens and
    | its `is_connected()` check report something truthful rather than an empty
    | module. `useSnippet` is overridden to false by SiteKitHooks whenever
    | `emit_snippet` is on, so it is not repeated here.
    |
    | `accountID` is the GTM account these containers live under. Left blank
    | until someone reads it off the GTM UI; Site Kit's dashboards need it, tag
    | delivery does not.
    */
    'tagmanager' => [
        'accountID' => (string) env('GTM_ACCOUNT_ID', ''),
        'ampContainerID' => '',
        'internalContainerID' => '',
        'internalAMPContainerID' => '',
    ],

    /*
    |--------------------------------------------------------------------------
    | Force the Tag Manager module active
    |--------------------------------------------------------------------------
    |
    | Site Kit only registers modules listed in `googlesitekit_active_modules`.
    | With `emit_snippet` on, the theme does the rendering and this is not
    | needed for tags to fire — it exists so that turning `emit_snippet` off
    | hands over cleanly, without also needing someone in wp-admin.
    |
    | Off by default: forcing a module active on a Site Kit that has never been
    | connected puts a broken-looking module in the admin for no gain.
    */
    'force_module_active' => filter_var(env('GTM_FORCE_MODULE_ACTIVE', false), FILTER_VALIDATE_BOOLEAN),
];
