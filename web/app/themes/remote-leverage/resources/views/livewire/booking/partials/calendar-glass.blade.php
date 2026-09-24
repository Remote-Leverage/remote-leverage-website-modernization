{{-- Steps 2 & 3 of the glass skin: the calendar and the times, rendered by rlBookingCalendar
     (resources/js/booking-calendar.js) in the visitor's own zone.

     wire:ignore because Alpine owns everything inside: the server only ever sends the slots as UTC
     instants ($wire.openSlots), and a Livewire morph would otherwise fight the templates for the DOM.
     Server state still reaches it — $wire is reactive, so a refused booking's refreshed slots and
     reset selection show up here as that response lands. --}}
<div wire:key="glass-calendar" wire:ignore x-data="rlBookingCalendar(@js($calendarConfig))">
  {{-- Step 2: Date Picker Calendar --}}
  <template x-if="view === 'date'">
    <div class="animate-wizard-step">
      {{-- Top Bar: Circular Back Button (Left) & Nav/Month Title (Right) --}}
      <div class="flex items-center justify-between mb-8">
        <button
          type="button"
          @click="$wire.goToStep(1)"
          class="w-12 h-12 rounded-full bg-white text-brand-purple flex items-center justify-center shadow-lg hover:scale-110 active:scale-95 transition-all duration-200 ease-out cursor-pointer shrink-0"
          aria-label="Back to Step 1"
        >
          <svg class="w-5 h-5 text-brand-purple" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
          </svg>
        </button>

        <div class="flex items-center gap-5">
          <div class="flex items-center gap-3 text-white/70">
            <button type="button" @click="prevMonth()" class="p-1.5 hover:text-white hover:scale-125 active:scale-90 transition-all duration-200 cursor-pointer text-base" aria-label="Previous Month">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/>
              </svg>
            </button>
            <button type="button" @click="nextMonth()" class="p-1.5 hover:text-white hover:scale-125 active:scale-90 transition-all duration-200 cursor-pointer text-base" aria-label="Next Month">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
              </svg>
            </button>
          </div>
          <h3 class="text-xl font-bold text-white tracking-tight transition-all duration-200" x-text="title"></h3>
        </div>
      </div>

      {{-- Day Name Column Headers --}}
      <div class="grid grid-cols-7 text-center text-xs sm:text-sm font-semibold text-white/80 uppercase tracking-wider mb-5">
        <span>SUN</span><span>MON</span><span>TUE</span><span>WED</span><span>THU</span><span>FRI</span><span>SAT</span>
      </div>

      {{-- Dates Grid. rlBookingCalendar replays the month transition on the ref when paging. --}}
      <div x-ref="grid" class="grid grid-cols-7 gap-y-3 sm:gap-y-4 text-center items-center justify-items-center animate-grid-month">
        <template x-for="cell in grid" :key="cell.key">
          <div class="contents">
            <template x-if="cell.empty">
              <div class="w-11 h-11 sm:w-12 sm:h-12"></div>
            </template>
            <template x-if="! cell.empty && cell.hasAvailability">
              <button
                type="button"
                @click="pickDate(cell.date)"
                class="w-11 h-11 sm:w-12 sm:h-12 rounded-full bg-white text-brand-purple font-bold text-base sm:text-lg flex items-center justify-center shadow-lg hover:scale-115 active:scale-95 transition-all duration-200 ease-out cursor-pointer hover:shadow-2xl"
              >
                <span x-text="cell.day"></span>
              </button>
            </template>
            <template x-if="! cell.empty && ! cell.hasAvailability">
              <div class="w-11 h-11 sm:w-12 sm:h-12 flex items-center justify-center text-white/60 text-base sm:text-lg font-normal select-none transition-opacity duration-200">
                <span x-text="cell.day"></span>
              </div>
            </template>
          </div>
        </template>
      </div>

      {{-- See the note on the light skin's copy of this. --}}
      <template x-if="monthIsEmpty">
        <div class="mt-4 rounded-xl bg-white/10 border border-white/20 px-4 py-3 text-center">
          <p class="text-xs font-semibold text-white"><span x-text="title"></span> is fully booked.</p>
          <template x-if="nextOpen">
            <div>
              <p class="text-xs text-white/80 mt-1">
                Next opening is <span x-text="format(nextOpen, 'weekdayMonthDay')"></span>.
              </p>
              <button type="button" @click="jumpToNext()"
                class="mt-2 text-xs font-semibold text-white underline hover:text-white/80">
                Go to <span x-text="format(nextOpen, 'monthDay')"></span>
              </button>
            </div>
          </template>
          <template x-if="! nextOpen">
            <p class="text-xs text-white/80 mt-1">
              New times open continuously — check back shortly, or email
              <a href="mailto:contact@remoteleverage.com" class="underline hover:text-white">contact@remoteleverage.com</a>
              and we will find you a slot.
            </p>
          </template>
        </div>
      </template>
    </div>
  </template>

  {{-- Step 3: Time Slot Selection --}}
  <template x-if="view === 'time'">
    <div class="animate-wizard-step">
      {{-- Top Bar: Circular Back Button --}}
      <div class="mb-2">
        <button
          type="button"
          @click="backToDates()"
          class="w-12 h-12 rounded-full bg-white text-brand-purple flex items-center justify-center shadow-lg hover:scale-110 active:scale-95 transition-all duration-200 ease-out cursor-pointer shrink-0"
          aria-label="Back to Calendar"
        >
          <svg class="w-5 h-5 text-brand-purple" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
          </svg>
        </button>
      </div>

      {{-- Date & Timezone Header --}}
      <div class="text-center">
        <h3 class="text-xl font-bold text-white tracking-tight" x-text="day('weekday')"></h3>
        <p class="text-sm font-medium text-white/90 mt-0.5" x-text="day('monthDayYear')"></p>

        <div class="mt-3">
          <span class="text-xs text-white/70 block">Time zone</span>
          <div class="relative inline-block mt-0.5 group cursor-pointer">
            <select @change="setZone($event.target.value)" aria-label="Time zone" class="opacity-0 absolute inset-0 w-full h-full cursor-pointer z-10">
              <template x-for="choice in choices" :key="choice">
                <option :value="choice" :selected="choice === tz" x-text="`${fallbackLabel(choice)} (${clock(choice)})`"></option>
              </template>
            </select>
            <div class="inline-flex items-center gap-1.5 text-sm font-semibold text-white group-hover:text-white/90 transition-colors duration-150 pointer-events-none">
              <svg class="w-4 h-4 text-white/80 transition-transform duration-200 group-hover:rotate-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <circle cx="12" cy="12" r="10" stroke-width="2"/>
                <path stroke-width="2" d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
              </svg>
              <span class="underline decoration-white/40 underline-offset-4" x-text="`${tz.replaceAll('_', ' ')} (${clock(tz)})`"></span>
              <svg class="w-3.5 h-3.5 text-white/70 transition-transform duration-200 group-hover:translate-y-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
              </svg>
            </div>
          </div>
        </div>
      </div>

      {{-- Thin Divider --}}
      <hr class="border-t border-white/20 my-6 w-full" />

      {{-- Section Title --}}
      <div class="text-center mb-5">
        <h4 class="text-lg font-bold text-white">Select a Time</h4>
        <p class="text-xs font-medium text-white/80 mt-0.5">Duration: 30 min</p>
      </div>

      {{-- Slot Options Stack. One block wrapper per slot, because space-y spaces direct children. --}}
      <div class="space-y-3 max-h-[380px] overflow-y-auto pr-1">
        <template x-for="slot in times" :key="slot.iso">
          <div>
            <template x-if="$wire.selectedSlot === slot.iso">
              <div class="grid grid-cols-2 gap-3 transition-all duration-200">
                <div class="w-full py-3.5 px-4 rounded-xl bg-brand-purple border border-white/30 text-white font-bold text-base text-center shadow flex items-center justify-center transition-all duration-200" x-text="slot.label"></div>
                <button
                  type="button"
                  @click="confirm()"
                  :disabled="submitting"
                  class="w-full py-3.5 px-4 rounded-xl bg-[#F97316] hover:bg-[#EA580C] text-white font-bold text-base text-center shadow-lg hover:shadow-orange-500/30 hover:scale-[1.02] active:scale-[0.97] transition-all duration-200 ease-out cursor-pointer flex items-center justify-center gap-2 animate-confirm-pop"
                >
                  <span x-show="! submitting">Confirm</span>
                  <span x-show="submitting" class="flex items-center gap-1.5">
                    <svg class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span>Reserving...</span>
                  </span>
                </button>
              </div>
            </template>
            <template x-if="$wire.selectedSlot !== slot.iso">
              <button
                type="button"
                @click="pickSlot(slot.iso)"
                class="w-full py-3.5 px-4 rounded-xl bg-white hover:bg-white/95 text-brand-purple font-bold text-base text-center shadow hover:shadow-md hover:scale-[1.015] active:scale-[0.985] transition-all duration-200 ease-out cursor-pointer block"
                x-text="slot.label"
              ></button>
            </template>
          </div>
        </template>

        <template x-if="times.length === 0">
          <p class="text-sm text-white/70 py-6 text-center animate-wizard-step">No times available for this date.</p>
        </template>
      </div>

      <template x-if="unreachable">
        <p class="mt-4 text-xs text-red-200 text-center">We could not reach the calendar. Check your connection and press Confirm again.</p>
      </template>
    </div>
  </template>
</div>
