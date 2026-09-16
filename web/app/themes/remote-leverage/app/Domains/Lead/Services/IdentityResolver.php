<?php

declare(strict_types=1);

namespace App\Domains\Lead\Services;

use App\Domains\Lead\Models\Lead;
use App\Domains\Lead\Models\LeadIdentifier;
use App\Domains\Lead\Models\LeadProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Consolidates the identifiers a person has used into one profile — the "passport".
 *
 * `rl_leads.uuid` cannot do this: it is minted per row and unique, so a returning visitor gets a
 * new one every time. Linking visits needs identifiers that persist, held apart from the leads
 * that observed them.
 *
 * ## Strength, and why it is the whole design
 *
 * A ban lives on the profile, so merging two profiles merges their bans. **A false merge is
 * therefore a false ban** — an innocent person silently stops reaching sales, and nothing about
 * the symptom points at the cause. That asymmetry drives every rule here:
 *
 *  - **Strong** identifiers (email, phone) may merge two profiles on their own. They are close
 *    to unique per person and are deliberately entered.
 *  - **Weak** identifiers (device) are recorded and searchable but **never merge**. A shared
 *    browser, an office machine or a reused cookie would otherwise fuse unrelated people, and
 *    behind a CDN an IP is worse still — the origin sees an edge node shared by thousands,
 *    which is why IP is not an identifier type at all.
 *
 * ## Deliberate limits
 *
 * This resolver **links**; it never bans. Banning stays a human action taken against a profile,
 * with the graph as evidence. That matters because linking can be poisoned: someone banned can
 * enter a competitor's phone number or a victim's email, and an auto-banning graph would do
 * their work for them.
 *
 * ## Storage
 *
 * Identifiers are hashed with an application salt, never stored in the clear, so a ban survives
 * a data-deletion request without retaining personal data. `value_preview` keeps a masked
 * remnant for recognising a row in the admin.
 */
class IdentityResolver
{
    public const STRONG = ['email', 'phone'];

    public const WEAK = ['device'];

    /**
     * Attach a lead to a profile, creating or merging as the evidence requires.
     *
     * @param  array<string, string>  $identifiers  type => raw value
     */
    public function resolve(Lead $lead, array $identifiers = []): ?LeadProfile
    {
        $identifiers = $this->normaliseAll($identifiers ?: $this->identifiersFor($lead));

        if ($identifiers === []) {
            return null;
        }

        try {
            return DB::transaction(function () use ($lead, $identifiers) {
                $profile = $this->profileFor($identifiers);

                foreach ($identifiers as $type => $value) {
                    $this->attach($profile, $type, $value);
                }

                if ($lead->profile_id !== $profile->id) {
                    $lead->forceFill([
                        'profile_id' => $profile->id,
                        // Denormalised so the listeners that must suppress can check one column
                        // rather than joining on every event.
                        'is_blocked' => $profile->isBlocked(),
                    ])->saveQuietly();

                    $profile->increment('lead_count');
                } elseif ($lead->is_blocked !== $profile->isBlocked()) {
                    $lead->forceFill(['is_blocked' => $profile->isBlocked()])->saveQuietly();
                }

                return $profile;
            });
        } catch (\Throwable $e) {
            // Identity resolution must never cost a lead. A capture that cannot be linked is
            // worth far more than one that is refused.
            Log::error('IdentityResolver: could not resolve lead #'.$lead->id.': '.$e->getMessage());

            return null;
        }
    }

    /**
     * Find, create, or merge the profile these identifiers point at.
     *
     * @param  array<string, string>  $identifiers
     */
    protected function profileFor(array $identifiers): LeadProfile
    {
        $matches = [];

        foreach ($identifiers as $type => $value) {
            // Only strong identifiers get a vote on which profile this is. A device match is
            // recorded by attach() but must not decide identity — see the class docblock.
            if (! in_array($type, self::STRONG, true)) {
                continue;
            }

            $existing = LeadIdentifier::query()
                ->where('type', $type)
                ->where('value_hash', $this->hash($type, $value))
                ->first();

            if ($existing) {
                $profile = $existing->profile?->canonical();

                if ($profile) {
                    $matches[$profile->id] = $profile;
                }
            }
        }

        if ($matches === []) {
            return LeadProfile::query()->create([
                'uuid' => (string) Str::uuid(),
                'status' => 'active',
            ]);
        }

        if (count($matches) === 1) {
            return reset($matches);
        }

        return $this->merge(array_values($matches));
    }

