<?php

declare(strict_types=1);

use App\Ai\InsightsCapability;
use App\Ai\Provisioning\ContentAgentProvisioner;

/**
 * ContentAgentProvisioner is mostly WordPress user API calls, which are covered
 * by running it for real. What is worth pinning here is the contract between
 * its capability list and config/ai-wordpress.php, because the two are written
 * in different files and nothing at runtime complains when they drift.
 *
 * A capability whose config flag is missing reads as false forever, which
 * silently produces an agent that cannot do its job — and a flag that defaults
 * open silently produces one that can publish to production. Both fail quietly,
 * so they are asserted rather than trusted.
 */
function managedCapabilities(): array
{
    $property = new ReflectionClassConstant(ContentAgentProvisioner::class, 'MANAGED_CAPABILITIES');

    return $property->getValue();
}

function agentConfig(): array
{
    // Read as source rather than through config(), which needs a booted app.
    $config = require dirname(__DIR__, 2).'/config/ai-wordpress.php';

    return $config['content_agent'];
}

it('maps every managed capability to a config flag that exists', function () {
    $config = agentConfig();

    // A managed capability whose flag is missing from config reads as false
    // forever, so the agent silently never gets it.
    foreach (managedCapabilities() as $flag) {
        expect(array_keys($config))->toContain($flag);
    }
});

it('manages the four capabilities that widen what an agent may do', function () {
    expect(managedCapabilities())->toBe([
        'publish_pages' => 'can_publish',
        'edit_published_pages' => 'can_edit_published',
        InsightsCapability::NAME => 'can_read_leads',
        'upload_files' => 'can_upload_media',
    ]);
});

/**
 * upload_files is the one managed capability the editor role already grants, so
 * it is the one where "default closed" depends on ensure() writing an explicit
 * denial rather than simply never granting it. If it were ever moved out of this
 * list to sit alongside edit_pages, the agent would silently regain uploads on
 * every environment.
 */
it('manages upload_files rather than letting the role decide it', function () {
    expect(managedCapabilities())->toHaveKey('upload_files');
});

/**
 * These defaulted closed until 2026-09-18 and now default open, by explicit
 * decision — the marketing workflow needs all four, and carrying them as
 * per-environment secrets meant a forgotten secret produced a draft-only agent
 * with no error anywhere to explain it.
 *
 * Pinned rather than left implicit because the consequence is worth being
 * deliberate about: a new environment that sets nothing gets an agent that can
 * publish, edit live pages, read customer contact details and write to uploads.
 * Anyone flipping one of these back should have to change this test and read
 * this comment on the way past.
 */
it('defaults every capability flag open', function (string $flag) {
    // env() falls back to the default when the variable is unset, which is the
    // state a fresh environment is in.
    expect(agentConfig()[$flag])->toBeTrue();
})->with(['can_publish', 'can_edit_published', 'can_read_leads', 'can_upload_media']);

/**
 * The env() override is what keeps "default open" from meaning "always open".
 * ensure() reconciles in both directions, so a false here actually revokes on
 * the next deploy rather than leaving an earlier grant in place.
 */
it('keeps every capability overridable per environment', function () {
    $source = file_get_contents(dirname(__DIR__, 2).'/config/ai-wordpress.php');

    foreach ([
        'AI_AGENT_CAN_PUBLISH',
        'AI_AGENT_CAN_EDIT_PUBLISHED',
        'AI_AGENT_CAN_READ_LEADS',
        'AI_AGENT_CAN_UPLOAD_MEDIA',
    ] as $variable) {
        expect($source)->toContain("env('{$variable}'");
    }
});

it('does not grant edit_pages through this list', function () {
    // edit_pages comes with the role. If it were managed here, a missing config
    // flag would revoke it and leave an agent that cannot do anything at all.
    expect(managedCapabilities())->not->toHaveKey('edit_pages');
});
