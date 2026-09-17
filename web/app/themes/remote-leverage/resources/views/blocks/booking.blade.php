{{-- Embeds the multi-step Livewire booking wizard (details, date, time, confirmation). Use
     wherever a page needs the live scheduler rather than a link. --}}
<div class="w-full px-4 sm:px-6 lg:px-8">
    {{-- revenue-first: this block is the page-bottom booking section, where the revenue question
         leads as a radio list. The glass skin already renders it that way; this brings the
         naked/default skins in line (direction 2026-09-15). --}}
    <livewire:booking.multistep-booking-wizard :skin="$skin ?? 'glass'" :revenue-first="true" :revenueFirst="true" :lazy="false" />
</div>
