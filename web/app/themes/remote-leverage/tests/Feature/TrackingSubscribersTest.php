<?php

declare(strict_types=1);

use App\Domains\Tracking\Actions\RecordBehaviorEventAction;
use App\Domains\Tracking\Data\AnalyticsEventData;
use App\Domains\Tracking\Data\UserProfileData;
use App\Domains\Tracking\Gateways\CustomerIOClient;
use App\Domains\Tracking\Subscribers\GravityFormsSubmissionSubscriber;

describe('GravityFormsSubmissionSubscriber', function () {
    test('captures form submission, identifies user in Customer.io, and records analytics event', function () {
        $mockCustomerIO = $this->createMock(CustomerIOClient::class);
        $mockRecordAction = $this->createMock(RecordBehaviorEventAction::class);

        // Expect Customer.io identification
        $mockCustomerIO->expects($this->once())
            ->method('identify')
            ->with($this->callback(function (UserProfileData $profile) {
                return $profile->email === 'sarah.connor@cyberdyne.io'
                    && $profile->name === 'Sarah Connor'
                    && $profile->traits['source_form'] === 'Executive Talent Request Form';
            }));

        // Expect behavior event recording
        $mockRecordAction->expects($this->once())
            ->method('execute')
            ->with($this->callback(function (AnalyticsEventData $event) {
                return $event->event === 'Form Submitted'
                    && $event->distinctId === 'sarah.connor@cyberdyne.io'
                    && $event->properties['form_id'] === 5
                    && $event->properties['form_title'] === 'Executive Talent Request Form';
            }));

        $subscriber = new GravityFormsSubmissionSubscriber($mockCustomerIO, $mockRecordAction);

        $form = [
            'id' => 5,
            'title' => 'Executive Talent Request Form',
            'fields' => [
                (object) ['id' => 1, 'type' => 'name'],
                (object) ['id' => 2, 'type' => 'email'],
                (object) ['id' => 3, 'type' => 'textarea'],
            ],
        ];

        $entry = [
            'id' => 999,
            '1.3' => 'Sarah',
            '1.6' => 'Connor',
            '2' => 'sarah.connor@cyberdyne.io',
            '3' => 'Need 2 senior real estate coordinators immediately.',
            'source_url' => 'https://remoteleverage.com/hire-talent',
        ];

        $subscriber->handleSubmission($entry, $form);
    });

    test('attributes referral code from cookie if present during form submission', function () {
        $_COOKIE['rl_ref'] = 'apex-capital';

        $mockCustomerIO = $this->createMock(CustomerIOClient::class);
        $mockRecordAction = $this->createMock(RecordBehaviorEventAction::class);

        $mockCustomerIO->expects($this->once())
            ->method('identify')
            ->with($this->callback(function (UserProfileData $profile) {
                return $profile->referralCode === 'apex-capital';
            }));

        $mockRecordAction->expects($this->once())
            ->method('execute')
            ->with($this->callback(function (AnalyticsEventData $event) {
                return $event->properties['referral_code'] === 'apex-capital';
            }));

        $subscriber = new GravityFormsSubmissionSubscriber($mockCustomerIO, $mockRecordAction);

        $form = [
            'id' => 1,
            'title' => 'Lead Form',
            'fields' => [
                (object) ['id' => 1, 'type' => 'email'],
            ],
        ];

        $entry = [
            'id' => 101,
            '1' => 'lead@apex.com',
        ];

        $subscriber->handleSubmission($entry, $form);

        unset($_COOKIE['rl_ref']);
    });
});
