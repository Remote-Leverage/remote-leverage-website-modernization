<?php

declare(strict_types=1);

use App\Support\HeaderMode;

/**
 * The white overlay header is only legible over a dark hero. `acf/partner-hero` paints
 * either the dark partner gradient or production's pale #F4F6FC band depending on its
 * `tone` field, so the tone — not merely the block's presence — decides.
 *
 * `/ecommerce-virtual-assistant/` shipped the inverted logo and white nav links over the
 * light band, which made the whole header invisible.
 */
it('floats the header over a partner hero that uses its default dark tone', function () {
    $content = '<!-- wp:acf/partner-hero {"name":"acf/partner-hero","data":{"headline":"Lano"},"mode":"preview"} /-->';

    expect(HeaderMode::contentOpensWithDarkHero($content))->toBeTrue();
});

it('does not float the header over a light-toned partner hero', function () {
    $content = '<!-- wp:acf/partner-hero {"name":"acf/partner-hero","data":{"tone":"light","headline":"Ecommerce"},"mode":"preview"} /-->';

    expect(HeaderMode::contentOpensWithDarkHero($content))->toBeFalse();
});

it('reads the tone even when it sits past the opening window', function () {
    // The opening window is 2000 chars; a real block's ACF payload pushes `tone` well
    // into the comment, so it has to be read from the full content.
    $filler = str_repeat('"badges_0_text":"No Contracts","_badges_0_text":"field_x",', 40);
    $content = '<!-- wp:acf/partner-hero {"name":"acf/partner-hero","data":{'.$filler.'"tone":"light"},"mode":"preview"} /-->';

    expect(strlen($content))->toBeGreaterThan(2000)
        ->and(HeaderMode::contentOpensWithDarkHero($content))->toBeFalse();
});

it('keeps treating a light tone further down the page as irrelevant', function () {
    // Only the opening section decides; a light-toned hero after 2000 chars of other
    // content is not what the header sits on.
    $content = str_repeat('<!-- wp:paragraph --><p>Body copy.</p><!-- /wp:paragraph -->', 60)
        .'<!-- wp:acf/partner-hero {"name":"acf/partner-hero","data":{"tone":"light"},"mode":"preview"} /-->';

    expect(HeaderMode::contentOpensWithDarkHero($content))->toBeFalse();
});

it('still recognises the contractor payments hero as dark', function () {
    $content = '<!-- wp:acf/contractor-payments-hero {"name":"acf/contractor-payments-hero","data":{},"mode":"preview"} /-->';

    expect(HeaderMode::contentOpensWithDarkHero($content))->toBeTrue();
});

it('leaves an ordinary page on the standard header', function () {
    $content = '<!-- wp:heading --><h2>Pricing</h2><!-- /wp:heading -->';

    expect(HeaderMode::contentOpensWithDarkHero($content))->toBeFalse();
});
