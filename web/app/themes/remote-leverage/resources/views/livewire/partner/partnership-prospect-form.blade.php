{{--
  The /become-a-partner/ form card, then the partnership calendar (or a thank-you) in its place.

  Geometry from the Figma layer tree at 1366px: a 548px white card, 8px radius, 30px padding on
  every side, so the fields are 488px wide. Each label + input block is 82px — a 20px label line,
  10px, a 52px input — on a 102px pitch, so 20px between blocks. The third question's label wraps
  to two lines in the comp (a 102px block), which the <br> reproduces from `sm` up; on a phone it
  wraps wherever the width puts it. The textarea is 153px and the submit sits 20px under it at
  488x50 — the site's 'small' CTA pill, from BlockDefaults::ctaPillClasses(), so it cannot drift
  from the pills elsewhere on the page.

  Inputs follow the light booking wizard's field treatment (grey fill, faint border, magenta
  focus ring), at the comp's 52px height and 6px radius rather than the wizard's 48px/12px.

  `novalidate`: every error is the server's and renders under its field. The browser's own
  required-field bubbles would pre-empt that with a differently styled message for the same rule.
--}}
@php
  $label = 'block text-[14px] leading-5 text-[#1A1A1A] mb-2.5';
  $control = 'block w-full h-[52px] rounded-md border bg-[#F5F6F8] px-4.5 text-[14px] text-[#1A1A1A] placeholder:text-[#9CA0A8] transition focus:outline-none focus:ring-2 focus:ring-brand-magenta/15 focus:border-brand-magenta';
  $error = 'mt-1.5 text-[13px] leading-snug text-status-alert';

  $fields = [
    ['firstName', 'First Name', 'First Name', 'text', 'given-name'],
    ['lastName', 'Last Name', 'Last Name', 'text', 'family-name'],
    ['email', 'Business Email', 'name@company.com', 'email', 'email'],
    ['company', 'Company', 'Company name', 'text', 'organization'],
    ['role', 'Your Role', 'Your role', 'text', 'organization-title'],
  ];

  $selects = [
    ['organizationType', 'organization_type', 'What type of organization are you?'],
    ['monthlyRevenue', 'monthly_revenue', 'What&rsquo;s your company&rsquo;s monthly revenue?'],
    ['businessesReached', 'businesses_reached', 'Approximately how many businesses<br class="hidden sm:inline"> do you work with or reach?'],
  ];
@endphp

{{-- Behaviour lives in resources/js/partnership-prospect-form.js, not inline: this renders inside
     post content, and wptexturize curls the quotes in any attribute holding a `=>` or `>`. --}}
