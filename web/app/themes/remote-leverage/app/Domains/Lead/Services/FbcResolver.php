<?php

declare(strict_types=1);

namespace App\Domains\Lead\Services;

use App\Domains\Lead\Actions\CaptureLeadAction;
use App\Domains\Lead\Actions\RecordBouncedLeadAction;

/**
 * Decides what `fbc` — Meta's click cookie — a write should carry.
 *
 * Extracted from {@see CaptureLeadAction}, which owned these rules
 * privately, when a second write path needed them: a visitor refused at the email check is
 * recorded by {@see RecordBouncedLeadAction} and never reaches
 * `CaptureLeadAction` at all, so reaching for the wizard's mount-time value there would have
 * recorded a synthetic `fbc` and called it real.
 *
 * The rules are not a formality — the Conversions API depends on them — so there is one copy:
 *
 *  - **Upgrade-only.** A confirmed-real `fbc` is never replaced by anything. Until one exists,
 *    a live cookie beats a mount-time value.
 *  - **Only a cookie that matches this click.** A `_fbc` left over from a different ad click in
 *    the same browser is not evidence about this lead.
 *  - **Silence unless the caller collected attribution at all.** Callers with no `fbc` key of
 *    their own — a referral form typed in by hand, a gated download — must not have whoever is
 *    currently in that browser attributed to them.
 */
class FbcResolver
{
    /**
     * A live `_fbc` cookie, read directly off the request/superglobal at write time.
     *
     * The booking wizard collects attribution once, in Livewire's `mount()` — the initial
     * full-page render, which happens before the browser has had any chance to run Meta's pixel
     * JS. A visitor who lands with a bare `fbclid` gets a synthetic `fbc` stamped into the
     * wizard's frozen `attributionNamed` at that instant, and every later write in the same
     * wizard session (each field blur, each step) reuses that same stale value even after the
     * genuine cookie lands in the browser a few milliseconds later. Re-reading the cookie here,
     * on every persist, lets the real value overwrite the synthetic one as soon as it exists.
     *
     * Cookie-only, deliberately: unlike `AttributionCollector`, this never checks the query
     * string. A Livewire round trip posts to `/livewire/update`, not back to the landing URL, so
     * there is no fresher query-string value to prefer over what `attributionNamed` already
     * carries — only the cookie can have changed since `mount()`.
     */
    public function liveCookie(): ?string
    {
        $request = function_exists('request') ? request() : null;

        $value = $request?->cookie('_fbc')
            ?? $request?->cookie('fbc')
            ?? $_COOKIE['_fbc'] ?? $_COOKIE['fbc'] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * What to store for `fbc` on this write, or null to leave it exactly as it is.
     *
     * A real `_fbc` — Meta's own cookie, or anything already confirmed real on a previous
     * write — is never replaced by anything once stored: not a fresher cookie, not a
     * mount-time value, nothing computed here outranks Meta's own click record. Until then,
     * `fbc` is upgrade-only: prefer a live cookie over whatever the caller is holding (the
     * wizard's `mount()`-frozen value, a hand-typed referral form's silence, …), but only when
     * it actually agrees with the click this lead carries — see `matchesClick()`.
     *
     * Does nothing at all — does not even read the live cookie — unless `$attributionNamed`
     * itself carries an `fbc` key. Only `AttributionCollector` sets that key, and only the
     * booking wizard runs it; every other caller (a referral typed in by hand, a gated-download
     * form, the instant-call widget) has no `fbc` of its own to speak of. Reading the live
     * cookie anyway there would attribute whoever is currently in that browser — a sales rep on
     * the phone, someone downloading a PDF with no ad click involved — to a stray `_fbc` that
     * has nothing to do with the lead being captured.
     *
     * @param  array<string, mixed>  $attributionNamed  The caller's collected named attribution.
     * @param  array<string, mixed>  $attribution  Its blob, read for `fbc_synthetic`.
     * @param  string|null  $storedFbc  What is already on the record, if anything.
     * @param  bool  $storedIsSynthetic  Whether that stored value is only a stand-in.
     * @return array{value: string, synthetic: bool}|null
     */
    public function resolve(
        array $attributionNamed,
        array $attribution,
        ?string $fbclid,
        ?string $storedFbc = null,
        bool $storedIsSynthetic = true,
    ): ?array {
        if (! array_key_exists('fbc', $attributionNamed)) {
            return null;
        }

        if ((string) $storedFbc !== '' && ! $storedIsSynthetic) {
            return null;
        }

        $candidates = [
            ['value' => $this->liveCookie(), 'synthetic' => false],
            ['value' => $attributionNamed['fbc'] ?? null, 'synthetic' => (bool) ($attribution['fbc_synthetic'] ?? false)],
        ];

        foreach ($candidates as $candidate) {
            if (! is_string($candidate['value']) || $candidate['value'] === '') {
                continue;
            }

            if ($this->matchesClick($candidate['value'], $fbclid)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Whether `fbc`'s embedded click id agrees with the click this lead actually carries.
     *
     * Meta's format is `fb.<subdomainIndex>.<creationTimeMs>.<fbclid>` — everything from the
     * third dot onward is the click id verbatim, so a mismatch means this `fbc` belongs to a
     * different click than the one this lead is being attributed to (a stale cookie left over
     * from an old ad click, or one written by a different tab/campaign sharing the same
     * browser). Absence of a `fbclid` to compare against is not a mismatch: `AttributionCollector`
     * already relies on `_fbc` outliving the landing page for a visitor who browses a few pages
     * before converting, and rejecting the cookie there would break exactly that case.
     */
    public function matchesClick(string $fbc, ?string $fbclid): bool
    {
        if ($fbclid === null || $fbclid === '') {
            return true;
        }

        $parts = explode('.', $fbc, 4);

        return count($parts) === 4 && $parts[3] === $fbclid;
    }
}
