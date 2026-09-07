<div wire:poll.30s="checkAvailability" class="inline-block">
  {{-- Trigger Button or Floating Pill Card --}}
  @if ($layoutStyle === 'pill')
    @if (! $isDismissed)
      <div class="inline-flex items-center gap-4 py-2 px-3 sm:px-4 rounded-pill bg-surface-white/95 backdrop-blur-md border-2 border-lavender-tint shadow-card hover:shadow-lg transition-all duration-300">
        <div class="flex -space-x-3 items-center">
          <img src="{{ Vite::asset('resources/images/avatar1.jpg') }}" alt="Sales Rep" class="w-10 h-10 rounded-full object-cover ring-2 ring-white shadow-sm" />
          <img src="{{ Vite::asset('resources/images/avatar2.jpg') }}" alt="Sales Manager" class="w-10 h-10 rounded-full object-cover ring-2 ring-white shadow-sm" />
        </div>
        <div class="text-left">
          <div class="text-xs font-bold font-display text-brand-hero tracking-tight">Connect with Sales</div>
          <div class="text-2xs text-text-muted flex items-center gap-1.5 font-medium mt-0.5">
            <span class="relative flex h-2 w-2">
              <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-status-success opacity-75"></span>
              <span class="relative inline-flex rounded-full h-2 w-2 bg-status-success"></span>
            </span>
            <span>{{ $isAvailable ? 'Experts available now' : 'Schedule a call' }}</span>
          </div>
        </div>
        <button 
          type="button" 
          wire:click="openInstantModal" 
          class="btn-primary py-2 px-4 text-2xs uppercase tracking-wider cursor-pointer shadow-sm ml-1"
        >
          <span>{{ $buttonText }}</span>
        </button>
        <button 
          type="button" 
          wire:click="dismissPill" 
          class="text-slate-400 hover:text-slate-700 text-lg leading-none p-1 cursor-pointer transition" 
          aria-label="Dismiss"
        >&times;</button>
      </div>
    @endif
  @else
    <button 
      type="button" 
      wire:click="openInstantModal" 
      class="inline-flex items-center justify-between gap-6 h-15.25 py-1.5 pl-7 pr-2 rounded-pill bg-white border-2 border-lavender-tint shadow-card hover:shadow-lg hover:border-brand-purple/40 transition-all duration-200 cursor-pointer"
    >
      <div class="text-left">
        <span class="text-sm font-bold uppercase tracking-wider text-brand-hero font-display block">{{ $buttonText }}</span>
        @if ($buttonStatusText !== '')
          <div class="text-2xs text-text-muted flex items-center gap-1.5 mt-0.5">
            <span>{{ $buttonStatusText }}</span>
            <span class="h-2 w-2 rounded-full bg-status-success"></span>
          </div>
        @endif
      </div>
      <div class="w-11 h-11 rounded-full bg-brand-purple text-white flex items-center justify-center shadow-sm">
        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
      </div>
    </button>
  @endif

  {{-- Instant Call Modal (Authentic Split Layout) --}}
  @if ($modalOpen)
    <div 
      x-data="{
        secondsLeft: 5,
        timer: null,
        startCountdown() {
          this.secondsLeft = 5;
          if (this.timer) clearInterval(this.timer);
          this.timer = setInterval(() => {
            this.secondsLeft--;
            if (this.secondsLeft <= 0) {
              clearInterval(this.timer);
              @if ($activeMeetUrl)
                window.open('{{ $activeMeetUrl }}', '_blank');
              @endif
            }
          }, 1000);
        }
      }"
      x-init="$watch('$wire.currentStep', value => { if (value === 3) startCountdown(); })"
      class="fixed inset-0 z-50 bg-brand-midnight/75 backdrop-blur-sm flex items-center justify-center p-4"
    >
      <div class="relative w-full max-w-3xl bg-surface-white rounded-card-lg border border-slate-200/80 shadow-2xl overflow-hidden grid grid-cols-1 md:grid-cols-12">
        <button 
          type="button" 
          wire:click="closeInstantModal" 
          class="absolute top-4 right-4 z-10 w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 hover:text-slate-800 flex items-center justify-center transition cursor-pointer" 
          aria-label="Close"
        >
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
        </button>

        {{-- LEFT COLUMN: Value Proposition & Branding --}}
        <div class="md:col-span-5 bg-linear-to-br from-brand-hero via-brand-midnight to-brand-dark-violet text-white p-6 sm:p-8 flex flex-col justify-between">
          <div>
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-pill bg-white/10 text-white text-2xs font-bold uppercase tracking-wider mb-3">
              <span class="relative flex h-2 w-2">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-status-success opacity-75"></span>
                <span class="relative inline-flex rounded-full h-2 w-2 bg-status-success"></span>
              </span>
              <span>Instant Sales Call</span>
            </div>
            <h3 class="text-xl font-bold font-display text-white tracking-tight mb-2">{{ $formTitle }}</h3>
            <p class="text-xs text-slate-300 leading-relaxed mb-6">{{ $formSubtitle }}</p>

            <ul class="space-y-3 text-xs text-slate-200">
              <li class="flex items-start gap-2.5">
                <span class="w-5 h-5 rounded-full bg-emerald-500/20 text-status-success flex items-center justify-center shrink-0 mt-0.5">
                  <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                </span>
                <span><strong>Instant Connection</strong> &mdash; Auto-dispatches to an available rep</span>
              </li>
              <li class="flex items-start gap-2.5">
                <span class="w-5 h-5 rounded-full bg-emerald-500/20 text-status-success flex items-center justify-center shrink-0 mt-0.5">
                  <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                </span>
                <span><strong>Private Google Meet Room</strong> &mdash; 1-on-1 video call</span>
              </li>
              <li class="flex items-start gap-2.5">
                <span class="w-5 h-5 rounded-full bg-emerald-500/20 text-status-success flex items-center justify-center shrink-0 mt-0.5">
                  <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                </span>
                <span><strong>15-Minute Session</strong> &mdash; Fast, focused discussion</span>
              </li>
            </ul>
          </div>

          <div class="pt-6 mt-6 border-t border-white/10 flex items-center gap-2 text-2xs font-bold text-status-success uppercase tracking-wider">
            <span class="relative flex h-2 w-2">
              <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-status-success opacity-75"></span>
              <span class="relative inline-flex rounded-full h-2 w-2 bg-status-success"></span>
            </span>
            <span>Representatives Active &amp; Ready</span>
          </div>
        </div>

        {{-- RIGHT COLUMN: Lead Form & Call Resolution --}}
        <div class="md:col-span-7 p-6 sm:p-8 bg-white flex flex-col justify-center">
          @if ($errorMessage)
            <div class="p-3 rounded-card bg-red-50 border border-red-200 text-status-alert text-xs mb-4">
              {{ $errorMessage }}
            </div>
          @endif

          {{-- STEP 1: LEAD FORM STEP --}}
          @if ($currentStep === 1)
            <div>
              <div class="mb-4">
                <h4 class="text-base font-bold font-display text-brand-hero">Enter Your Details</h4>
                <p class="text-xs text-text-muted">Jump straight into the video room once submitted.</p>
              </div>

              <form wire:submit.prevent="connectInstantCall" class="space-y-4">
                <div>
                  <label class="block text-2xs font-bold uppercase tracking-wider text-text-body mb-1">Full Name *</label>
                  <input 
                    type="text" 
                    wire:model="visitorName" 
                    placeholder="John Doe" 
                    class="w-full px-4 py-2.5 rounded-card border border-slate-200 text-xs text-text-body focus:ring-2 focus:ring-brand-purple/20 focus:border-brand-purple bg-white"
                    required 
                  />
                  @error('visitorName') <span class="text-status-alert text-2xs mt-1 block">{{ $message }}</span> @enderror
                </div>

                <div>
                  <label class="block text-2xs font-bold uppercase tracking-wider text-text-body mb-1">Email Address *</label>
                  <input 
                    type="email" 
                    wire:model="visitorEmail" 
                    placeholder="john@example.com" 
                    class="w-full px-4 py-2.5 rounded-card border border-slate-200 text-xs text-text-body focus:ring-2 focus:ring-brand-purple/20 focus:border-brand-purple bg-white"
                    required 
                  />
                  @error('visitorEmail') <span class="text-status-alert text-2xs mt-1 block">{{ $message }}</span> @enderror
                </div>

                <div>
                  <label class="block text-2xs font-bold uppercase tracking-wider text-text-body mb-1">Phone Number (Optional)</label>
                  <input 
                    type="tel" 
                    wire:model="visitorPhone" 
                    placeholder="+1 (555) 000-0000" 
                    class="w-full px-4 py-2.5 rounded-card border border-slate-200 text-xs text-text-body focus:ring-2 focus:ring-brand-purple/20 focus:border-brand-purple bg-white"
                  />
                  @error('visitorPhone') <span class="text-status-alert text-2xs mt-1 block">{{ $message }}</span> @enderror
                </div>

                <button 
                  type="submit" 
                  class="w-full btn-primary cursor-pointer text-xs flex items-center justify-center gap-2 mt-2"
                >
                  <span>Connect &amp; Join Call</span>
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                </button>
              </form>
            </div>
          @endif

          {{-- STEP 2: CONNECTING STATE --}}
          @if ($currentStep === 2)
            <div class="text-center py-10 space-y-4">
              <div class="w-12 h-12 rounded-full border-4 border-brand-purple/20 border-t-brand-purple animate-spin mx-auto"></div>
              <h4 class="text-base font-bold font-display text-brand-hero">Allocating Representative...</h4>
              <p class="text-xs text-text-muted max-w-xs mx-auto">Please hold while we prepare your Google Meet room link.</p>
            </div>
          @endif

          {{-- STEP 3: CALL CONFIRMATION STEP --}}
          @if ($currentStep === 3)
            <div class="text-center py-6 space-y-4">
              <div class="inline-flex items-center gap-2 px-3 py-1 rounded-pill bg-green-50 text-status-success text-2xs font-bold uppercase tracking-wider">
                <span class="relative flex h-2 w-2">
                  <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-status-success opacity-75"></span>
                  <span class="relative inline-flex rounded-full h-2 w-2 bg-status-success"></span>
                </span>
                <span>Call Assigned &amp; Ready</span>
              </div>
              <h4 class="text-base font-bold font-display text-brand-hero">
                Representative joined the meeting room, click below to join the consultation
              </h4>

              @if ($activeMeetUrl)
                <a 
                  href="{{ $activeMeetUrl }}" 
                  target="_blank" 
                  rel="noopener" 
                  class="btn-magenta cursor-pointer shadow-lg inline-flex items-center gap-2 py-3.5 px-6 text-xs uppercase tracking-wider"
                >
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                  <span>Click to Join Google Meet Call</span>
                </a>
              @endif

              <div class="text-xs text-text-muted">
                Auto-opening in <span class="font-bold text-brand-purple font-mono" x-text="secondsLeft">5</span>s...
              </div>
            </div>
          @endif
        </div>
      </div>
    </div>
  @endif
</div>
