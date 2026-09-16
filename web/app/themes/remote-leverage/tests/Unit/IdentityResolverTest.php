<?php

declare(strict_types=1);

use App\Domains\Lead\Models\Lead;
use App\Domains\Lead\Models\LeadIdentifier;
use App\Domains\Lead\Models\LeadProfile;
use App\Domains\Lead\Services\IdentityResolver;
use Illuminate\Support\Str;

/*
 * Identity consolidation — the "passport".
 *
 * The stakes are asymmetric and that shapes every test here: a ban lives on the profile, so
 * merging two profiles merges their bans, and **a false merge is a false ban**. An innocent
 * person silently stops reaching sales and nothing about the symptom points at the cause.
 *
 * So the tests that matter most are the ones asserting what does NOT merge.
 */

beforeEach(function () {
    LeadIdentifier::query()->delete();
    LeadProfile::query()->delete();
    Lead::query()->delete();
    config(['app.key' => 'base64:'.base64_encode(str_repeat('k', 32))]);
});

function makeLead(array $attributes = []): Lead
{
    return Lead::query()->create(array_merge([
        'uuid' => (string) Str::uuid(),
        'name' => 'Test Person',
        'email' => 'person@example.com',
        'source_type' => 'organic',
        'status' => 'captured',
    ], $attributes));
}

describe('consolidation', function () {
    test('a first sighting creates one profile carrying every identifier', function () {
        $lead = makeLead(['email' => 'ada@example.com', 'phone' => '+1 650 555 0100', 'device_id' => 'dev-1']);

        $profile = (new IdentityResolver)->resolve($lead);

        expect($profile)->not->toBeNull()
            ->and($profile->identifiers()->count())->toBe(3)
            ->and($lead->fresh()->profile_id)->toBe($profile->id);
    });

    test('a known email links a second lead to the same profile', function () {
        $resolver = new IdentityResolver;

        $first = $resolver->resolve(makeLead(['email' => 'ada@example.com']));
        $second = $resolver->resolve(makeLead(['email' => 'ada@example.com', 'phone' => '+1 650 555 0100']));

        expect($second->id)->toBe($first->id)
            // The new phone joins the existing person rather than starting a new one.
            ->and($second->identifiers()->count())->toBe(2);
    });

    test('a known phone with a NEW email attaches that email to the same profile', function () {
        // The scenario the feature exists for: someone returning under a different address.
        $resolver = new IdentityResolver;

        $first = $resolver->resolve(makeLead(['email' => 'first@example.com', 'phone' => '+1 650 555 0100']));
        $second = $resolver->resolve(makeLead(['email' => 'second@example.com', 'phone' => '+1 650 555 0100']));

        expect($second->id)->toBe($first->id)
            ->and($second->identifiers()->where('type', 'email')->count())->toBe(2);
    });

    test('two separately-known identifiers arriving together merge their profiles', function () {
        $resolver = new IdentityResolver;

        $a = $resolver->resolve(makeLead(['email' => 'ada@example.com', 'phone' => null]));
        $b = $resolver->resolve(makeLead(['email' => 'grace@example.com', 'phone' => '+1 650 555 0100']));

        expect($a->id)->not->toBe($b->id);

        // Now one submission proves they are the same person.
        $merged = $resolver->resolve(makeLead(['email' => 'ada@example.com', 'phone' => '+1 650 555 0100']));

        expect($merged->id)->toBe($a->id)
            ->and(LeadProfile::query()->find($b->id)->merged_into_id)->toBe($a->id);
    });

    test('a merged profile is kept and points at the winner, so the merge is reversible', function () {
        // Deleting the loser would make an over-merge permanent and invisible.
        $resolver = new IdentityResolver;

        $a = $resolver->resolve(makeLead(['email' => 'ada@example.com']));
        $b = $resolver->resolve(makeLead(['phone' => '+1 650 555 0100', 'email' => 'grace@example.com']));
        $resolver->resolve(makeLead(['email' => 'ada@example.com', 'phone' => '+1 650 555 0100']));

        $loser = LeadProfile::query()->find($b->id);

        expect($loser)->not->toBeNull()
            ->and($loser->merged_at)->not->toBeNull()
            ->and($loser->canonical()->id)->toBe($a->id);
    });
});

