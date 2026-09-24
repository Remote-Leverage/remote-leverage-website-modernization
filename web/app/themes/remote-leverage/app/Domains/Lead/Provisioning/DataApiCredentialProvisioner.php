<?php

declare(strict_types=1);

namespace App\Domains\Lead\Provisioning;

use App\Ai\InsightsCapability;
use App\Ai\Provisioning\ContentAgentProvisioner;
use App\Domains\Sync\Provisioning\SyncCredentialProvisioner;
use RuntimeException;
use WP_Application_Passwords;
use WP_User;

/**
 * Issues the credentials the data team's tooling reads rl-data/v1 with.
 *
 * Without this there is no way to issue one at all. The routes check
 * InsightsCapability::NAME, which is otherwise granted only by
 * `wp acorn rl:ai:grant-insights`, and neither staging nor production has a
 * shell to run it in.
 *
 * What a credential reaches is every lead and booking, customer contact
 * details included, so two things are deliberate:
 *
 *  - The user holds that one capability and nothing beyond subscriber, and
 *    that is re-asserted on every issue rather than only at creation. The
 *    account sits under Users like any other; a password that has left the
 *    building should not quietly become an editor's because somebody promoted
 *    the account to fix something unrelated.
 *  - Passwords are named, one per consumer, and revoked one at a time. The
 *    warehouse loader, a notebook and a BI tool are separate blast radii, and
 *    cutting one off must not make the others reconnect.
 *
 * @see ContentAgentProvisioner::issuePassword() for the per-consumer pattern.
 * @see SyncCredentialProvisioner for the dedicated-user pattern.
 */
class DataApiCredentialProvisioner
{
    public const USER_LOGIN = 'data-api';

    public const USER_EMAIL = 'data-api@remoteleverage.com';

    /**
     * Recorded in front of the name of every password this tool issues, so it
     * can tell its own from any created by hand on the user's profile and only
     * ever lists or revokes its own. A name rather than app_id because name is
     * what the other provisioners identify by, and because it shows on that
     * profile screen, which app_id does not.
     */
    public const PASSWORD_PREFIX = 'data-api:';

    /**
     * Current provisioning state, for display on the admin screen.
     *
     * @return array{exists: bool, login: string, has_capability: bool, password_count: int, available: bool, unavailable_reason: ?string}
     */
    public function status(): array
    {
        $user = $this->findUser();
        $available = $this->applicationPasswordsAvailable();

        return [
            'exists' => $user instanceof WP_User,
            'login' => self::USER_LOGIN,
            'has_capability' => $user instanceof WP_User && $user->has_cap(InsightsCapability::NAME),
            'password_count' => $user instanceof WP_User ? count($this->ownPasswords($user->ID)) : 0,
            'available' => $available,
            'unavailable_reason' => $available ? null : $this->unavailableReason(),
        ];
    }

    /**
     * Issue a password under a caller-chosen name, revoking nothing.
     *
     * A name already in use is refused rather than issued twice: two rows
     * called "looker" are indistinguishable on the screen, so revoking "the"
     * looker credential becomes a coin toss between the one that leaked and
     * the one still in use.
     *
     * @return array{user_login: string, password: string, name: string}
     *
     * @throws RuntimeException
     */
    public function issuePassword(string $name): array
    {
        $name = trim($name);

        if ($name === '') {
            throw new RuntimeException('Give the credential a name, after whatever will use it, so it can be revoked individually later.');
        }

        if (! $this->applicationPasswordsAvailable()) {
            throw new RuntimeException($this->unavailableReason() ?? 'Application passwords are unavailable.');
        }

        $user = $this->ensureUser();

        foreach ($this->ownPasswords($user->ID) as $password) {
            if (strcasecmp($this->label($password), $name) === 0) {
                throw new RuntimeException(sprintf(
                    'A credential named "%s" already exists. Revoke it first, or use a distinct name so each one stays individually revocable.',
                    $name,
                ));
            }
        }

        $created = WP_Application_Passwords::create_new_application_password(
            $user->ID,
            ['name' => self::PASSWORD_PREFIX.$name],
        );

        if (is_wp_error($created)) {
            throw new RuntimeException('Could not create an application password: '.$created->get_error_message());
        }

        // The plaintext is available only here — it is hashed on save and can
        // never be read back, which is why the screen shows it once.
        return [
            'user_login' => $user->user_login,
            'password' => (string) $created[0],
            'name' => $name,
        ];
    }

