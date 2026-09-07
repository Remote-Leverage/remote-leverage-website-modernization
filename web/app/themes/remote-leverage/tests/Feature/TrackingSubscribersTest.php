<?php

declare(strict_types=1);

use App\Domains\Lead\Events\LeadCreated;
use App\Domains\Lead\Models\Lead;
use App\Domains\Lead\Models\LeadActivityLog;
use App\Domains\Lead\Services\LeadActivityLogger;
use App\Domains\Tracking\Actions\RecordBehaviorEventAction;
use App\Domains\Tracking\Data\AnalyticsEventData;
use App\Domains\Tracking\Data\UserProfileData;
use App\Domains\Tracking\Gateways\CustomerIOClient;
use App\Domains\Tracking\Listeners\HandleLeadCreatedForTracking;
use Illuminate\Support\Str;

describe('Tracking Domain LeadCreated Event Listener', function () {
    beforeEach(function () {
        LeadActivityLog::truncate();
        Lead::truncate();
    });

    test('HandleLeadCreatedForTracking identifies user in Customer.io, records telemetry, and writes activity log', function () {
        $mockCustomerIO = $this->createMock(CustomerIOClient::class);
        $mockRecordAction = $this->createMock(RecordBehaviorEventAction::class);
        $activityLogger = new LeadActivityLogger;

        $lead = Lead::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Sarah Connor',
            'first_name' => 'Sarah',
            'last_name' => 'Connor',
            'email' => 'sarah.connor@cyberdyne.io',
            'phone' => '+13055550199',
            'company' => 'Cyberdyne Resistance',
            'role_needed' => 'Executive Assistant',
            'weekly_hours' => '40',
            'source_type' => 'partnership',
            'source_id' => 'apex-capital',
            'status' => 'captured',
        ]);

        // Expect Customer.io identification
        $mockCustomerIO->expects($this->once())
            ->method('identify')
            ->with($this->callback(function (UserProfileData $profile) use ($lead) {
                return $profile->email === 'sarah.connor@cyberdyne.io'
                    && $profile->name === 'Sarah Connor'
                    && $profile->traits['source_type'] === 'partnership'
                    && $profile->traits['source_id'] === 'apex-capital'
                    && $profile->traits['lead_id'] === $lead->id;
            }));

        // Expect behavior event recording
        $mockRecordAction->expects($this->once())
            ->method('execute')
            ->with($this->callback(function (AnalyticsEventData $event) {
                return $event->event === 'Lead Captured'
                    && $event->distinctId === 'sarah.connor@cyberdyne.io'
                    && $event->properties['source_type'] === 'partnership'
                    && $event->properties['source_id'] === 'apex-capital';
            }));

        $listener = new HandleLeadCreatedForTracking($mockCustomerIO, $mockRecordAction, $activityLogger);
        $listener->handle(new LeadCreated($lead));

        // Verify dual logging stage 2 (consumption)
        $log = LeadActivityLog::query()
            ->where('lead_id', $lead->id)
            ->where('actor_domain', 'Tracking')
            ->where('stage', 'consumption')
            ->first();

        expect($log)->not->toBeNull()
            ->and($log->event_type)->toBe('LeadCreated')
            ->and($log->outcome)->toBe('succeeded');
    });
});
