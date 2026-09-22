<div class="w-full" x-data="rlBookingStepScroll()"
     x-init="$wire.detectTimezone(Intl.DateTimeFormat().resolvedOptions().timeZone || '')">
  {{-- "Looking for VA work?" Shown when the partial lead reads as a possible VA — LeadAudience
       decides that, the same rule as the dashboard's badge and filter; config/booking.php holds
       only the copy. Checked before the pricing warning because it is a different conversation.
       The secondary action carries on to the calendar: the rule is a prompt rather than a
       verdict, and being told you look like an applicant when you came to hire is worse than a
       wasted call. --}}
  @if ($showApplicantNotice && ($applicant = $this->applicantNotice()))
    <div class="w-full max-w-[640px] mx-auto rounded-[28px] bg-brand-purple-deep/95 border border-white/10 p-7 sm:p-9 text-white shadow-2xl"
         wire:key="va-applicant-notice">
      <button type="button" wire:click="backFromApplicantNotice" aria-label="Go back"
              class="w-11 h-11 rounded-full bg-white/90 hover:bg-white text-brand-purple-deep flex items-center justify-center mb-6 transition cursor-pointer">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline>
        </svg>
      </button>

      <h2 class="text-2xl sm:text-[28px] font-bold leading-snug mb-5 whitespace-pre-line">{{ $applicant['heading'] ?? '' }}</h2>

      <div class="space-y-3 text-[15px] leading-relaxed text-white/90">
        @foreach (($applicant['body'] ?? []) as $block)
          @if (($block['type'] ?? '') === 'bullet')
            <div class="flex gap-3 pl-1">
              <span class="mt-2 w-1.5 h-1.5 rounded-full bg-white/70 shrink-0" aria-hidden="true"></span>
              <p>{{ $block['text'] ?? '' }}</p>
            </div>
          @else
            <p>
              @isset($block['lead'])<strong class="font-semibold text-white">{{ $block['lead'] }}</strong> @endisset
              {{ $block['text'] ?? '' }}
            </p>
          @endif
        @endforeach
      </div>

      {{-- Alpine rather than wire:click: a Livewire click handler on an anchor risks swallowing
           the navigation, and a jobs link that does not open is the one failure this panel
           cannot afford. $wire records the handoff without touching the default action. --}}
      <a href="{{ $applicant['jobs_url'] }}" target="_blank" rel="noopener"
         x-on:click="$wire.trackApplicantJobsClick()"
         class="mt-7 w-full py-4 px-6 rounded-full bg-[#8B5CF6] hover:bg-[#7C3AED] text-white font-bold text-base tracking-wide transition-all duration-200 flex items-center justify-center gap-2 shadow-md cursor-pointer">
        {{ $applicant['button'] ?? 'See VA openings' }}
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline>
        </svg>
      </a>

      <button type="button" wire:click="dismissApplicantNotice" wire:loading.attr="disabled"
              class="mt-4 w-full py-2 text-sm font-semibold text-white/80 hover:text-white underline underline-offset-4 transition cursor-pointer disabled:opacity-60">
        {{ $applicant['continue'] ?? "No — I'm here to hire a VA" }}
      </button>
    </div>

  {{-- Revenue-band pricing warning. Sits between step 1 and the calendar: the visitor has
       already been captured as a partial lead, so leaving here still produces a lead and a
       Slack alert. See config/booking.php for the copy and which bands trigger it. --}}
  @elseif ($showWarning && ($warning = $this->warningForBand()))
    <div class="w-full max-w-[640px] mx-auto rounded-[28px] bg-brand-purple-deep/95 border border-white/10 p-7 sm:p-9 text-white shadow-2xl"
         wire:key="pricing-warning">
      <button type="button" wire:click="dismissWarning" aria-label="Go back"
              class="w-11 h-11 rounded-full bg-white/90 hover:bg-white text-brand-purple-deep flex items-center justify-center mb-6 transition cursor-pointer">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline>
        </svg>
      </button>

      <h2 class="text-2xl sm:text-[28px] font-bold leading-snug mb-5 whitespace-pre-line">{{ $warning['warning_heading'] ?? '' }}</h2>

      <div class="space-y-3 text-[15px] leading-relaxed text-white/90">
        @foreach (($warning['warning_body'] ?? []) as $block)
          @if (($block['type'] ?? '') === 'bullet')
            <div class="flex gap-3 pl-1">
              <span class="mt-2 w-1.5 h-1.5 rounded-full bg-white/70 shrink-0" aria-hidden="true"></span>
              <p>{{ $block['text'] ?? '' }}</p>
            </div>
          @else
            <p>
              @isset($block['lead'])<strong class="font-semibold text-white">{{ $block['lead'] }}</strong> @endisset
              {{ $block['text'] ?? '' }}
            </p>
          @endif
        @endforeach
      </div>

      <button type="button" wire:click="acknowledgeWarning" wire:loading.attr="disabled"
              class="mt-7 w-full py-4 px-6 rounded-full bg-[#8B5CF6] hover:bg-[#7C3AED] text-white font-bold text-base tracking-wide transition-all duration-200 flex items-center justify-center gap-2 shadow-md cursor-pointer disabled:opacity-60">
        {{ $warning['warning_button'] ?? 'Continue' }}
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline>
        </svg>
      </button>
    </div>
  @elseif ($skin === 'glass')
    <div class="w-full max-w-[500px] mx-auto lg:ml-auto">
      <div class="w-full rounded-[28px] bg-white/[0.08] backdrop-blur-md border border-white/15 p-6 sm:p-8 md:p-9 shadow-2xl text-white">

        {{-- Hidden Acquisition & UTM Tracking Fields --}}
        <input type="hidden" name="utm_source" wire:model="utmSource" value="{{ $utmSource }}" id="rl_utm_source_glass">
        <input type="hidden" name="utm_medium" wire:model="utmMedium" value="{{ $utmMedium }}" id="rl_utm_medium_glass">
        <input type="hidden" name="utm_campaign" wire:model="utmCampaign" value="{{ $utmCampaign }}" id="rl_utm_campaign_glass">
        <input type="hidden" name="utm_term" wire:model="utmTerm" value="{{ $utmTerm }}" id="rl_utm_term_glass">
        <input type="hidden" name="utm_content" wire:model="utmContent" value="{{ $utmContent }}" id="rl_utm_content_glass">
        <input type="hidden" name="gclid" wire:model="gclid" value="{{ $gclid }}" id="rl_gclid_glass">
        <input type="hidden" name="fbclid" wire:model="fbclid" value="{{ $fbclid }}" id="rl_fbclid_glass">
        <input type="hidden" name="referral_code" wire:model="referralCode" value="{{ $referralCode }}" id="rl_referral_code_glass">
        <input type="hidden" name="landing_url" wire:model="landingUrl" value="{{ $landingUrl }}" id="rl_landing_url_glass">
        <input type="hidden" name="referrer_url" wire:model="referrerUrl" value="{{ $referrerUrl }}" id="rl_referrer_url_glass">
        <input type="hidden" name="session_id" wire:model="sessionId" value="{{ $sessionId }}" id="rl_session_id_glass">

        @if ($errorMessage)
          <div class="p-3.5 mb-5 rounded-xl bg-red-500/20 border border-red-400 text-red-200 text-xs flex items-center gap-2.5">
            <svg class="w-4 h-4 shrink-0 text-red-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span>{{ $errorMessage }}</span>
          </div>
        @endif

        @if ($currentStep === 1 && ! $isBooked)
          <div wire:key="glass-step-1" class="space-y-4 animate-wizard-step">
            {{-- 1. Monthly Revenue --}}
            <div>
              <label class="block text-xs sm:text-[13px] font-bold text-white mb-2.5">
                What is your company's current monthly revenue? <span class="text-[#EF4444]">*</span>
              </label>
              <div class="space-y-2">
                @foreach (\App\Domains\Lead\Services\LeadQualification::REVENUE_BANDS as $revOption)
                  <label class="flex items-center gap-2.5 cursor-pointer text-xs sm:text-[13px] text-white font-medium select-none group">
                    <input 
                      type="radio" 
                      name="monthlyRevenue" 
                      value="{{ $revOption }}" 
                      wire:model.live="monthlyRevenue"
                      class="w-4 h-4 rounded-full border-0 bg-white text-brand-purple focus:ring-0 focus:ring-offset-0 cursor-pointer transition-transform duration-150 group-hover:scale-110"
                    />
                    <span class="group-hover:text-white/90 transition-colors duration-150">{{ $revOption }}</span>
                  </label>
                @endforeach
              </div>
              @error('monthlyRevenue') <span class="text-red-400 text-xs block mt-1">{{ $message }}</span> @enderror
            </div>

            {{-- 2. Name: First & Last --}}
            <div>
              <span class="block text-xs sm:text-[13px] font-bold text-white mb-1">
                Name <span class="text-[#EF4444]">*</span>
              </span>
              <div class="grid grid-cols-2 gap-3">
                <div>
                  <input 
                    type="text" 
                    id="booking-first-name"
                    name="first_name"
                    autocomplete="given-name"
                    aria-label="First Name"
                    aria-required="true"
                    wire:model="firstName"
                    class="w-full h-10 sm:h-11 px-3.5 rounded-lg bg-[#F0F3FA] text-slate-900 text-sm focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand-purple transition-all duration-200"
                  />
                  <label for="booking-first-name" class="block text-[11px] text-white/60 mt-1 cursor-pointer">First</label>
                  @error('firstName') <span class="text-red-400 text-xs block mt-0.5">{{ $message }}</span> @enderror
                </div>
                <div>
                  <input 
                    type="text" 
                    id="booking-last-name"
                    name="last_name"
                    autocomplete="family-name"
                    aria-label="Last Name"
                    aria-required="true"
                    wire:model="lastName"
                    class="w-full h-10 sm:h-11 px-3.5 rounded-lg bg-[#F0F3FA] text-slate-900 text-sm focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand-purple transition-all duration-200"
                  />
                  <label for="booking-last-name" class="block text-[11px] text-white/60 mt-1 cursor-pointer">Last</label>
                  @error('lastName') <span class="text-red-400 text-xs block mt-0.5">{{ $message }}</span> @enderror
                </div>
              </div>
            </div>

            {{-- 3. Business Email --}}
            <div>
              <label for="booking-email" class="block text-xs sm:text-[13px] font-bold text-white mb-1 cursor-pointer">
                Business Email <span class="text-[#EF4444]">*</span>
              </label>
              <input 
                type="email" 
                id="booking-email"
                name="email"
                autocomplete="email"
                aria-label="Business Email"
                aria-required="true"
                wire:model="email"
                class="w-full h-10 sm:h-11 px-3.5 rounded-lg bg-[#F0F3FA] text-slate-900 text-sm focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand-purple transition-all duration-200"
              />
              @error('email') <span class="text-red-400 text-xs block mt-1">{{ $message }}</span> @enderror
            </div>

            {{-- 4. Phone --}}
            <div>
              <label for="booking-phone-input" class="block text-xs sm:text-[13px] font-bold text-white mb-1 cursor-pointer">
                Phone <span class="text-[#EF4444]">*</span>
              </label>
              <div 
                wire:ignore 
                x-data="phoneInputComponent({ initialCountry: '{{ strtolower($phoneCountry ?: 'us') }}' })"
                class="relative w-full"
              >
                <input 
                  x-ref="phoneInput"
                  type="tel"
                  id="booking-phone-input"
                  name="phone"
                  autocomplete="tel"
                  aria-label="Phone Number"
                  aria-required="true"
                  value="{{ $phone }}"
                  placeholder="(201) 555-0123"
                  class="w-full h-10 sm:h-11 rounded-lg bg-white text-slate-900 text-sm focus:outline-none focus:ring-2 focus:ring-brand-purple transition-all duration-200"
                />
              </div>
              @error('phone') <span class="text-red-400 text-xs block mt-1">{{ $message }}</span> @enderror
            </div>

            {{-- 5. Consent Checkbox --}}
            <div class="pt-1">
              <label for="booking-consent-checkbox" class="flex items-start gap-2.5 cursor-pointer text-[10.5px] sm:text-[11px] leading-[1.4] text-white/80 select-none">
                <input 
                  type="checkbox" 
                  id="booking-consent-checkbox"
                  name="consent"
                  aria-label="Consent to receive SMS appointment reminders"
                  wire:model="consent"
                  class="mt-0.5 w-4 h-4 rounded bg-[#F0F3FA] text-brand-purple border-0 focus:ring-0 focus:ring-offset-0 shrink-0 cursor-pointer"
                />
                <span>
                  I agree to receive SMS appointment reminders from Remote Leverage about the consultation I'm booking, and to be contacted by phone and email. Messaging frequency varies. Standard message and data rates may apply. Reply STOP to unsubscribe, HELP for help, or call (650) 668-0728 / email paula@remoteleverage.com. By consenting I acknowledge I have read and agree to Remote Leverage’s <a href="/terms-of-use" class="underline hover:text-white transition-colors duration-150">Terms &amp; Conditions</a> and <a href="/privacy-policy" class="underline hover:text-white transition-colors duration-150">Privacy Policy</a>. I can withdraw consent at any time.
                </span>
              </label>
            </div>

            {{-- 6. Submit Button --}}
            <div class="pt-2">
              <button 
                type="button" 
                wire:click="goToStep(2)"
                wire:loading.attr="disabled"
                class="w-full py-3.5 px-6 rounded-full bg-[#E0E5EC] hover:bg-white text-[#4A5568] hover:text-black font-bold text-sm tracking-wide flex items-center justify-center gap-2 transition-all duration-200 shadow-md hover:shadow-xl hover:scale-[1.01] active:scale-[0.98] cursor-pointer disabled:opacity-50"
              >
                <span wire:loading.remove>Book a Call</span>
                <span wire:loading>Processing...</span>
                <svg wire:loading.remove class="w-4 h-4 transition-transform duration-200 group-hover:translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                </svg>
              </button>
            </div>
          </div>
        @elseif ($isBooked)
          {{-- Booking Confirmation --}}
          <div wire:key="glass-step-booked" class="p-4 sm:p-6 text-center space-y-4 animate-wizard-step">
            <div class="w-14 h-14 rounded-full bg-status-success/20 text-status-success flex items-center justify-center mx-auto transition-transform duration-300 hover:scale-110">
              <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
            </div>
            <h3 class="text-xl font-bold font-display text-white">Call Confirmed!</h3>
            <p class="text-xs sm:text-sm text-white/80">We've reserved your strategy session. Check your email for calendar invite details.</p>
            <div class="text-2xs text-white/60">Reference: <code class="font-mono text-white">{{ $bookingReference }}</code></div>
          </div>
        @else
          {{-- Steps 2 & 3 in Glass Skin --}}
          @if ($currentStep === 2)
            {{-- Step 2: Date Picker Calendar --}}
            <div wire:key="glass-step-2" class="animate-wizard-step">
              {{-- Top Bar: Circular Back Button (Left) & Nav/Month Title (Right) --}}
              <div class="flex items-center justify-between mb-8">
                <button 
                  type="button" 
                  wire:click="goToStep(1)"
                  class="w-12 h-12 rounded-full bg-white text-brand-purple flex items-center justify-center shadow-lg hover:scale-110 active:scale-95 transition-all duration-200 ease-out cursor-pointer shrink-0"
                  aria-label="Back to Step 1"
                >
                  <svg class="w-5 h-5 text-brand-purple" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                  </svg>
                </button>

                <div class="flex items-center gap-5">
                  <div class="flex items-center gap-3 text-white/70">
                    <button type="button" wire:click="prevMonth" class="p-1.5 hover:text-white hover:scale-125 active:scale-90 transition-all duration-200 cursor-pointer text-base" aria-label="Previous Month">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/>
                      </svg>
                    </button>
                    <button type="button" wire:click="nextMonth" class="p-1.5 hover:text-white hover:scale-125 active:scale-90 transition-all duration-200 cursor-pointer text-base" aria-label="Next Month">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
                      </svg>
                    </button>
                  </div>
                  <h3 class="text-xl font-bold text-white tracking-tight transition-all duration-200">{{ $monthTitle }}</h3>
                </div>
              </div>

              {{-- Day Name Column Headers --}}
              <div class="grid grid-cols-7 text-center text-xs sm:text-sm font-semibold text-white/80 uppercase tracking-wider mb-5">
                <span>SUN</span><span>MON</span><span>TUE</span><span>WED</span><span>THU</span><span>FRI</span><span>SAT</span>
              </div>

              {{-- Dates Grid --}}
              <div wire:key="glass-grid-{{ $currentYear }}-{{ $currentMonth }}" class="grid grid-cols-7 gap-y-3 sm:gap-y-4 text-center items-center justify-items-center animate-grid-month">
                @foreach ($daysGrid as $cell)
                  @if ($cell['empty'])
                    <div class="w-11 h-11 sm:w-12 sm:h-12"></div>
                  @else
                    @if ($cell['hasAvailability'])
                      <button
                        type="button"
                        wire:click="selectDate('{{ $cell['date'] }}')"
                        wire:target="selectDate('{{ $cell['date'] }}')"
                        wire:loading.attr="disabled"
                        wire:loading.class="opacity-60 cursor-wait"
                        class="w-11 h-11 sm:w-12 sm:h-12 rounded-full bg-white text-brand-purple font-bold text-base sm:text-lg flex items-center justify-center shadow-lg hover:scale-115 active:scale-95 transition-all duration-200 ease-out cursor-pointer hover:shadow-2xl"
                      >
                        <span wire:loading.remove wire:target="selectDate('{{ $cell['date'] }}')">{{ $cell['day'] }}</span>
                        <svg wire:loading wire:target="selectDate('{{ $cell['date'] }}')" class="animate-spin h-4 w-4 text-brand-purple" fill="none" viewBox="0 0 24 24">
                          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                      </button>
                    @else
                      <div class="w-11 h-11 sm:w-12 sm:h-12 flex items-center justify-center text-white/60 text-base sm:text-lg font-normal select-none transition-opacity duration-200">
                        <span>{{ $cell['day'] }}</span>
                      </div>
                    @endif
                  @endif
                @endforeach
              </div>

              {{-- See the note on the light skin's copy of this. --}}
              @if (empty($availableDates))
                <div class="mt-4 rounded-xl bg-white/10 border border-white/20 px-4 py-3 text-center">
                  <p class="text-xs font-semibold text-white">{{ $monthTitle }} is fully booked.</p>
                  @if ($nextAvailableDate)
                    <p class="text-xs text-white/80 mt-1">
                      Next opening is
                      {{ \Carbon\Carbon::parse($nextAvailableDate, $timezone)->format('l, F j') }}.
                    </p>
                    <button type="button" wire:click="jumpToNextAvailable"
                      class="mt-2 text-xs font-semibold text-white underline hover:text-white/80">
                      Go to {{ \Carbon\Carbon::parse($nextAvailableDate, $timezone)->format('F j') }}
                    </button>
                  @else
                    <p class="text-xs text-white/80 mt-1">
                      New times open continuously — check back shortly, or email
                      <a href="mailto:contact@remoteleverage.com" class="underline hover:text-white">contact@remoteleverage.com</a>
                      and we will find you a slot.
                    </p>
                  @endif
                </div>
              @endif
            </div>

          @elseif ($currentStep === 3)
            {{-- Step 3: Time Slot Selection --}}
            <div wire:key="glass-step-3" class="animate-wizard-step">
              {{-- Top Bar: Circular Back Button --}}
              <div class="mb-2">
                <button 
                  type="button" 
                  wire:click="goToStep(2)"
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
                <h3 class="text-xl font-bold text-white tracking-tight">{{ \Carbon\Carbon::parse($selectedDate)->format('l') }}</h3>
                <p class="text-sm font-medium text-white/90 mt-0.5">{{ \Carbon\Carbon::parse($selectedDate)->format('F j, Y') }}</p>
                
                <div class="mt-3">
                  <span class="text-xs text-white/70 block">Time zone</span>
                  <div class="relative inline-block mt-0.5 group cursor-pointer">
                    <select wire:model.live="timezone" class="opacity-0 absolute inset-0 w-full h-full cursor-pointer z-10">
                      @foreach ($this->timezoneChoices() as $tzChoice)
                        <option value="{{ $tzChoice }}">{{ str_replace(['_', '/'], [' ', ', '], $tzChoice) }} ({{ \Carbon\Carbon::now($tzChoice)->format('H:i') }})</option>
                      @endforeach
                    </select>
                    <div class="inline-flex items-center gap-1.5 text-sm font-semibold text-white group-hover:text-white/90 transition-colors duration-150 pointer-events-none">
                      <svg class="w-4 h-4 text-white/80 transition-transform duration-200 group-hover:rotate-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="10" stroke-width="2"/>
                        <path stroke-width="2" d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
                      </svg>
                      <span class="underline decoration-white/40 underline-offset-4">{{ str_replace('_', ' ', $timezone) }} ({{ \Carbon\Carbon::now($timezone)->format('H:i') }})</span>
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

              {{-- Slot Options Stack --}}
              <div class="space-y-3 max-h-[380px] overflow-y-auto pr-1">
                @forelse ($availableSlots as $slot)
                  @if ($selectedSlot === $slot['iso'])
                    <div wire:key="slot-selected-{{ $slot['iso'] }}" class="grid grid-cols-2 gap-3 transition-all duration-200">
                      <div class="w-full py-3.5 px-4 rounded-xl bg-brand-purple border border-white/30 text-white font-bold text-base text-center shadow flex items-center justify-center transition-all duration-200">
                        {{ $slot['time'] }}
                      </div>
                      <button
                        type="button"
                        wire:click="submitBooking"
                        wire:target="submitBooking"
                        wire:loading.attr="disabled"
                        class="w-full py-3.5 px-4 rounded-xl bg-[#F97316] hover:bg-[#EA580C] text-white font-bold text-base text-center shadow-lg hover:shadow-orange-500/30 hover:scale-[1.02] active:scale-[0.97] transition-all duration-200 ease-out cursor-pointer flex items-center justify-center gap-2 animate-confirm-pop"
                      >
                        <span wire:loading.remove wire:target="submitBooking">Confirm</span>
                        <span wire:loading.flex wire:target="submitBooking" class="flex items-center gap-1.5">
                          <svg class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                          </svg>
                          <span>Reserving...</span>
                        </span>
                      </button>
                    </div>
                  @else
                    <button
                      type="button"
                      wire:key="slot-opt-{{ $slot['iso'] }}"
                      wire:click="selectSlot('{{ $slot['iso'] }}')"
                      class="w-full py-3.5 px-4 rounded-xl bg-white hover:bg-white/95 text-brand-purple font-bold text-base text-center shadow hover:shadow-md hover:scale-[1.015] active:scale-[0.985] transition-all duration-200 ease-out cursor-pointer block"
                    >
                      {{ $slot['time'] }}
                    </button>
                  @endif
                @empty
                  <p class="text-sm text-white/70 py-6 text-center animate-wizard-step">No times available for this date.</p>
                @endforelse
              </div>
            </div>
          @endif
        @endif

      </div>
    </div>
  @else
    {{-- Default / Naked Skin for /book-consultation and Hero Blocks --}}
    <div class="w-full {{ $skin === 'naked' ? '' : 'max-w-[580px] mx-auto' }}">
      <div class="w-full {{ $skin === 'naked' ? 'bg-transparent' : 'bg-surface-white rounded-card-lg border border-slate-200/80 shadow-card overflow-hidden' }}">
    
    {{-- Hidden Acquisition & UTM Tracking Fields (matches rl-testing forms & handl-utm-grabber) --}}
    <input type="hidden" name="utm_source" wire:model="utmSource" value="{{ $utmSource }}" id="rl_utm_source">
    <input type="hidden" name="utm_medium" wire:model="utmMedium" value="{{ $utmMedium }}" id="rl_utm_medium">
    <input type="hidden" name="utm_campaign" wire:model="utmCampaign" value="{{ $utmCampaign }}" id="rl_utm_campaign">
    <input type="hidden" name="utm_term" wire:model="utmTerm" value="{{ $utmTerm }}" id="rl_utm_term">
    <input type="hidden" name="utm_content" wire:model="utmContent" value="{{ $utmContent }}" id="rl_utm_content">
    <input type="hidden" name="gclid" wire:model="gclid" value="{{ $gclid }}" id="rl_gclid">
    <input type="hidden" name="fbclid" wire:model="fbclid" value="{{ $fbclid }}" id="rl_fbclid">
    <input type="hidden" name="referral_code" wire:model="referralCode" value="{{ $referralCode }}" id="rl_referral_code">
    <input type="hidden" name="landing_url" wire:model="landingUrl" value="{{ $landingUrl }}" id="rl_landing_url">
    <input type="hidden" name="referrer_url" wire:model="referrerUrl" value="{{ $referrerUrl }}" id="rl_referrer_url">
    <input type="hidden" name="session_id" wire:model="sessionId" value="{{ $sessionId }}" id="rl_session_id">

    @if (! $hideProfileHeader)
    {{-- 1. Profile Header --}}
    <div class="flex items-center gap-4 p-6 sm:p-7 border-b border-slate-100 bg-white">
      <img src="{{ !empty($profileImage) && $profileImage !== '/images/avatar1.jpg' ? $profileImage : Vite::asset('resources/images/avatar1.jpg') }}" alt="Host Profile" width="52" height="52" loading="lazy" decoding="async" class="w-13 h-13 rounded-full object-cover shrink-0 ring-2 ring-brand-purple/10" />
      <div>
        @if ($profileEyebrow)
          <div class="text-xs font-bold text-slate-600 uppercase tracking-wider mb-0.5">{{ $profileEyebrow }}</div>
        @endif
        <div class="text-lg font-bold font-display text-brand-hero tracking-tight">{{ $profileName }}</div>
        <div class="text-xs text-slate-600 flex items-center gap-1.5 mt-0.5 font-medium">
          <svg class="w-3.5 h-3.5 text-brand-purple" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"></circle>
            <polyline points="12 6 12 12 16 14"></polyline>
          </svg>
          <span>{{ $profileDuration }}</span>
        </div>
      </div>
    </div>
    @endif

    @if (! $isBooked)
      @if (! $hideProgressBar)
      {{-- 2. Progress Bar with Connected Dots --}}
      <div class="flex items-center justify-between px-6 sm:px-8 py-4 border-b border-slate-100 bg-slate-50/50">
        @for ($i = 1; $i <= $totalSteps; $i++)
          <div 
            class="flex items-center gap-2 cursor-pointer group"
            wire:click="goToStep({{ $i }})"
          >
            <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold transition-all duration-200
              {{ $currentStep === $i ? 'bg-brand-purple text-white ring-4 ring-brand-purple/20' : ($currentStep > $i ? 'bg-status-success text-white' : 'bg-slate-200 text-slate-500') }}
            ">
              @if ($currentStep > $i)
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"></polyline></svg>
              @else
                {{ $i }}
              @endif
            </div>
            <span class="text-xs font-semibold hidden sm:inline {{ $currentStep === $i ? 'text-brand-hero font-bold' : ($currentStep > $i ? 'text-text-body' : 'text-slate-400') }}">
              {{ $stepTitles[$i] }}
            </span>
          </div>
          @if ($i < $totalSteps)
            <div class="flex-1 h-0.5 mx-2 {{ $currentStep > $i ? 'bg-status-success' : 'bg-slate-200' }}"></div>
          @endif
        @endfor
      </div>
      @endif

      {{-- Error Notification Banner --}}
      @if ($errorMessage)
        <div class="p-4 mx-6 mt-4 rounded-card bg-red-50 border border-red-200 text-status-alert text-xs flex items-center gap-3">
          <svg class="w-5 h-5 shrink-0 text-status-alert" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <span>{{ $errorMessage }}</span>
        </div>
      @endif

      {{-- ────────────────────────────────────────────────────────── --}}
      {{-- STEP 1: Your Details (with Progressive Isolated Fields)    --}}
      {{-- ────────────────────────────────────────────────────────── --}}
      @if ($currentStep === 1)
        <div 
          wire:key="light-step-1"
          class="animate-wizard-step {{ $skin === 'naked' ? 'p-0 space-y-4' : 'p-6 sm:p-8 space-y-4' }}"
          {{-- Only the two values that are fixed at mount. Field values must NOT be seeded
               here: Livewire re-renders this attribute on every round trip, and Alpine treats
               a changed `x-data` as a new component — it tears the scope down and rebuilds it
               from whatever the server last knew. Anything typed since the request went out is
               then overwritten in the DOM by the stale copy. That is exactly how picking a
               revenue band (the one `.live` field, and a slow one because it calls Calendly)
               wiped the name the visitor was typing at the time.

               `isolated` and `steps` are settled in mount() and never change, so this string is
               byte-identical on every render and the morph leaves it alone. Keep it that way:
               adding a live field here reintroduces the bug. --}}
          x-data="rlBookingWizardIsolated(@js(['isolated' => (bool) $enableIsolatedFields, 'steps' => $isolatedSteps]))"
        >
          @if ($skin !== 'naked')
            <div class="space-y-1 mb-6">
              <h3 class="text-xl font-bold font-display text-brand-hero tracking-tight">{{ $cardTitle }}</h3>
              @if ($cardSubtitle !== '')
                <p class="text-xs text-slate-600">{{ $cardSubtitle }}</p>
              @endif
            </div>
          @endif

          @php
            $renderedSteps = !empty($isolatedSteps) ? $isolatedSteps : [
              ['step_label' => 'Email', 'step_fields' => ['email']],
              ['step_label' => 'Complete First Step', 'step_fields' => ['name', 'phone', 'monthly_revenue', 'consent']],
            ];

            // Revenue leads the form on the page-bottom booking blocks and the hire-va hero
            // (direction 2026-09-15). Lift it out of whichever sub-step declared it and put it
            // at the head of the first one, so it is genuinely before every other field rather
            // than merely first within its own group.
            if ($revenueFirst) {
              foreach ($renderedSteps as $i => $s) {
                $renderedSteps[$i]['step_fields'] = array_values(array_diff($s['step_fields'] ?? [], ['monthly_revenue']));
              }
              $renderedSteps[0]['step_fields'] = array_merge(['monthly_revenue'], $renderedSteps[0]['step_fields'] ?? []);
            }
          @endphp

          @foreach ($renderedSteps as $stepIdx => $subStep)
            <div 
              wire:key="isolated-substep-{{ $stepIdx }}"
              data-substep="{{ $stepIdx }}"
              x-show="canShowStep({{ $stepIdx }})"
              x-transition:enter="transition ease-out duration-400 transform"
              x-transition:enter-start="opacity-0 translate-y-3"
              x-transition:enter-end="opacity-100 translate-y-0"
              class="space-y-4 {{ $stepIdx > 0 ? 'pt-2' : '' }}"
              :class="{ 'rl-isolated-step-revealed': isStepRevealed({{ $stepIdx }}) }"
              style="{{ $enableIsolatedFields && $stepIdx > 0 ? 'display: none;' : '' }}"
            >
              @foreach ($subStep['step_fields'] as $fieldKey)
                @if ($fieldKey === 'email')
                  {{-- 1. Work Email --}}
                  <div class="space-y-1.5">
                    <label for="default-email" class="block text-[13.5px] font-medium text-slate-900 cursor-pointer">
                      Email: <span class="text-slate-900">*</span>
                    </label>
                    <input 
                      type="email" 
                      id="default-email"
                      name="email"
                      autocomplete="email"
                      aria-label="Email"
                      aria-required="true"
                      wire:model="email"
                      @input="onFieldInput('email', {{ $stepIdx }})"
                      @blur="advanceIfValid({{ $stepIdx }})"
                      @keydown.enter.prevent="advanceIfValid({{ $stepIdx }})"
                      placeholder="name@company.com"
                      class="w-full px-4 py-2.5 h-[48px] rounded-xl border border-slate-200 focus:ring-2 focus:ring-[#F8248A]/15 focus:border-[#F8248A] text-sm text-slate-900 bg-[#F8F9FA] transition placeholder-slate-400"
                    />
                    @error('email') <span class="text-status-alert text-xs block mt-1">{{ $message }}</span> @enderror
                  </div>

                @elseif ($fieldKey === 'monthly_revenue')
                  {{-- 2. Monthly Company Revenue (MRR) - Horizontal Interactive Pills --}}
                  <div class="space-y-2 pt-0.5">
                    <label class="block text-[13.5px] font-medium text-slate-900">
                      What's your company's monthly revenue?
                    </label>
                    @php
                      $revenueOptions = [
                        '$0 to $5k Per Month' => '$0k to $5k',
                        '$5k to $10k Per Month' => '$5k to $10k',
                        '$10k to $50k Per Month' => '$10k to $50k',
                        '$50k-$100k Per Month' => '$50k-$100k',
                        '$100k+ Per Month' => '$100k+',
                      ];
                    @endphp

                    @if ($revenueFirst)
                      {{-- Vertical radio list, matching the glass skin's treatment. --}}
                      <div class="space-y-2 pt-0.5">
                        @foreach ($revenueOptions as $val => $label)
                          <label class="group flex cursor-pointer select-none items-center gap-2.5 text-[13.5px] font-medium text-slate-900">
                            <input
                              type="radio"
                              name="monthlyRevenue"
                              value="{{ $val }}"
                              wire:model.live="monthlyRevenue"
                              @change="onFieldInput('monthly_revenue', {{ $stepIdx }})"
                              class="h-4 w-4 cursor-pointer border-slate-300 text-[#F8248A] transition-transform duration-150 focus:ring-2 focus:ring-[#F8248A]/20 group-hover:scale-110"
                            />
                            <span class="transition-colors duration-150 group-hover:text-slate-700">{{ $val }}</span>
                          </label>
                        @endforeach
                      </div>
                    @elseif ($compactFields)
                      <select
                        id="default-monthly-revenue"
                        name="monthlyRevenue"
                        aria-label="Monthly company revenue"
                        wire:model.live="monthlyRevenue"
                        @change="onFieldInput('monthly_revenue', {{ $stepIdx }})"
                        class="w-full px-4 py-2.5 h-[48px] rounded-xl border border-slate-200 focus:ring-2 focus:ring-[#F8248A]/15 focus:border-[#F8248A] text-sm text-slate-900 bg-[#F8F9FA] transition"
                      >
                        <option value="">Company Revenue per Month</option>
                        @foreach ($revenueOptions as $val => $label)
                          <option value="{{ $val }}">{{ $label }}</option>
                        @endforeach
                      </select>
                    @else
                    <div class="flex flex-wrap gap-2.5 pt-0.5">
                      @foreach ([
                        '$0 to $5k Per Month' => '$0k to $5k',
                        '$5k to $10k Per Month' => '$5k to $10k',
                        '$10k to $50k Per Month' => '$10k to $50k',
                        '$50k-$100k Per Month' => '$50k-$100k',
                        '$100k+ Per Month' => '$100k+',
                      ] as $val => $label)
                        <label 
                          class="cursor-pointer inline-flex items-center justify-center px-4 py-2 rounded-full border text-xs sm:text-sm font-medium transition-all duration-150 select-none"
                          :class="$wire.monthlyRevenue === '{{ $val }}' 
                            ? 'border-[#F8248A] text-[#F8248A] bg-white ring-1 ring-[#F8248A]/40 shadow-xs' 
                            : 'border border-slate-200 bg-[#F8F9FA] text-slate-800 hover:border-slate-300 hover:bg-slate-100/80'"
                        >
                          <input 
                            type="radio" 
                            name="monthlyRevenue" 
                            value="{{ $val }}" 
                            wire:model.live="monthlyRevenue"
                            @change="onFieldInput('monthly_revenue', {{ $stepIdx }})"
                            class="sr-only"
                          />
                          <span>{{ $label }}</span>
                        </label>
                      @endforeach
                    </div>
                    @endif
                    @error('monthlyRevenue') <span class="text-status-alert text-xs block mt-1">{{ $message }}</span> @enderror
                  </div>

                @elseif ($fieldKey === 'name')
                  {{-- 3. Name — ALWAYS side by side, on every instance of this form (direction
                       2026-09-15). It was previously stacked unless $compactFields was set; the
                       glass branch above has always paired them, and the two must not disagree. --}}
                  <div class="grid grid-cols-2 gap-3">
                    <div class="space-y-1.5">
                      <label for="default-first-name" class="block text-[13.5px] font-medium text-slate-900 cursor-pointer">
                        First Name: <span class="text-slate-900">*</span>
                      </label>
                      <input 
                        type="text" 
                        id="default-first-name"
                        name="first_name"
                        autocomplete="given-name"
                        aria-label="First Name"
                        aria-required="true"
                        wire:model="firstName"
                        @input="onFieldInput('name', {{ $stepIdx }})"
                        @blur="onFieldInput('name', {{ $stepIdx }})"
                        placeholder="First Name"
                        class="w-full px-4 py-2.5 h-[48px] rounded-xl border border-slate-200 focus:ring-2 focus:ring-[#F8248A]/15 focus:border-[#F8248A] text-sm text-slate-900 bg-[#F8F9FA] transition placeholder-slate-400"
                      />
                      @error('firstName') <span class="text-status-alert text-xs block mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div class="space-y-1.5">
                      <label for="default-last-name" class="block text-[13.5px] font-medium text-slate-900 cursor-pointer">
                        Last Name: <span class="text-slate-900">*</span>
                      </label>
                      <input 
                        type="text" 
                        id="default-last-name"
                        name="last_name"
                        autocomplete="family-name"
                        aria-label="Last Name"
                        aria-required="true"
                        wire:model="lastName"
                        @input="onFieldInput('name', {{ $stepIdx }})"
                        @blur="onFieldInput('name', {{ $stepIdx }})"
                        placeholder="Last Name"
                        class="w-full px-4 py-2.5 h-[48px] rounded-xl border border-slate-200 focus:ring-2 focus:ring-[#F8248A]/15 focus:border-[#F8248A] text-sm text-slate-900 bg-[#F8F9FA] transition placeholder-slate-400"
                      />
                      @error('lastName') <span class="text-status-alert text-xs block mt-1">{{ $message }}</span> @enderror
                    </div>
                  </div>

                @elseif ($fieldKey === 'phone')
                  {{-- 4. Phone Number with Country Selector --}}
                  <div class="space-y-1.5">
                    <label for="default-phone-input" class="block text-[13.5px] font-medium text-slate-900 cursor-pointer">
                      Phone: <span class="text-slate-900">*</span>
                    </label>
                    <div 
                      wire:ignore 
                      x-data="phoneInputComponent({ initialCountry: '{{ strtolower($phoneCountry ?: 'us') }}' })"
                      class="relative w-full"
                    >
                      <input 
                        x-ref="phoneInput"
                        type="tel" 
                        id="default-phone-input"
                        name="phone"
                        value="{{ $phone }}"
                        x-model="phoneVal"
                        placeholder="(201) 555-0123"
                        class="w-full px-4 py-2.5 h-[48px] rounded-xl border border-slate-200 text-sm text-slate-900 bg-[#F8F9FA] focus:ring-2 focus:ring-[#F8248A]/15 focus:border-[#F8248A] focus:outline-none transition placeholder-slate-400"
                      />
                    </div>
                    @error('phone') <span class="text-status-alert text-xs block mt-1">{{ $message }}</span> @enderror
                  </div>

                @elseif ($fieldKey === 'consent')
                  {{-- 5. Terms & SMS Consent Checkbox --}}
                  <div class="pt-1">
                    <label for="default-consent-checkbox" class="flex items-start gap-2.5 cursor-pointer text-[11px] sm:text-[11.5px] leading-[1.45] text-slate-500 select-none">
                      <input 
                        type="checkbox" 
                        id="default-consent-checkbox"
                        name="consent"
                        aria-label="Consent to receive SMS appointment reminders"
                        wire:model="consent"
                        class="mt-0.5 w-4 h-4 rounded bg-[#E5E7EB] text-[#F8248A] border-slate-300 focus:ring-0 focus:ring-offset-0 shrink-0 cursor-pointer"
                      />
                      <span>
                        I consent to Remote Leverage contacting me by phone, SMS, and email. Messaging frequency varies. Standard message and data rates may apply. To unsubscribe, reply STOP anytime. For help, call (650) 668-0728 or email paula@remoteleverage.com. By consenting I acknowledge I have read and agree to Remote Leverage’s <a href="/terms-of-use" class="underline hover:text-[#F8248A] transition-colors">Terms &amp; Conditions</a> and <a href="/privacy-policy" class="underline hover:text-[#F8248A] transition-colors">Privacy Policy</a>. I can withdraw consent at any time.
                      </span>
                    </label>
                  </div>
                @endif
              @endforeach

              @if ($stepIdx < count($renderedSteps) - 1)
                <div 
                  x-show="canShowContinue({{ $stepIdx }})" 
                  class="pt-3"
                  style="{{ $enableIsolatedFields && $stepIdx === 0 ? '' : 'display: none;' }}"
                >
                  <button 
                    type="button" 
                    @click="advanceIfValid({{ $stepIdx }})"
                    :disabled="!isSubStepValid({{ $stepIdx }})"
                    class="w-full py-3.5 sm:py-4 px-6 rounded-full bg-[#F8248A] hover:bg-[#D81575] text-white font-bold text-sm sm:text-base tracking-wide transition-all duration-200 flex items-center justify-center gap-2 shadow-md cursor-pointer disabled:opacity-60 disabled:cursor-not-allowed"
                  >
                    <span>{{ $buttonText ?: 'Find me an Assistant' }}</span>
                    <svg class="w-4 h-4 text-white shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                    </svg>
                  </button>
                </div>
              @endif
            </div>
          @endforeach

          {{-- Step 1 Completion Button --}}
          <div 
            x-show="canShowFinalButton()"
            x-transition:enter="transition ease-out duration-300 transform"
            x-transition:enter-start="opacity-0 translate-y-2"
            x-transition:enter-end="opacity-100 translate-y-0"
            class="pt-3"
            style="{{ $enableIsolatedFields ? 'display: none;' : '' }}"
          >
            {{-- This step is not instant: it validates, verifies the address with ZeroBounce
                 and captures the partial lead, so it makes a network call before it can
                 advance. Without a busy state the button reads as broken and gets clicked
                 again, which is what the double-submit guard then has to absorb. --}}
            <button 
              type="button" 
              wire:click="goToStep(2)"
              wire:loading.attr="disabled"
              wire:target="goToStep"
              class="w-full py-3.5 sm:py-4 px-6 rounded-full bg-[#F8248A] hover:bg-[#D81575] text-white font-bold text-sm sm:text-base tracking-wide transition-all duration-200 flex items-center justify-center gap-2 shadow-md hover:shadow-lg cursor-pointer disabled:opacity-70 disabled:cursor-wait"
            >
              <span wire:loading.remove wire:target="goToStep">{{ $buttonText ?: 'Find me an Assistant' }}</span>
              <svg wire:loading.remove wire:target="goToStep" class="w-4 h-4 text-white shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
              </svg>

              <span wire:loading.inline-flex wire:target="goToStep" class="inline-flex items-center gap-2">
                <svg class="w-4 h-4 animate-spin shrink-0" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4z"></path>
                </svg>
                Checking your details…
              </span>
            </button>
          </div>
        </div>
      @endif

      {{-- ────────────────────────────────────────────────────────── --}}
      {{-- STEP 2: Pick a Date (Calendar Grid)                        --}}
      {{-- ────────────────────────────────────────────────────────── --}}
      @if ($currentStep === 2)
        <div wire:key="light-step-2" class="animate-wizard-step {{ $skin === 'naked' ? 'p-0' : 'p-6 sm:p-8' }}">
          {{-- Header mirrors the glass skin's: a circular back button on the left, then the
               month chevrons and the month title as one group on the right. Light colours,
               same geometry — the two skins are the same calendar, not two designs. --}}
          <div class="flex items-center justify-between gap-4 mb-7">
            <button
              type="button"
              wire:click="goToStep(1)"
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
                  wire:click="prevMonth"
                  class="p-1.5 rounded-full hover:text-brand-purple hover:scale-125 active:scale-90 transition-all duration-200 cursor-pointer"
                  aria-label="Previous Month"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/>
                  </svg>
                </button>
                <button
                  type="button"
                  wire:click="nextMonth"
                  class="p-1.5 rounded-full hover:text-brand-purple hover:scale-125 active:scale-90 transition-all duration-200 cursor-pointer"
                  aria-label="Next Month"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
                  </svg>
                </button>
              </div>
              <h3 class="text-lg sm:text-xl font-bold text-brand-hero tracking-tight">{{ $monthTitle }}</h3>
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
          <div wire:key="light-grid-{{ $currentYear }}-{{ $currentMonth }}"
               class="grid grid-cols-7 gap-y-2 sm:gap-y-3 text-center items-center justify-items-center animate-grid-month">
            @foreach ($daysGrid as $cell)
              @if ($cell['empty'])
                <div class="w-11 h-11 sm:w-12 sm:h-12"></div>
              @else
                @php $isOpen = $cell['hasAvailability'] && ! $cell['isPast']; @endphp
                <button
                  type="button"
                  wire:click="selectDate('{{ $cell['date'] }}')"
                  wire:target="selectDate('{{ $cell['date'] }}')"
                  wire:loading.attr="disabled"
                  wire:loading.class="opacity-60 cursor-wait"
                  @disabled(! $isOpen)
                  @if ($cell['isToday']) aria-current="date" @endif
                  class="relative w-11 h-11 sm:w-12 sm:h-12 rounded-full text-base sm:text-lg flex items-center justify-center transition-all duration-200 ease-out
                    {{ $cell['isSelected'] ? 'bg-brand-purple text-white font-bold shadow-md shadow-brand-purple/30 scale-105' : '' }}
                    {{ $isOpen && ! $cell['isSelected'] ? 'bg-[#FDF2E6] text-brand-purple font-bold cursor-pointer hover:bg-[#FAE4CE] hover:scale-110 active:scale-95' : '' }}
                    {{ ! $isOpen ? 'text-slate-300 font-normal cursor-not-allowed' : '' }}
                  "
                >
                  <span wire:loading.remove wire:target="selectDate('{{ $cell['date'] }}')">{{ $cell['day'] }}</span>
                  <svg wire:loading wire:target="selectDate('{{ $cell['date'] }}')" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                  </svg>

                  {{-- Today. Sits inside the circle so it never changes the grid's rhythm; it
                       is dropped on the selected day, where the solid fill already says where
                       you are and the dot would only muddy it. --}}
                  @if ($cell['isToday'] && ! $cell['isSelected'])
                    <span class="absolute bottom-1.5 left-1/2 -translate-x-1/2 w-1.5 h-1.5 rounded-full {{ $isOpen ? 'bg-brand-orange-warm' : 'bg-slate-300' }}" aria-hidden="true"></span>
                  @endif
                </button>
              @endif
            @endforeach
          </div>

          <p class="mt-4 text-center text-xs text-text-muted font-medium">Click any date to see times</p>

          {{-- Every day in the grid is @disabled when the month has no availability, so an
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
          @if (empty($availableDates))
            <div class="mt-4 rounded-card bg-amber-50 border border-amber-200 px-4 py-3 text-center">
              <p class="text-xs text-amber-900 font-semibold">{{ $monthTitle }} is fully booked.</p>
              @if ($nextAvailableDate)
                <p class="text-xs text-amber-800 mt-1">
                  Next opening is
                  {{ \Carbon\Carbon::parse($nextAvailableDate, $timezone)->format('l, F j') }}.
                </p>
                <button type="button" wire:click="jumpToNextAvailable"
                  class="mt-2 text-xs font-semibold text-amber-900 underline hover:text-amber-950">
                  Go to {{ \Carbon\Carbon::parse($nextAvailableDate, $timezone)->format('F j') }}
                </button>
              @else
                <p class="text-xs text-amber-800 mt-1">
                  New times open continuously — check back shortly, or email
                  <a href="mailto:contact@remoteleverage.com" class="underline hover:text-amber-950">contact@remoteleverage.com</a>
                  and we will find you a slot.
                </p>
              @endif
            </div>
          @endif

          @error('selectedDate')
            <span class="text-status-alert text-xs block text-center mt-3">{{ $message }}</span>
          @enderror
        </div>
      @endif

      {{-- ────────────────────────────────────────────────────────── --}}
      {{-- STEP 3: Select a Time                                      --}}
      {{-- ────────────────────────────────────────────────────────── --}}
      @if ($currentStep === 3)
        <div wire:key="light-step-3" class="animate-wizard-step {{ $skin === 'naked' ? 'p-0 space-y-6' : 'p-6 sm:p-8 space-y-6' }}">
          <div class="flex items-center justify-between border-b border-slate-100 pb-4">
            <button 
              type="button" 
              wire:click="goToStep(2)"
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
                wire:model.live="timezone" 
                class="text-2xs font-semibold text-slate-600 bg-transparent border-0 focus:ring-0 cursor-pointer"
              >
                @foreach ($this->timezoneChoices() as $tzChoice)
                  <option value="{{ $tzChoice }}">{{ $this->timezoneLabel($tzChoice) }}</option>
                @endforeach
              </select>
            </div>
          </div>

          <div>
            <h3 class="text-sm font-bold text-brand-hero mb-1">
              Available Times for {{ Carbon\Carbon::parse($selectedDate)->format('l, F j, Y') }}
            </h3>
            <p class="text-xs text-text-muted mb-4">Select the 30-minute consultation slot that works best for your schedule:</p>

            <div class="space-y-3 max-h-[380px] overflow-y-auto pr-1">
              @forelse ($availableSlots as $slot)
                @if ($selectedSlot === $slot['iso'])
                  <div wire:key="slot-selected-{{ $slot['iso'] }}" class="grid grid-cols-2 gap-3 transition-all duration-200">
                    <div class="w-full py-3 px-4 rounded-card bg-brand-purple border border-brand-purple text-white text-xs sm:text-sm font-bold text-center shadow flex items-center justify-center transition-all duration-200">
                      {{ $slot['time'] }}
                    </div>
                    <button
                      type="button"
                      wire:click="submitBooking"
                      wire:target="submitBooking"
                      wire:loading.attr="disabled"
                      class="w-full py-3 px-4 rounded-card bg-[#F8248A] hover:bg-[#E91E63] text-white text-xs sm:text-sm font-bold text-center shadow-lg hover:shadow-pink-500/25 hover:scale-[1.02] active:scale-[0.97] transition-all duration-200 ease-out cursor-pointer flex items-center justify-center gap-2 animate-confirm-pop"
                    >
                      <span wire:loading.remove wire:target="submitBooking">Confirm</span>
                      <span wire:loading.flex wire:target="submitBooking" class="flex items-center gap-1.5">
                        <svg class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span>Reserving...</span>
                      </span>
                    </button>
                  </div>
                @else
                  <button 
                    type="button"
                    wire:key="slot-opt-{{ $slot['iso'] }}"
                    wire:click="selectSlot('{{ $slot['iso'] }}')"
                    wire:target="selectSlot('{{ $slot['iso'] }}')"
                    wire:loading.attr="disabled"
                    wire:loading.class="border-brand-purple bg-brand-purple/5 opacity-70 cursor-wait"
                    class="w-full py-3 px-4 rounded-card border border-slate-200 hover:border-brand-purple hover:bg-brand-purple/5 text-text-body font-semibold text-xs sm:text-sm transition-all duration-200 ease-out cursor-pointer block text-center"
                  >
                    {{ $slot['time'] }}
                  </button>
                @endif
              @empty
                <p class="text-xs sm:text-sm text-text-muted py-6 text-center">
                  No direct slots remaining on this day. Please click Change Date to select another day.
                </p>
              @endforelse
            </div>
          </div>

          @error('selectedSlot') 
            <span class="text-status-alert text-xs block text-center mt-2">{{ $message }}</span> 
          @enderror
        </div>
      @endif

    @else
      {{-- ────────────────────────────────────────────────────────── --}}
      {{-- BOOKING CONFIRMED (Summary Card & Add to Calendar)         --}}
      {{-- ────────────────────────────────────────────────────────── --}}
      <div wire:key="light-step-booked" class="animate-wizard-step {{ $skin === 'naked' ? 'p-0 text-center space-y-6' : 'p-8 sm:p-10 text-center space-y-6' }}">
        <div class="w-16 h-16 rounded-full bg-status-success/10 text-status-success mx-auto flex items-center justify-center">
          <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
          </svg>
        </div>

        <div class="space-y-2">
          <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-pill bg-green-50 text-status-success text-2xs font-bold uppercase tracking-wider">
            Confirmed &amp; Scheduled
          </span>
          <h3 class="text-2xl sm:text-3xl font-bold font-display text-brand-hero tracking-tight">
            You're All Set, {{ $name }}!
          </h3>
          <p class="text-xs sm:text-sm text-text-muted max-w-md mx-auto">
            Your 30-minute strategy session is confirmed for:
          </p>
          <p class="text-sm font-bold text-brand-purple">
            {{ $confirmedTime }}
          </p>
        </div>

        @if ($meetingUrl)
          <div class="p-4 rounded-card bg-slate-50 border border-slate-200 inline-block max-w-md w-full">
            <span class="text-2xs font-bold uppercase tracking-wider text-text-muted block mb-1">Google Meet Video Room</span>
            <a href="{{ $meetingUrl }}" target="_blank" rel="noopener" class="text-xs font-bold text-brand-purple hover:underline break-all">
              {{ $meetingUrl }}
            </a>
          </div>
        @endif

        {{-- Add to Calendar Options --}}
        <div class="pt-4 border-t border-slate-100">
          <span class="text-2xs font-bold uppercase tracking-wider text-text-muted block mb-3">Add to your calendar</span>
          <div class="flex items-center justify-center gap-4">
            <a 
              href="https://calendar.google.com/calendar/render?action=TEMPLATE&text=Strategy+Consultation+with+Remote+Leverage&details=Video+Call:+{{ urlencode($meetingUrl ?? '') }}"
              target="_blank" 
              rel="noopener"
              class="px-4 py-2 rounded-card bg-slate-100 hover:bg-slate-200 text-text-body text-xs font-semibold inline-flex items-center gap-2 transition"
            >
              <img src="{{ Vite::asset('resources/images/g-calendar.png') }}" alt="Google Calendar" width="960" height="960" class="w-4 h-4 object-contain" />
              <span>Google</span>
            </a>
            <a 
              href="https://outlook.live.com/calendar/0/deeplink/compose?subject=Strategy+Consultation+with+Remote+Leverage&body=Video+Call:+{{ urlencode($meetingUrl ?? '') }}"
              target="_blank" 
              rel="noopener"
              class="px-4 py-2 rounded-card bg-slate-100 hover:bg-slate-200 text-text-body text-xs font-semibold inline-flex items-center gap-2 transition"
            >
              <img src="{{ Vite::asset('resources/images/outlook.png') }}" alt="Outlook Calendar" width="960" height="894" class="w-4 h-4 object-contain" />
              <span>Outlook</span>
            </a>
            <a 
              href="#" 
              onclick="alert('Calendar invite has been emailed to you as an .ics attachment.'); return false;"
              class="px-4 py-2 rounded-card bg-slate-100 hover:bg-slate-200 text-text-body text-xs font-semibold inline-flex items-center gap-2 transition"
            >
              <img src="{{ Vite::asset('resources/images/apple.png') }}" alt="Apple Calendar" width="814" height="1000" class="w-4 h-4 object-contain" />
              <span>Apple (ICS)</span>
            </a>
          </div>
        </div>

        <div class="text-2xs text-text-muted">
          Confirmation ID: <code class="font-mono text-slate-600">{{ $bookingReference }}</code>
        </div>
      </div>
    @endif

      </div>
    </div>
  @endif

  @script
  <script>
    (function() {
      function getCookie(name) {
        const match = document.cookie.match(new RegExp('(^|;\\s*)(' + name + ')=([^;]*)'));
        return match ? decodeURIComponent(match[3]) : null;
      }

      function getParam(param) {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get(param);
      }

      // Sync client-side cookies / URL params to Livewire if not populated by server request
      const utmSource = getParam('utm_source') || getCookie('utm_source') || getCookie('handl_utm_source');
      const utmMedium = getParam('utm_medium') || getCookie('utm_medium') || getCookie('handl_utm_medium');
      const utmCampaign = getParam('utm_campaign') || getCookie('utm_campaign') || getCookie('handl_utm_campaign');
      const utmTerm = getParam('utm_term') || getCookie('utm_term') || getCookie('handl_utm_term');
      const utmContent = getParam('utm_content') || getCookie('utm_content') || getCookie('handl_utm_content');
      const gclid = getParam('gclid') || getCookie('gclid');
      const fbclid = getParam('fbclid') || getCookie('fbclid') || getCookie('_fbc');
      const referralCode = getParam('via') || getParam('ref') || getParam('r') || getCookie('rl_referrer');

      if (utmSource && !$wire.get('utmSource')) $wire.set('utmSource', utmSource, false);
      if (utmMedium && !$wire.get('utmMedium')) $wire.set('utmMedium', utmMedium, false);
      if (utmCampaign && !$wire.get('utmCampaign')) $wire.set('utmCampaign', utmCampaign, false);
      if (utmTerm && !$wire.get('utmTerm')) $wire.set('utmTerm', utmTerm, false);
      if (utmContent && !$wire.get('utmContent')) $wire.set('utmContent', utmContent, false);
      if (gclid && !$wire.get('gclid')) $wire.set('gclid', gclid, false);
      if (fbclid && !$wire.get('fbclid')) $wire.set('fbclid', fbclid, false);
      if (referralCode && !$wire.get('referralCode')) $wire.set('referralCode', referralCode, false);

      /*
       * PostHog's session id, so the lead timeline can link to the session replay.
       *
       * Only the browser knows it, so unlike the rest of the attribution it cannot be read
       * server-side. It is not available synchronously either: the snippet queues calls until
       * array.js loads, and `get_session_id` returns nothing until then.
       *
       * `onSessionId` is PostHog's own callback for exactly this, and it is in the snippet's
       * stubbed method list — so registering it before array.js lands is safe, and it fires the
       * moment the id exists. It replaced a 500ms x 20 poll that only existed because PostHog
       * arrived from the GTM container at an unpredictable time. `TrackingHooks` installs
       * the stub in `wp_head` ahead of this component (array.js waits for idle/load), so
       * registering `onSessionId` here is safe.
       *
       * `false` on the set() keeps it out of the request queue: this is a passive stamp and
       * must never cost the visitor a round trip mid-form.
       */
      try {
        const stampPostHog = function (sessionId) {
          if (sessionId && !$wire.get('posthogSessionId')) {
            $wire.set('posthogSessionId', sessionId, false);
          }
        };

        if (window.posthog && typeof window.posthog.onSessionId === 'function') {
          window.posthog.onSessionId(stampPostHog);
        }
      } catch (e) {
        // PostHog blocked or absent; the lead simply arrives without a replay link.
      }
    })();
  </script>
  @endscript
</div>