    /**
     * Every password this tool issued, newest first, under the name it was
     * issued with.
     *
     * Metadata only, since the plaintext is unrecoverable by design. "Never
     * used" on a credential issued weeks ago is the row worth revoking.
     *
     * @return array<int, array{uuid: string, name: string, created: ?int, last_used: ?int}>
     */
    public function passwords(): array
    {
        $user = $this->findUser();

        if (! $user instanceof WP_User) {
            return [];
        }

        $rows = [];

        foreach ($this->ownPasswords($user->ID) as $password) {
            $rows[] = [
                'uuid' => (string) ($password['uuid'] ?? ''),
                'name' => $this->label($password),
                'created' => isset($password['created']) ? (int) $password['created'] : null,
                'last_used' => isset($password['last_used']) ? (int) $password['last_used'] : null,
            ];
        }

        usort($rows, fn (array $a, array $b) => ($b['created'] ?? 0) <=> ($a['created'] ?? 0));

        return $rows;
    }

    /**
     * Revoke exactly one credential, leaving every other one working.
     *
     * Only one this tool issued: the uuid arrives from a form, and a password
     * someone made by hand on this user is theirs to manage, not this screen's.
     */
    public function revokePassword(string $uuid): bool
    {
        $user = $this->findUser();

        if (! $user instanceof WP_User || trim($uuid) === '') {
            return false;
        }

        foreach ($this->ownPasswords($user->ID) as $password) {
            if (($password['uuid'] ?? null) !== $uuid) {
                continue;
            }

            $deleted = WP_Application_Passwords::delete_application_password($user->ID, $uuid);

            return ! is_wp_error($deleted) && $deleted;
        }

        return false;
    }

    /**
     * The kill switch: every password this tool issued, and the capability.
     *
     * The capability is the half that matters most. Dropping it also disarms
     * any password added to this user by hand, which this tool neither lists
     * nor deletes — so afterwards nothing authenticating as data-api can read a
     * lead, whoever minted it. remove_cap() is enough here, unlike the content
     * agent's explicit denials, because subscriber never grants it back.
     *
     * The user row is left alone; deleting users is not something a
     * credentials screen should do silently. Issuing again re-grants.
     */
    public function revokeAll(): void
    {
        $user = $this->findUser();

        if (! $user instanceof WP_User) {
            return;
        }

        foreach ($this->ownPasswords($user->ID) as $password) {
            if (isset($password['uuid'])) {
                WP_Application_Passwords::delete_application_password($user->ID, (string) $password['uuid']);
            }
        }

        if ($user->has_cap(InsightsCapability::NAME)) {
            $user->remove_cap(InsightsCapability::NAME);
        }
    }

    /**
     * Create the user if absent, then hold it to subscriber plus the one
     * capability. Explicit denials are left in place: they only ever narrow
     * what a leaked credential could do.
     */
    private function ensureUser(): WP_User
    {
        $user = $this->findUser() ?: $this->createUser();

        if (array_values($user->roles) !== ['subscriber']) {
            $user->set_role('subscriber');
        }

        foreach ($user->caps as $capability => $granted) {
            $capability = (string) $capability;

            if ($granted && $capability !== InsightsCapability::NAME && ! in_array($capability, $user->roles, true)) {
                $user->remove_cap($capability);
            }
        }

        if (! $user->has_cap(InsightsCapability::NAME)) {
            $user->add_cap(InsightsCapability::NAME);
        }

        return $user;
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
            // No one signs in as this user interactively; access is only ever
            // through an application password issued separately.
            'user_pass' => wp_generate_password(32, true, true),
            'role' => 'subscriber',
            'display_name' => 'Data API',
        ]);

        if (is_wp_error($userId)) {
            throw new RuntimeException('Could not create the data-api user: '.$userId->get_error_message());
        }

        $user = get_user_by('id', $userId);

        if (! $user instanceof WP_User) {
            throw new RuntimeException('The data-api user was created but could not be loaded.');
        }

        return $user;
    }

    /**
     * Application passwords this tool issued, identified by prefix so one
     * created by hand for another purpose is never listed or revoked here.
     *
     * @return array<int, array<string, mixed>>
     */
    private function ownPasswords(int $userId): array
    {
        $passwords = WP_Application_Passwords::get_user_application_passwords($userId);

        return array_values(array_filter(
            is_array($passwords) ? $passwords : [],
            fn ($password) => str_starts_with((string) ($password['name'] ?? ''), self::PASSWORD_PREFIX),
        ));
    }

    /**
     * @param  array<string, mixed>  $password
     */
    private function label(array $password): string
    {
        return substr((string) ($password['name'] ?? ''), strlen(self::PASSWORD_PREFIX));
    }

    private function applicationPasswordsAvailable(): bool
    {
        return function_exists('wp_is_application_passwords_available')
            && wp_is_application_passwords_available();
    }

    /**
     * WordPress refuses application passwords over plain HTTP outside local
     * environments, which is the failure people hit first — say so explicitly
     * rather than letting the issue fail with a bare error.
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
