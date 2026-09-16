<?php

declare(strict_types=1);

use App\Infrastructure\WordPress\Security\WordfenceConfigurator;

/*
 * config/wordfence.php applied to WordFence's option store.
 *
 * The risk this guards is not "does it write" — it is that WordFence stores booleans as '1'/''
 * and numbers as strings. A strict comparison against typed config values reports every setting
 * as drifted on every deploy and rewrites the whole set each time, which makes the "nothing
 * changed" report meaningless and hides real drift in the noise.
 *
 * WordFence is not installed in the test environment, so a fake stands in for `wfConfig`. The
 * subject overrides only get()/set()/isKnownKey() — the comparison logic under test is the real
 * one.
 */

/** A configurator backed by an in-memory store, standing in for wfConfig. */
function wordfenceConfigurator(array $stored, ?array $knownKeys = null): object
{
    return new class($stored, $knownKeys) extends WordfenceConfigurator
    {
        public array $writes = [];

        public function __construct(public array $stored, private ?array $knownKeys) {}

        public function available(): bool
        {
            return true;
        }

        protected function isKnownKey(string $key): bool
        {
            return $this->knownKeys === null || in_array($key, $this->knownKeys, true);
        }

        protected function get(string $key): mixed
        {
            return $this->stored[$key] ?? null;
        }

        protected function set(string $key, mixed $value): void
        {
            if (is_bool($value)) {
                $value = $value ? '1' : '';
            }

            $this->writes[$key] = $value;
            $this->stored[$key] = $value;
        }
    };
}

describe('WordfenceConfigurator', function () {
    test('writes a setting that differs from what is stored', function () {
        config(['wordfence.apply_on_deploy' => true, 'wordfence.settings' => ['firewallEnabled' => true]]);

        $subject = wordfenceConfigurator(['firewallEnabled' => '']);
        $result = $subject->apply();

        expect($result['applied'])->toHaveKey('firewallEnabled')
            ->and($subject->writes['firewallEnabled'])->toBe('1');
    });

    test("WordFence's '1' and '' are recognised as the booleans they represent", function () {
        // The regression this exists for: without loose comparison every deploy rewrites
        // every boolean, because true !== '1'.
        config(['wordfence.apply_on_deploy' => true, 'wordfence.settings' => [
            'firewallEnabled' => true,
            'autoUpdate' => false,
            'liveTrafficEnabled' => false,
        ]]);

        $subject = wordfenceConfigurator([
            'firewallEnabled' => '1',
            'autoUpdate' => '',
            'liveTrafficEnabled' => '',
        ]);
        $result = $subject->apply();

        expect($result['applied'])->toBe([])
            ->and($subject->writes)->toBe([])
            ->and($result['unchanged'])->toHaveCount(3);
    });

    test('numbers stored as strings are not rewritten every run', function () {
        config(['wordfence.apply_on_deploy' => true, 'wordfence.settings' => [
            'loginSec_maxFailures' => 20,
            'alert_maxHourly' => 10,
        ]]);

        $subject = wordfenceConfigurator(['loginSec_maxFailures' => '20', 'alert_maxHourly' => '10']);

        expect($subject->apply()['applied'])->toBe([])
            ->and($subject->writes)->toBe([]);
    });

    test('a changed number is still detected', function () {
        config(['wordfence.apply_on_deploy' => true, 'wordfence.settings' => ['loginSec_maxFailures' => 20]]);

        $subject = wordfenceConfigurator(['loginSec_maxFailures' => '5']);

        expect($subject->apply()['applied'])->toHaveKey('loginSec_maxFailures');
    });

    test('applying twice is a no-op the second time', function () {
        config(['wordfence.apply_on_deploy' => true, 'wordfence.settings' => [
            'firewallEnabled' => true,
            'loginSec_lockoutMins' => 60,
        ]]);

        $subject = wordfenceConfigurator([]);
        $first = $subject->apply();
        $subject->writes = [];
        $second = $subject->apply();

        expect($first['applied'])->toHaveCount(2)
            ->and($second['applied'])->toBe([])
            ->and($subject->writes)->toBe([]);
    });

    test('an unknown key is reported and never written', function () {
        // wfConfig::set() would happily create a row nothing reads, which looks like success.
        config(['wordfence.apply_on_deploy' => true, 'wordfence.settings' => [
            'firewallEnabled' => true,
            'firewallEnabledTypo' => true,
        ]]);

        $subject = wordfenceConfigurator([], knownKeys: ['firewallEnabled']);
        $result = $subject->apply();

        expect($result['unknown'])->toBe(['firewallEnabledTypo'])
            ->and($subject->writes)->not->toHaveKey('firewallEnabledTypo')
            ->and($subject->writes)->toHaveKey('firewallEnabled');
    });

    test('settings absent from the config are left alone', function () {
        config(['wordfence.apply_on_deploy' => true, 'wordfence.settings' => ['firewallEnabled' => true]]);

        $subject = wordfenceConfigurator(['firewallEnabled' => '', 'someOtherSetting' => 'keep me']);
        $subject->apply();

        expect($subject->writes)->toHaveCount(1)
            ->and($subject->stored['someOtherSetting'])->toBe('keep me');
    });

    test('apply_on_deploy false skips without writing', function () {
        config(['wordfence.apply_on_deploy' => false, 'wordfence.settings' => ['firewallEnabled' => true]]);

        $subject = wordfenceConfigurator([]);
        $result = $subject->apply();

        expect($result['skipped'])->toBeString()
            ->and($subject->writes)->toBe([]);
    });
});

describe('config/wordfence.php', function () {
    test('autoUpdate is off, because composer owns the plugin version', function () {
        $config = require __DIR__.'/../../config/wordfence.php';

        expect($config['settings']['autoUpdate'])->toBeFalse();
    });

    test('the firewall and login security are on', function () {
        $config = require __DIR__.'/../../config/wordfence.php';

        expect($config['settings']['firewallEnabled'])->toBeTrue()
            ->and($config['settings']['loginSecurityEnabled'])->toBeTrue();
    });

    test('invalid-user lockout stays off while a CDN fronts the origin', function () {
        // One bot enumerating usernames from behind the shared edge IP would otherwise lock
        // out every real visitor arriving through the same CloudFront/Cloudflare node.
        $config = require __DIR__.'/../../config/wordfence.php';

        expect($config['settings']['loginSec_lockInvalidUsers'])->toBeFalse();
    });
});
