<?php

declare(strict_types=1);

/*
 * The trailing-slash 301 in docker/nginx.conf, and the query string it carries.
 *
 * nginx's `rewrite` appends the original query string to the replacement on its own. The rule
 * also named `$is_args$args`, so every redirected query arrived twice: /x?gclid=A answered
 * Location: /x/?gclid=A?gclid=A, PHP read gclid as "A?gclid=A", and the browser read the last
 * parameter of every UTM set the same way. Live from v-20260918-v7 to 2026-09-24, on every ad
 * that landed without a trailing slash.
 *
 * Read from source because the suite has no nginx. The behaviour itself was checked against
 * nginx 1.27 when this was fixed: the rule below sends /x?a=1 to /x/?a=1, and /x to /x/.
 */
function slashRedirectReplacement(): string
{
    $conf = (string) file_get_contents(__DIR__.'/../../../../../../docker/nginx.conf');

    preg_match_all('/^\s*rewrite\s+(\S+)\s+(\S+)\s+permanent;/m', $conf, $rules, PREG_SET_ORDER);

    expect($rules)->toHaveCount(1);

    return $rules[0][2];
}

test('the trailing-slash redirect names no query string of its own', function () {
    expect(slashRedirectReplacement())
        ->not->toContain('$args')
        ->not->toContain('$is_args')
        ->not->toContain('$query_string')
        ->not->toContain('$request_uri');
});

/*
 * The opposite mistake. A replacement ending in `?` tells nginx not to append the original
 * arguments, which would drop every click id instead of doubling it.
 */
test('the trailing-slash redirect still carries the query string across', function () {
    expect(slashRedirectReplacement())
        ->not->toContain('?')
        ->toBe('https://$http_host/$1/');
});
