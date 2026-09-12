<?php

declare(strict_types=1);

namespace App\Domains\Sync\Provisioning;

use App\Domains\Sync\SyncCapability;
use App\Domains\Sync\SyncEnvironment;
use RuntimeException;
use WP_Application_Passwords;
use WP_User;

/**
 * Creates the sync-service credential without WP-CLI.
 *
 * This is the whole reason the sync feature was unreachable on staging: the
 * transport (App\Domains\Sync\SyncClient) has always worked over HTTP, but the
 * credential it authenticates with could only be created by running
 * `wp user create` and `wp acorn rl:sync:grant` on the remote. Staging has no
 * shell, so the credential could never exist there.
 *
 * Everything here is what those two commands do, callable from wp-admin instead.
 * `rl:sync:grant` still exists and still works — this is an alternative entry
 * point to the same end state, not a replacement.
 */
class SyncCredentialProvisioner
{
    public const USER_LOGIN = 'sync-service';

    public const USER_EMAIL = 'sync-service@remoteleverage.com';

    /**
     * Name recorded against the application password, so this tool can tell its
     * own credentials from any created by hand and only ever revoke its own.
     */
    public const PASSWORD_NAME = 'environment-sync';

    /**
     * Current provisioning state, for display on the admin screen.
     *
     * @return array{exists: bool, has_capability: bool, password_count: int, available: bool, unavailable_reason: ?string}
     */
    public function status(): array
    {
        $user = $this->findUser();
        $available = $this->applicationPasswordsAvailable();

        return [
            'exists' => $user instanceof WP_User,
            'has_capability' => $user instanceof WP_User && $user->has_cap(SyncCapability::NAME),
            'password_count' => $user instanceof WP_User ? count($this->ownPasswords($user->ID)) : 0,
            'available' => $available,
            'unavailable_reason' => $available ? null : $this->unavailableReason(),
        ];
    }

    /**
     * Create the user if absent, grant the capability, and mint a fresh
     * application password.
     *
     * Any application password this tool previously issued is revoked first, so
     * exactly one is live at a time and re-running is the way to rotate.
     *
     * @return array{user_login: string, password: string}
     *
     * @throws RuntimeException
     */
    public function provision(): array
    {
        SyncEnvironment::assertSyncEnabled();

        if (! $this->applicationPasswordsAvailable()) {
            throw new RuntimeException($this->unavailableReason() ?? 'Application passwords are unavailable.');
        }

        $user = $this->findUser() ?: $this->createUser();

        if (! $user->has_cap(SyncCapability::NAME)) {
            $user->add_cap(SyncCapability::NAME);
        }

        $this->revokeOwnPasswords($user->ID);

        $created = WP_Application_Passwords::create_new_application_password(
            $user->ID,
            ['name' => self::PASSWORD_NAME],
        );

        if (is_wp_error($created)) {
            throw new RuntimeException('Could not create an application password: '.$created->get_error_message());
        }

        // create_new_application_password() returns [ plaintext password, item ].
        // The plaintext is available only here — it is hashed on save and can
        // never be read back, which is why the screen shows it once.
        return [
            'user_login' => $user->user_login,
            'password' => (string) $created[0],
        ];
    }

    /**
     * Revoke this tool's credentials: its application passwords, and the
     * capability that makes them useful. The user row itself is left alone —
     * deleting users is not something a sync screen should do silently.
     */
    public function revoke(): void
    {
        SyncEnvironment::assertSyncEnabled();

        $user = $this->findUser();

        if (! $user instanceof WP_User) {
            return;
        }

        $this->revokeOwnPasswords($user->ID);

        if ($user->has_cap(SyncCapability::NAME)) {
            $user->remove_cap(SyncCapability::NAME);
        }
    }

    private function findUser(): ?WP_User
    {
        $user = get_user_by('login', self::USER_LOGIN);

        return $user instanceof WP_User ? $user : null;
    }

    private function createUser(): WP_User
    {
        $userId = wp_insert_user([
            'user_login' => self::USER_LOGIN,
            'user_email' => self::USER_EMAIL,
            'user_pass' => wp_generate_password(32, true, true),
            'role' => 'subscriber',
            'display_name' => 'Environment Sync Service',
        ]);

        if (is_wp_error($userId)) {
            throw new RuntimeException('Could not create the sync-service user: '.$userId->get_error_message());
        }

        $user = get_user_by('id', $userId);

        if (! $user instanceof WP_User) {
            throw new RuntimeException('The sync-service user was created but could not be loaded.');
        }

        return $user;
    }

    /**
     * Application passwords this tool issued, identified by name so a password
     * created by hand for another purpose is never revoked out from under it.
     *
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

    /**
     * WordPress refuses application passwords over plain HTTP outside local
     * environments, which is the failure people hit first — say so explicitly
     * rather than letting the mint fail with a bare error.
     */
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
}
