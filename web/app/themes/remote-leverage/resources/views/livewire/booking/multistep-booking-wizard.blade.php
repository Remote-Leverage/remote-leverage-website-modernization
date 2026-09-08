<div class="w-full">
  @if ($skin === 'glass')
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
          <div class="space-y-4">
            {{-- 1. Monthly Revenue --}}
            <div>
              <label class="block text-xs sm:text-[13px] font-bold text-white mb-2.5">
                What is your company's current monthly revenue? <span class="text-[#EF4444]">*</span>
              </label>
              <div class="space-y-2">
                @foreach ([
                  '$0 to $5k Per Month',
                  '$5k to $10k Per Month',
                  '$10k to $50k Per Month',
                  '$50k-$100k Per Month',
                  '$100k+ Per Month',
                ] as $revOption)
                  <label class="flex items-center gap-2.5 cursor-pointer text-xs sm:text-[13px] text-white font-medium select-none group">
                    <input 
                      type="radio" 
                      name="monthlyRevenue" 
                      value="{{ $revOption }}" 
                      wire:model.live="monthlyRevenue"
                      class="w-4 h-4 rounded-full border-0 bg-white text-brand-purple focus:ring-0 focus:ring-offset-0 cursor-pointer"
                    />
                    <span class="group-hover:text-white/90">{{ $revOption }}</span>
                  </label>
                @endforeach
              </div>
              @error('monthlyRevenue') <span class="text-red-400 text-xs block mt-1">{{ $message }}</span> @enderror
            </div>

            {{-- 2. Name: First & Last --}}
            <div>
              <label class="block text-xs sm:text-[13px] font-bold text-white mb-1">
                Name <span class="text-[#EF4444]">*</span>
              </label>
              <div class="grid grid-cols-2 gap-3">
                <div>
                  <input 
                    type="text" 
                    wire:model="firstName"
                    class="w-full h-10 sm:h-11 px-3.5 rounded-lg bg-[#F0F3FA] text-slate-900 text-sm focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand-purple transition"
                  />
                  <span class="block text-[11px] text-white/60 mt-1">First</span>
                  @error('firstName') <span class="text-red-400 text-xs block mt-0.5">{{ $message }}</span> @enderror
                </div>
                <div>
                  <input 
                    type="text" 
                    wire:model="lastName"
                    class="w-full h-10 sm:h-11 px-3.5 rounded-lg bg-[#F0F3FA] text-slate-900 text-sm focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand-purple transition"
                  />
                  <span class="block text-[11px] text-white/60 mt-1">Last</span>
                  @error('lastName') <span class="text-red-400 text-xs block mt-0.5">{{ $message }}</span> @enderror
                </div>
              </div>
            </div>

            {{-- 3. Business Email --}}
            <div>
              <label class="block text-xs sm:text-[13px] font-bold text-white mb-1">
                Business Email <span class="text-[#EF4444]">*</span>
              </label>
              <input 
                type="email" 
                wire:model="email"
                class="w-full h-10 sm:h-11 px-3.5 rounded-lg bg-[#F0F3FA] text-slate-900 text-sm focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand-purple transition"
              />
              @error('email') <span class="text-red-400 text-xs block mt-1">{{ $message }}</span> @enderror
            </div>

            {{-- 4. Phone --}}
            <div>
              <label class="block text-xs sm:text-[13px] font-bold text-white mb-1">
                Phone <span class="text-[#EF4444]">*</span>
              </label>
              <div 
                wire:ignore 
                x-data="{
                  iti: null,
                  initIti() {
                    const input = this.$refs.phoneInput;
                    if (!input || !window.intlTelInput) return;

                    this.iti = window.intlTelInput(input, {
                      initialCountry: '{{ strtolower($phoneCountry ?: "us") }}',
                      countryOrder: ['us', 'gb', 'ca', 'co', 'mx', 'au'],
                      separateDialCode: false,
                      strictMode: true,
                      autoPlaceholder: 'aggressive',
                      geoIpLookup: (callback) => {
                        fetch('https://ipapi.co/json')
                          .then(res => res.json())
                          .then(data => callback(data.country_code))
                          .catch(() => callback('us'));
                      }
                    });

                    const sync = () => {
                      const country = this.iti.getSelectedCountryData();
                      if (country && country.iso2) {
                        $wire.phoneCountry = country.iso2.toUpperCase();
                      }
                      $wire.phone = this.iti.getNumber() || input.value;
                    };

                    const adjustPhonePadding = () => {
                      const selectedCountry = this.$el.querySelector('.iti__selected-country');
                      if (selectedCountry && input) {
                        const width = selectedCountry.offsetWidth || 0;
                        if (width > 0) {
                          input.style.setProperty('padding-left', (width + 12) + 'px', 'important');
                        }
                      }
                    };

                    adjustPhonePadding();
                    setTimeout(adjustPhonePadding, 50);
                    setTimeout(adjustPhonePadding, 250);

                    // Hide browser tooltip on country flag
                    const flag = this.$el.querySelector('.iti__selected-country');
                    if (flag) {
                      const observer = new MutationObserver(() => {
                        if (flag.getAttribute('title')) {
                          flag.removeAttribute('title');
                        }
                        adjustPhonePadding();
                      });
                      observer.observe(flag, { attributes: true });
                      flag.removeAttribute('title');
                    }

                    input.addEventListener('countrychange', () => {
                      sync();
                      setTimeout(adjustPhonePadding, 10);
                    });
                    input.addEventListener('input', sync);
                    input.addEventListener('change', sync);
                    input.addEventListener('blur', sync);

                    if ($wire.phone && this.iti) {
                      this.iti.setNumber($wire.phone);
                      setTimeout(adjustPhonePadding, 10);
                    }
                  },
                  init() {
                    if (window.intlTelInput) {
                      this.initIti();
                    } else {
                      const check = setInterval(() => {
                        if (window.intlTelInput) {
                          clearInterval(check);
                          this.initIti();
                        }
                      }, 30);
                      setTimeout(() => clearInterval(check), 3000);
                    }
                  }
                }"
                class="relative w-full"
              >
                <input 
                  x-ref="phoneInput"
                  type="tel"
                  id="booking-phone-input"
                  name="phone"
                  value="{{ $phone }}"
                  placeholder="(201) 555-0123"
                  class="w-full h-10 sm:h-11 rounded-lg bg-white text-slate-900 text-sm focus:outline-none focus:ring-2 focus:ring-brand-purple transition"
                />
              </div>
              @error('phone') <span class="text-red-400 text-xs block mt-1">{{ $message }}</span> @enderror
            </div>

            {{-- 5. Consent Checkbox --}}
            <div class="pt-1">
              <label class="flex items-start gap-2.5 cursor-pointer text-[10.5px] sm:text-[11px] leading-[1.4] text-white/80 select-none">
                <input 
                  type="checkbox" 
                  required 
                  checked 
                  class="mt-0.5 w-4 h-4 rounded bg-[#F0F3FA] text-brand-purple border-0 focus:ring-0 focus:ring-offset-0 shrink-0 cursor-pointer"
                />
                <span>
                  I agree to receive SMS appointment reminders from Remote Leverage about the consultation I'm booking, and to be contacted by phone and email. Messaging frequency varies. Standard message and data rates may apply. Reply STOP to unsubscribe, HELP for help, or call (650) 668-0728 / email paula@remoteleverage.com. By consenting I acknowledge I have read and agree to Remote Leverage’s <a href="/terms-of-use" class="underline hover:text-white">Terms &amp; Conditions</a> and <a href="/privacy-policy" class="underline hover:text-white">Privacy Policy</a>. I can withdraw consent at any time.
                </span>
              </label>
            </div>

            {{-- 6. Submit Button --}}
            <div class="pt-2">
              <button 
                type="button" 
                wire:click="goToStep(2)"
                wire:loading.attr="disabled"
                class="w-full py-3.5 px-6 rounded-full bg-[#E0E5EC] hover:bg-white text-[#4A5568] hover:text-black font-bold text-sm tracking-wide flex items-center justify-center gap-2 transition-all duration-200 shadow-md cursor-pointer disabled:opacity-50"
              >
                <span wire:loading.remove>Book a Call</span>
                <span wire:loading>Processing...</span>
                <svg wire:loading.remove class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                </svg>
              </button>
            </div>
          </div>
        @elseif ($isBooked)
          {{-- Booking Confirmation --}}
          <div class="p-4 sm:p-6 text-center space-y-4">
            <div class="w-14 h-14 rounded-full bg-status-success/20 text-status-success flex items-center justify-center mx-auto">
              <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
            </div>
            <h3 class="text-xl font-bold font-display text-white">Call Confirmed!</h3>
            <p class="text-xs sm:text-sm text-white/80">We've reserved your strategy session. Check your email for calendar invite details.</p>
            <div class="text-2xs text-white/60">Reference: <code class="font-mono text-white">{{ $bookingReference }}</code></div>
          </div>
        @else
          {{-- Calendar / Slots Selection (Step 2, 3, 4) in light container --}}
          <div class="bg-white rounded-2xl p-5 sm:p-6 text-slate-900 shadow-lg">
            <div class="flex items-center justify-between pb-3 mb-4 border-b border-slate-100">
              <button 
                type="button" 
                wire:click="goToStep({{ $currentStep - 1 }})"
                class="text-xs font-semibold text-slate-500 hover:text-black inline-flex items-center gap-1.5 transition cursor-pointer"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                <span>Back</span>
              </button>
              <span class="text-xs font-bold text-brand-purple">Step {{ $currentStep }} of {{ $totalSteps }}</span>
            </div>

            @if ($currentStep === 2)
              {{-- Month Navigation Header --}}
              <div class="flex items-center justify-between mb-3 px-1">
                <h4 class="text-sm font-bold text-slate-900">{{ $monthTitle }}</h4>
                <div class="flex items-center gap-1">
                  <button type="button" wire:click="prevMonth" class="p-1.5 rounded-full hover:bg-slate-100 text-slate-600 transition cursor-pointer" aria-label="Previous Month">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"></polyline></svg>
                  </button>
                  <button type="button" wire:click="nextMonth" class="p-1.5 rounded-full hover:bg-slate-100 text-slate-600 transition cursor-pointer" aria-label="Next Month">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"></polyline></svg>
                  </button>
                </div>
              </div>

              {{-- Day Name Column Headers --}}
              <div class="grid grid-cols-7 text-center text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">
                <span>SUN</span><span>MON</span><span>TUE</span><span>WED</span><span>THU</span><span>FRI</span><span>SAT</span>
              </div>

              {{-- Dates Grid --}}
              <div class="grid grid-cols-7 gap-1 text-center">
                @foreach ($daysGrid as $cell)
                  @if ($cell['empty'])
                    <div class="h-8 w-8"></div>
                  @else
                    <button
                      type="button"
                      wire:click="selectDate('{{ $cell['date'] }}')"
                      @disabled($cell['isPast'] || ! $cell['hasAvailability'])
                      class="mx-auto h-8 w-8 rounded-full text-xs font-semibold flex items-center justify-center transition-all duration-150
                        {{ $cell['isSelected'] ? 'bg-brand-purple text-white font-bold shadow-md scale-105' : '' }}
                        {{ $cell['hasAvailability'] && ! $cell['isSelected'] ? 'hover:bg-brand-purple/10 text-slate-900 font-bold hover:text-brand-purple cursor-pointer' : '' }}
                        {{ ! $cell['hasAvailability'] || $cell['isPast'] ? 'text-slate-300 cursor-not-allowed' : '' }}
                      "
                    >
                      <span>{{ $cell['day'] }}</span>
                    </button>
                  @endif
                @endforeach
              </div>
            @elseif ($currentStep === 3)
              {{-- Time slots --}}
              <h4 class="text-sm font-bold text-slate-900 mb-2">Select a Time for {{ \Carbon\Carbon::parse($selectedDate)->format('D, M j') }}</h4>
              <div class="grid grid-cols-2 gap-2 max-h-56 overflow-y-auto pr-1">
                @forelse ($availableSlots as $slot)
                  <button
                    type="button"
                    wire:click="selectSlot('{{ $slot['time'] }}')"
                    class="py-2 px-3 rounded-lg border text-xs font-semibold transition cursor-pointer {{ $selectedSlot === $slot['time'] ? 'border-brand-purple bg-brand-purple text-white' : 'border-slate-200 hover:border-brand-purple text-slate-800' }}"
                  >
                    {{ $slot['time'] }}
                  </button>
                @empty
                  <p class="text-xs text-slate-500 col-span-2 py-4 text-center">No times available for this date.</p>
                @endforelse
              </div>
              @if ($selectedSlot)
                <div class="pt-3 flex justify-end">
                  <button type="button" wire:click="goToStep(4)" class="py-2.5 px-5 rounded-full bg-brand-purple hover:bg-brand-purple-deep text-white font-bold text-xs tracking-wide transition cursor-pointer">
                    Confirm Time
                  </button>
                </div>
              @endif
            @elseif ($currentStep === 4)
              {{-- Final submit --}}
              <div class="space-y-3">
                <h4 class="text-sm font-bold text-slate-900">Final Confirmation</h4>
                <p class="text-xs text-slate-600">Meeting: <strong>{{ \Carbon\Carbon::parse($selectedDate)->format('M j, Y') }} at {{ $selectedSlot }}</strong></p>
                <textarea wire:model="notes" rows="2" placeholder="Any specific requirements or notes?" class="w-full p-2.5 rounded-lg border border-slate-200 text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-brand-purple"></textarea>
                <button type="button" wire:click="submitBooking" wire:loading.attr="disabled" class="w-full py-3 rounded-full bg-brand-purple hover:bg-brand-purple-deep text-white font-bold text-sm tracking-wide transition cursor-pointer">
                  <span wire:loading.remove>Complete Booking</span>
                  <span wire:loading>Reserving...</span>
                </button>
              </div>
            @endif
          </div>
        @endif

      </div>
    </div>
  @else
    {{-- Default Skin for /book-consultation --}}
    <div class="w-full max-w-[580px] mx-auto">
      <div class="w-full bg-surface-white rounded-card-lg border border-slate-200/80 shadow-card overflow-hidden">
    
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

    @if (! $isBooked)
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
      {{-- STEP 1: Your Details                                       --}}
      {{-- ────────────────────────────────────────────────────────── --}}
      @if ($currentStep === 1)
        <div class="p-6 sm:p-8 space-y-4">
          <div class="space-y-1 mb-6">
            <h3 class="text-xl font-bold font-display text-brand-hero tracking-tight">Your Contact Information</h3>
            <p class="text-xs text-slate-600">Provide your contact details so our advisor can review your company requirements.</p>
          </div>

          {{-- 1. Work Email (First) --}}
          <div class="space-y-1">
            <label class="block text-xs font-bold uppercase tracking-wider text-text-body">Work Email *</label>
            <input 
              type="email" 
              wire:model="email"
              placeholder="sarah@company.com"
              class="w-full px-4 py-2.5 rounded-card border border-slate-200 focus:ring-2 focus:ring-brand-purple/20 focus:border-brand-purple text-sm text-text-body bg-white transition"
            />
            @error('email') <span class="text-status-alert text-xs block mt-1">{{ $message }}</span> @enderror
          </div>

          {{-- 2. First Name & Last Name (Two Separate Fields) --}}
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="space-y-1">
              <label class="block text-xs font-bold uppercase tracking-wider text-text-body">First Name *</label>
              <input 
                type="text" 
                wire:model="firstName"
                placeholder="e.g. Sarah"
                class="w-full px-4 py-2.5 rounded-card border border-slate-200 focus:ring-2 focus:ring-brand-purple/20 focus:border-brand-purple text-sm text-text-body bg-white transition"
              />
              @error('firstName') <span class="text-status-alert text-xs block mt-1">{{ $message }}</span> @enderror
            </div>

            <div class="space-y-1">
              <label class="block text-xs font-bold uppercase tracking-wider text-text-body">Last Name *</label>
              <input 
                type="text" 
                wire:model="lastName"
                placeholder="e.g. Jenkins"
                class="w-full px-4 py-2.5 rounded-card border border-slate-200 focus:ring-2 focus:ring-brand-purple/20 focus:border-brand-purple text-sm text-text-body bg-white transition"
              />
              @error('lastName') <span class="text-status-alert text-xs block mt-1">{{ $message }}</span> @enderror
            </div>
          </div>

          {{-- 3. Phone Number with Country Selector --}}
          <div class="space-y-1">
            <label class="block text-xs font-bold uppercase tracking-wider text-text-body">Phone Number</label>
            <div 
              wire:ignore 
              x-data="{
                iti: null,
                initIti() {
                  const input = this.$refs.phoneInput;
                  if (!input || !window.intlTelInput) return;

                  this.iti = window.intlTelInput(input, {
                    initialCountry: '{{ strtolower($phoneCountry ?: "us") }}',
                    countryOrder: ['us', 'gb', 'ca', 'co', 'mx', 'au'],
                    separateDialCode: false,
                    strictMode: true,
                    autoPlaceholder: 'aggressive',
                    geoIpLookup: (callback) => {
                      fetch('https://ipapi.co/json')
                        .then(res => res.json())
                        .then(data => callback(data.country_code))
                        .catch(() => callback('us'));
                    }
                  });

                  const sync = () => {
                    const country = this.iti.getSelectedCountryData();
                    if (country && country.iso2) {
                      $wire.phoneCountry = country.iso2.toUpperCase();
                    }
                    $wire.phone = this.iti.getNumber() || input.value;
                  };

                  const adjustPhonePadding = () => {
                    const selectedCountry = this.$el.querySelector('.iti__selected-country');
                    if (selectedCountry && input) {
                      const width = selectedCountry.offsetWidth || 0;
                      if (width > 0) {
                        input.style.setProperty('padding-left', (width + 12) + 'px', 'important');
                      }
                    }
                  };

                  adjustPhonePadding();
                  setTimeout(adjustPhonePadding, 50);
                  setTimeout(adjustPhonePadding, 250);

                  // Hide browser tooltip on country flag
                  const flag = this.$el.querySelector('.iti__selected-country');
                  if (flag) {
                    const observer = new MutationObserver(() => {
                      if (flag.getAttribute('title')) {
                        flag.removeAttribute('title');
                      }
                      adjustPhonePadding();
                    });
                    observer.observe(flag, { attributes: true });
                    flag.removeAttribute('title');
                  }

                  input.addEventListener('countrychange', () => {
                    sync();
                    setTimeout(adjustPhonePadding, 10);
                  });
                  input.addEventListener('input', sync);
                  input.addEventListener('change', sync);
                  input.addEventListener('blur', sync);

                  if ($wire.phone && this.iti) {
                    this.iti.setNumber($wire.phone);
                    setTimeout(adjustPhonePadding, 10);
                  }
                },
                init() {
                  if (window.intlTelInput) {
                    this.initIti();
                  } else {
                    const check = setInterval(() => {
                      if (window.intlTelInput) {
                        clearInterval(check);
                        this.initIti();
                      }
                    }, 30);
                    setTimeout(() => clearInterval(check), 3000);
                  }
                }
              }"
              class="relative w-full"
            >
              <input 
                x-ref="phoneInput"
                type="tel" 
                id="default-phone-input"
                name="phone"
                value="{{ $phone }}"
                placeholder="(555) 000-0000"
                class="w-full px-4 py-2.5 rounded-card border border-slate-200 text-sm text-text-body bg-white focus:ring-2 focus:ring-brand-purple/20 focus:border-brand-purple focus:outline-none transition"
              />
            </div>
            @error('phone') <span class="text-status-alert text-xs block mt-1">{{ $message }}</span> @enderror
          </div>

          {{-- 4. Monthly Company Revenue (MRR) - Radio Select (Low to High) --}}
          <div class="space-y-2">
            <label class="block text-xs font-bold uppercase tracking-wider text-text-body">Monthly Company Revenue (MRR) *</label>
            <div class="space-y-2">
              @foreach ([
                '$0 to $5k Per Month' => '$0 to $5k Per Month',
                '$5k to $10k Per Month' => '$5k to $10k Per Month',
                '$10k to $50k Per Month' => '$10k to $50k Per Month',
                '$50k-$100k Per Month' => '$50k-$100k Per Month',
                '$100k+ Per Month' => '$100k+ Per Month',
              ] as $val => $label)
                <label 
                  class="flex items-center gap-3 px-4 py-3 rounded-card border transition cursor-pointer {{ $monthlyRevenue === $val ? 'border-brand-purple bg-brand-purple/5 ring-1 ring-brand-purple/20 text-brand-hero' : 'border-slate-200 bg-white hover:border-slate-300 text-text-body' }}"
                >
                  <input 
                    type="radio" 
                    name="monthlyRevenue" 
                    value="{{ $val }}" 
                    wire:model.live="monthlyRevenue"
                    class="w-4 h-4 text-brand-purple border-slate-300 focus:ring-brand-purple/20 focus:ring-offset-0 cursor-pointer"
                  />
                  <span class="text-xs sm:text-sm font-medium">{{ $label }}</span>
                </label>
              @endforeach
            </div>
            @error('monthlyRevenue') <span class="text-status-alert text-xs block mt-1">{{ $message }}</span> @enderror
          </div>

          <div class="pt-6 border-t border-slate-100 flex justify-end">
            <button 
              type="button" 
              wire:click="goToStep(2)"
              class="btn-primary cursor-pointer inline-flex items-center gap-2"
            >
              <span>Next: Pick a Date</span>
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
            </button>
          </div>
        </div>
      @endif

      {{-- ────────────────────────────────────────────────────────── --}}
      {{-- STEP 2: Pick a Date (Calendar Grid)                        --}}
      {{-- ────────────────────────────────────────────────────────── --}}
      @if ($currentStep === 2)
        <div class="p-6 sm:p-8">
          <div class="flex items-center justify-between mb-4">
            <button 
              type="button" 
              wire:click="goToStep(1)"
              class="text-xs font-semibold text-text-muted hover:text-brand-hero inline-flex items-center gap-1.5 transition cursor-pointer"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
              <span>Back</span>
            </button>
            <span class="text-xs text-text-muted font-medium">Click any date to see times</span>
          </div>

          <div>
            {{-- Month Navigation Header --}}
            <div class="flex items-center justify-between mb-4 px-2">
              <h3 class="text-base font-bold text-brand-hero">{{ $monthTitle }}</h3>
              <div class="flex items-center gap-2">
                <button 
                  type="button" 
                  wire:click="prevMonth"
                  class="p-2 rounded-full hover:bg-slate-100 text-slate-600 transition cursor-pointer"
                  aria-label="Previous Month"
                >
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"></polyline></svg>
                </button>
                <button 
                  type="button" 
                  wire:click="nextMonth"
                  class="p-2 rounded-full hover:bg-slate-100 text-slate-600 transition cursor-pointer"
                  aria-label="Next Month"
                >
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"></polyline></svg>
                </button>
              </div>
            </div>

            {{-- Day Name Column Headers --}}
            <div class="grid grid-cols-7 text-center text-2xs font-bold text-slate-400 uppercase tracking-wider mb-2">
              <span>SUN</span><span>MON</span><span>TUE</span><span>WED</span><span>THU</span><span>FRI</span><span>SAT</span>
            </div>

            {{-- Dates Grid --}}
            <div class="grid grid-cols-7 gap-1 text-center">
              @foreach ($daysGrid as $cell)
                @if ($cell['empty'])
                  <div class="h-9 w-9"></div>
                @else
                  <button
                    type="button"
                    wire:click="selectDate('{{ $cell['date'] }}')"
                    @disabled($cell['isPast'] || ! $cell['hasAvailability'])
                    class="mx-auto h-9 w-9 rounded-full text-xs font-semibold flex items-center justify-center transition-all duration-150
                      {{ $cell['isSelected'] ? 'bg-brand-purple text-white font-bold shadow-md shadow-brand-purple/30 scale-105' : '' }}
                      {{ $cell['hasAvailability'] && ! $cell['isSelected'] ? 'hover:bg-brand-purple/10 text-brand-hero font-bold hover:text-brand-purple cursor-pointer' : '' }}
                      {{ ! $cell['hasAvailability'] || $cell['isPast'] ? 'text-slate-300 cursor-not-allowed' : '' }}
                    "
                  >
                    <span>{{ $cell['day'] }}</span>
                  </button>
                @endif
              @endforeach
            </div>
          </div>

          @error('selectedDate') 
            <span class="text-status-alert text-xs block text-center mt-3">{{ $message }}</span> 
          @enderror
        </div>
      @endif

      {{-- ────────────────────────────────────────────────────────── --}}
      {{-- STEP 3: Select a Time                                      --}}
      {{-- ────────────────────────────────────────────────────────── --}}
      @if ($currentStep === 3)
        <div class="p-6 sm:p-8 space-y-6">
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
                <option value="America/New_York">Eastern Time (ET)</option>
                <option value="America/Chicago">Central Time (CT)</option>
                <option value="America/Denver">Mountain Time (MT)</option>
                <option value="America/Los_Angeles">Pacific Time (PT)</option>
                <option value="Europe/London">London (GMT/BST)</option>
                <option value="Europe/Paris">Central Europe (CET)</option>
              </select>
            </div>
          </div>

          <div>
            <h3 class="text-sm font-bold text-brand-hero mb-1">
              Available Times for {{ Carbon\Carbon::parse($selectedDate)->format('l, F j, Y') }}
            </h3>
            <p class="text-xs text-text-muted mb-4">Select the 30-minute consultation slot that works best for your schedule:</p>

            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2.5 max-h-[300px] overflow-y-auto pr-1">
              @forelse ($availableSlots as $slot)
                <button 
                  type="button"
                  wire:click="selectSlot('{{ $slot['iso'] }}')"
                  class="py-2.5 px-3 text-center rounded-card border text-xs font-semibold transition cursor-pointer
                    {{ $selectedSlot === $slot['iso'] ? 'bg-brand-purple border-brand-purple text-white shadow-md shadow-brand-purple/20 font-bold' : 'border-slate-200 hover:border-brand-purple text-text-body hover:bg-brand-purple/5' }}
                  "
                >
                  {{ $slot['time'] }}
                </button>
              @empty
                <div class="col-span-3 text-center py-6 text-xs text-slate-400">
                  No direct slots remaining on this day. Please click Back to select another date.
                </div>
              @endforelse
            </div>
          </div>

          @error('selectedSlot') 
            <span class="text-status-alert text-xs block text-center mt-2">{{ $message }}</span> 
          @enderror
        </div>
      @endif

      {{-- ────────────────────────────────────────────────────────── --}}
      {{-- STEP 4: Additional Info & Guests (Confirmation Review)     --}}
      {{-- ────────────────────────────────────────────────────────── --}}
      @if ($currentStep === 4)
        <div class="p-6 sm:p-8 space-y-6">
          <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <button 
              type="button" 
              wire:click="goToStep(3)"
              class="text-xs font-semibold text-text-muted hover:text-brand-hero inline-flex items-center gap-1.5 transition cursor-pointer"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
              <span>Back to Times</span>
            </button>
            <span class="text-xs font-bold uppercase tracking-wider text-brand-purple">Final Review</span>
          </div>

          {{-- Selected Meeting Summary Card --}}
          <div class="p-4 rounded-card bg-lavender-surface border border-brand-purple/20 space-y-2">
            <div class="flex items-center gap-2 text-brand-midnight font-bold text-sm">
              <svg class="w-4 h-4 text-brand-purple" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                <line x1="16" y1="2" x2="16" y2="6"></line>
                <line x1="8" y1="2" x2="8" y2="6"></line>
                <line x1="3" y1="10" x2="21" y2="10"></line>
              </svg>
              <span>{{ Carbon\Carbon::parse($selectedSlot, $timezone)->format('l, F j, Y \a\t g:i A') }} ({{ $timezone }})</span>
            </div>
            <div class="text-xs text-slate-600 flex items-center gap-4">
              <span>Attendee: <strong>{{ $name }}</strong> ({{ $email }})</span>
              @if ($company) <span>&bull; Company: <strong>{{ $company }}</strong></span> @endif
            </div>
          </div>

          {{-- Guest Invitee Repeater --}}
          <div class="space-y-2">
            <label class="block text-xs font-bold uppercase tracking-wider text-text-body">Add Guests (Optional)</label>
            <p class="text-xs text-text-muted">Add colleagues or partners who should receive the Google Meet invitation:</p>
            
            <div class="flex items-center gap-2">
              <input 
                type="email" 
                wire:model="newGuestEmail"
                wire:keydown.enter.prevent="addGuest"
                placeholder="colleague@company.com" 
                class="flex-1 px-4 py-2 rounded-card border border-slate-200 text-xs text-text-body focus:ring-2 focus:ring-brand-purple/20 focus:border-brand-purple"
              />
              <button 
                type="button" 
                wire:click="addGuest"
                class="px-4 py-2 rounded-card bg-slate-100 hover:bg-slate-200 text-text-body text-xs font-bold transition cursor-pointer"
              >
                + Add Guest
              </button>
            </div>

            @if (! empty($guestEmails))
              <div class="flex flex-wrap gap-2 pt-2">
                @foreach ($guestEmails as $idx => $guestEmail)
                  <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-pill bg-slate-100 text-slate-700 text-xs font-medium">
                    <span>{{ $guestEmail }}</span>
                    <button type="button" wire:click="removeGuest({{ $idx }})" class="text-slate-400 hover:text-red-500 font-bold">&times;</button>
                  </span>
                @endforeach
              </div>
            @endif
          </div>

          {{-- Notes / Specific Bottlenecks --}}
          <div class="space-y-1">
            <label class="block text-xs font-bold uppercase tracking-wider text-text-body">Notes / Objectives for this Call</label>
            <textarea 
              wire:model="notes" 
              rows="2" 
              placeholder="Tell us what candidate profiles or specific skills you need..."
              class="w-full px-4 py-2 rounded-card border border-slate-200 text-xs text-text-body focus:ring-2 focus:ring-brand-purple/20 focus:border-brand-purple"
            ></textarea>
          </div>

          <div class="pt-4 border-t border-slate-100 flex items-center justify-between">
            <div class="text-2xs text-text-muted">
              Instant calendar invite will be emailed to you.
            </div>
            <button 
              type="button" 
              wire:click="submitBooking"
              wire:loading.attr="disabled"
              class="btn-magenta cursor-pointer shadow-lg inline-flex items-center gap-2"
            >
              <span wire:loading.remove>Confirm Consultation</span>
              <span wire:loading class="inline-flex items-center gap-2">
                <svg class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                Booking Slot...
              </span>
            </button>
          </div>
        </div>
      @endif

    @else
      {{-- ────────────────────────────────────────────────────────── --}}
      {{-- BOOKING CONFIRMED (Summary Card & Add to Calendar)         --}}
      {{-- ────────────────────────────────────────────────────────── --}}
      <div class="p-8 sm:p-10 text-center space-y-6">
        <div class="w-16 h-16 rounded-full bg-status-success/10 text-status-success mx-auto flex items-center justify-center">
          <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
          </svg>
        </div>

        <div class="space-y-2">
          <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-pill bg-green-50 text-status-success text-2xs font-bold uppercase tracking-wider">
            Confirmed &amp; Scheduled
          </span>
          <h3 class="text-2xl sm:text-3xl font-extrabold font-display text-brand-hero tracking-tight">
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
              <img src="{{ Vite::asset('resources/images/g-calendar.png') }}" alt="Google Calendar" class="w-4 h-4 object-contain" />
              <span>Google</span>
            </a>
            <a 
              href="https://outlook.live.com/calendar/0/deeplink/compose?subject=Strategy+Consultation+with+Remote+Leverage&body=Video+Call:+{{ urlencode($meetingUrl ?? '') }}"
              target="_blank" 
              rel="noopener"
              class="px-4 py-2 rounded-card bg-slate-100 hover:bg-slate-200 text-text-body text-xs font-semibold inline-flex items-center gap-2 transition"
            >
              <img src="{{ Vite::asset('resources/images/outlook.png') }}" alt="Outlook Calendar" class="w-4 h-4 object-contain" />
              <span>Outlook</span>
            </a>
            <a 
              href="#" 
              onclick="alert('Calendar invite has been emailed to you as an .ics attachment.'); return false;"
              class="px-4 py-2 rounded-card bg-slate-100 hover:bg-slate-200 text-text-body text-xs font-semibold inline-flex items-center gap-2 transition"
            >
              <img src="{{ Vite::asset('resources/images/apple.png') }}" alt="Apple Calendar" class="w-4 h-4 object-contain" />
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
    })();
  </script>
  @endscript
</div>
