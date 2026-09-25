<?php

declare(strict_types=1);
use App\Application\Http\Support\WebhookSignature;
use App\Domains\PartnerHub\Support\PartnershipSettings;
use Illuminate\Http\Request;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to extend it using the "pest()" function to bind a different class or traits.
|
*/

pest()->extend(TestCase::class)->in('Feature', 'Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things with ease.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may find yourself looking for a little more.
| Here you can also define custom helper functions that can be used across your tests.
|
*/

/**
 * Build a signed webhook request, the way Stripe and Calendly actually send one.
 *
 * Both providers sign the raw JSON body, so the payload has to go in as a JSON string with a
 * JSON content type — passing it as form parameters leaves `getContent()` empty and would
 * "verify" a signature over nothing.
 *
 * @param  array<string, mixed>  $payload
 */
function signedWebhookRequest(
    string $uri,
    array $payload,
    string $secret,
    string $headerName,
    ?int $timestamp = null,
): Request {
    $body = json_encode($payload, JSON_THROW_ON_ERROR);

    return Request::create($uri, 'POST', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_'.strtoupper(str_replace('-', '_', $headerName)) => WebhookSignature::sign($body, $secret, $timestamp),
    ], $body);
}

/**
 * Save Partners Hub → Partnership Settings values for a test, merged over what is saved.
 *
 * The settings are a WordPress option, not config, so tests write the mocked option store the
 * way the settings screen writes the real one.
 *
 * @param  array<string, string>  $values  any of slack_channel, calendly_url, calendly_event_type
 */
function partnershipSettings(array $values): void
{
    $saved = get_option(PartnershipSettings::OPTION, []);

    update_option(PartnershipSettings::OPTION, array_merge(is_array($saved) ? $saved : [], $values));
}