describe('what must NOT merge', function () {
    test('a shared device does not merge two people', function () {
        // A family machine, an office kiosk, a borrowed browser. Because a ban lives on the
        // profile, merging on this would ban whoever else used the computer.
        $resolver = new IdentityResolver;

        $a = $resolver->resolve(makeLead(['email' => 'ada@example.com', 'device_id' => 'shared-browser']));
        $b = $resolver->resolve(makeLead(['email' => 'grace@example.com', 'device_id' => 'shared-browser']));

        expect($a->id)->not->toBe($b->id);
    });

    test('a device identifier is still recorded, as evidence', function () {
        // Not merging is not the same as not knowing.
        $resolver = new IdentityResolver;
        $profile = $resolver->resolve(makeLead(['email' => 'ada@example.com', 'device_id' => 'dev-9']));

        $device = $profile->identifiers()->where('type', 'device')->first();

        expect($device)->not->toBeNull()
            ->and($device->strength)->toBe('weak');
    });

    test('a placeholder phone number is discarded rather than merging everyone who typed it', function () {
        $resolver = new IdentityResolver;

        $a = $resolver->resolve(makeLead(['email' => 'ada@example.com', 'phone' => '1234']));
        $b = $resolver->resolve(makeLead(['email' => 'grace@example.com', 'phone' => '1234']));

        expect($a->id)->not->toBe($b->id)
            ->and($a->identifiers()->where('type', 'phone')->count())->toBe(0);
    });

    test('a lead with nothing identifiable gets no profile at all', function () {
        $profile = (new IdentityResolver)->resolve(makeLead(['email' => '', 'phone' => null, 'device_id' => null]));

        expect($profile)->toBeNull();
    });
});

describe('normalisation', function () {
    test('the same phone written three ways is one identifier', function () {
        $resolver = new IdentityResolver;

        $a = $resolver->resolve(makeLead(['email' => 'a@example.com', 'phone' => '+1 (650) 555-0100']));
        $b = $resolver->resolve(makeLead(['email' => 'b@example.com', 'phone' => '16505550100']));

        expect($b->id)->toBe($a->id);
    });

    test('Gmail dots and plus-tags fold, because they really are one mailbox', function () {
        $resolver = new IdentityResolver;

        $a = $resolver->resolve(makeLead(['email' => 'ada.lovelace+jobs@gmail.com']));
        $b = $resolver->resolve(makeLead(['email' => 'adalovelace@gmail.com']));

        expect($b->id)->toBe($a->id);
    });

    test('dots are NOT folded at providers where they are significant', function () {
        // Folding everywhere would merge two real, different people.
        $resolver = new IdentityResolver;

        $a = $resolver->resolve(makeLead(['email' => 'ada.lovelace@example.com']));
        $b = $resolver->resolve(makeLead(['email' => 'adalovelace@example.com']));

        expect($b->id)->not->toBe($a->id);
    });

    test('case and surrounding whitespace never create a second person', function () {
        $resolver = new IdentityResolver;

        $a = $resolver->resolve(makeLead(['email' => 'Ada@Example.com']));
        $b = $resolver->resolve(makeLead(['email' => '  ada@example.com  ']));

        expect($b->id)->toBe($a->id);
    });
});

describe('storage', function () {
    test('identifiers are stored hashed, never in the clear', function () {
        // So a ban survives a deletion request without retaining personal data.
        (new IdentityResolver)->resolve(makeLead(['email' => 'ada@example.com']));

        $row = LeadIdentifier::query()->where('type', 'email')->first();

        expect($row->value_hash)->toHaveLength(64)
            ->and($row->value_hash)->not->toContain('ada')
            ->and($row->value_preview)->not->toBe('ada@example.com')
            ->and($row->value_preview)->toContain('@example.com');
    });

    test('the hash is typed, so an email and a phone cannot collide', function () {
        $resolver = new IdentityResolver;

        expect($resolver->hash('email', '16505550100'))
            ->not->toBe($resolver->hash('phone', '16505550100'));
    });
});

describe('blocking', function () {
    test('a blocked profile marks the leads attached to it', function () {
        $resolver = new IdentityResolver;
        $profile = $resolver->resolve(makeLead(['email' => 'ada@example.com']));

        $profile->forceFill(['status' => 'blocked', 'blocked_at' => now()])->save();

        $returning = makeLead(['email' => 'ada@example.com']);
        $resolver->resolve($returning);

        expect($returning->fresh()->is_blocked)->toBeTrue();
    });

    test('a ban survives a merge even when the blocked profile is the newer one', function () {
        // The winner is the oldest profile. Without this the ban would be dropped simply
        // because it was raised against the more recent record.
        $resolver = new IdentityResolver;

        $old = $resolver->resolve(makeLead(['email' => 'ada@example.com']));
        $new = $resolver->resolve(makeLead(['email' => 'grace@example.com', 'phone' => '+1 650 555 0100']));

        $new->forceFill(['status' => 'blocked', 'blocked_at' => now(), 'block_reason' => 'abuse'])->save();

        $merged = $resolver->resolve(makeLead(['email' => 'ada@example.com', 'phone' => '+1 650 555 0100']));

        expect($merged->id)->toBe($old->id)
            ->and($merged->isBlocked())->toBeTrue()
            ->and($merged->block_reason)->toContain('abuse');
    });

    test('resolving never bans on its own', function () {
        // Linking can be poisoned: someone banned can enter a victim's phone number. Banning
        // stays a human action with the graph as evidence.
        $resolver = new IdentityResolver;
        $profile = $resolver->resolve(makeLead(['email' => 'ada@example.com', 'phone' => '+1 650 555 0100']));

        expect($profile->isBlocked())->toBeFalse()
            ->and($profile->status)->toBe('active');
    });
});
