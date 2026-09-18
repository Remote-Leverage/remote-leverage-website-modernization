<?php

declare(strict_types=1);

use App\Ai\Provisioning\ContentAgentProvisioner;
use App\Infrastructure\WordPress\Admin\AiAccessAdmin;

/**
 * The screen is mostly markup and WordPress user-API calls, which are covered by
 * using it. What is pinned here is the handful of invariants that fail silently
 * and expensively:
 *
 *  - every entry point must be gated on manage_options, since this one issues
 *    credentials rather than merely displaying settings;
 *  - the reserved shared-credential name must stay refused, or a colleague gets
 *    a password that a later --rotate sweeps away with no notice;
 *  - the endpoint must be derived from the site being viewed, since a hardcoded
 *    one hands out working credentials pointing at the wrong environment.
 */
function screenSource(): string
{
    return file_get_contents(
        dirname(__DIR__, 2).'/app/Infrastructure/WordPress/Admin/AiAccessAdmin.php'
    );
}

it('gates every entry point on manage_options', function () {
    $source = screenSource();

    // Both the renderer and the POST handler, not just the menu registration —
    // add_options_page's capability argument does not protect admin_init.
    expect(substr_count($source, "current_user_can('manage_options')"))->toBeGreaterThanOrEqual(2);
    expect($source)->toContain("capability: 'manage_options'");
});

it('verifies a nonce before acting on a POST', function () {
    expect(screenSource())->toContain('check_admin_referer(self::SLUG)');
});

it('derives the endpoint from the current site rather than hardcoding one', function () {
    $source = screenSource();

    expect($source)->toContain("home_url('/wp-json/mcp/mcp-adapter-default-server')");
    expect($source)->not->toContain('https://remoteleverage.com/wp-json');
});

/**
 * The plaintext exists exactly once. Holding it in a transient keyed to the
 * admin who asked keeps it out of another user's session and expires it whether
 * or not they copied it.
 */
it('keys the one-time plaintext to the requesting user and expires it', function () {
    $source = screenSource();

    expect($source)->toContain('get_current_user_id()');
    expect($source)->toContain('MINUTE_IN_SECONDS');
    expect($source)->toContain('delete_transient($issuedKey)');
});

it('issues named credentials rather than the swept shared one', function () {
    $source = screenSource();

    // If this ever called mintPassword(), every previously issued per-person
    // credential would be revoked the next time an admin used this screen.
    expect($source)->toContain('issuePassword(');
    expect($source)->not->toContain('mintPassword(');
});

it('refuses the reserved shared-credential name', function () {
    $provisioner = new ContentAgentProvisioner;

    expect(fn () => $provisioner->issuePassword(ContentAgentProvisioner::PASSWORD_NAME))
        ->toThrow(RuntimeException::class);

    // Case-insensitively, because WP_Application_Passwords does not normalise
    // the name and --rotate's sweep compares it case-insensitively too.
    expect(fn () => $provisioner->issuePassword('MCP-Client'))
        ->toThrow(RuntimeException::class);
});

it('refuses an unnamed credential', function () {
    expect(fn () => (new ContentAgentProvisioner)->issuePassword('   '))
        ->toThrow(RuntimeException::class);
});

it('registers under Settings with its own slug', function () {
    expect(AiAccessAdmin::SLUG)->toBe('rl-ai-access');
    expect(screenSource())->toContain('add_options_page');
});
