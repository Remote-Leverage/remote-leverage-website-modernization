<?php

declare(strict_types=1);

namespace App\Ai\Provisioning;

use App\Ai\InsightsCapability;
use App\Domains\Sync\Provisioning\SyncCredentialProvisioner;
use RuntimeException;
use WP_Application_Passwords;
use WP_User;

/**
 * Creates and maintains the WordPress identity an MCP client authenticates as.
 *
 * Deliberately split in two, and the split is the whole safety story:
 *
 *  - ensure() is idempotent, creates no secret, and is safe to run on every
 *    deploy. It guarantees the user exists and that its capabilities match
 *    config exactly — including revoking ones config no longer grants.
 *  - mintPassword() is the only thing that produces a credential, is never
 *    called automatically, and returns a plaintext that exists exactly once.
 *
 * A deploy that minted passwords would print them into CloudWatch, so the
 * deploy path is the half that cannot leak.
 *
 * @see SyncCredentialProvisioner for the same pattern applied to sync-service.
 */
class ContentAgentProvisioner
{
    /**
     * Name recorded against the application password, so this tool can tell
     * its own credentials from any created by hand and only revokes its own.
     */
    public const PASSWORD_NAME = 'mcp-client';

    /**
     * Capabilities this tool manages, mapped to the config flag that decides
     * each one. Being an exhaustive list matters: ensure() sets every entry to
     * exactly what config says, so tightening config actually takes a
     * capability away rather than leaving a previously-granted one behind.
     *
     * edit_pages is absent because it comes with the role and nothing here
     * should be able to produce an agent that cannot do its job at all.
     *
     * upload_files is the odd one out: the editor role grants it, so unlike the
     * others it is revoked rather than merely withheld when its flag is off.
     * That works only because ensure() writes an explicit per-user denial, which
     * beats the role — remove_cap() would hand it straight back.
     */
    private const MANAGED_CAPABILITIES = [
        'publish_pages' => 'can_publish',
        'edit_published_pages' => 'can_edit_published',
        InsightsCapability::NAME => 'can_read_leads',
        'upload_files' => 'can_upload_media',
    ];

    /**
     * @return array{exists: bool, login: string, role: string, capabilities: array<string, bool>, password_count: int, available: bool, unavailable_reason: ?string}
     */
    public function status(): array
    {
        $user = $this->findUser();
        $available = $this->applicationPasswordsAvailable();

        $capabilities = [];

        foreach (array_keys(self::MANAGED_CAPABILITIES) as $capability) {
            $capabilities[$capability] = $user instanceof WP_User && $user->has_cap($capability);
        }

        return [
            'exists' => $user instanceof WP_User,
            'login' => $this->login(),
            'role' => $this->config('role', 'editor'),
            'capabilities' => $capabilities,
            'password_count' => $user instanceof WP_User ? count($this->ownPasswords($user->ID)) : 0,
            'available' => $available,
            'unavailable_reason' => $available ? null : $this->unavailableReason(),
        ];
    }

    /**
     * Bring the agent user in line with config. Safe to run repeatedly and on
     * every container start; creates no credential.
     *
     * @return array{created: bool, granted: array<int, string>, revoked: array<int, string>}
     */
    public function ensure(): array
    {
        $user = $this->findUser();
        $created = false;

        if (! $user instanceof WP_User) {
            $user = $this->createUser();
            $created = true;
        }

        $granted = [];
        $revoked = [];

        foreach (self::MANAGED_CAPABILITIES as $capability => $flag) {
            $wanted = (bool) $this->config($flag, false);
            $has = $user->has_cap($capability);

            if ($wanted === $has) {
                continue;
            }

            if ($wanted) {
                $user->add_cap($capability);
                $granted[] = $capability;

                continue;
            }

            // add_cap($cap, false) writes an explicit per-user denial, which
            // beats the role. remove_cap() would only drop the override and
            // let an editor's own publish_pages come back through the role.
            $user->add_cap($capability, false);
            $revoked[] = $capability;
        }

        return ['created' => $created, 'granted' => $granted, 'revoked' => $revoked];
    }

