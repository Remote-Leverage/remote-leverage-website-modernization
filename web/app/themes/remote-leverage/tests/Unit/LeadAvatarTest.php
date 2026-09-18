<?php

declare(strict_types=1);

use App\Domains\Lead\Services\LeadAvatar;

/*
 * The avatar beside a lead in the admin list.
 *
 * The thing worth guarding is not that a logo appears — it is what leaves the server. Only the
 * email's domain may ever be sent, and only for a domain that belongs to a company. A regression
 * that let an address, or a hash of one, into that URL would not break any page; it would quietly
 * hand a third party the means to recognise every person in the pipeline.
 */

test('only the domain is ever sent, never the address', function () {
    $url = LeadAvatar::logoUrl('sandra.whitfield@kw.com');

    expect($url)->toContain('domain=kw.com')
        ->and($url)->not->toContain('sandra')
        ->and($url)->not->toContain('whitfield')
        ->and($url)->not->toContain('%40')
        ->and($url)->not->toContain('@')
        // The local part hashed would be just as identifying as the local part.
        ->and($url)->not->toContain(md5('sandra.whitfield@kw.com'))
        ->and($url)->not->toContain(hash('sha256', 'sandra.whitfield@kw.com'));
});

test('a consumer mailbox asks for no logo at all', function () {
    // Null has to mean "emit no <img>": the request itself is what we are avoiding, and over
    // half of these leads are on one of these providers.
    foreach ([
        'someone@gmail.com',
        'someone@googlemail.com',
        'someone@yahoo.com',
        'someone@yahoo.co.uk',
        'someone@hotmail.fr',
        'someone@outlook.es',
        'someone@live.com.au',
        'someone@icloud.com',
        'someone@aol.com',
        'someone@sbcglobal.net',
        'someone@comcast.net',
        'someone@protonmail.com',
    ] as $email) {
        expect(LeadAvatar::logoUrl($email))->toBeNull("expected no logo request for {$email}");
    }
});

test('a company mailbox asks for its logo', function () {
    foreach (['kw.com', 'allstate.com', 'remoteleverage.com', 'yenneslaw.com'] as $domain) {
        expect(LeadAvatar::logoUrl("person@{$domain}"))->toContain("domain={$domain}");
    }
});

test('the domain is read off the last at-sign and lowercased', function () {
    expect(LeadAvatar::domain('Person@KW.com'))->toBe('kw.com')
        // Quoted local parts may legally contain an @; the domain is what follows the last one.
        ->and(LeadAvatar::domain('"odd@name"@kw.com'))->toBe('kw.com')
        ->and(LeadAvatar::domain('  person@kw.com  '))->toBe('kw.com');
});

test('a malformed address yields no domain and no request', function () {
    foreach ([null, '', '   ', 'not-an-email', 'person@', '@kw.com', 'person@localhost'] as $email) {
        expect(LeadAvatar::domain($email))->toBeNull()
            ->and(LeadAvatar::logoUrl($email))->toBeNull();
    }
});

test('initials fall back through name, then address, then a constant', function () {
    expect(LeadAvatar::initials('Sandra Whitfield', 'x@kw.com'))->toBe('SW')
        ->and(LeadAvatar::initials('Sandra Jane Whitfield', 'x@kw.com'))->toBe('SW')
        ->and(LeadAvatar::initials('Sandra', 'x@kw.com'))->toBe('SA')
        ->and(LeadAvatar::initials('', 'sandra@kw.com'))->toBe('SA')
        ->and(LeadAvatar::initials(null, null))->toBe('RL');
});

test('a tint is stable for a person and survives their name arriving later', function () {
    // A partial capture has an address and no name; step two fills the name in. The row should
    // not change colour when it does.
    $partial = LeadAvatar::tint('sandra@kw.com', null);
    $complete = LeadAvatar::tint('sandra@kw.com', 'Sandra Whitfield');

    expect($complete)->toBe($partial)
        ->and($partial)->toHaveCount(2)
        ->and($partial[0])->toStartWith('#');
});

test('tints spread across the palette rather than collapsing onto one', function () {
    $seen = [];

    foreach (range(1, 60) as $n) {
        $seen[] = LeadAvatar::tint("lead{$n}@example.com")[0];
    }

    // A hash that always landed on the same bucket would still pass every test above.
    expect(count(array_unique($seen)))->toBeGreaterThan(4);
});