<div x-data="rlPartnershipForm()" class="mx-auto w-full max-w-[548px] scroll-mt-24 rounded-lg bg-white p-5 sm:p-[30px]">
  @if (! $submitted)
    <form wire:submit="submit" novalidate>
      @if ($errorMessage)
        <div class="mb-5 rounded-md border border-status-alert/30 bg-status-alert/5 px-4 py-3 text-[13px] text-status-alert" role="alert">
          {{ $errorMessage }}
        </div>
      @endif

      <div class="space-y-5">
        @foreach ($fields as [$property, $text, $placeholder, $type, $autocomplete])
          <div>
            <label for="pp-{{ $property }}" class="{{ $label }}">{{ $text }} *</label>
            <input
              id="pp-{{ $property }}"
              type="{{ $type }}"
              wire:model="{{ $property }}"
              autocomplete="{{ $autocomplete }}"
              @if ($type === 'email') inputmode="email" @endif
              placeholder="{{ $placeholder }}"
              aria-required="true"
              @if ($errors->has($property)) aria-invalid="true" aria-describedby="pp-{{ $property }}-error" @endif
              class="{{ $control }} {{ $errors->has($property) ? 'border-status-alert' : 'border-[#E9EBEF]' }}"
            />
            @error($property)
              <p id="pp-{{ $property }}-error" class="{{ $error }}">{{ $message }}</p>
            @enderror
          </div>
        @endforeach

        @foreach ($selects as [$property, $column, $question])
          <div>
            <label for="pp-{{ $property }}" class="{{ $label }}">{!! $question !!} *</label>
            <div class="relative">
              {{-- `appearance-none` plus our own caret: the platform arrow differs per OS and
                   cannot be coloured or positioned to match the comp. --}}
              <select
                id="pp-{{ $property }}"
                wire:model="{{ $property }}"
                aria-required="true"
                @if ($errors->has($property)) aria-invalid="true" aria-describedby="pp-{{ $property }}-error" @endif
                class="{{ $control }} appearance-none cursor-pointer pr-11 {{ $errors->has($property) ? 'border-status-alert' : 'border-[#E9EBEF]' }}"
              >
                <option value="">Select one</option>
                @foreach ($options[$column] as $slug => $optionLabel)
                  <option value="{{ $slug }}">{{ $optionLabel }}</option>
                @endforeach
              </select>
              <svg class="pointer-events-none absolute right-5 top-1/2 h-[6px] w-[10px] -translate-y-1/2 text-[#1A1A1A]" viewBox="0 0 10 6" fill="currentColor" aria-hidden="true">
                <path d="M0 0h10L5 6z" />
              </svg>
            </div>
            @error($property)
              <p id="pp-{{ $property }}-error" class="{{ $error }}">{{ $message }}</p>
            @enderror
          </div>
        @endforeach

        <div>
          <label for="pp-message" class="sr-only">Where you see an opportunity to work together (optional)</label>
          <textarea
            id="pp-message"
            wire:model="message"
            maxlength="2000"
            placeholder="Tell us briefly where you see an opportunity to work together (optional)"
            @if ($errors->has('message')) aria-invalid="true" aria-describedby="pp-message-error" @endif
            class="{{ $control }} h-[153px] resize-none py-4 leading-5 {{ $errors->has('message') ? 'border-status-alert' : 'border-[#E9EBEF]' }}"
          ></textarea>
          @error('message')
            <p id="pp-message-error" class="{{ $error }}">{{ $message }}</p>
          @enderror
        </div>

        {{-- The label is long for a 16px nowrap pill, so below `sm` the pill trims its 40px
             sides and may wrap rather than overflow the card. --}}
        <button
          type="submit"
          wire:loading.attr="disabled"
          wire:target="submit"
          class="{{ \App\Support\BlockDefaults::ctaPillClasses('w-full cursor-pointer disabled:cursor-wait disabled:opacity-80 max-sm:px-5 max-sm:whitespace-normal max-sm:text-center max-sm:leading-tight', 'small') }}"
        >
          <span wire:loading.remove wire:target="submit">{{ $buttonText }}</span>
          <span wire:loading wire:target="submit">Sending&hellip;</span>
          <span wire:loading.remove wire:target="submit" class="inline-flex">
            @include('blocks.partials.cta-pill-icon', ['icon' => 'arrow', 'iconSize' => 18])
          </span>
        </button>
      </div>
    </form>
  @elseif ($calendarUrl)
    {{-- The partnership calendar. wire:ignore so a later round trip (recordBooking) cannot morph
         the iframe back to its first height or reload it mid-booking.

         Calendly's embedded page posts `calendly.page_height` as it grows and
         `calendly.event_scheduled` once the call is booked; both are only trusted from its own
         origin. The height starts where Calendly's stacked (narrow) layout needs it, so a page
         that never reports one still shows the whole calendar. --}}
    <div
      wire:key="partnership-calendar"
      wire:ignore
      x-data="rlPartnershipCalendar(@js(\App\Domains\PartnerHub\Support\PartnershipCallCalendar::ORIGIN))"
      x-init="reveal()"
    >
      <h3 class="font-display text-[22px] leading-tight font-bold text-[#1A1A1A]">Book your partnership call</h3>
      <p class="mt-2 mb-5 text-[14px] leading-5 text-[#4B4F57]">
        Thanks, {{ $firstName }}. Pick a time that suits you and we&rsquo;ll talk through where we can work together.
      </p>
      <iframe
        src="{{ $calendarUrl }}"
        title="Book a partnership call"
        class="block w-full border-0"
        style="height: 1050px"
        x-bind:style="{ height: height + 'px' }"
        allow="payment"
      ></iframe>
    </div>
  @else
    <div wire:key="partnership-thanks" x-init="reveal()" class="py-6 text-center" role="status">
      <svg class="mx-auto h-12 w-12 text-brand-magenta" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
        <circle cx="12" cy="12" r="10.4" stroke-width="1.5" />
        <path d="M7.8 12.3l2.8 2.8 5.6-5.8" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
      </svg>
      <h3 class="mt-4 font-display text-[22px] leading-tight font-bold text-[#1A1A1A]">Thanks, {{ $firstName }}</h3>
      <p class="mt-2 text-[15px] leading-6 text-[#4B4F57]">Our partnerships team will be in touch.</p>
    </div>
  @endif
</div>