    /**
     * Issue a fresh application password, revoking any this tool issued
     * before — so exactly one is live at a time and re-running is how you
     * rotate.
     *
     * @return array{user_login: string, password: string}
     */
    public function mintPassword(): array
    {
        if (! $this->applicationPasswordsAvailable()) {
            throw new RuntimeException($this->unavailableReason() ?? 'Application passwords are unavailable.');
        }

        $this->ensure();

        $user = $this->findUser();

        if (! $user instanceof WP_User) {
            throw new RuntimeException('The content agent user could not be loaded after provisioning.');
        }

        $this->revokeOwnPasswords($user->ID);

        $created = WP_Application_Passwords::create_new_application_password(
            $user->ID,
            ['name' => self::PASSWORD_NAME],
        );

        if (is_wp_error($created)) {
            throw new RuntimeException('Could not create an application password: '.$created->get_error_message());
        }

        // The plaintext is available only here — it is hashed on save and can
        // never be read back.
        return [
            'user_login' => $user->user_login,
            'password' => (string) $created[0],
        ];
    }

    /**
     * Issue a password under a caller-chosen name, revoking nothing.
     *
     * This is the per-person credential, and it is deliberately not
     * mintPassword(). That one is the shared credential and revokes every
     * password named PASSWORD_NAME as it goes, which is what makes it a
     * rotation. Handing a colleague something that a later rotation would sweep
     * out from under them is the failure this exists to prevent: their access
     * dies with no event, no notice and no obvious cause.
     *
     * The name is therefore the whole safety story, and PASSWORD_NAME is
     * refused rather than silently renamed — a caller asking for that name
     * wants the rotation semantics and should call mintPassword().
     *
     * @return array{user_login: string, password: string, name: string}
     */
    public function issuePassword(string $name): array
    {
        $name = trim($name);

        if ($name === '') {
            throw new RuntimeException('Give the credential a name, so it can be revoked individually later.');
        }

        if (strcasecmp($name, self::PASSWORD_NAME) === 0) {
            throw new RuntimeException(sprintf(
                'The name "%s" is reserved for the shared credential, which --rotate revokes as a set. '
                .'Use a distinct name (for example "claude-jane") so this one stays individually revocable.',
                self::PASSWORD_NAME,
            ));
        }

        if (! $this->applicationPasswordsAvailable()) {
            throw new RuntimeException($this->unavailableReason() ?? 'Application passwords are unavailable.');
        }

        $this->ensure();

        $user = $this->findUser();

        if (! $user instanceof WP_User) {
            throw new RuntimeException('The content agent user could not be loaded after provisioning.');
        }

        $created = WP_Application_Passwords::create_new_application_password($user->ID, ['name' => $name]);

        if (is_wp_error($created)) {
            throw new RuntimeException('Could not create an application password: '.$created->get_error_message());
        }

        return [
            'user_login' => $user->user_login,
            'password' => (string) $created[0],
            'name' => $name,
        ];
    }

    /**
     * Every application password on the agent, newest first.
     *
     * The plaintext is unrecoverable by design, so this is metadata only — what
     * it is for, when it was made and whether it has ever been used. "Never
     * used" on a credential issued last month is the signal worth acting on.
     *
     * @return array<int, array{uuid: string, name: string, created: ?int, last_used: ?int, managed: bool}>
     */
    public function passwords(): array
    {
        $user = $this->findUser();

        if (! $user instanceof WP_User) {
            return [];
        }

        $passwords = WP_Application_Passwords::get_user_application_passwords($user->ID);
        $rows = [];

        foreach (is_array($passwords) ? $passwords : [] as $password) {
            $name = (string) ($password['name'] ?? '');

            $rows[] = [
                'uuid' => (string) ($password['uuid'] ?? ''),
                'name' => $name,
                'created' => isset($password['created']) ? (int) $password['created'] : null,
                'last_used' => isset($password['last_used']) ? (int) $password['last_used'] : null,
                // Flags the shared credential, which --rotate sweeps as a set.
                'managed' => strcasecmp($name, self::PASSWORD_NAME) === 0,
            ];
        }

        usort($rows, fn (array $a, array $b) => ($b['created'] ?? 0) <=> ($a['created'] ?? 0));

        return $rows;
    }

