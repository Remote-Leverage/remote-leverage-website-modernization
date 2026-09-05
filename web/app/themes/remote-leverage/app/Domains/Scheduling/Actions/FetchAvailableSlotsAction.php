<?php

declare(strict_types=1);

namespace App\Domains\Scheduling\Actions;

use App\Domains\Scheduling\Data\TimeSlotData;
use App\Domains\Scheduling\Gateways\CalendlyClient;
use Carbon\Carbon;

class FetchAvailableSlotsAction
{
    public function __construct(
        protected CalendlyClient $calendlyClient
    ) {}

    /**
     * Fetch available booking slots for a given date range.
     *
     * @return array<TimeSlotData>
     */
    public function execute(?string $startDate = null, ?string $endDate = null, string $timezone = 'UTC'): array
    {
        $start = $startDate ? Carbon::parse($startDate, $timezone)->startOfDay() : Carbon::now($timezone);
        $end = $endDate ? Carbon::parse($endDate, $timezone)->endOfDay() : Carbon::now($timezone)->addDays(7)->endOfDay();

        $eventTypeId = env('CALENDLY_EVENT_TYPE_ID');
        if (! $eventTypeId) {
            // Fallback generation of realistic mock slots if API key/event type is not yet populated
            return $this->generateDefaultSlots($start, $end, $timezone);
        }

        $rawSlots = $this->calendlyClient->getAvailableSlots(
            $eventTypeId,
            $start->toIso8601String(),
            $end->toIso8601String()
        );

        $slots = [];
        foreach ($rawSlots as $slot) {
            $slots[] = TimeSlotData::fromArray([
                'start_time' => $slot['start_time'],
                'end_time' => $slot['end_time'] ?? Carbon::parse($slot['start_time'])->addMinutes(30)->toIso8601String(),
                'timezone' => $timezone,
                'available' => (bool) ($slot['status'] ?? true),
                'consultant_name' => 'Remote Leverage Specialist',
            ]);
        }

        return $slots;
    }

    /**
     * Generate structured mock slots for initial preview when external API is waiting on credentials.
     */
    protected function generateDefaultSlots(Carbon $start, Carbon $end, string $timezone): array
    {
        $slots = [];
        $current = $start->copy();

        while ($current->lte($end)) {
            // Monday to Friday only
            if (! in_array($current->dayOfWeek, [Carbon::SATURDAY, Carbon::SUNDAY])) {
                foreach ([10, 11, 14, 15, 16] as $hour) {
                    $slotTime = $current->copy()->setHour($hour)->setMinute(0)->setSecond(0);
                    if ($slotTime->gt(Carbon::now($timezone))) {
                        $slots[] = new TimeSlotData(
                            startTime: $slotTime->toIso8601String(),
                            endTime: $slotTime->copy()->addMinutes(30)->toIso8601String(),
                            timezone: $timezone,
                            available: true,
                            consultantName: 'Remote Leverage Strategy Team',
                        );
                    }
                }
            }
            $current->addDay();
        }

        return $slots;
    }
}
