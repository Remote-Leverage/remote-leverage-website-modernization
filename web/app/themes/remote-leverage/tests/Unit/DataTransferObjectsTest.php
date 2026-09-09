<?php

declare(strict_types=1);

use App\Domains\Referral\Data\PayoutData;
use App\Domains\Referral\Data\ReferralData;
use App\Domains\Referral\Data\ReferrerData;
use App\Domains\Scheduling\Data\BookingRequestData;
use App\Domains\Scheduling\Data\TimeSlotData;
use App\Domains\Tracking\Data\AnalyticsEventData;
use App\Domains\Tracking\Data\UserProfileData;

describe('Data Transfer Objects', function () {
    test('ReferralData instantiates correctly and serializes to array', function () {
        $dto = ReferralData::fromArray([
            'referrer_id' => 42,
            'referral_code' => 'apex-capital',
            'ip_address' => '192.168.1.1',
            'landing_url' => 'https://remoteleverage.com/executive-assistant',
            'utm_source' => 'linkedin',
            'utm_medium' => 'referrer_post',
            'utm_campaign' => 'q3_launch',
        ]);

        expect($dto->referrerId)->toBe(42)
            ->and($dto->referralCode)->toBe('apex-capital')
            ->and($dto->utmSource)->toBe('linkedin')
            ->and($dto->status)->toBe('clicked');

        $arr = $dto->toArray();
        expect($arr['referrer_id'])->toBe(42)
            ->and($arr['referral_code'])->toBe('apex-capital')
            ->and($arr['utm_medium'])->toBe('referrer_post');
    });

    test('PayoutData handles amounts and statuses', function () {
        $dto = PayoutData::fromArray([
            'referrer_id' => 10,
            'amount' => 1250.50,
            'currency' => 'USD',
            'status' => 'pending',
            'stripe_transfer_id' => 'tr_12345',
        ]);

        expect($dto->referrerId)->toBe(10)
            ->and($dto->amount)->toBe(1250.50)
            ->and($dto->currency)->toBe('USD')
            ->and($dto->status)->toBe('pending');

        expect($dto->toArray()['stripe_transfer_id'])->toBe('tr_12345');
    });

    test('ReferrerData validates company and referral codes', function () {
        $dto = ReferrerData::fromArray([
            'name' => 'Adrian Miller',
            'email' => 'adrian@scaleops.com',
            'company' => 'ScaleOps Agency',
            'referral_code' => 'scaleops',
            'stripe_account_id' => 'acct_123456789',
            'status' => 'active',
        ]);

        expect($dto->name)->toBe('Adrian Miller')
            ->and($dto->company)->toBe('ScaleOps Agency')
            ->and($dto->referralCode)->toBe('scaleops')
            ->and($dto->stripeAccountId)->toBe('acct_123456789')
            ->and($dto->status)->toBe('active');
    });

    test('BookingRequestData supports camelCase and snake_case inputs', function () {
        $snake = BookingRequestData::fromArray([
            'name' => 'Sarah Connor',
            'email' => 'sarah@skynet-defense.com',
            'start_time' => '2026-09-10T14:00:00Z',
            'timezone' => 'America/New_York',
            'referral_code' => 'apex',
        ]);

        expect($snake->name)->toBe('Sarah Connor')
            ->and($snake->startTime)->toBe('2026-09-10T14:00:00Z')
            ->and($snake->referralCode)->toBe('apex');

        $camel = BookingRequestData::fromArray([
            'name' => 'John Connor',
            'email' => 'john@resistance.org',
            'startTime' => '2026-09-10T15:00:00Z',
            'referralCode' => 'cyberdyne',
        ]);

        expect($camel->startTime)->toBe('2026-09-10T15:00:00Z')
            ->and($camel->referralCode)->toBe('cyberdyne');
    });

    test('TimeSlotData holds available slot intervals', function () {
        $dto = TimeSlotData::fromArray([
            'start_time' => '2026-09-12 10:00:00',
            'end_time' => '2026-09-12 10:30:00',
            'timezone' => 'America/New_York',
            'available' => true,
            'consultant_name' => 'Alex Rivera',
        ]);

        expect($dto->startTime)->toBe('2026-09-12 10:00:00')
            ->and($dto->endTime)->toBe('2026-09-12 10:30:00')
            ->and($dto->timezone)->toBe('America/New_York')
            ->and($dto->available)->toBeTrue()
            ->and($dto->consultantName)->toBe('Alex Rivera');
    });

    test('UserProfileData handles traits and fallback identifiers', function () {
        $dto = UserProfileData::fromArray([
            'email' => 'founder@acme.com',
            'name' => 'Elon Musk',
            'referral_code' => 'mars',
            'traits' => [
                'company_size' => '50-100',
                'role' => 'CEO',
            ],
        ]);

        expect($dto->identifier)->toBe('founder@acme.com')
            ->and($dto->email)->toBe('founder@acme.com')
            ->and($dto->name)->toBe('Elon Musk')
            ->and($dto->traits['role'])->toBe('CEO');
    });

    test('AnalyticsEventData serializes properties correctly', function () {
        $dto = AnalyticsEventData::fromArray([
            'event' => 'Consultation Scheduled',
            'distinct_id' => 'lead_999',
            'properties' => [
                'source' => 'booking_wizard',
                'role' => 'Executive Assistant',
            ],
        ]);

        expect($dto->event)->toBe('Consultation Scheduled')
            ->and($dto->distinctId)->toBe('lead_999')
            ->and($dto->properties['role'])->toBe('Executive Assistant');
    });
});
