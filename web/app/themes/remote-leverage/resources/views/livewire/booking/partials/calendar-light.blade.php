{{-- Steps 2 & 3 of the default and naked skins. Same component as calendar-glass.blade.php — see
     the note there for why Alpine owns this and the server only sends UTC slots. --}}
<div wire:key="light-calendar" wire:ignore x-data="rlBookingCalendar(@js($calendarConfig))">
  {{-- ────────────────────────────────────────────────────────── --}}
  {{-- STEP 2: Pick a Date (Calendar Grid)                        --}}
  {{-- ────────────────────────────────────────────────────────── --}}
  <template x-if="view === 'date'">
    <div class="animate-wizard-step {{ $skin === 'naked' ? 'p-0' : 'p-6 sm:p-8' }}">
      {{-- Header mirrors the glass skin's: a circular back button on the left, then the
           month chevrons and the month title as one group on the right. Light colours,
           same geometry — the two skins are the same calendar, not two designs. --}}
      <div class="flex items-center justify-between gap-4 mb-7">
        <button
          type="button"
          @click="$wire.goToStep(1)"
          class="w-12 h-12 rounded-full border border-slate-200 bg-white text-brand-purple flex items-center justify-center shadow-sm hover:border-brand-purple/40 hover:shadow-md hover:scale-110 active:scale-95 transition-all duration-200 ease-out cursor-pointer shrink-0"
          aria-label="Back to your details"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
          </svg>
        </button>

        <div class="flex items-center gap-4 sm:gap-5">
          <div class="flex items-center gap-1.5 text-slate-400">
            <button
              type="button"
              @click="prevMonth()"
              class="p-1.5 rounded-full hover:text-brand-purple hover:scale-125 active:scale-90 transition-all duration-200 cursor-pointer"
              aria-label="Previous Month"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/>
              </svg>
            </button>
            <button
              type="button"
              @click="nextMonth()"
              class="p-1.5 rounded-full hover:text-brand-purple hover:scale-125 active:scale-90 transition-all duration-200 cursor-pointer"
              aria-label="Next Month"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
              </svg>
            </button>
          </div>
          <h3 class="text-lg sm:text-xl font-bold text-brand-hero tracking-tight" x-text="title"></h3>
        </div>
      </div>

      {{-- Day Name Column Headers --}}
      <div class="grid grid-cols-7 text-center text-2xs sm:text-xs font-bold text-brand-hero uppercase tracking-wider mb-4">
        <span>SUN</span><span>MON</span><span>TUE</span><span>WED</span><span>THU</span><span>FRI</span><span>SAT</span>
      </div>

      {{-- Dates Grid. A bookable day is a filled circle, everything else is flat grey —
           the same read as the glass skin, where availability is the white pill. The
           peach is the light card's equivalent of that pill; selection stays a solid
           brand-purple fill so it can never be mistaken for "merely open". --}}
      <div x-ref="grid" class="grid grid-cols-7 gap-y-2 sm:gap-y-3 text-center items-center justify-items-center animate-grid-month">
        <template x-for="cell in grid" :key="cell.key">
          <div class="contents">
            <template x-if="cell.empty">
              <div class="w-11 h-11 sm:w-12 sm:h-12"></div>
            </template>
            <template x-if="! cell.empty">
              <button
                type="button"
                @click="pickDate(cell.date)"
                :disabled="! cell.hasAvailability"
                :aria-current="cell.isToday ? 'date' : null"
                class="relative w-11 h-11 sm:w-12 sm:h-12 rounded-full text-base sm:text-lg flex items-center justify-center transition-all duration-200 ease-out"
                :class="{
                  'bg-brand-purple text-white font-bold shadow-md shadow-brand-purple/30 scale-105': cell.isSelected,
                  'bg-[#FDF2E6] text-brand-purple font-bold cursor-pointer hover:bg-[#FAE4CE] hover:scale-110 active:scale-95': cell.hasAvailability && ! cell.isSelected,
                  'text-slate-300 font-normal cursor-not-allowed': ! cell.hasAvailability,
                }"
              >
                <span x-text="cell.day"></span>

                {{-- Today. Sits inside the circle so it never changes the grid's rhythm; it
                     is dropped on the selected day, where the solid fill already says where
                     you are and the dot would only muddy it. --}}
                <template x-if="cell.isToday && ! cell.isSelected">
                  <span class="absolute bottom-1.5 left-1/2 -translate-x-1/2 w-1.5 h-1.5 rounded-full"
                        :class="cell.hasAvailability ? 'bg-brand-orange-warm' : 'bg-slate-300'"
                        aria-hidden="true"></span>
                </template>
              </button>
            </template>
          </div>
        </template>
      </div>

      <p class="mt-4 text-center text-xs text-text-muted font-medium">Click any date to see times</p>

      {{-- Every day in the grid is disabled when the month has no availability, so an
           empty calendar and a fully-booked one are the same greyed-out pixels, and the
           form simply looks broken. That is how the 2026-09-17 t10 sell-out reached
           engineering instead of sales.

           Two things the copy has to get right, both learned from that incident:

           "Fully booked", not "no times" — this is a sold-out calendar, not a failure,
           and the wording is what routes it to the right team.

           Never suggest the next month. Calendly only offers a rolling four-day booking
           window on these event types, so the next month is *always* empty and that
           advice sends every visitor who takes it into a dead end. The soonest real date
           is what they need, and it is almost always days away, not weeks. --}}
      <template x-if="monthIsEmpty">
        <div class="mt-4 rounded-card bg-amber-50 border border-amber-200 px-4 py-3 text-center">
          <p class="text-xs text-amber-900 font-semibold"><span x-text="title"></span> is fully booked.</p>
          <template x-if="nextOpen">
            <div>
              <p class="text-xs text-amber-800 mt-1">
                Next opening is <span x-text="format(nextOpen, 'weekdayMonthDay')"></span>.
              </p>
              <button type="button" @click="jumpToNext()"
                class="mt-2 text-xs font-semibold text-amber-900 underline hover:text-amber-950">
                Go to <span x-text="format(nextOpen, 'monthDay')"></span>
              </button>
            </div>
          </template>
          <template x-if="! nextOpen">
            <p class="text-xs text-amber-800 mt-1">
              New times open continuously — check back shortly, or email
              <a href="mailto:contact@remoteleverage.com" class="underline hover:text-amber-950">contact@remoteleverage.com</a>
              and we will find you a slot.
            </p>
          </template>
        </div>
      </template>
    </div>
  </template>

  {{-- ────────────────────────────────────────────────────────── --}}
  {{-- STEP 3: Select a Time                                      --}}
  {{-- ────────────────────────────────────────────────────────── --}}
  <template x-if="view === 'time'">
    <div class="animate-wizard-step {{ $skin === 'naked' ? 'p-0 space-y-6' : 'p-6 sm:p-8 space-y-6' }}">
      <div class="flex items-center justify-between border-b border-slate-100 pb-4">
        <button
          type="button"
          @click="backToDates()"
          class="text-xs font-semibold text-text-muted hover:text-brand-hero inline-flex items-center gap-1.5 transition cursor-pointer"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
          <span>Change Date</span>
        </button>

        {{-- Timezone Dropdown --}}
        <div class="flex items-center gap-2">
          <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <circle cx="12" cy="12" r="10"/>
            <path d="M2 12h20M12 2a15.3 15.3 0 014 10 15.3 15.3 0 01-4 10 15.3 15.3 0 01-4-10 15.3 15.3 0 014-10z"/>
          </svg>
          <select
            @change="setZone($event.target.value)"
            aria-label="Time zone"
            class="text-2xs font-semibold text-slate-600 bg-transparent border-0 focus:ring-0 cursor-pointer"
          >
            <template x-for="choice in choices" :key="choice">
              <option :value="choice" :selected="choice === tz" x-text="label(choice)"></option>
            </template>
          </select>
        </div>
      </div>

      <div>
        <h3 class="text-sm font-bold text-brand-hero mb-1">
          Available Times for <span x-text="day('long')"></span>
        </h3>
        <p class="text-xs text-text-muted mb-4">Select the 30-minute consultation slot that works best for your schedule:</p>

        {{-- One block wrapper per slot, because space-y spaces direct children. --}}
        <div class="space-y-3 max-h-[380px] overflow-y-auto pr-1">
          <template x-for="slot in times" :key="slot.iso">
            <div>
              <template x-if="$wire.selectedSlot === slot.iso">
                <div class="grid grid-cols-2 gap-3 transition-all duration-200">
                  <div class="w-full py-3 px-4 rounded-card bg-brand-purple border border-brand-purple text-white text-xs sm:text-sm font-bold text-center shadow flex items-center justify-center transition-all duration-200" x-text="slot.label"></div>
                  <button
                    type="button"
                    @click="confirm()"
                    :disabled="submitting"
                    class="w-full py-3 px-4 rounded-card bg-[#F8248A] hover:bg-[#E91E63] text-white text-xs sm:text-sm font-bold text-center shadow-lg hover:shadow-pink-500/25 hover:scale-[1.02] active:scale-[0.97] transition-all duration-200 ease-out cursor-pointer flex items-center justify-center gap-2 animate-confirm-pop"
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
                  class="w-full py-3 px-4 rounded-card border border-slate-200 hover:border-brand-purple hover:bg-brand-purple/5 text-text-body font-semibold text-xs sm:text-sm transition-all duration-200 ease-out cursor-pointer block text-center"
                  x-text="slot.label"
                ></button>
              </template>
            </div>
          </template>

          <template x-if="times.length === 0">
            <p class="text-xs sm:text-sm text-text-muted py-6 text-center">
              No direct slots remaining on this day. Please click Change Date to select another day.
            </p>
          </template>
        </div>

        <template x-if="unreachable">
          <p class="mt-4 text-xs text-status-alert text-center">We could not reach the calendar. Check your connection and press Confirm again.</p>
        </template>
      </div>
    </div>
  </template>
</div>
