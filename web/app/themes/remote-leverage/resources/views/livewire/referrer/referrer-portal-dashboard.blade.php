@php
  /**
   * Status vocabulary, in one place. The old template branched on `closed_won` and fell
   * everything else through to "Pending Review", so a genuinely fulfilled referral was
   * displayed as pending — `closed_won` is not a status this application ever writes.
   * Referral::STATUSES is the source of truth.
   */
  $statusStyles = [
      'pending'   => ['label' => 'Pending review', 'class' => 'bg-amber-100 text-amber-800'],
      'qualified' => ['label' => 'Qualified',      'class' => 'bg-blue-100 text-blue-800'],
      'fulfilled' => ['label' => 'Deal closed',    'class' => 'bg-green-100 text-green-800'],
      'rewarded'  => ['label' => 'Rewarded',       'class' => 'bg-emerald-100 text-emerald-800'],
      'rejected'  => ['label' => 'Not proceeding', 'class' => 'bg-slate-200 text-slate-600'],
  ];

  $toneStyles = [
      'success' => 'bg-status-success',
      'info'    => 'bg-brand-purple',
      'warn'    => 'bg-amber-500',
      'error'   => 'bg-status-alert',
  ];

  $money = fn (float $amount, string $currency) => ($currency === 'USD' ? '$' : $currency.' ').number_format($amount, 2);
@endphp

