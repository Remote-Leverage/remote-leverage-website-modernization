<?php

use App\Support\ReadingTime;

it('rounds up to whole minutes', function () {
    $words = str_repeat('word ', ReadingTime::WORDS_PER_MINUTE + 1);

    expect(ReadingTime::minutes($words))->toBe(2);
});

it('never reports less than a minute', function () {
    expect(ReadingTime::minutes('Three short words.'))->toBe(1)
        ->and(ReadingTime::minutes(''))->toBe(1);
});

it('counts prose, not markup', function () {
    $plain = str_repeat('word ', 450);
    $marked = str_repeat('<p class="wp-block-paragraph">word</p>', 450);

    expect(ReadingTime::minutes($marked))->toBe(ReadingTime::minutes($plain));
});

it('ignores shortcodes', function () {
    expect(ReadingTime::minutes('[gallery ids="1,2,3"] one two three'))->toBe(1);
});
