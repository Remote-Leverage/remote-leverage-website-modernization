<?php

/**
 * WordFence settings, as code.
 *
 * WordFence keeps its configuration in the database (`wfconfig`), set through wp-admin. That
 * does not survive this stack: the application container is immutable and rebuilt on every
 * deploy, and staging's database is periodically refreshed from elsewhere, so anything tuned
 * by hand is one release away from being gone with nothing to say it ever existed.
 *
 * Everything below is applied by `WordfenceConfigurator`, which runs from `rl:deploy` on every
 * container start. It is idempotent — a value already correct is left alone — so the cost of
 * re-running it is a handful of reads.
 *
 * **This file is the source of truth.** A setting changed in wp-admin that also appears here is
 * reverted on the next deploy. That is the point; if a setting needs to differ, change it here.
 * Settings NOT listed here are left entirely alone, so wp-admin remains usable for everything
 * this file does not claim.
 *
 * Keys are WordFence's own `wfConfig` keys, verified against
 * `web/app/plugins/wordfence/lib/wfConfig.php` — an unknown key is reported and skipped rather
 * than written, because `wfConfig::set()` would happily create a row nothing ever reads.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Apply on deploy
    |--------------------------------------------------------------------------
    |
    | Set false to leave WordFence entirely under wp-admin control on an
    | environment. `rl:deploy` skips the step and reports that it did.
    */
    'apply_on_deploy' => filter_var(env('WORDFENCE_APPLY_ON_DEPLOY', true), FILTER_VALIDATE_BOOLEAN),

    /*
    |--------------------------------------------------------------------------
    | Settings
    |--------------------------------------------------------------------------
    */
    'settings' => [

        /*
         * Never let WordFence update itself.
         *
         * The plugin is a composer dependency (`wp-plugin/wordfence`), so the version is
         * pinned in composer.lock and shipped in the image. A self-update writes into a
         * read-only application tree — it either fails, or on a writable mount succeeds and
         * silently diverges from the lockfile until the next deploy reverts it. Either way
         * the running version stops matching what was reviewed.
         */
        'autoUpdate' => false,

        /*
         * The firewall itself, and the login-attack protection that is most of WordFence's
         * day-to-day value on a site with public forms.
         */
        'firewallEnabled' => true,
        'loginSecurityEnabled' => true,

        /*
         * Brute-force thresholds. Deliberately not aggressive: this site sits behind
         * CloudFront/Cloudflare, so a single origin IP can front many real visitors and a
         * tight limit locks out a shared NAT rather than an attacker.
         */
        'loginSec_maxFailures' => 20,
        'loginSec_countFailMins' => 60,
        'loginSec_lockoutMins' => 60,
        'loginSec_maxForgotPasswd' => 20,

        /*
         * Do not lock out on invalid usernames. With a CDN in front, one bot enumerating
         * usernames from behind the same edge IP as real traffic would take everyone with it.
         */
        'loginSec_lockInvalidUsers' => false,

        /*
         * Stop the login form distinguishing "no such user" from "wrong password", and stop
         * /?author=N enumerating usernames. Both are free.
         */
        'loginSec_maskLoginErrors' => true,
        'loginSec_disableAuthorScan' => true,
        'loginSec_blockAdminReg' => true,

        /*
         * Live traffic writes a row per request. Behind a CDN most requests never reach the
         * origin, so the view is misleading as well as expensive — and the useful signal
         * (blocks, lockouts) is recorded regardless of this setting.
         */
        'liveTrafficEnabled' => false,

        /*
         * Alerting. Kept narrow on purpose: an alert stream nobody reads is worse than none,
         * because it trains people to ignore the one that matters.
         */
        'alertOn_critical' => true,
        'alertOn_warnings' => false,
        'alertOn_block' => false,
        'alertOn_loginLockout' => true,
        'alertOn_adminLogin' => false,
        'alertOn_nonAdminLogin' => false,
        'alertOn_wordfenceDeactivated' => true,
        'alertOn_update' => false,
        'alert_maxHourly' => 10,
    ],
];
