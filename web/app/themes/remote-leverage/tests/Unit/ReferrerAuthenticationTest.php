<?php

declare(strict_types=1);

use App\Domains\Referral\Actions\RegisterReferrerAction;
use App\Domains\Referral\Models\Referrer;
use App\Domains\Referral\Repositories\EloquentReferrerRepository;

describe('Referrer registration password handling', function () {
    test('registering a referrer hashes the password rather than storing it in plain text', function () {
        $action = new RegisterReferrerAction(new EloquentReferrerRepository);

        $referrer = $action->execute([
            'name' => 'Jordan Hayes',
            'email' => 'jordan-'.uniqid().'@venture.com',
            'password' => 'super-secret-8',
        ]);

        expect($referrer->password)->not->toBe('super-secret-8')
            ->and(password_verify('super-secret-8', $referrer->password))->toBeTrue();
    });

    test('registering a referrer without a password throws', function () {
        $action = new RegisterReferrerAction(new EloquentReferrerRepository);

        expect(fn () => $action->execute([
            'name' => 'No Password',
            'email' => 'nopass-'.uniqid().'@venture.com',
        ]))->toThrow(InvalidArgumentException::class);
    });
});

describe('Referrer model hides password from serialization', function () {
    test('toArray/toJson omit the password attribute', function () {
        $referrer = Referrer::query()->create([
            'name' => 'Hidden Password Referrer',
            'email' => 'hidden-'.uniqid().'@venture.com',
            'referral_code' => 'hidden-'.uniqid(),
            'password' => password_hash('whatever', PASSWORD_BCRYPT),
            'status' => 'active',
        ]);

        expect($referrer->toArray())->not->toHaveKey('password');
    });
});
