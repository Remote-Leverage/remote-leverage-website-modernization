<div class="w-full max-w-5xl mx-auto p-4 sm:p-6 lg:p-8">

  {{-- UN-AUTHENTICATED STATE: Partner Lookup --}}
  @if (! $isAuthenticated)
    <div class="max-w-md mx-auto bg-surface-white rounded-card-lg border border-slate-200/80 shadow-card p-8 text-center">
      <div class="w-12 h-12 mx-auto rounded-full bg-brand-purple/10 text-brand-purple flex items-center justify-center mb-4">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
        </svg>
      </div>

      <h3 class="text-2xl font-bold font-display text-brand-hero tracking-tight">
        Partner Portal
      </h3>
      <p class="text-text-muted text-xs sm:text-sm mt-1 mb-6">
        Access your referral links, tracking metrics, and commission payouts.
      </p>

      @if ($loginError)
        <div class="mb-4 p-3 rounded-card bg-red-50 border border-red-200 text-red-700 text-xs text-left">
          {{ $loginError }}
        </div>
      @endif

      <form wire:submit.prevent="authenticatePartner" class="space-y-4">
        <div>
          <label class="block text-xs font-semibold text-text-slate mb-1 text-left">
            Partner Code or Email
          </label>
          <input
            type="text"
            wire:model="lookupCode"
            placeholder="e.g. adrian-492a or you@agency.com"
            class="w-full text-sm rounded-card border-slate-200 py-2.5 px-3.5 focus:border-brand-purple focus:ring-brand-purple text-text-body bg-white"
            required
          />
        </div>

        <button
          type="submit"
          wire:loading.attr="disabled"
          class="w-full py-3 rounded-cta bg-brand-purple hover:bg-brand-purple-deep text-white font-bold text-sm shadow-md transition cursor-pointer flex items-center justify-center gap-2"
        >
          <span wire:loading.remove wire:target="authenticatePartner">Access Portal</span>
          <span wire:loading wire:target="authenticatePartner">Authenticating...</span>
        </button>
      </form>

      <div class="mt-6 pt-6 border-t border-slate-100 text-xs text-text-muted">
        Not a partner yet? 
        <a href="#register" class="text-brand-purple font-semibold hover:underline">Apply to our Partner Network</a>
      </div>
    </div>

  {{-- AUTHENTICATED STATE: Partner Dashboard --}}
  @else
    <div class="space-y-8 animate-fadeIn">
      
      {{-- Dashboard Top Bar --}}
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-6 border-b border-slate-200/60">
        <div>
          <div class="flex items-center gap-3">
            <h2 class="text-2xl sm:text-3xl font-bold font-display text-brand-hero">
              Welcome back, {{ $partner->name }}
            </h2>
            <span class="px-2.5 py-0.5 rounded-pill bg-emerald-100 text-emerald-800 text-2xs font-bold uppercase tracking-wider">
              Active Partner
            </span>
          </div>
          <p class="text-text-muted text-xs sm:text-sm mt-0.5">
            Partner Code: <span class="font-mono font-bold text-text-body">{{ $partnerCode }}</span>
            @if ($partner->company) &bull; {{ $partner->company }} @endif
          </p>
        </div>

        <button
          type="button"
          wire:click="logout"
          class="self-start sm:self-auto px-4 py-2 rounded-pill border border-slate-200 text-text-muted hover:text-text-body text-xs font-semibold hover:bg-slate-50 transition cursor-pointer"
        >
          Sign Out
        </button>
      </div>

      {{-- Referral Link Card --}}
      <div 
        x-data="{ copied: false }"
        class="p-6 rounded-card-lg bg-gradient-to-r from-brand-midnight to-brand-hero text-white shadow-xl relative overflow-hidden"
      >
        <div class="relative z-10 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
          <div class="space-y-1 max-w-xl">
            <span class="text-xs font-bold uppercase tracking-wider text-purple-300">Your Unique Tracking Link</span>
            <h3 class="text-lg font-bold text-white">Share with your clients & network</h3>
            <p class="text-xs text-slate-300">
              Includes 24-hour IP deduplication and 90-day cookie attribution for all incoming leads.
            </p>
          </div>

          <div class="flex items-center gap-2 bg-white/10 backdrop-blur-md rounded-pill p-1.5 border border-white/20">
            <input
              type="text"
              readonly
              value="{{ $this->referralUrl }}"
              class="bg-transparent border-0 text-white font-mono text-xs px-3 py-1 focus:ring-0 w-64 select-all"
            />
            <button
              type="button"
              @click="
                navigator.clipboard.writeText('{{ $this->referralUrl }}');
                copied = true;
                setTimeout(() => copied = false, 2500);
              "
              class="px-4 py-2 rounded-pill bg-brand-magenta hover:bg-brand-magenta-hover text-white text-xs font-bold transition shadow cursor-pointer flex items-center gap-1.5 shrink-0"
            >
              <span x-show="!copied">Copy Link</span>
              <span x-show="copied" x-cloak class="flex items-center gap-1">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                Copied!
              </span>
            </button>
          </div>
        </div>
      </div>

      {{-- KPI Stats Grid --}}
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        {{-- Total Clicks --}}
        <div class="p-5 rounded-card bg-surface-white border border-slate-200/80 shadow-card">
          <div class="text-xs font-semibold text-text-slate uppercase tracking-wider">Referral Clicks</div>
          <div class="text-3xl font-extrabold font-display text-brand-hero mt-2">
            {{ number_format($metrics['total_clicks']) }}
          </div>
          <div class="text-2xs text-text-muted mt-1">Unique visitor sessions</div>
        </div>

        {{-- Total Leads --}}
        <div class="p-5 rounded-card bg-surface-white border border-slate-200/80 shadow-card">
          <div class="text-xs font-semibold text-text-slate uppercase tracking-wider">Booked Consultations</div>
          <div class="text-3xl font-extrabold font-display text-brand-purple mt-2">
            {{ number_format($metrics['total_leads']) }}
          </div>
          <div class="text-2xs text-text-muted mt-1">{{ $metrics['conversion_rate'] }}% Conversion Rate</div>
        </div>

        {{-- Pending Payout --}}
        <div class="p-5 rounded-card bg-surface-white border border-slate-200/80 shadow-card">
          <div class="text-xs font-semibold text-text-slate uppercase tracking-wider">Pending Rewards</div>
          <div class="text-3xl font-extrabold font-display text-brand-orange mt-2">
            ${{ number_format($metrics['pending_payout'], 2) }}
          </div>
          <div class="text-2xs text-text-muted mt-1">Clearing on next cycle</div>
        </div>

        {{-- Lifetime Earnings --}}
        <div class="p-5 rounded-card bg-surface-white border border-slate-200/80 shadow-card">
          <div class="text-xs font-semibold text-text-slate uppercase tracking-wider">Total Earnings</div>
          <div class="text-3xl font-extrabold font-display text-emerald-600 mt-2">
            ${{ number_format($metrics['lifetime_earnings'], 2) }}
          </div>
          <div class="text-2xs text-text-muted mt-1">All-time commission generated</div>
        </div>
      </div>

      {{-- Recent Activity / Payouts --}}
      <div class="bg-surface-white rounded-card-lg border border-slate-200/80 shadow-card overflow-hidden">
        <div class="p-6 border-b border-slate-100 flex items-center justify-between">
          <h3 class="text-lg font-bold font-display text-brand-hero">
            Commission & Reward History
          </h3>
          <span class="text-xs text-text-muted">Updated automatically</span>
        </div>

        @if (empty($recentActivity))
          <div class="p-8 text-center text-text-muted text-sm">
            No commissions recorded yet. Share your referral link above to start generating rewards!
          </div>
        @else
          <div class="divide-y divide-slate-100">
            @foreach ($recentActivity as $activity)
              <div class="p-4 sm:px-6 flex items-center justify-between hover:bg-slate-50 transition">
                <div class="flex items-center gap-3">
                  <div class="w-8 h-8 rounded-full bg-brand-purple/10 text-brand-purple flex items-center justify-center font-bold text-xs">
                    $
                  </div>
                  <div>
                    <div class="font-bold text-text-body text-sm">Referral Commission</div>
                    <div class="text-2xs text-text-muted">{{ $activity['date'] }}</div>
                  </div>
                </div>

                <div class="flex items-center gap-4">
                  <div class="text-right">
                    <div class="font-extrabold text-sm text-text-body">{{ $activity['amount'] }}</div>
                    <div class="text-2xs text-text-slate">{{ $activity['currency'] }}</div>
                  </div>
                  <span class="px-2.5 py-1 rounded-pill text-2xs font-semibold {{ $activity['status'] === 'paid' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                    {{ ucfirst($activity['status']) }}
                  </span>
                </div>
              </div>
            @endforeach
          </div>
        @endif
      </div>

    </div>
  @endif

</div>
