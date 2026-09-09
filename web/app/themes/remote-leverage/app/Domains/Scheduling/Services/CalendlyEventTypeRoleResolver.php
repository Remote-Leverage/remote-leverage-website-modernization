<?php

declare(strict_types=1);

namespace App\Domains\Scheduling\Services;

class CalendlyEventTypeRoleResolver
{
    public const OPTION_KEY = 'rl_calendly_event_type_roles';

    public const ROLES = ['default', 't10', 't0', 'live_call'];

    /**
     * Resolve one role's event-type URI: admin-configured option value first,
     * falling back to the equivalent config/env value so a fresh environment
     * with no admin option set yet still works.
     */
    public function get(string $role): ?string
    {
        $stored = $this->storedRoles();

        if (! empty($stored[$role])) {
            return $stored[$role];
        }

        return match ($role) {
            'default' => config('services.calendly.default_event_type'),
            't10' => config('services.calendly.t10_event_type'),
            't0' => config('services.calendly.t0_event_type'),
            'live_call' => config('services.calendly.live_call_event_type') ?: config('services.calendly.t0_event_type'),
            default => null,
        };
    }

    /**
     * @return array<string, ?string>
     */
    public function all(): array
    {
        $resolved = [];

        foreach (self::ROLES as $role) {
            $resolved[$role] = $this->get($role);
        }

        return $resolved;
    }

    /**
     * @param  array<string, string>  $roleUriMap
     */
    public function save(array $roleUriMap): void
    {
        $filtered = array_intersect_key($roleUriMap, array_flip(self::ROLES));

        if (function_exists('update_option')) {
            update_option(self::OPTION_KEY, $filtered);
        }
    }

    /**
     * @return array<string, string>
     */
    protected function storedRoles(): array
    {
        $stored = function_exists('get_option') ? get_option(self::OPTION_KEY, []) : [];

        return is_array($stored) ? $stored : [];
    }
}