    /**
     * Revoke exactly one credential, leaving every other one working.
     *
     * This is what makes per-person passwords worth the trouble: somebody
     * leaves, their access ends, and nobody else has to reconnect.
     */
    public function revokePassword(string $uuid): bool
    {
        $user = $this->findUser();

        if (! $user instanceof WP_User || trim($uuid) === '') {
            return false;
        }

        $deleted = WP_Application_Passwords::delete_application_password($user->ID, $uuid);

        return ! is_wp_error($deleted) && $deleted;
    }

    /**
     * Revoke this tool's credentials and the capabilities that make them
     * useful. The user row is left alone; deleting users is not something a
     * provisioner should do silently.
     */
    public function revoke(): void
    {
        $user = $this->findUser();

        if (! $user instanceof WP_User) {
            return;
        }

        $this->revokeOwnPasswords($user->ID);

        foreach (array_keys(self::MANAGED_CAPABILITIES) as $capability) {
            $user->add_cap($capability, false);
        }
    }

    private function login(): string
    {
        return $this->config('login', 'ai-content-agent');
    }

    private function findUser(): ?WP_User
    {
        $user = get_user_by('login', $this->login());

        return $user instanceof WP_User ? $user : null;
    }

    private function createUser(): WP_User
    {
        $userId = wp_insert_user([
            'user_login' => $this->login(),
            'user_email' => $this->config('email', 'ai-content-agent@remoteleverage.com'),
            // No one signs in as this user interactively; access is only ever
            // through an application password minted separately.
            'user_pass' => wp_generate_password(32, true, true),
            'role' => $this->config('role', 'editor'),
            'display_name' => 'AI Content Agent',
        ]);

        if (is_wp_error($userId)) {
            throw new RuntimeException('Could not create the content agent user: '.$userId->get_error_message());
        }

        $user = get_user_by('id', $userId);

        if (! $user instanceof WP_User) {
            throw new RuntimeException('The content agent user was created but could not be loaded.');
        }

        return $user;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function ownPasswords(int $userId): array
    {
        $passwords = WP_Application_Passwords::get_user_application_passwords($userId);

        return array_values(array_filter(
            is_array($passwords) ? $passwords : [],
            fn ($password) => ($password['name'] ?? null) === self::PASSWORD_NAME,
        ));
    }

    private function revokeOwnPasswords(int $userId): void
    {
        foreach ($this->ownPasswords($userId) as $password) {
            if (isset($password['uuid'])) {
                WP_Application_Passwords::delete_application_password($userId, (string) $password['uuid']);
            }
        }
    }

    private function applicationPasswordsAvailable(): bool
    {
        return function_exists('wp_is_application_passwords_available')
            && wp_is_application_passwords_available();
    }

    private function unavailableReason(): ?string
    {
        if (! function_exists('wp_is_application_passwords_available')) {
            return 'This WordPress install does not support application passwords.';
        }

        if (wp_is_application_passwords_available()) {
            return null;
        }

        if (! is_ssl() && wp_get_environment_type() !== 'local') {
            return 'Application passwords require HTTPS outside local environments. '
                .'This site is being served over plain HTTP.';
        }

        return 'Application passwords are disabled on this site '
            .'(the wp_is_application_passwords_available filter returned false).';
    }

    private function config(string $key, mixed $default): mixed
    {
        return config("ai-wordpress.content_agent.{$key}", $default);
    }
}
