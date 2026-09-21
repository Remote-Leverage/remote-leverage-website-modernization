<?php

declare(strict_types=1);

use App\Domains\Tools\Services\OpenAiProxy;
use App\Domains\Tools\Services\OpenAiProxyGuard;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Http;

/**
 * The legacy tool proxy — `/wp-json/jobwidget/v1/{chat,chat4,whisper}`.
 *
 * What is being defended here is a credential, not a feature. The version this replaces lived
 * in hello-theme-child/functions.php with `permission_callback => '__return_true'`, the API key
 * hardcoded in the file, the browser's request body forwarded verbatim and no limit of any
 * kind. Anyone who read the page source had an unmetered OpenAI account.
 *
 * So these tests are mostly about what the endpoint *refuses*, and about the two places the
 * obvious implementation is quietly wrong: taking the left-most X-Forwarded-For entry (which a
 * caller controls, so the rate limit never fires) and returning upstream's status to the
 * browser (which tells an anonymous caller whether our key is valid).
 */
function jobWidgetConfig(array $overrides = []): array
{
    return array_replace_recursive([
        'enabled' => true,
        'api_key' => 'sk-test-key',
        'base_url' => 'https://api.openai.com/v1',
        'chat_models' => ['gpt-3.5-turbo', 'gpt-4o-mini'],
        'transcription_model' => 'whisper-1',
        'max_tokens' => 1000,
        'max_body_bytes' => 64 * 1024,
        'max_audio_bytes' => 1024,
        'rate_limit' => ['requests' => 3, 'window' => 600],
        'require_nonce' => true,
        'timeout' => 45,
        'transcription_timeout' => 90,
        'connect_timeout' => 5,
    ], $overrides);
}

beforeEach(function () {
    $GLOBALS['_wp_mock_transients'] = [];

    Facade::clearResolvedInstance(HttpFactory::class);
    Http::swap(new HttpFactory);
});

it('registers nothing on an environment with no API key', function () {
    $guard = new OpenAiProxyGuard(jobWidgetConfig(['api_key' => '']));

    expect($guard->enabled())->toBeFalse();
})->group('job-widget');

it('stays dark when the feature flag is off even with a key present', function () {
    $guard = new OpenAiProxyGuard(jobWidgetConfig(['enabled' => false]));

    expect($guard->enabled())->toBeFalse();
})->group('job-widget');

it('refuses a request from another site', function () {
    $guard = new OpenAiProxyGuard(jobWidgetConfig());

    $error = $guard->authorize(wp_create_nonce('wp_rest'), 'https://evil.example', '203.0.113.9');

    expect($error)->toBeInstanceOf(WP_Error::class)
        ->and($error->get_error_code())->toBe('jobwidget_bad_origin');
})->group('job-widget');

it('allows a same-origin request and a request with no Origin at all', function () {
    $guard = new OpenAiProxyGuard(jobWidgetConfig());

    expect($guard->originAllowed('https://remoteleverage.com'))->toBeTrue()
        ->and($guard->originAllowed(''))->toBeTrue()
        ->and($guard->originAllowed(null))->toBeTrue();
})->group('job-widget');

it('refuses a missing or stale nonce', function () {
    $guard = new OpenAiProxyGuard(jobWidgetConfig());

    foreach ([null, '', 'stale-nonce'] as $nonce) {
        $error = $guard->authorize($nonce, 'https://remoteleverage.com', '203.0.113.9');

        expect($error)->toBeInstanceOf(WP_Error::class)
            ->and($error->get_error_code())->toBe('jobwidget_bad_nonce');
    }
})->group('job-widget');

it('rate limits a caller once the window budget is spent', function () {
    $guard = new OpenAiProxyGuard(jobWidgetConfig());
    $nonce = wp_create_nonce('wp_rest');

    // The configured budget is 3.
    for ($i = 0; $i < 3; $i++) {
        expect($guard->authorize($nonce, 'https://remoteleverage.com', '203.0.113.9'))->toBeNull();
    }

    $error = $guard->authorize($nonce, 'https://remoteleverage.com', '203.0.113.9');

    expect($error)->toBeInstanceOf(WP_Error::class)
        ->and($error->get_error_code())->toBe('jobwidget_rate_limited')
        ->and($error->get_error_data()['status'])->toBe(429);
})->group('job-widget');

