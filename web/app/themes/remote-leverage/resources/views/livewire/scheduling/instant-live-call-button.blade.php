<div wire:poll.30s="checkAvailability" class="inline-block">
  {{-- Main Trigger Button --}}
  @if ($buttonSize === 'compact')
    <button
      type="button"
      wire:click="openInstantModal"
      class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-pill bg-surface-white/90 hover:bg-white text-text-body border border-slate-200/80 shadow-sm hover:shadow text-xs font-semibold transition-all duration-200 cursor-pointer"
    >
      <span class="relative flex h-2 w-2">
        @if ($isAvailable)
          <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-status-success opacity-75"></span>
          <span class="relative inline-flex rounded-full h-2 w-2 bg-status-success"></span>
        @else
          <span class="relative inline-flex rounded-full h-2 w-2 bg-slate-400"></span>
        @endif
      </span>
      <span>{{ $isAvailable ? 'Talk Now' : 'Schedule' }}</span>
    </button>

  @elseif ($buttonSize === 'hero')
    <button
      type="button"
      wire:click="openInstantModal"
      class="group inline-flex items-center gap-3 px-8 py-4 rounded-cta bg-gradient-to-r from-brand-purple to-brand-magenta hover:opacity-95 text-white font-bold text-base shadow-glow-purple hover:shadow-xl transition-all duration-300 transform hover:-translate-y-0.5 cursor-pointer"
    >
      <span class="relative flex h-3 w-3">
        @if ($isAvailable)
          <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-status-success opacity-75"></span>
          <span class="relative inline-flex rounded-full h-3 w-3 bg-status-success shadow-glow-green"></span>
        @else
          <span class="relative inline-flex rounded-full h-3 w-3 bg-amber-400"></span>
        @endif
      </span>
      <span>{{ $isAvailable ? 'Join Live Consultant Now' : 'Schedule Strategy Call' }}</span>
      <svg class="w-5 h-5 transition-transform group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
      </svg>
    </button>

  @else
    {{-- Default Size --}}
    <button
      type="button"
      wire:click="openInstantModal"
      class="inline-flex items-center gap-2.5 px-5 py-2.5 rounded-pill bg-brand-midnight hover:bg-brand-dark-violet text-white text-xs sm:text-sm font-semibold border border-purple-900/40 shadow-sm hover:shadow-md transition-all duration-200 cursor-pointer"
    >
      <span class="relative flex h-2.5 w-2.5">
        @if ($isAvailable)
          <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-status-success opacity-75"></span>
          <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-status-success"></span>
        @else
          <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-slate-400"></span>
        @endif
      </span>
      <span>{{ $statusLabel }}</span>
    </button>
  @endif

  {{-- Instant Call Modal --}}
  @if ($modalOpen)
    <div 
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-brand-midnight/70 backdrop-blur-sm animate-fadeIn"
      wire:keydown.escape="closeInstantModal"
    >
      <div class="relative w-full max-w-md bg-surface-white rounded-card-lg border border-slate-200/80 shadow-2xl p-6 sm:p-8">
        {{-- Close Button --}}
        <button
          type="button"
          wire:click="closeInstantModal"
          class="absolute top-4 right-4 text-text-muted hover:text-text-body p-1 rounded-full hover:bg-slate-100 transition cursor-pointer"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>

        {{-- Modal Header --}}
        <div class="flex items-center gap-3 mb-4">
          <div class="w-10 h-10 rounded-full bg-emerald-100 flex items-center justify-center text-emerald-600">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
          </div>
          <div>
            <h4 class="text-lg font-bold font-display text-brand-hero">
              {{ $isAvailable ? 'Instant Video Consultation' : 'Consultants in Session' }}
            </h4>
            <div class="flex items-center gap-1.5 text-xs text-text-muted">
              <span class="h-2 w-2 rounded-full {{ $isAvailable ? 'bg-status-success' : 'bg-amber-400' }}"></span>
              <span>{{ $isAvailable ? 'Staffing Director Available' : 'Next Available in 15 mins' }}</span>
            </div>
          </div>
        </div>

        @if ($errorMessage)
          <div class="mb-4 p-3 rounded-card bg-red-50 border border-red-200 text-red-700 text-xs">
            {{ $errorMessage }}
          </div>
        @endif

        @if ($isAvailable)
          <p class="text-xs sm:text-sm text-text-muted mb-5 leading-relaxed">
            Enter your details below to jump into a private Google Meet conference with our US staffing director right now.
          </p>

          <form wire:submit.prevent="connectInstantCall" class="space-y-4">
            <div>
              <label class="block text-xs font-semibold text-text-slate mb-1">Your Full Name</label>
              <input
                type="text"
                wire:model="visitorName"
                placeholder="Alex Mercer"
                class="w-full text-xs sm:text-sm rounded-card border-slate-200 py-2.5 px-3.5 focus:border-brand-purple focus:ring-brand-purple text-text-body bg-white"
                required
              />
              @error('visitorName') <span class="text-red-500 text-2xs mt-1 block">{{ $message }}</span> @enderror
            </div>

            <div>
              <label class="block text-xs font-semibold text-text-slate mb-1">Work Email</label>
              <input
                type="email"
                wire:model="visitorEmail"
                placeholder="alex@company.com"
                class="w-full text-xs sm:text-sm rounded-card border-slate-200 py-2.5 px-3.5 focus:border-brand-purple focus:ring-brand-purple text-text-body bg-white"
                required
              />
              @error('visitorEmail') <span class="text-red-500 text-2xs mt-1 block">{{ $message }}</span> @enderror
            </div>

            <button
              type="submit"
              wire:loading.attr="disabled"
              class="w-full py-3.5 rounded-cta bg-gradient-to-r from-brand-purple to-brand-magenta hover:opacity-95 text-white font-bold text-xs sm:text-sm shadow-lg transition-all duration-200 cursor-pointer flex items-center justify-center gap-2"
            >
              <span wire:loading.remove wire:target="connectInstantCall">Launch Instant Meeting</span>
              <span wire:loading wire:target="connectInstantCall" class="flex items-center gap-2">
                <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Generating Conference...
              </span>
            </button>
          </form>
        @else
          <p class="text-xs sm:text-sm text-text-muted mb-5 leading-relaxed">
            All our senior consultants are currently in live client consultations. Would you like to reserve a guaranteed spot on our calendar?
          </p>
          <a
            href="#booking-wizard"
            wire:click="closeInstantModal"
            class="block w-full text-center py-3 rounded-cta bg-brand-purple hover:bg-brand-purple-deep text-white font-bold text-xs sm:text-sm shadow-md transition"
          >
            Pick a Time on Calendar
          </a>
        @endif
      </div>
    </div>
  @endif
</div>
