{{-- Embeds the multi-step Livewire booking wizard (details, date, time, confirmation). Use
     wherever a page needs the live scheduler rather than a link. --}}
<div class="w-full">
    <livewire:booking.multistep-booking-wizard :skin="$skin ?? 'glass'" :lazy="false" />
</div>