it('does not spend a visitor budget on requests it rejects before the limit', function () {
    $guard = new OpenAiProxyGuard(jobWidgetConfig());

    // Three rejected attempts from the same IP...
    for ($i = 0; $i < 3; $i++) {
        $guard->authorize('stale-nonce', 'https://remoteleverage.com', '203.0.113.9');
    }

    // ...must leave the budget untouched, or one hotlinking script locks out a real visitor
    // sharing an office IP.
    expect($guard->authorize(wp_create_nonce('wp_rest'), 'https://remoteleverage.com', '203.0.113.9'))
        ->toBeNull();
})->group('job-widget');

it('counts different callers separately', function () {
    $guard = new OpenAiProxyGuard(jobWidgetConfig(['rate_limit' => ['requests' => 1, 'window' => 600]]));
    $nonce = wp_create_nonce('wp_rest');

    expect($guard->authorize($nonce, null, '203.0.113.9'))->toBeNull()
        ->and($guard->authorize($nonce, null, '203.0.113.10'))->toBeNull()
        ->and($guard->authorize($nonce, null, '203.0.113.9'))->toBeInstanceOf(WP_Error::class);
})->group('job-widget');

it('bills an unresolvable IP to a shared bucket rather than waving it through', function () {
    $guard = new OpenAiProxyGuard(jobWidgetConfig(['rate_limit' => ['requests' => 1, 'window' => 600]]));
    $nonce = wp_create_nonce('wp_rest');

    expect($guard->authorize($nonce, null, null))->toBeNull()
        ->and($guard->authorize($nonce, null, null))->toBeInstanceOf(WP_Error::class);
})->group('job-widget');

/*
 * The one that matters. CloudFront *appends* the viewer IP to whatever X-Forwarded-For the
 * viewer sent, so the left-most entry is attacker-supplied. Reading it — which is the natural
 * thing to do, and what AttributionCollector correctly does for attribution — would let one
 * caller present a fresh IP per request and never meet the rate limit at all.
 */
it('rate limits on the right-most forwarded IP, which the caller cannot forge', function () {
    $guard = new OpenAiProxyGuard(jobWidgetConfig());

    $ip = $guard->resolveClientIp([
        'HTTP_X_FORWARDED_FOR' => '10.0.0.1, 198.51.100.7',
        'REMOTE_ADDR' => '172.16.0.1',
    ]);

    expect($ip)->toBe('198.51.100.7');
})->group('job-widget');

it('falls back to REMOTE_ADDR when nothing is forwarded', function () {
    $guard = new OpenAiProxyGuard(jobWidgetConfig());

    expect($guard->resolveClientIp(['REMOTE_ADDR' => '198.51.100.7']))->toBe('198.51.100.7')
        ->and($guard->resolveClientIp([]))->toBeNull();
})->group('job-widget');

it('refuses a model that is not on the allowlist', function () {
    $guard = new OpenAiProxyGuard(jobWidgetConfig());

    $error = $guard->sanitizeChatPayload([
        'model' => 'o1-preview',
        'messages' => [['role' => 'user', 'content' => 'hello']],
    ]);

    expect($error)->toBeInstanceOf(WP_Error::class)
        ->and($error->get_error_code())->toBe('jobwidget_bad_model');
})->group('job-widget');

it('caps max_tokens and temperature instead of forwarding what the browser asked for', function () {
    $guard = new OpenAiProxyGuard(jobWidgetConfig());

    $body = $guard->sanitizeChatPayload([
        'model' => 'gpt-3.5-turbo',
        'messages' => [['role' => 'user', 'content' => 'hello']],
        'max_tokens' => 128000,
        'temperature' => 9,
    ]);

    expect($body['max_tokens'])->toBe(1000)
        ->and($body['temperature'])->toBe(2.0);
})->group('job-widget');

it('forwards only the keys it recognises', function () {
    $guard = new OpenAiProxyGuard(jobWidgetConfig());

    $body = $guard->sanitizeChatPayload([
        'model' => 'gpt-3.5-turbo',
        'messages' => [['role' => 'user', 'content' => 'hello']],
        // Anything the widget never sent has no business reaching OpenAI on our key.
        'tools' => [['type' => 'function']],
        'stream' => true,
        'n' => 50,
    ]);

    expect(array_keys($body))->toBe(['model', 'messages']);
})->group('job-widget');

it('drops multimodal content arrays, which are a way to smuggle image URLs through', function () {
    $guard = new OpenAiProxyGuard(jobWidgetConfig());

    $body = $guard->sanitizeChatPayload([
        'model' => 'gpt-3.5-turbo',
        'messages' => [
            ['role' => 'user', 'content' => [['type' => 'image_url', 'image_url' => ['url' => 'https://evil.example/x.png']]]],
            ['role' => 'user', 'content' => 'describe this'],
        ],
    ]);

    expect($body['messages'])->toBe([['role' => 'user', 'content' => 'describe this']]);
})->group('job-widget');