<div class="w-full">

  {{-- Referrer Authentication Login Screen --}}
  @if (! $isAuthenticated)
    <div class="max-w-md mx-auto my-12">
      @include('partials.referrer-auth-tabs', ['active' => 'login'])

      <div class="bg-surface-white rounded-card-lg border border-slate-200/80 p-8 shadow-card text-center">
        <div class="w-12 h-12 rounded-full bg-brand-purple/10 text-brand-purple mx-auto flex items-center justify-center mb-4">
          {!! app(\App\Infrastructure\WordPress\Admin\WordPressAdminTheme::class)->getIsoSvg('currentColor', 26) !!}
        </div>

        <h2 class="text-2xl font-bold font-display text-brand-hero tracking-tight">Referrer Portal Login</h2>
        <p class="text-xs text-text-muted mt-2 mb-6">Enter your registered referral code or referrer email address to access your live attribution dashboard.</p>

        @if ($loginError)
          <div class="mb-4 p-3 rounded-card bg-red-50 border border-red-200 text-status-alert text-xs text-left">
            {{ $loginError }}
          </div>
        @endif

        <form wire:submit.prevent="authenticateReferrer" class="space-y-4">
          <div>
            <input
              type="text"
              wire:model="lookupCode"
              placeholder="e.g. RL-REFERRER-123 or referrer@agency.com"
              class="w-full h-11 px-3.5 rounded-lg bg-[#F0F3FA] text-slate-900 text-sm focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand-purple transition-all duration-200"
            />
          </div>

          <div>
            <input
              type="password"
              wire:model="password"
              placeholder="Password"
              class="w-full h-11 px-3.5 rounded-lg bg-[#F0F3FA] text-slate-900 text-sm focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand-purple transition-all duration-200"
            />
          </div>

          <button type="submit" class="w-full btn-primary cursor-pointer text-xs">
            Access Dashboard
          </button>
        </form>

        <div class="mt-6 pt-6 border-t border-slate-100 text-xs text-text-muted">
          Don't have a referrer account yet?
          <a href="{{ route('referrer.register') }}" class="font-bold text-brand-purple hover:underline">Apply here</a>.
        </div>
      </div>
    </div>

  @else
    <div class="max-w-7xl mx-auto p-4 sm:p-6 lg:p-8" id="rl-dashboard-app">

      {{-- ── HEADER ────────────────────────────────────────────────────────────── --}}
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
        <div class="flex items-center gap-3">
          <div class="w-11 h-11 rounded-full overflow-hidden shrink-0 border border-slate-200 ring-2 ring-brand-purple/10">
            <img src="{{ Vite::asset('resources/images/avatar.png') }}" alt="Remote Leverage" width="1000" height="1000" class="w-full h-full object-cover" />
          </div>
          <div>
            <h1 class="text-xl sm:text-2xl font-bold font-display text-brand-hero tracking-tight leading-tight">
              Hi {{ $referrer->name }}
            </h1>
            <span class="text-2xs font-bold text-slate-400 tracking-wider uppercase block">Partner Dashboard</span>
          </div>
        </div>

        <div class="flex items-center gap-3">
          {{-- Demo toggle. Rendered only for administrators; the component re-checks the
               capability on every render, so hiding it here is presentation, not the guard. --}}
          @if ($canToggleDemo)
            <button
              type="button"
              wire:click="toggleDemoMode"
              class="inline-flex items-center gap-2 px-3 py-2 rounded-card border text-2xs font-bold uppercase tracking-wider transition cursor-pointer
                {{ $isDemo ? 'bg-brand-purple text-white border-brand-purple shadow-sm' : 'bg-white text-text-muted border-slate-200 hover:border-brand-purple/40 hover:text-brand-purple' }}"
              title="Administrator only — renders bundled sample data for demonstrations"
            >
              <span class="w-1.5 h-1.5 rounded-full {{ $isDemo ? 'bg-white' : 'bg-slate-300' }}"></span>
              <span>{{ $isDemo ? 'Demo data on' : 'Demo data' }}</span>
            </button>
          @endif

          <span class="hidden sm:inline text-xs text-text-muted">
            Code <strong class="text-text-body font-mono">{{ $referrerCode }}</strong>
          </span>

          <button type="button" wire:click="logout" class="text-xs text-status-alert hover:underline cursor-pointer">
            Log out
          </button>
        </div>
      </div>

      @if ($isDemo)
        <div class="mb-6 flex items-start gap-3 p-4 rounded-card-lg bg-amber-50 border border-amber-200">
          <svg class="w-4 h-4 text-amber-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
          </svg>
          <p class="text-xs text-amber-900">
            <strong class="font-bold">Demonstration data.</strong>
            Every figure below is a bundled sample, not this account's real activity. Nothing here is stored and no reward is owed.
          </p>
        </div>
      @endif

      {{-- ── KPI ROW ───────────────────────────────────────────────────────────── --}}
      <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 sm:gap-4 mb-6">
        @php
          $kpis = [
              ['value' => $metrics['reach'],      'label' => 'Link clicks',   'tint' => 'bg-blue-50 text-blue-600'],
              ['value' => $metrics['referrals'],  'label' => 'Referrals',     'tint' => 'bg-purple-50 text-brand-purple'],
              ['value' => $metrics['qualified'],  'label' => 'In progress',   'tint' => 'bg-indigo-50 text-indigo-600'],
              ['value' => $metrics['fulfilled'],  'label' => 'Deals closed',  'tint' => 'bg-green-50 text-status-success'],
              ['value' => $metrics['stale'],      'label' => 'Needs a nudge', 'tint' => 'bg-amber-50 text-amber-600'],
          ];
        @endphp

        @foreach ($kpis as $i => $kpi)
          <div class="bg-surface-white rounded-card-lg border border-slate-200/80 p-4 sm:p-5 shadow-card-subtle {{ $i === 4 ? 'col-span-2 lg:col-span-1' : '' }}">
            <div class="text-2xl sm:text-3xl font-bold text-brand-hero font-display tracking-tight">{{ $kpi['value'] }}</div>
            <div class="text-2xs font-bold text-slate-400 uppercase tracking-wider mt-1">{{ $kpi['label'] }}</div>
          </div>
        @endforeach
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

        {{-- ── LEFT: earnings, link generator, share ─────────────────────────── --}}
        <div class="lg:col-span-4 space-y-6">

          {{-- Earnings --}}
          <div class="bg-surface-white rounded-card-lg border border-slate-200/80 shadow-card overflow-hidden">
            <div class="p-5 border-b border-slate-100">
              <h3 class="text-sm font-bold text-brand-hero font-display">Your commissions</h3>
              <p class="text-2xs text-text-muted mt-0.5">Earned on deals that reach completion.</p>
            </div>

            <div class="p-5 space-y-4">
              <div>
                <div class="text-3xl font-bold text-brand-hero font-display tracking-tight">
                  {{ $money($earnings['total'], $earnings['currency']) }}
                </div>
                <div class="text-2xs font-bold text-slate-400 uppercase tracking-wider mt-1">Total earned</div>
              </div>

              <div class="grid grid-cols-2 gap-3 pt-4 border-t border-slate-100">
                <div>
                  <div class="text-base font-bold text-status-success">{{ $money($earnings['due'], $earnings['currency']) }}</div>
                  <div class="text-2xs text-text-muted mt-0.5">Awaiting payout</div>
                </div>
                <div>
                  <div class="text-base font-bold text-text-body">{{ $money($earnings['issued'], $earnings['currency']) }}</div>
                  <div class="text-2xs text-text-muted mt-0.5">Already paid</div>
                </div>
              </div>

              {{-- Payouts are arranged by the partnerships team. Stripe Connect is switched
                   off (STRIPE_CONNECT_ENABLED), so promising self-serve payout here would be
                   promising something the portal cannot do. --}}
              <p class="text-2xs text-text-muted pt-3 border-t border-slate-100 leading-relaxed">
                Commissions are settled by the partnerships team once a deal completes. Reply to your welcome email with any payout question.
              </p>
            </div>
          </div>

          {{-- Referral link generator --}}
          <div class="bg-surface-white rounded-card-lg border border-slate-200/80 p-5 shadow-card space-y-4">
            <label for="rl-landing-selector" class="block text-xs font-bold uppercase tracking-wider text-text-body">
              Share a landing page
            </label>

            <select
              id="rl-landing-selector"
              wire:change="updatedSelectedLandingUrl($event.target.value)"
              class="w-full px-3 py-2.5 rounded-card border border-slate-200 text-xs font-medium text-text-body bg-white focus:ring-2 focus:ring-brand-purple/20 focus:border-brand-purple cursor-pointer"
            >
              @foreach ($landingPages as $lp)
                <option value="{{ $lp['base_url'] }}">{{ $lp['name'] }}</option>
              @endforeach
            </select>

            <div class="flex items-center gap-2">
              <input
                type="text"
                id="rl-referral-link-input"
                readonly
                value="{{ $selectedLandingUrl }}"
                class="flex-1 min-w-0 px-3 py-2.5 rounded-card border border-slate-200 bg-slate-50 text-xs font-mono text-text-body select-all"
              />
              <button
                type="button"
                onclick="navigator.clipboard.writeText(document.getElementById('rl-referral-link-input').value); this.querySelector('span').textContent='Copied';"
                class="shrink-0 px-4 py-2.5 rounded-card bg-brand-purple hover:bg-brand-purple-deep text-white text-xs font-bold transition flex items-center gap-1.5 cursor-pointer shadow-sm"
              >
                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                  <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                </svg>
                <span>Copy</span>
              </button>
            </div>

            <div class="pt-1">
              <span class="text-2xs font-bold uppercase tracking-wider text-slate-400 block mb-2">Share via</span>
              <div class="flex items-center gap-2.5">
                <a href="https://api.whatsapp.com/send?text={{ urlencode('Scale your team with top 1% Latin American talent: ' . $selectedLandingUrl) }}" target="_blank" rel="noopener" class="p-2 rounded-card bg-green-50 text-green-600 hover:bg-green-100 transition" title="Share on WhatsApp">
                  <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z"/></svg>
                </a>
                <a href="https://www.linkedin.com/sharing/share-offsite/?url={{ urlencode($selectedLandingUrl) }}" target="_blank" rel="noopener" class="p-2 rounded-card bg-blue-50 text-blue-600 hover:bg-blue-100 transition" title="Share on LinkedIn">
                  <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.238-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.79-1.75-1.764s.784-1.764 1.75-1.764 1.75.79 1.75 1.764-.783 1.764-1.75 1.764zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z"/></svg>
                </a>
                <a href="https://twitter.com/intent/tweet?text={{ urlencode('Scale your business operations with Remote Leverage: ' . $selectedLandingUrl) }}" target="_blank" rel="noopener" class="p-2 rounded-card bg-slate-100 text-slate-800 hover:bg-slate-200 transition" title="Share on X">
                  <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                </a>
                <a href="mailto:?subject={{ urlencode('Talent Solution Recommendation') }}&body={{ urlencode('Take a look at Remote Leverage for dedicated remote staffing: ' . $selectedLandingUrl) }}" class="p-2 rounded-card bg-purple-50 text-brand-purple hover:bg-purple-100 transition" title="Share via Email">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                </a>
              </div>
            </div>

            <div class="pt-4 border-t border-slate-100">
              <button
                type="button"
                wire:click="openSubmitLeadModal"
                class="w-full py-3 rounded-card bg-lavender-surface hover:bg-purple-100 text-brand-purple font-bold text-xs uppercase tracking-wider transition border border-brand-purple/20 flex items-center justify-center gap-2 cursor-pointer shadow-sm"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                <span>Submit a prospect</span>
              </button>
            </div>
          </div>
        </div>

        {{-- ── RIGHT: referral pipeline ──────────────────────────────────────── --}}
        <div class="lg:col-span-8">
          <div class="bg-surface-white rounded-card-lg border border-slate-200/80 shadow-card overflow-hidden">

            <div class="p-5 border-b border-slate-100">
              <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                  <h3 class="text-sm font-bold text-brand-hero font-display">Your referrals</h3>
                  <p class="text-2xs text-text-muted mt-0.5">
                    Select any row to see its full history. Anything without movement for {{ $staleDays }} days is flagged.
                  </p>
                </div>

                <input
                  type="search"
                  wire:model.live.debounce.300ms="search"
                  placeholder="Search name or email"
                  class="w-full sm:w-52 px-3 py-2 rounded-card border border-slate-200 text-xs text-text-body focus:ring-2 focus:ring-brand-purple/20 focus:border-brand-purple"
                />
              </div>

              {{-- Status filters --}}
              @php
                $filters = [
                    'all'       => 'All',
                    'pending'   => 'Pending',
                    'qualified' => 'In progress',
                    'fulfilled' => 'Closed',
                    'rewarded'  => 'Rewarded',
                    'stale'     => 'Needs a nudge',
                ];
              @endphp
              <div class="flex flex-wrap items-center gap-1.5 mt-4">
                @foreach ($filters as $value => $label)
                  <button
                    type="button"
                    wire:click="setStatusFilter('{{ $value }}')"
                    class="px-2.5 py-1 rounded-pill text-2xs font-bold uppercase tracking-wider transition cursor-pointer border
                      {{ $statusFilter === $value
                          ? 'bg-brand-purple text-white border-brand-purple'
                          : 'bg-white text-text-muted border-slate-200 hover:border-brand-purple/40 hover:text-brand-purple' }}"
                  >
                    {{ $label }}
                  </button>
                @endforeach
              </div>
            </div>

            <div class="divide-y divide-slate-100">
              @forelse ($referrals as $row)
                @php
                  $style = $statusStyles[$row['status']] ?? $statusStyles['pending'];
                  $isOpen = $expandedReferralId === $row['id'];
                @endphp

                <div>
                  <button
                    type="button"
                    wire:click="toggleTimeline({{ $row['id'] }})"
                    class="w-full text-left p-4 sm:p-5 hover:bg-slate-50/60 transition cursor-pointer flex items-start gap-4"
                    aria-expanded="{{ $isOpen ? 'true' : 'false' }}"
                  >
                    <div class="flex-1 min-w-0">
                      <div class="flex flex-wrap items-center gap-2">
                        <span class="font-semibold text-sm text-text-body truncate">{{ $row['name'] }}</span>

                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-2xs font-bold {{ $style['class'] }}">
                          {{ $style['label'] }}
                        </span>

                        @if ($row['is_stale'])
                          <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-2xs font-bold bg-amber-100 text-amber-800">
                            <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                            No movement {{ $row['days_since_change'] }}d
                          </span>
                        @endif
                      </div>

                      <div class="text-2xs text-text-muted mt-1 flex flex-wrap items-center gap-x-3 gap-y-0.5">
                        <span>{{ $row['email'] }}</span>
                        <span>Referred {{ $row['date'] }}</span>
                        @if ($row['stage'])
                          <span class="font-semibold text-text-body">{{ $row['stage'] }}</span>
                        @elseif ($row['awaiting_sync'])
                          {{-- Distinguished from "nothing has happened": an unsynced lead has no
                               known stage, and showing it as stalled would blame the prospect
                               for an integration that has not run yet. --}}
                          <span class="italic">Awaiting first sync</span>
                        @endif
                      </div>
                    </div>

                    <div class="text-right shrink-0">
                      <div class="text-sm font-bold text-text-body">
                        {{ $row['payout'] > 0 ? $money($row['payout'], $row['currency']) : '—' }}
                      </div>
                      <div class="text-2xs text-text-muted mt-0.5">
                        {{ $isOpen ? 'Hide' : 'History' }}
                      </div>
                    </div>
                  </button>

                  @if ($isOpen)
                    <div class="px-4 sm:px-5 pb-5 -mt-1">
                      <div class="rounded-card bg-slate-50/80 border border-slate-100 p-4">
                        @if (count($row['timeline']) > 0)
                          <ol class="space-y-3">
                            @foreach ($row['timeline'] as $entry)
                              <li class="flex items-start gap-3">
                                <span class="w-1.5 h-1.5 rounded-full mt-1.5 shrink-0 {{ $toneStyles[$entry['tone']] ?? 'bg-slate-300' }}"></span>
                                <div class="flex-1 min-w-0 flex flex-wrap items-baseline justify-between gap-x-3">
                                  <span class="text-xs text-text-body">{{ $entry['label'] }}</span>
                                  <time datetime="{{ $entry['iso'] }}" class="text-2xs text-text-muted">{{ $entry['at'] }}</time>
                                </div>
                              </li>
                            @endforeach
                          </ol>
                        @else
                          <p class="text-2xs text-text-muted">
                            No activity recorded for this referral yet.
                          </p>
                        @endif

                        @if ($row['is_stale'])
                          <p class="text-2xs text-amber-800 mt-4 pt-3 border-t border-slate-200">
                            Last movement {{ $row['last_change_at'] }} — {{ $row['days_since_change'] }} days ago. A short check-in with your contact often restarts these.
                          </p>
                        @endif
                      </div>
                    </div>
                  @endif
                </div>

              @empty
                <div class="py-12 text-center px-5">
                  <p class="text-sm text-text-body font-semibold">
                    {{ $totalReferrals > 0 ? 'No referrals match this filter.' : 'No referrals yet.' }}
                  </p>
                  <p class="text-xs text-text-muted mt-1">
                    {{ $totalReferrals > 0
                        ? 'Try a different status or clear your search.'
                        : 'Share your link or submit a prospect to start tracking.' }}
                  </p>
                </div>
              @endforelse
            </div>

            @if ($hasMore)
              <div class="p-4 border-t border-slate-100 text-center">
                <button
                  type="button"
                  wire:click="loadMore"
                  class="px-4 py-2 rounded-card bg-slate-100 hover:bg-slate-200 text-text-body text-xs font-semibold cursor-pointer transition"
                >
                  Show more ({{ $filteredCount - count($referrals) }} remaining)
                </button>
              </div>
            @endif
          </div>
        </div>

      </div>
    </div>

    {{-- ── DIRECT LEAD SUBMISSION MODAL ──────────────────────────────────────── --}}
    @if ($showLeadModal)
      <div class="fixed inset-0 z-50 bg-brand-midnight/70 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-surface-white rounded-card-lg max-w-lg w-full p-6 sm:p-8 shadow-2xl relative border border-slate-200/80">

          <div class="flex items-center justify-between pb-4 border-b border-slate-100">
            <div class="flex items-center gap-2.5">
              <div class="w-8 h-8 rounded-card bg-brand-purple/10 text-brand-purple flex items-center justify-center">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                  <circle cx="12" cy="7" r="4"></circle>
                </svg>
              </div>
              <h3 class="text-base font-bold text-brand-hero font-display">Submit Referral Lead</h3>
            </div>
            <button type="button" wire:click="closeSubmitLeadModal" class="text-slate-400 hover:text-slate-700 text-xl font-bold cursor-pointer">&times;</button>
          </div>

          <div class="py-4 space-y-4">
            <p class="text-xs text-text-muted">
              Refer a prospective client directly. Once the deal completes, your commission is credited automatically.
            </p>

            @if ($leadModalError)
              <div class="p-3 rounded-card bg-red-50 border border-red-200 text-status-alert text-xs">{{ $leadModalError }}</div>
            @endif

            @if ($leadModalSuccess)
              <div class="p-3 rounded-card bg-green-50 border border-green-200 text-status-success text-xs">{{ $leadModalSuccess }}</div>
            @endif

            <div class="space-y-3">
              <div>
                <label class="block text-2xs font-bold uppercase tracking-wider text-text-body mb-1">Lead Full Name *</label>
                <input type="text" wire:model="leadModalName" placeholder="e.g. David Vance" class="w-full px-3 py-2 rounded-card border border-slate-200 text-xs text-text-body focus:ring-2 focus:ring-brand-purple/20 focus:border-brand-purple" />
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                  <label class="block text-2xs font-bold uppercase tracking-wider text-text-body mb-1">Lead Email</label>
                  <input type="email" wire:model="leadModalEmail" placeholder="david@company.com" class="w-full px-3 py-2 rounded-card border border-slate-200 text-xs text-text-body focus:ring-2 focus:ring-brand-purple/20 focus:border-brand-purple" />
                </div>
                <div>
                  <label class="block text-2xs font-bold uppercase tracking-wider text-text-body mb-1">Lead Phone</label>
                  <input type="tel" wire:model="leadModalPhone" placeholder="+1 (555) 000-0000" class="w-full px-3 py-2 rounded-card border border-slate-200 text-xs text-text-body focus:ring-2 focus:ring-brand-purple/20 focus:border-brand-purple" />
                </div>
              </div>

              <div>
                <label class="block text-2xs font-bold uppercase tracking-wider text-text-body mb-1">Target Service</label>
                <select wire:model="leadModalLandingPage" class="w-full px-3 py-2 rounded-card border border-slate-200 text-xs text-text-body bg-white cursor-pointer">
                  @foreach ($landingPages as $lp)
                    <option value="{{ $lp['name'] }}">{{ $lp['name'] }}</option>
                  @endforeach
                </select>
              </div>

              <div>
                <label class="block text-2xs font-bold uppercase tracking-wider text-text-body mb-1">Project Notes (Optional)</label>
                <textarea wire:model="leadModalNotes" rows="2" placeholder="Role requirements, headcount needed, or timeline..." class="w-full px-3 py-2 rounded-card border border-slate-200 text-xs text-text-body focus:ring-2 focus:ring-brand-purple/20 focus:border-brand-purple"></textarea>
              </div>
            </div>

            <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-3">
              <button type="button" wire:click="closeSubmitLeadModal" class="px-4 py-2 rounded-card bg-slate-100 hover:bg-slate-200 text-text-body text-xs font-semibold cursor-pointer transition">Cancel</button>
              <button type="button" wire:click="submitDirectLead" class="px-5 py-2 rounded-card bg-brand-purple hover:bg-brand-purple-deep text-white text-xs font-bold cursor-pointer transition shadow-sm">Submit Lead</button>
            </div>
          </div>

        </div>
      </div>
    @endif

  @endif
</div>