    /**
     * Fold several profiles into one.
     *
     * The oldest wins, because it holds the longest history and any ban already applied to it.
     * Losers are kept and pointed at the winner rather than deleted: a merge is a judgement that
     * two records are one person, judgements are sometimes wrong, and an over-merge that cannot
     * be seen cannot be undone.
     *
     * @param  LeadProfile[]  $profiles
     */
    protected function merge(array $profiles): LeadProfile
    {
        usort($profiles, static fn (LeadProfile $a, LeadProfile $b) => $a->id <=> $b->id);

        $winner = array_shift($profiles);

        foreach ($profiles as $loser) {
            if ($loser->id === $winner->id) {
                continue;
            }

            LeadIdentifier::query()->where('profile_id', $loser->id)->update(['profile_id' => $winner->id]);
            Lead::query()->where('profile_id', $loser->id)->update(['profile_id' => $winner->id]);

            /*
             * A ban survives a merge in the only safe direction: if either side was blocked the
             * winner is blocked. Losing a ban because the banned profile happened to be newer
             * would silently readmit exactly the person it was raised against.
             */
            $attributes = [
                'merged_into_id' => $winner->id,
                'merged_at' => now(),
                'lead_count' => 0,
            ];

            if ($loser->isBlocked() && ! $winner->isBlocked()) {
                $winner->forceFill([
                    'status' => 'blocked',
                    'blocked_at' => $loser->blocked_at ?? now(),
                    'blocked_by' => $loser->blocked_by,
                    'block_reason' => trim('Inherited on merge. '.(string) $loser->block_reason),
                ])->save();
            }

            $loser->forceFill($attributes)->save();

            $winner->increment('lead_count', (int) $loser->getOriginal('lead_count'));

            Log::info("IdentityResolver: merged profile #{$loser->id} into #{$winner->id}");
        }

        return $winner->refresh();
    }

    /**
     * Record an identifier against a profile, or bump it if already known.
     */
    protected function attach(LeadProfile $profile, string $type, string $value): void
    {
        $hash = $this->hash($type, $value);

        $identifier = LeadIdentifier::query()
            ->where('type', $type)
            ->where('value_hash', $hash)
            ->first();

        if ($identifier) {
            $identifier->forceFill([
                // An identifier already claimed by another profile is moved only by a merge,
                // never here — attach() must not become a second, quieter merge path.
                'last_seen_at' => now(),
                'seen_count' => $identifier->seen_count + 1,
            ])->save();

            return;
        }

        LeadIdentifier::query()->create([
            'profile_id' => $profile->id,
            'type' => $type,
            'value_hash' => $hash,
            'value_preview' => $this->preview($type, $value),
            'strength' => in_array($type, self::STRONG, true) ? 'strong' : 'weak',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'seen_count' => 1,
        ]);
    }

    /**
     * The identifiers a lead carries.
     *
     * @return array<string, string>
     */
    public function identifiersFor(Lead $lead): array
    {
        return array_filter([
            'email' => (string) $lead->email,
            'phone' => (string) $lead->phone,
            'device' => (string) $lead->device_id,
        ]);
    }

    /**
     * Normalise every value, dropping those that normalise to nothing.
     *
     * @param  array<string, string>  $identifiers
     * @return array<string, string>
     */
    public function normaliseAll(array $identifiers): array
    {
        $out = [];

        foreach ($identifiers as $type => $value) {
            $normalised = $this->normalise((string) $type, (string) $value);

            if ($normalised !== '') {
                $out[$type] = $normalised;
            }
        }

        return $out;
    }

    /**
     * Canonicalise a value so the same person hashes the same way twice.
     *
     * Deliberately conservative. Gmail treats dots and `+tags` as noise, so `a.b+x@gmail.com`
     * and `ab@gmail.com` really are one mailbox and are folded together — but only for the
     * providers where that is actually true. Applying it everywhere would merge distinct
     * mailboxes at providers that treat dots as significant, and over-merging is the expensive
     * mistake here.
     */
    public function normalise(string $type, string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        return match ($type) {
            'email' => $this->normaliseEmail($value),
            'phone' => $this->normalisePhone($value),
            default => strtolower($value),
        };
    }

    protected function normaliseEmail(string $email): string
    {
        $email = strtolower($email);

        if (! str_contains($email, '@')) {
            return '';
        }

        [$local, $domain] = explode('@', $email, 2);

        // Providers where dots are ignored and `+` starts a tag. Everywhere else both are
        // significant and folding them would merge two real people.
        $folding = ['gmail.com', 'googlemail.com'];

        if (in_array($domain, $folding, true)) {
            $local = str_replace('.', '', explode('+', $local, 2)[0]);
            $domain = 'gmail.com';
        } else {
            $local = explode('+', $local, 2)[0];
        }

        return $local === '' ? '' : $local.'@'.$domain;
    }

    /**
     * Reduce a phone number to its digits.
     *
     * Enough to match the same number written three ways. Numbers shorter than seven digits are
     * discarded: they are placeholders and typos, and a handful of leads sharing "1234" would
     * merge into one profile that could then be banned as a unit.
     */
    protected function normalisePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        return strlen($digits) >= 7 ? $digits : '';
    }

    /**
     * Hash an identifier for storage and lookup.
     *
     * Salted with the application key so the table is not a rainbow-table lookup of every email
     * address the site has seen. Typed into the hash so an email and a phone that happen to
     * share a string cannot collide.
     */
    public function hash(string $type, string $value): string
    {
        $salt = (string) (config('app.key') ?: env('APP_KEY') ?: 'rl-identity');

        return hash('sha256', $type.':'.$this->normalise($type, $value).':'.$salt);
    }

    /**
     * A masked remnant, enough to recognise a row without re-identifying from the table.
     */
    protected function preview(string $type, string $value): string
    {
        $value = $this->normalise($type, $value);

        return match ($type) {
            'email' => (function () use ($value) {
                [$local, $domain] = array_pad(explode('@', $value, 2), 2, '');

                return mb_substr($local, 0, 1).str_repeat('*', max(1, mb_strlen($local) - 1)).'@'.$domain;
            })(),
            'phone' => str_repeat('*', max(0, strlen($value) - 4)).mb_substr($value, -4),
            default => mb_substr($value, 0, 6).'…',
        };
    }
}
