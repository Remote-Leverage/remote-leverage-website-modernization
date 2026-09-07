<div class="relative w-full max-w-3xl mx-auto bg-surface-white/95 backdrop-blur-md rounded-card-lg border border-slate-200/80 shadow-card p-6 sm:p-10 transition-all duration-300">
  
  {{-- Progress Header --}}
  @if (! $isBooked)
    <div class="mb-8">
      <div class="flex items-center justify-between text-xs sm:text-sm font-semibold text-text-muted mb-3">
        <span class="{{ $currentStep >= 1 ? 'text-brand-purple font-bold' : '' }}">
          1. Qualification
        </span>
        <span class="{{ $currentStep >= 2 ? 'text-brand-purple font-bold' : '' }}">
          2. Select Time
        </span>
        <span class="{{ $currentStep >= 3 ? 'text-brand-purple font-bold' : '' }}">
          3. Confirm Details
        </span>
      </div>
      <div class="w-full bg-slate-100 h-2 rounded-pill overflow-hidden">
        <div 
          class="h-full bg-gradient-to-r from-brand-purple to-brand-magenta transition-all duration-500 rounded-pill"
          style="width: {{ ($currentStep / 3) * 100 }}%"
        ></div>
      </div>
    </div>
  @endif

  {{-- Error Banner --}}
  @if ($errorMessage)
    <div class="mb-6 p-4 rounded-card bg-red-50 border border-red-200 text-red-700 text-sm flex items-start gap-3">
      <svg class="w-5 h-5 shrink-0 text-red-500 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
      <span>{{ $errorMessage }}</span>
    </div>
  @endif

  {{-- STEP 1: Qualification --}}
  @if ($currentStep === 1 && ! $isBooked)
    <div class="space-y-6">
      <div>
        <h3 class="text-2xl font-bold font-display text-brand-hero tracking-tight">
          What type of remote leverage do you need?
        </h3>
        <p class="text-text-muted text-sm mt-1">
          Tell us about your team's current bottleneck so we can match you with the right specialist.
        </p>
      </div>

      {{-- Role Selection --}}
      <div class="space-y-2">
        <label class="block text-xs font-semibold uppercase tracking-wider text-text-slate">
          Target Role / Specialty
        </label>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          @foreach ([
            'Executive Assistant' => 'Inbox, calendar, scheduling & operations',
            'Real Estate Assistant' => 'Transaction coordinator, cold outreach & CRM',
            'E-commerce Manager' => 'Order fulfillment, Shopify & customer support',
            'Marketing Specialist' => 'Social media, content workflows & campaigns',
          ] as $role => $desc)
            <button
              type="button"
              wire:click="$set('roleNeeded', '{{ $role }}')"
              class="text-left p-4 rounded-card border transition-all duration-200 flex flex-col justify-between {{ $roleNeeded === $role ? 'border-brand-purple bg-brand-purple/5 ring-2 ring-brand-purple/20' : 'border-slate-200 hover:border-slate-300 bg-white' }}"
            >
              <div class="flex items-center justify-between w-full mb-1">
                <span class="font-bold text-text-body text-sm">{{ $role }}</span>
                <span class="w-4 h-4 rounded-full border flex items-center justify-center {{ $roleNeeded === $role ? 'border-brand-purple bg-brand-purple text-white' : 'border-slate-300' }}">
                  @if ($roleNeeded === $role)
                    <svg class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                  @endif
                </span>
              </div>
              <span class="text-xs text-text-muted">{{ $desc }}</span>
            </button>
          @endforeach
        </div>
        @error('roleNeeded') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
      </div>

      {{-- Hours Per Week --}}
      <div class="space-y-2">
        <label class="block text-xs font-semibold uppercase tracking-wider text-text-slate">
          Weekly Workload Needed
        </label>
        <div class="grid grid-cols-3 gap-3">
          @foreach ([
            '20' => 'Part-Time (20 hrs)',
            '40' => 'Full-Time (40 hrs)',
            'custom' => 'Multiple Roles (40+ hrs)',
          ] as $hoursKey => $hoursLabel)
            <button
              type="button"
              wire:click="$set('hoursPerWeek', '{{ $hoursKey }}')"
              class="p-3 text-center rounded-card border text-sm font-semibold transition-all duration-200 {{ $hoursPerWeek === (string)$hoursKey ? 'border-brand-purple bg-brand-purple text-white shadow-sm' : 'border-slate-200 hover:border-slate-300 bg-white text-text-body' }}"
            >
              {{ $hoursLabel }}
            </button>
          @endforeach
        </div>
        @error('hoursPerWeek') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
      </div>

      {{-- Start Timeline --}}
      <div class="space-y-2">
        <label class="block text-xs font-semibold uppercase tracking-wider text-text-slate">
          Estimated Start Date
        </label>
        <div class="grid grid-cols-3 gap-3">
          @foreach (['Immediately', 'Within 2 Weeks', 'Next Month'] as $timeline)
            <button
              type="button"
              wire:click="$set('startDate', '{{ $timeline }}')"
              class="p-3 text-center rounded-card border text-xs sm:text-sm font-semibold transition-all duration-200 {{ $startDate === $timeline ? 'border-brand-purple bg-brand-purple/10 text-brand-purple' : 'border-slate-200 hover:border-slate-300 bg-white text-text-body' }}"
            >
              {{ $timeline }}
            </button>
          @endforeach
        </div>
        @error('startDate') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
      </div>

      {{-- Step 1 Next Button --}}
      <div class="pt-4 flex justify-end">
        <button
          type="button"
          wire:click="nextStep"
          class="px-8 py-3.5 rounded-cta bg-brand-magenta hover:bg-brand-magenta-hover text-white font-bold text-sm tracking-wide shadow-lg hover:shadow-xl transition-all duration-200 transform hover:-translate-y-0.5 cursor-pointer flex items-center gap-2"
        >
          <span>Choose Call Time</span>
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
          </svg>
        </button>
      </div>
    </div>
  @endif

  {{-- STEP 2: Timezone & Slot Picker --}}
  @if ($currentStep === 2 && ! $isBooked)
    <div class="space-y-6">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h3 class="text-2xl font-bold font-display text-brand-hero tracking-tight">
            Select Date & Time
          </h3>
          <p class="text-text-muted text-sm mt-1">
            Pick a 30-minute slot for a strategy consultation with our staffing advisor.
          </p>
        </div>

        {{-- Timezone Dropdown --}}
        <div class="sm:w-56">
          <label class="block text-xs font-semibold text-text-slate mb-1">Timezone</label>
          <select
            wire:model.live="timezone"
            class="w-full text-xs rounded-card border-slate-200 py-2 px-3 focus:border-brand-purple focus:ring-brand-purple text-text-body bg-white"
          >
            <option value="America/New_York">Eastern Time (ET)</option>
            <option value="America/Chicago">Central Time (CT)</option>
            <option value="America/Denver">Mountain Time (MT)</option>
            <option value="America/Los_Angeles">Pacific Time (PT)</option>
            <option value="UTC">UTC / GMT</option>
            <option value="Europe/London">London (GMT/BST)</option>
          </select>
        </div>
      </div>

      {{-- Calendar Days Tabs --}}
      <div class="space-y-2">
        <label class="block text-xs font-semibold uppercase tracking-wider text-text-slate">
          Available Days
        </label>
        <div class="grid grid-cols-4 sm:grid-cols-7 gap-2">
          @foreach ($calendarDays as $day)
            <button
              type="button"
              wire:click="selectDate('{{ $day['date'] }}')"
              class="py-3 px-2 rounded-card text-center border transition-all duration-200 cursor-pointer {{ $selectedDate === $day['date'] ? 'border-brand-purple bg-brand-purple text-white shadow-md' : 'border-slate-200 hover:border-slate-300 bg-white text-text-body' }}"
            >
              <div class="text-2xs uppercase tracking-wider {{ $selectedDate === $day['date'] ? 'text-purple-200' : 'text-text-muted' }}">
                {{ $day['day_name'] }}
              </div>
              <div class="text-base sm:text-lg font-bold mt-0.5">
                {{ $day['day_num'] }}
              </div>
              <div class="text-2xs {{ $selectedDate === $day['date'] ? 'text-purple-200' : 'text-text-slate' }}">
                {{ $day['month_name'] }}
              </div>
            </button>
          @endforeach
        </div>
        @error('selectedDate') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
      </div>

      {{-- Available Slots Grid --}}
      <div class="space-y-2 pt-2">
        <div class="flex items-center justify-between">
          <label class="block text-xs font-semibold uppercase tracking-wider text-text-slate">
            Available Timeslots
          </label>
          <span wire:loading wire:target="selectDate, timezone" class="text-xs text-brand-purple font-medium flex items-center gap-1.5">
            <svg class="animate-spin w-3.5 h-3.5" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            Updating slots...
          </span>
        </div>

        @if (empty($availableSlots))
          <div class="p-8 text-center rounded-card bg-slate-50 border border-slate-200 text-text-muted text-sm">
            No remaining open slots found for this date. Please pick another day or timezone.
          </div>
        @else
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 max-h-56 overflow-y-auto pr-1">
            @foreach ($availableSlots as $slot)
              <button
                type="button"
                wire:click="selectSlot('{{ $slot['start_time'] }}')"
                class="py-2.5 px-3 rounded-card text-xs sm:text-sm font-semibold border transition-all duration-200 cursor-pointer {{ $selectedSlot === $slot['start_time'] ? 'border-brand-magenta bg-brand-magenta text-white shadow-md' : 'border-slate-200 hover:border-brand-purple/40 bg-white text-text-body hover:bg-slate-50' }}"
              >
                {{ $slot['display_time'] }}
              </button>
            @endforeach
          </div>
        @endif
        @error('selectedSlot') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
      </div>

      {{-- Step 2 Navigation Buttons --}}
      <div class="pt-4 flex items-center justify-between">
        <button
          type="button"
          wire:click="previousStep"
          class="px-5 py-2.5 rounded-pill border border-slate-200 text-text-muted hover:text-text-body text-xs sm:text-sm font-semibold transition cursor-pointer"
        >
          &larr; Back
        </button>

        <button
          type="button"
          wire:click="nextStep"
          @if (! $selectedSlot) disabled @endif
          class="px-8 py-3.5 rounded-cta bg-brand-magenta hover:bg-brand-magenta-hover disabled:opacity-50 disabled:cursor-not-allowed text-white font-bold text-sm tracking-wide shadow-lg hover:shadow-xl transition-all duration-200 transform hover:-translate-y-0.5 cursor-pointer flex items-center gap-2"
        >
          <span>Continue to Contact</span>
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
          </svg>
        </button>
      </div>
    </div>
  @endif

  {{-- STEP 3: Contact & Confirm Details --}}
  @if ($currentStep === 3 && ! $isBooked)
    <div class="space-y-6">
      <div>
        <h3 class="text-2xl font-bold font-display text-brand-hero tracking-tight">
          Where should we send the invite?
        </h3>
        <p class="text-text-muted text-sm mt-1">
          Review your selected slot and provide your contact details to reserve.
        </p>
      </div>

      {{-- Selected Summary Pill --}}
      <div class="p-4 rounded-card bg-brand-purple/5 border border-brand-purple/20 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 text-sm">
        <div>
          <span class="font-bold text-brand-purple">{{ $roleNeeded }}</span>
          <span class="text-text-muted text-xs block sm:inline sm:ml-2">({{ $hoursPerWeek }} hrs/week &bull; {{ $startDate }})</span>
        </div>
        <div class="text-xs font-semibold text-brand-hero">
          {{ \Carbon\Carbon::parse($selectedSlot, $timezone)->format('M j, Y \a\t g:i A') }} ({{ $timezone }})
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        {{-- Full Name --}}
        <div>
          <label class="block text-xs font-semibold text-text-slate mb-1">Full Name *</label>
          <input
            type="text"
            wire:model="name"
            placeholder="Jane Doe"
            class="w-full text-sm rounded-card border-slate-200 py-2.5 px-3.5 focus:border-brand-purple focus:ring-brand-purple text-text-body bg-white"
          />
          @error('name') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
        </div>

        {{-- Work Email --}}
        <div>
          <label class="block text-xs font-semibold text-text-slate mb-1">Work Email *</label>
          <input
            type="email"
            wire:model="email"
            placeholder="jane@company.com"
            class="w-full text-sm rounded-card border-slate-200 py-2.5 px-3.5 focus:border-brand-purple focus:ring-brand-purple text-text-body bg-white"
          />
          @error('email') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
        </div>

        {{-- Company --}}
        <div>
          <label class="block text-xs font-semibold text-text-slate mb-1">Company / Agency</label>
          <input
            type="text"
            wire:model="company"
            placeholder="Acme Corp"
            class="w-full text-sm rounded-card border-slate-200 py-2.5 px-3.5 focus:border-brand-purple focus:ring-brand-purple text-text-body bg-white"
          />
        </div>

        {{-- Phone Number --}}
        <div>
          <label class="block text-xs font-semibold text-text-slate mb-1">Phone Number (Optional)</label>
          <input
            type="tel"
            wire:model="phone"
            placeholder="+1 (555) 000-0000"
            class="w-full text-sm rounded-card border-slate-200 py-2.5 px-3.5 focus:border-brand-purple focus:ring-brand-purple text-text-body bg-white"
          />
        </div>
      </div>

      {{-- Specific Notes --}}
      <div>
        <label class="block text-xs font-semibold text-text-slate mb-1">Key Objectives / Notes for our Advisor</label>
        <textarea
          wire:model="notes"
          rows="3"
          placeholder="Share your tech stack, current challenges, or any specific requirements..."
          class="w-full text-sm rounded-card border-slate-200 py-2.5 px-3.5 focus:border-brand-purple focus:ring-brand-purple text-text-body bg-white"
        ></textarea>
      </div>

      {{-- Step 3 Navigation Buttons --}}
      <div class="pt-4 flex items-center justify-between">
        <button
          type="button"
          wire:click="previousStep"
          class="px-5 py-2.5 rounded-pill border border-slate-200 text-text-muted hover:text-text-body text-xs sm:text-sm font-semibold transition cursor-pointer"
        >
          &larr; Back
        </button>

        <button
          type="button"
          wire:click="submitBooking"
          wire:loading.attr="disabled"
          class="px-8 py-3.5 rounded-cta bg-gradient-to-r from-brand-purple to-brand-magenta hover:opacity-95 text-white font-bold text-sm tracking-wide shadow-xl transition-all duration-200 transform hover:-translate-y-0.5 cursor-pointer flex items-center gap-2"
        >
          <span wire:loading.remove wire:target="submitBooking">Confirm & Schedule Call</span>
          <span wire:loading wire:target="submitBooking" class="flex items-center gap-2">
            <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            Securing Slot...
          </span>
        </button>
      </div>
    </div>
  @endif

  {{-- CONFIRMATION STATE --}}
  @if ($isBooked)
    <div class="text-center py-6 space-y-6 animate-fadeIn">
      <div class="w-16 h-16 mx-auto rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center shadow-inner">
        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
        </svg>
      </div>

      <div>
        <h3 class="text-2xl sm:text-3xl font-bold font-display text-brand-hero tracking-tight">
          You're All Set, {{ explode(' ', trim($name))[0] }}!
        </h3>
        <p class="text-text-muted text-sm mt-2 max-w-md mx-auto">
          We have reserved your 30-minute strategy consultation. A calendar invite has been dispatched to <span class="font-semibold text-text-body">{{ $email }}</span>.
        </p>
      </div>

      <div class="p-5 max-w-md mx-auto rounded-card bg-slate-50 border border-slate-200 text-left space-y-2">
        <div class="flex items-center justify-between text-xs text-text-slate">
          <span>Scheduled Time</span>
          <span class="font-bold text-text-body">{{ $confirmedTime }}</span>
        </div>
        <div class="flex items-center justify-between text-xs text-text-slate">
          <span>Role Inquired</span>
          <span class="font-bold text-brand-purple">{{ $roleNeeded }}</span>
        </div>
        @if ($bookingReference)
          <div class="flex items-center justify-between text-xs text-text-slate pt-2 border-t border-slate-200/60">
            <span>Reference</span>
            <span class="font-mono text-2xs text-text-muted">{{ substr($bookingReference, 0, 16) }}...</span>
          </div>
        @endif
      </div>

      <div class="flex flex-col sm:flex-row items-center justify-center gap-3 pt-2">
        @if ($meetingUrl)
          <a
            href="{{ $meetingUrl }}"
            target="_blank"
            rel="noopener noreferrer"
            class="w-full sm:w-auto px-6 py-3 rounded-cta bg-brand-purple hover:bg-brand-purple-deep text-white text-xs sm:text-sm font-bold shadow-md transition inline-flex items-center justify-center gap-2"
          >
            <span>Open Meeting Room</span>
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
          </a>
        @endif
        
        <button
          type="button"
          wire:click="$set('isBooked', false); $set('currentStep', 1);"
          class="w-full sm:w-auto px-6 py-3 rounded-cta border border-slate-200 text-text-muted hover:text-text-body text-xs sm:text-sm font-semibold transition"
        >
          Book Another Time
        </button>
      </div>
    </div>
  @endif

</div>
