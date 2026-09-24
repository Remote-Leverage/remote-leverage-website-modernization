<?php

declare(strict_types=1);

namespace App\Domains\Lead\Services;

/**
 * Decides what `_fbp` — Meta's browser cookie — a write should carry.
 *
 * Same staleness problem {@see FbcResolver} solves for `fbc`: the booking wizard collects
 * attribution once, in Livewire's `mount()`, which can run before Meta's pixel JS has written
 * `_fbp` to the browser. Every later write in that wizard session (each field blur, each step)
 * otherwise keeps reusing that mount-time snapshot even after the real cookie lands a few
 * milliseconds later — so this re-reads it live, on every persist, and lets the real value win
 * the moment it exists.
 *
 * Simpler than `fbc`'s rules: `_fbp` is not tied to a specific ad click, so there is no
 * "matches this click" check and no synthetic stand-in to protect against — a live cookie is
 * always the freshest truth here, and there is nothing to accidentally downgrade to.
 */
class FbpResolver
{
    /**
     * A live `_fbp` cookie, read directly off the request/superglobal at write time.
     *
     * See {@see FbcResolver::liveCookie()} for why this reads the live request rather than the
     * wizard's frozen `attributionNamed`/`attribution` — the same reasoning applies here.
     */
    public function liveCookie(): ?string
    {
        $request = function_exists('request') ? request() : null;

        $value = $request?->cookie('_fbp') ?? $_COOKIE['_fbp'] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * What to store for `_fbp` on this write, or null to leave it exactly as it is.
     *
     * Does nothing — does not even read the live cookie — unless this submission carries some
     * attribution of its own. Only the booking wizard runs `AttributionCollector`; a hand-typed
     * referral form or a gated download has no attribution to speak of, and reading the live
     * cookie there would attribute whoever is currently sitting in that browser to a submission
     * that never claimed to be attributed at all — the same guard `FbcResolver::resolve()` applies
     * for `fbc`.
     *
     * Preference order: a live cookie beats whatever this request's own `AttributionCollector`
     * run captured into the blob, which beats whatever was already on the record — each is only
     * used when the one before it is missing, so a later write without the cookie can never blank
     * out a value an earlier one already secured.
     *
     * @param  array<string, mixed>  $attributionNamed  The caller's collected named attribution.
     * @param  array<string, mixed>  $attribution  Its blob, read for the freshly-collected `_fbp`.
     * @param  string|null  $storedFbp  What is already on the record, if anything.
     */
    public function resolve(array $attributionNamed, array $attribution, ?string $storedFbp = null): ?string
    {
        if ($attributionNamed === [] && $attribution === []) {
            return null;
        }

        $handl = is_array($attribution['handl'] ?? null) ? $attribution['handl'] : [];

        $value = $this->liveCookie() ?? ($handl['_fbp'] ?? null) ?? $storedFbp;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
