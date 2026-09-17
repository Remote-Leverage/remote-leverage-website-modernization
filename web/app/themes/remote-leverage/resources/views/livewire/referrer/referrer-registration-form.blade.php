<div class="w-full max-w-xl mx-auto">

  {{-- The switcher only belongs on the application form itself; once an application is
       submitted the success panel takes over and its own CTAs lead onward. --}}
  @if (! $isSubmitted)
    @include('partials.referrer-auth-tabs', ['active' => 'sign-up'])
  @endif

  <div class="bg-surface-white rounded-card-lg border border-slate-200/80 shadow-card p-6 sm:p-10 transition-all">

    @if (! $isSubmitted)
      <div>
        {{-- Centred to match the login card, whose wrapper carries text-center. The two
             cards sit in the same place under the switcher, so a flip in alignment reads
             as the page jumping when you change tabs. --}}
        <div class="mb-6 text-center">
          <span class="inline-block px-3 py-1 rounded-pill bg-brand-purple/10 text-brand-purple text-xs font-bold uppercase tracking-wider">
            Strategic Network
          </span>
          <h3 class="text-2xl sm:text-3xl font-bold font-display text-brand-hero tracking-tight mt-2">
            Join the Remote Leverage Referral Network
          </h3>
          <p class="text-text-muted text-xs sm:text-sm mt-1 leading-relaxed">
            Refer clients who need remote executive talent to Remote Leverage and earn recurring commissions on every signup — no cost to you or your clients.
          </p>
        </div>

        @if ($errorMessage)
          <div class="mb-5 p-3.5 rounded-card bg-red-50 border border-red-200 text-red-700 text-xs flex items-center gap-2">
            <svg class="w-4 h-4 text-red-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span>{{ $errorMessage }}</span>
          </div>
        @endif

        <form wire:submit.prevent="submitApplication" class="space-y-4">
          <div>
            <label class="block text-xs sm:text-[13px] font-bold text-text-slate mb-2">Your Full Name *</label>
            <input
              type="text"
              wire:model="name"
              placeholder="Jordan Hayes"
              class="w-full h-11 px-3.5 rounded-lg bg-[#F0F3FA] text-slate-900 text-sm focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand-purple transition-all duration-200"
              required
            />
            @error('name') <span class="text-red-500 text-2xs mt-1 block">{{ $message }}</span> @enderror
          </div>

          <div>
            <label class="block text-xs sm:text-[13px] font-bold text-text-slate mb-2">Email Address *</label>
            <input
              type="email"
              wire:model="email"
              placeholder="jordan@venture.com"
              class="w-full h-11 px-3.5 rounded-lg bg-[#F0F3FA] text-slate-900 text-sm focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand-purple transition-all duration-200"
              required
            />
            @error('email') <span class="text-red-500 text-2xs mt-1 block">{{ $message }}</span> @enderror
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs sm:text-[13px] font-bold text-text-slate mb-2">Password *</label>
              <input
                type="password"
                wire:model="password"
                placeholder="At least 8 characters"
                class="w-full h-11 px-3.5 rounded-lg bg-[#F0F3FA] text-slate-900 text-sm focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand-purple transition-all duration-200"
                required
              />
              @error('password') <span class="text-red-500 text-2xs mt-1 block">{{ $message }}</span> @enderror
            </div>

            <div>
              <label class="block text-xs sm:text-[13px] font-bold text-text-slate mb-2">Confirm Password *</label>
              <input
                type="password"
                wire:model="password_confirmation"
                placeholder="Re-enter your password"
                class="w-full h-11 px-3.5 rounded-lg bg-[#F0F3FA] text-slate-900 text-sm focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand-purple transition-all duration-200"
                required
              />
            </div>
          </div>

          <div class="pt-3">
            <button
              type="submit"
              wire:loading.attr="disabled"
              class="w-full py-3.5 rounded-cta bg-gradient-to-r from-brand-purple to-brand-magenta hover:opacity-95 text-white font-bold text-sm tracking-wide shadow-lg hover:shadow-xl transition-all duration-200 cursor-pointer flex items-center justify-center gap-2"
            >
              <span wire:loading.remove wire:target="submitApplication">Create Referrer Account</span>
              <span wire:loading.flex wire:target="submitApplication" class="flex items-center gap-2">
                <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                Registering Referrer...
              </span>
            </button>
          </div>

          <p class="text-center text-2xs text-text-muted mt-2">
            By applying, you agree to Remote Leverage affiliate terms. Payouts are processed automatically.
          </p>
        </form>

        {{-- Mirrors the "Apply here" link on the login card, so each half of the flow
             names the other in prose as well as on the switcher above. --}}
        <div class="mt-6 pt-6 border-t border-slate-100 text-xs text-text-muted text-center">
          Already have a referrer account?
          <a href="{{ route('referrer.portal') }}" class="font-bold text-brand-purple hover:underline">Log in</a>.
        </div>
      </div>

    {{-- SUCCESS CONFIRMATION STATE --}}
    @else
      <div class="text-center py-4 space-y-6 animate-fadeIn" x-data="{ copied: false }">
        <div class="w-16 h-16 mx-auto rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center">
          <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
        </div>

        <div>
          <h3 class="text-2xl sm:text-3xl font-bold font-display text-brand-hero tracking-tight">
            Welcome to the Network, {{ $createdReferrer->name }}!
          </h3>
          <p class="text-text-muted text-xs sm:text-sm mt-1 max-w-md mx-auto">
            Your referrer account has been activated with instant lead tracking.
          </p>
        </div>

        {{-- Referral Link Box --}}
        <div class="p-4 rounded-card bg-slate-50 border border-slate-200 text-left space-y-2 max-w-md mx-auto">
          <div class="text-xs font-semibold text-text-slate uppercase tracking-wider">Your Referral Link</div>
          <div class="flex items-center gap-2">
            <input
              type="text"
              readonly
              value="https://remoteleverage.com/?via={{ $referralCode }}"
              class="w-full text-xs font-mono py-2 px-3 rounded-card bg-white border border-slate-200 text-text-body select-all"
            />
            <button
              type="button"
              @click="
                navigator.clipboard.writeText('https://remoteleverage.com/?via={{ $referralCode }}');
                copied = true;
                setTimeout(() => copied = false, 2500);
              "
              class="px-3.5 py-2 rounded-card bg-brand-purple text-white text-xs font-bold shrink-0 hover:bg-brand-purple-deep transition cursor-pointer"
            >
              <span x-show="!copied">Copy</span>
              <span x-show="copied" x-cloak>Copied!</span>
            </button>
          </div>
        </div>

        <div class="pt-2 flex flex-col sm:flex-row items-center justify-center gap-3">
          <a
            href="{{ home_url('/referrer-portal?referrer=' . $referralCode) }}"
            class="w-full sm:w-auto px-6 py-3 rounded-cta bg-brand-magenta hover:bg-brand-magenta-hover text-white text-xs sm:text-sm font-bold shadow-md transition inline-flex items-center justify-center gap-2"
          >
            <span>Open Referrer Dashboard</span>
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
          </a>
        </div>
      </div>
    @endif

  </div>

</div>
