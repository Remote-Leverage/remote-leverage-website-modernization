<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Legacy tool OpenAI proxy
    |--------------------------------------------------------------------------
    |
    | Backs `/wp-json/jobwidget/v1/{chat,chat4,whisper}` — the routes eight
    | Elementor-era tool pages call from the browser (the vastore5 job
    | description generator, the resume and text optimisers, the job posting
    | template generator, /tools/). On the legacy stack they lived in
    | `hello-theme-child/functions.php`; that theme went away at cutover and
    | took the routes with it, so every one of those widgets has been answering
    | "(No output received)" in v2 — a 404 body parses as JSON, so the widgets'
    | own error handling never fires.
    |
    | This is a *proxy*, not an integration: the browser composes the prompt and
    | this forwards it. That is the whole reason the hardening below exists.
    |
    */

    /*
     * Off means the routes are never registered, so they 404 exactly as they do
     * today. On by default: an environment with no key at all is already inert
     * (see `api_key` below), so the flag is for deliberately taking the tools
     * down, not for keeping them off by accident.
     */
    'enabled' => filter_var(env('JOB_WIDGET_PROXY_ENABLED', true), FILTER_VALIDATE_BOOLEAN),

    /*
     * Deliberately the same variable `config/ai.php` reads, not a new one.
     *
     * The legacy theme defined two constants, OPENAI_API_KEY and
     * OPENAI_API_KEY_40, and set both to the *same literal key* — the /chat4
     * split was aspirational, never real. Reproducing two variables would only
     * create a way for them to drift.
     *
     * `?:` rather than env()'s default argument: `OPENAI_API_KEY=` with nothing
     * after it hands back an empty string, which beats a default. The same trap
     * documented in config/services.php for POSTHOG_API_KEY.
     *
     * Empty here is not the end of the lookup. `OpenAiProxyGuard::apiKey()` falls
     * back to the Leads settings screen, because the ECS task definition maps
     * Secrets Manager keys to environment variables one at a time and a newly
     * added key cannot reach a deployed environment until the infrastructure repo
     * enumerates it — which is exactly what happened to this key on staging.
     * Environment wins wherever it is set.
     */
    'api_key' => trim((string) env('OPENAI_API_KEY', '')),

    'base_url' => rtrim((string) env('OPENAI_URL', 'https://api.openai.com/v1'), '/'),

    /*
     * Model allowlist. The proxy forwards a browser-supplied body, so without
     * this anyone who found the endpoint could bill your account for o1 runs.
     * These are the models the eight legacy pages actually send, read off the
     * snapshots in legacy-snapshots/pages: gpt-3.5-turbo everywhere, plus
     * gpt-4o-mini on /tools/.
     *
     * The Claude model that also appears in /tools/ is NOT proxied here and
     * never was — that widget posts straight to a Cloudflare Worker
     * (icy-math-19df.floral-wood-168a.workers.dev), which is a separate
     * dependency this does not revive.
     */
    'chat_models' => array_values(array_filter(array_map('trim', explode(',', (string) env(
        'JOB_WIDGET_CHAT_MODELS',
        'gpt-3.5-turbo,gpt-4o-mini,gpt-4o',
    ))))),

    'transcription_model' => env('JOB_WIDGET_TRANSCRIPTION_MODEL', 'whisper-1'),

    /*
     * Ceilings applied to whatever the browser asked for. The widgets request
     * 120–220 max_tokens; 1000 leaves room for a new tool without leaving the
     * endpoint open-ended.
     */
    'max_tokens' => (int) env('JOB_WIDGET_MAX_TOKENS', 1000),
    'max_body_bytes' => (int) env('JOB_WIDGET_MAX_BODY_BYTES', 64 * 1024),
    'max_audio_bytes' => (int) env('JOB_WIDGET_MAX_AUDIO_BYTES', 20 * 1024 * 1024),

    /*
     * Per-IP rate limit. The legacy routes had `permission_callback =>
     * '__return_true'` and no limit at all: an open, unauthenticated proxy to
     * the company OpenAI account that anyone could have driven a bill up on.
     *
     * A generous window — a visitor working through the generator legitimately
     * fires two requests per click — but bounded.
     */
    'rate_limit' => [
        'requests' => (int) env('JOB_WIDGET_RATE_LIMIT', 30),
        'window' => (int) env('JOB_WIDGET_RATE_WINDOW', 600),
    ],

    /*
     * Require a `wp_rest` nonce, which the widget reads from the page.
     *
     * Worth being honest about what this buys: the nonce is served to every
     * anonymous visitor, so it stops hotlinking and casual scripted abuse, not
     * a determined attacker. The rate limit and the model allowlist are what
     * bound the damage. Nonces last 12h and the nginx HTML cache is 60s, and
     * logged-in requests bypass that cache entirely ($skip_cache_cookie), so a
     * cached page can never hand a user a nonce minted for a different uid.
     */
    'require_nonce' => filter_var(env('JOB_WIDGET_REQUIRE_NONCE', true), FILTER_VALIDATE_BOOLEAN),

    /*
     * Chat is a foreground request with a spinner in front of the visitor;
     * transcription uploads an audio file and is slower. Both well under the
     * legacy 120s/180s, which only ever served to hold a PHP worker open.
     */
    'timeout' => (int) env('JOB_WIDGET_TIMEOUT', 45),
    'transcription_timeout' => (int) env('JOB_WIDGET_TRANSCRIPTION_TIMEOUT', 90),
    'connect_timeout' => (int) env('JOB_WIDGET_CONNECT_TIMEOUT', 5),
];
