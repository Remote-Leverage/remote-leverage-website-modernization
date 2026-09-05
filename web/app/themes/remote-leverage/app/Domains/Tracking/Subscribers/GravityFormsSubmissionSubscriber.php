<?php

declare(strict_types=1);

namespace App\Domains\Tracking\Subscribers;

use App\Domains\Tracking\Actions\RecordBehaviorEventAction;
use App\Domains\Tracking\Data\AnalyticsEventData;
use App\Domains\Tracking\Data\UserProfileData;
use App\Domains\Tracking\Gateways\CustomerIOClient;
use Illuminate\Support\Facades\Log;

class GravityFormsSubmissionSubscriber
{
    public function __construct(
        protected CustomerIOClient $customerIO,
        protected RecordBehaviorEventAction $recordEventAction,
    ) {}

    /**
     * Handle Gravity Forms form submission.
     */
    public function handleSubmission(array $entry, array $form): void
    {
        Log::info('Gravity Forms submission captured for tracking', [
            'form_id' => $form['id'] ?? null,
            'entry_id' => $entry['id'] ?? null,
        ]);

        $email = null;
        $name = null;
        $referralCode = $_COOKIE['rl_ref'] ?? null;

        // Search for email and name inputs in entry
        foreach ($form['fields'] as $field) {
            if ($field->type === 'email') {
                $email = $entry[$field->id] ?? null;
            } elseif ($field->type === 'name') {
                $name = trim(($entry[$field->id.'.3'] ?? '').' '.($entry[$field->id.'.6'] ?? ''));
            }
        }

        if ($email) {
            $profile = UserProfileData::fromArray([
                'identifier' => $email,
                'email' => $email,
                'name' => $name,
                'referral_code' => $referralCode,
                'traits' => [
                    'source_form' => $form['title'] ?? 'Form #'.$form['id'],
                    'submitted_at' => function_exists('current_time') ? current_time('mysql') : now()->toDateTimeString(),
                ],
            ]);

            $this->customerIO->identify($profile);
        }

        $pageUrl = $entry['source_url'] ?? (function_exists('home_url') ? home_url() : '/');

        $event = AnalyticsEventData::fromArray([
            'event' => 'Form Submitted',
            'distinct_id' => $email ?? 'anon_'.session_id(),
            'properties' => [
                'form_id' => $form['id'],
                'form_title' => $form['title'] ?? null,
                'referral_code' => $referralCode,
                'page_url' => $pageUrl,
            ],
        ]);

        $this->recordEventAction->execute($event);
    }
}