it('refuses a prompt larger than the configured ceiling', function () {
    $guard = new OpenAiProxyGuard(jobWidgetConfig(['max_body_bytes' => 100]));

    $error = $guard->sanitizeChatPayload([
        'model' => 'gpt-3.5-turbo',
        'messages' => [['role' => 'user', 'content' => str_repeat('a', 500)]],
    ]);

    expect($error)->toBeInstanceOf(WP_Error::class)
        ->and($error->get_error_data()['status'])->toBe(413);
})->group('job-widget');

it('refuses audio that is not really base64 and audio that is too large', function () {
    $guard = new OpenAiProxyGuard(jobWidgetConfig());

    $bad = $guard->sanitizeTranscriptionPayload(['audio' => '!!!not base64!!!', 'filename' => 'a.mp3']);
    expect($bad)->toBeInstanceOf(WP_Error::class)
        ->and($bad->get_error_code())->toBe('jobwidget_bad_audio');

    // max_audio_bytes is 1024 in the test config.
    $big = $guard->sanitizeTranscriptionPayload([
        'audio' => base64_encode(str_repeat('a', 4096)),
        'filename' => 'a.mp3',
    ]);
    expect($big)->toBeInstanceOf(WP_Error::class)
        ->and($big->get_error_data()['status'])->toBe(413);
})->group('job-widget');

it('sanitises the filename the browser supplies', function () {
    $guard = new OpenAiProxyGuard(jobWidgetConfig());

    $clean = $guard->sanitizeTranscriptionPayload([
        'audio' => base64_encode('audio-bytes'),
        'filename' => '../../../etc/passwd',
    ]);

    expect($clean['filename'])->not->toContain('/')
        ->and($clean['filename'])->not->toContain('..');
})->group('job-widget');

it('sends the key as a bearer token and nothing else of ours', function () {
    Http::fake([
        'api.openai.com/*' => Http::response(['choices' => [['message' => ['content' => 'ok']]]], 200),
    ]);

    $proxy = new OpenAiProxy(new OpenAiProxyGuard(jobWidgetConfig()), jobWidgetConfig());
    $result = $proxy->chat(['model' => 'gpt-3.5-turbo', 'messages' => [['role' => 'user', 'content' => 'hi']]]);

    expect($result['choices'][0]['message']['content'])->toBe('ok');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.openai.com/v1/chat/completions'
            && $request->hasHeader('Authorization', 'Bearer sk-test-key');
    });
})->group('job-widget');

/*
 * A 401 from OpenAI means *our* key is wrong. Passing that status through would let an
 * anonymous caller probe the state of our credential, and it tells the visitor nothing they
 * can act on. 502 is the honest answer from the browser's side.
 */
it('does not leak upstream status or body to the browser', function () {
    Http::fake([
        'api.openai.com/*' => Http::response(['error' => ['message' => 'Incorrect API key provided: sk-test-key']], 401),
    ]);

    $proxy = new OpenAiProxy(new OpenAiProxyGuard(jobWidgetConfig()), jobWidgetConfig());
    $error = $proxy->chat(['model' => 'gpt-3.5-turbo', 'messages' => [['role' => 'user', 'content' => 'hi']]]);

    expect($error)->toBeInstanceOf(WP_Error::class)
        ->and($error->get_error_data()['status'])->toBe(502)
        ->and($error->get_error_message())->not->toContain('sk-test-key')
        ->and($error->get_error_message())->not->toContain('API key');
})->group('job-widget');

it('answers the transcription shape the Audio Scorer widget reads', function () {
    Http::fake([
        'api.openai.com/*' => Http::response(['text' => 'hello there'], 200),
    ]);

    $proxy = new OpenAiProxy(new OpenAiProxyGuard(jobWidgetConfig()), jobWidgetConfig());

    expect($proxy->transcribe('audio-bytes', 'clip.mp3'))
        ->toBe(['text' => 'hello there', 'success' => true]);
})->group('job-widget');

it('treats an empty transcript as a failure rather than an empty success', function () {
    Http::fake([
        'api.openai.com/*' => Http::response(['text' => '   '], 200),
    ]);

    $proxy = new OpenAiProxy(new OpenAiProxyGuard(jobWidgetConfig()), jobWidgetConfig());
    $error = $proxy->transcribe('audio-bytes', 'clip.mp3');

    expect($error)->toBeInstanceOf(WP_Error::class)
        ->and($error->get_error_code())->toBe('jobwidget_no_transcript');
})->group('job-widget');
