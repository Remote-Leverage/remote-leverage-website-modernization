<?php

declare(strict_types=1);

/**
 * The marketing cost alert, and the definitions it reports on.
 *
 * Background and the decisions behind the numbers: docs/marketing-cost-alerts.md.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Cost alert
    |--------------------------------------------------------------------------
    */
    'cost_alert' => [

        /*
         * Which `wp_get_environment_type()` values run the scheduled alert.
         *
         * Production only by default, and this default was bought the hard way: the hourly job
         * was registered with `enabled` defaulting to true everywhere, WP-Cron fired it on a
         * local page request, and an empty cost card — 0 leads, 0 bookings — posted itself into
         * `#new-appts`, the live sales channel, from a laptop.
         *
         * The lead alerts have always posted to the real workspace from any environment, but a
         * human action triggers each one. A *timer* that posts to a shared Slack from whichever
         * developer happens to load a local page is a different thing, and it will keep happening
         * as long as the default is on.
         *
         * `--force` on the console command bypasses this, so a human can still fire one anywhere.
         */
        'environments' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('MARKETING_COST_ALERT_ENVIRONMENTS', 'production')),
        ))),

        /*
         * A hard off switch, independent of the environment list above. Null means "defer to
         * `environments`", which is the normal state; setting it false stops the alert in
         * production without editing the list.
         */
        'enabled' => env('MARKETING_COST_ALERT_ENABLED') !== null
            ? filter_var(env('MARKETING_COST_ALERT_ENABLED'), FILTER_VALIDATE_BOOL)
            : null,

        /*
         * Where it posts.
         *
         * A committed default rather than an empty one, for the same reason the Sentry DSN and
         * the Customer.io write key are committed: a channel name is not a secret, and the
         * alternative is an environment that silently falls back to `SlackCredentials::channel()`
         * — `#new-appts`, the per-lead stream the sales team watches all day. A cost digest
         * landing there is exactly what this alert exists to avoid, and it would happen on the
         * first deploy to any environment whose task definition does not yet map this variable.
         * ECS maps Secrets Manager keys one at a time, so "not yet" can last weeks.
         *
         * A name rather than a channel id, unlike `services.slack.channel`. `chat.postMessage`
         * accepts either, and the bot only holds `chat:write` and `chat:write.public`, so it
         * cannot call `conversations.list` to resolve a name to an id in the first place. The
         * trade is that renaming the channel in Slack breaks this; the id is pinned in the
         * comment below once a real post has reported it back.
         *
         * The bot posts to a public channel it has not joined via `chat:write.public`. A private
         * channel needs the bot invited first, or Slack answers `not_in_channel`.
         *
         * **A blank value falls back to the default rather than through to `#new-appts`.** A
         * channel name begins with `#`, and `#` opens a comment in a dotenv file — so the obvious
         * `MARKETING_COST_ALERT_CHANNEL=#marketing-cost-alerts` parses as an empty string, not as
         * the channel. Empty is not absent, so a plain `env(..., default)` would hand back that
         * empty string, the default would never apply, and the digest would land in the sales
         * stream. Quoting the value fixes it; not depending on someone remembering to quote it
         * fixes it properly. The same applies to an ECS task definition mapping a secret that
         * turns out to be blank.
         */
        'channel' => trim((string) env('MARKETING_COST_ALERT_CHANNEL', '')) ?: '#marketing-cost-alerts',

        /*
         * The business's clock, not the server's.
         *
         * "Today" has to mean the same day the ad accounts are billing on, or spend and bookings
         * are counted over different windows and every cost figure is wrong by whatever crossed
         * the boundary. Ad platforms report in the account's timezone; this is that timezone.
         */
        'timezone' => (string) env('MARKETING_COST_ALERT_TIMEZONE', 'America/New_York'),

        'currency' => (string) env('MARKETING_COST_ALERT_CURRENCY', 'USD'),

        /*
         * Local hours at which the card is refreshed, inclusive of both ends.
         *
         * Every tick posts its own card, so the channel is a scrollable record of how the day
         * developed rather than one message that keeps changing. See SendCostAlertAction for why
         * the history is the point.
         *
         * Round the clock by default. It used to be 9-18, which meant the first card of the day
         * arrived at 09:00 already describing a night nobody could see. The overnight hours are
         * cheap — the same one card, edited — and they are the ones where a stalled ad account
         * goes unnoticed longest.
         */
        'window' => [
            'from' => (int) env('MARKETING_COST_ALERT_FROM_HOUR', 0),
            'to' => (int) env('MARKETING_COST_ALERT_TO_HOUR', 23),
        ],

        /*
         * The hours somebody is at a desk. Not when the card posts — when silence is a problem.
         *
         * `AlertReconciler` reports a long gap since the last lead as an incident, and that is
         * only true during the working day: the identical gap at 4am is the night. This was the
         * same setting as `window` until the card went round the clock, at which point one value
         * could not answer both questions without getting one of them wrong.
         */
        /*
         * How stale the warehouse's own extract may be before the card says so.
         *
         * Not the same as the "as of" time on the card, which is when this application asked. That
         * advances whether or not the pipeline behind it is running, so a stalled ETL shows as
         * frozen figures wearing a current timestamp. Ninety minutes because the alert runs hourly
         * and the extract more often than that: a wider gap means stopped, not merely lagging.
         */
        'max_warehouse_age_minutes' => (int) env('MARKETING_MAX_WAREHOUSE_AGE_MINUTES', 90),

        'staffed' => [
            'from' => (int) env('MARKETING_COST_ALERT_STAFFED_FROM_HOUR', 9),
            'to' => (int) env('MARKETING_COST_ALERT_STAFFED_TO_HOUR', 18),
        ],

        /*
         * Cost targets, in the currency above. Null prints the figure with no verdict beside it.
         *
         * Worth setting. A cost per booking with nothing to compare it to is a number people
         * skim past within a week — it was one of the things wrong with the alert this replaces.
         */
        /*
         * Trimmed and compared against '' as well as null: an environment variable that is
         * present but blank — which is what `MARKETING_TARGET_CPB=` in a .env produces, and what
         * an ECS task definition mapping an empty secret produces — is the string '', not null.
         * Cast, that is a target of $0.00, and every single day is then reported as over target.
         */
        'targets' => [
            'cpb' => is_numeric(trim((string) env('MARKETING_TARGET_CPB'))) ? (float) env('MARKETING_TARGET_CPB') : null,
            'cpqb' => is_numeric(trim((string) env('MARKETING_TARGET_CPQB'))) ? (float) env('MARKETING_TARGET_CPQB') : null,
        ],

        /*
         * Custom emoji for the platform cards, as `slug => :shortcode:`.
         *
         * Brand logos, not native emoji. The house rule in docs/slack-app.md bans the latter and
         * the tests enforce it by unicode range, which a `:shortcode:` does not trip — a Meta
         * logo beside the Meta card is wayfinding, where a yellow moneybag beside a spend figure
         * is decoration.
         *
         * **Empty by default, and that is deliberate.** Slack renders an emoji it does not have
         * as the literal text `:meta:`, which is uglier than no icon at all. This bot cannot tell
         * which exist — `emoji.list` needs `emoji:read` and it holds only `chat:write` and
         * `chat:write.public` — so it cannot check before sending, and guessing would put literal
         * colons in the channel on every card.
         *
         * Uploaded 2026-09-19 for Meta, Google and Microsoft. To add another: upload the logo at
         * Slack → Customise workspace → Emoji (or /customize/emoji), then put its name here. A
         * slug left empty simply renders without an icon, so they can be added one at a time.
         *
         * These names are a promise to the workspace, not to Slack. Renaming or deleting an emoji
         * there turns every card's platform heading into literal colons with nothing failing, so
         * this list and the workspace have to be changed together.
         */
        'platform_emoji' => [
            'meta' => ':meta:',
            'google' => ':google:',
            'microsoft' => ':microsoft:',

            /*
             * Empty until somebody uploads one. These platforms carry no spend today, so they
             * only ever appear on the card if a lead arrives tagged with one — and an icon that
             * renders as the literal text `:linkedin:` is worse than the plain name.
             */
            'linkedin' => '',
            'customerio' => '',
            'chatgpt' => '',
            'trustpilot' => '',
        ],

        /*
         * Below this many bookings, a per-platform cost figure is marked as not a rate.
         *
         * In the legacy alert Google's CPB, its CPQB and its total spend were all $737.46,
         * because Google had exactly one booking that day. Three of its six per-platform figures
         * were a single event presented as a performance number.
         */
        'small_sample' => (int) env('MARKETING_SMALL_SAMPLE', 3),

        /* How many prior days the "vs average at this hour" comparison averages over. */
        'baseline_days' => (int) env('MARKETING_BASELINE_DAYS', 7),

        /*
         * Silence for longer than this, during the window above, is reported as a problem rather
         * than as a statistic. The legacy alert printed "Last lead: 1064 min ago" as a neutral
         * line on a day with $3,732 of spend.
         */
        'stale_lead_minutes' => (int) env('MARKETING_STALE_LEAD_MINUTES', 180),
    ],

    /*
    |--------------------------------------------------------------------------
    | What "qualified" means
    |--------------------------------------------------------------------------
    |
    | Both definitions are reported. See App\Domains\Lead\Services\LeadQualification for why
    | there are two and why the HubSpot one cannot be trusted yet.
    */
    'qualified' => [

        /*
         * HubSpot lifecycle stages at or past which a lead counts as qualified.
         *
         * The default is the standard HubSpot ladder from sales-qualified onward. A portal that
         * works `hs_lead_status` instead, or that stops at marketing-qualified, needs this
         * changed — it is a portal convention, not a fact about the code.
         */
        'hubspot_stages' => [
            'salesqualifiedlead',
            'opportunity',
            'customer',
            'evangelist',
        ],

        /*
         * Below this share of leads carrying any lifecycle stage, the HubSpot figure is
         * suppressed and the message says why instead of printing it.
         *
         * It will be below this today. `SyncHubSpotLifecycleAction` only polls leads attached to
         * an open referral, so the stage is populated for a small, self-selected slice — a count
         * over it is a count of referrals wearing the word "qualified". Widening that sync is
         * what makes the figure appear.
         */
        'hubspot_min_coverage' => (float) env('MARKETING_HUBSPOT_MIN_COVERAGE', 0.5),
    ],

    /*
    |--------------------------------------------------------------------------
    | The warehouse
    |--------------------------------------------------------------------------
    |
    | Spend, channel attribution and the booking counts that divide into them all come from the
    | data team's BigQuery view, not from this application. Not because the warehouse is more
    | accurate — it may or may not be — but because it is what every other report in the business
    | is built on, and a Slack card that disagrees with those is wrong whichever number is closer
    | to the truth.
    |
    | The query lives verbatim in resources/sql/marketing-home-daily.sql and is owned by the data
    | team. See App\Domains\Marketing\Gateways\BigQueryClient.
    */
    'warehouse' => [

        /*
         * The whole service account JSON, in one secret. Raw or base64, because a task definition
         * that mangles quoting is a real thing and base64 is the usual escape hatch.
         *
         * One blob rather than a private key split across variables: a PEM contains newlines, and
         * a newline in an ECS task definition value works locally and produces an opaque OpenSSL
         * error in production.
         */
        /*
         * A service account key, which genuinely is a JSON file you download from Google. Raw or
         * base64, because a task definition that mangles quoting is a real thing.
         */
        'credentials' => (string) env('BIGQUERY_CREDENTIALS_JSON', ''),

        /*
         * Or a user's OAuth credential, as three values.
         *
         * Not a JSON blob, because nobody has one: an OAuth flow hands back a client id, a secret
         * and a refresh token, and the only way to get them as a file is
         * `gcloud auth application-default login` on a machine. An existing automation holds them
         * as three fields in whatever runs it, which is how they should be pasted here too.
         */
        'client_id' => (string) env('BIGQUERY_CLIENT_ID', ''),
        'client_secret' => (string) env('BIGQUERY_CLIENT_SECRET', ''),
        'refresh_token' => (string) env('BIGQUERY_REFRESH_TOKEN', ''),

        /*
         * The project the query is billed to. Defaults to the service account's own project,
         * which is right unless the account is shared across projects.
         */
        'project_id' => (string) env('BIGQUERY_PROJECT_ID', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Ad platform read credentials
    |--------------------------------------------------------------------------
    |
    | **Nothing reads these any more.** Spend comes from the warehouse above; the Meta, Google and
    | Microsoft clients that used to read it directly were deleted when that landed, because two
    | sources for one number is how a card and a dashboard start disagreeing.
    |
    | The keys are kept so that a future integration needing write access, or a diagnostic that
    | wants to compare the warehouse against a platform directly, has somewhere to resolve
    | credentials from — and because deleting them from the sync allowlist would strand any that
    | are already set in an environment.
    |
    | Environment first, admin setting second, exactly as Slack and HubSpot resolve. See
    | App\Domains\Marketing\Support\AdPlatformCredentials.
    */
    'ads' => [

        'google' => [
            'developer_token' => (string) env('GOOGLE_ADS_DEVELOPER_TOKEN', ''),
            'client_id' => (string) env('GOOGLE_ADS_CLIENT_ID', ''),
            'client_secret' => (string) env('GOOGLE_ADS_CLIENT_SECRET', ''),
            'refresh_token' => (string) env('GOOGLE_ADS_REFRESH_TOKEN', ''),
            'customer_id' => (string) env('GOOGLE_ADS_CUSTOMER_ID', ''),
            'login_customer_id' => (string) env('GOOGLE_ADS_LOGIN_CUSTOMER_ID', ''),
        ],

        'meta' => [
            /*
             * Defaults to META_CAPI_ACCESS_TOKEN, which this site already holds.
             *
             * A Business Manager system user token can carry both `ads_read` and the Conversions
             * API permission, so in the common case one credential covers both and there is
             * nothing new to paste. It is still its own setting, because the two are not the
             * same grant: a CAPI-only token reads no insights, and the failure would otherwise
             * look like an ad account with no spend rather than a token missing a scope.
             */
            'access_token' => (string) env('META_ADS_ACCESS_TOKEN', env('META_CAPI_ACCESS_TOKEN', '')),
            'ad_account_id' => (string) env('META_ADS_ACCOUNT_ID', ''),
            'api_version' => (string) env('META_ADS_API_VERSION', 'v21.0'),
        ],

        'microsoft' => [
            'developer_token' => (string) env('MICROSOFT_ADS_DEVELOPER_TOKEN', ''),
            'client_id' => (string) env('MICROSOFT_ADS_CLIENT_ID', ''),
            'client_secret' => (string) env('MICROSOFT_ADS_CLIENT_SECRET', ''),
            'refresh_token' => (string) env('MICROSOFT_ADS_REFRESH_TOKEN', ''),
            'customer_id' => (string) env('MICROSOFT_ADS_CUSTOMER_ID', ''),
            'account_id' => (string) env('MICROSOFT_ADS_ACCOUNT_ID', ''),
        ],
    ],
];
