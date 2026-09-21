@php
  /**
   * Status vocabulary, in one place. Referral::STATUSES is the source of truth — the old
   * template branched on `closed_won`, which nothing writes, so fulfilled referrals displayed
   * as "Pending Review".
   *
   * The words are ReferralTimeline::MAP's, deliberately: the expanded history under a row
   * narrates a referral as "Referral received" then "Consultation booked", and the badge above
   * it used to call the same two states "Pending review" and "In progress". Three vocabularies
   * for one pipeline — badge, filter chip and history — read as three different things
   * happening. `rewarded` is the one status the history has no entry for, because nothing logs
   * a payout as a lead event; "Reward paid" keeps its register and says what the status means.
   */
  $statusStyles = [
      'pending'   => ['label' => 'Referral received',     'class' => 'bg-amber-50 text-amber-700 ring-amber-200'],
      'qualified' => ['label' => 'Consultation booked',   'class' => 'bg-blue-50 text-blue-700 ring-blue-200'],
      'fulfilled' => ['label' => 'Deal closed',           'class' => 'bg-emerald-50 text-emerald-700 ring-emerald-200'],
      'rewarded'  => ['label' => 'Reward paid',           'class' => 'bg-emerald-600 text-white ring-emerald-600'],
      'rejected'  => ['label' => 'Consultation canceled', 'class' => 'bg-slate-100 text-slate-500 ring-slate-200'],
  ];

  $toneStyles = [
      'success' => 'bg-emerald-500',
      'info'    => 'bg-brand-purple',
      'warn'    => 'bg-amber-500',
      'error'   => 'bg-rose-500',
  ];

  $money = fn (float $amount, string $currency) => ($currency === 'USD' ? '$' : $currency.' ').number_format($amount, 2);

  /*
   * Secondary text uses slate deliberately. The theme's own `--color-text-muted`,
   * `--color-text-slate`, `--color-text-secondary` and `--color-text-dim` are all defined as
   * #000000 in resources/css/app.css, so `text-text-muted` renders pure black — every caption,
   * label and timestamp came out at the same weight as the content it was annotating, which is
   * the single biggest reason this screen read flat. The tokens are used across a dozen other
   * templates, so they are left alone rather than redefined here.
   *
   * Captions use `text-[11px]` rather than the `text-2xs` this template used to carry.
   * There is no `--text-2xs` theme token and no such utility is generated — the class is a
   * no-op, here and in the nine other templates using it, so every "small caption" was
   * rendering at the inherited size. Combined with the black muted tokens above, that is why
   * captions came out as full-size black text. Defining the token globally would restyle
   * those nine templates too, so it is left for a separate, deliberate change.
   */
@endphp

<div class="w-full">

  {{-- ═══ SIGNED OUT ═══════════════════════════════════════════════════════ --}}
  @if (! $isAuthenticated)
    <div class="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8 py-12 sm:py-16">

      {{-- The page header lives here rather than in pages/referrer-portal.blade.php so it
           tracks auth state, exactly as partials/referrer-auth-tabs already documents. A
           centred marketing header above a signed-in dashboard cost ~200px before the fold
           and repeated the greeting that follows it. --}}
      @include('partials.referrer-auth-header', [
          'eyebrow' => 'Strategic Referral Network',
          'title' => 'Referrer Portal',
          'subtitle' => 'Track your referrals, see where each one stands, and follow your commissions.',
      ])

      <div class="max-w-md mx-auto">
        @include('partials.referrer-auth-tabs', ['active' => 'login'])

        <div class="bg-surface-white rounded-card-lg ring-1 ring-slate-200 p-8 shadow-card-subtle text-center">
          <div class="w-12 h-12 rounded-full bg-brand-purple/10 text-brand-purple mx-auto flex items-center justify-center mb-4">
            {!! app(\App\Infrastructure\WordPress\Admin\WordPressAdminTheme::class)->getIsoSvg('currentColor', 26) !!}
          </div>

          <h2 class="text-2xl font-bold font-display text-brand-hero tracking-tight">Referrer Portal Login</h2>
          <p class="text-xs text-slate-500 mt-2 mb-6">Enter your registered referral code or referrer email address to access your live attribution dashboard.</p>

          @if ($loginError)
            <div class="mb-4 p-3 rounded-card bg-rose-50 ring-1 ring-rose-200 text-rose-700 text-xs text-left">
              {{ $loginError }}
            </div>
          @endif

          <form wire:submit.prevent="authenticateReferrer" class="space-y-4">
            <input
              type="text"
              wire:model="lookupCode"
              placeholder="e.g. RL-REFERRER-123 or referrer@agency.com"
              class="w-full h-11 px-3.5 rounded-lg bg-[#F0F3FA] text-slate-900 text-sm focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand-purple transition-all duration-200"
            />
            <input
              type="password"
              wire:model="password"
              placeholder="Password"
              class="w-full h-11 px-3.5 rounded-lg bg-[#F0F3FA] text-slate-900 text-sm focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand-purple transition-all duration-200"
            />
            <button type="submit" class="w-full btn-primary cursor-pointer text-xs">Access Dashboard</button>
          </form>

          <div class="mt-6 pt-6 border-t border-slate-100 text-xs text-slate-500">
            Don't have a referrer account yet?
            <a href="{{ route('referrer.register') }}" class="font-bold text-brand-purple hover:underline">Apply here</a>.
          </div>
        </div>
      </div>
    </div>

  @else
    {{-- ═══ SIGNED IN ══════════════════════════════════════════════════════ --}}
    {{-- Wider than the theme's 1380px marketing container because three columns need it.
         That container rule governs migrated Elementor pages; this is an app route. --}}
    <div class="mx-auto w-full max-w-[1560px] px-4 sm:px-6 lg:px-8 py-6 lg:py-8">

      {{-- ── TOP BAR ───────────────────────────────────────────────────── --}}
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div class="flex items-center gap-3">
          <div class="w-11 h-11 rounded-full overflow-hidden shrink-0 ring-2 ring-brand-purple/15">
            <img src="{{ Vite::asset('resources/images/avatar.png') }}" alt="" width="1000" height="1000" class="w-full h-full object-cover" />
          </div>
          <div>
            <h1 class="text-xl sm:text-2xl font-bold font-display text-brand-hero tracking-tight leading-tight">
              Hi {{ $referrer->name }}
            </h1>
            <span class="text-[11px] font-bold text-slate-400 tracking-wider uppercase block">Partner Dashboard</span>
          </div>
        </div>

        <div class="flex items-center gap-2 sm:gap-3">
          @if ($canToggleDemo)
            <button
              type="button"
              wire:click="toggleDemoMode"
              class="inline-flex items-center gap-2 px-3 py-2 rounded-card ring-1 text-[11px] font-bold uppercase tracking-wider transition cursor-pointer
                {{ $isDemo ? 'bg-brand-purple text-white ring-brand-purple' : 'bg-white text-slate-500 ring-slate-200 hover:ring-brand-purple/40 hover:text-brand-purple' }}"
              title="Administrator only — renders bundled sample data for demonstrations"
            >
              <span class="w-1.5 h-1.5 rounded-full {{ $isDemo ? 'bg-white' : 'bg-slate-300' }}"></span>
              <span>{{ $isDemo ? 'Demo on' : 'Demo' }}</span>
            </button>
          @endif

          <button type="button" wire:click="logout" class="px-3 py-2 rounded-card ring-1 ring-slate-200 bg-white text-[11px] font-bold uppercase tracking-wider text-slate-500 hover:text-rose-600 hover:ring-rose-200 transition cursor-pointer">
            Log out
          </button>
        </div>
      </div>

      @if ($isDemo)
        <div class="mb-6 flex items-start gap-3 p-4 rounded-card-lg bg-amber-50 ring-1 ring-amber-200">
          <svg class="w-4 h-4 text-amber-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
          </svg>
          <p class="text-xs text-amber-900">
            <strong class="font-bold">Demonstration data.</strong>
            Every figure below is a bundled sample, not this account's real activity. Nothing is stored and no reward is owed.
          </p>
        </div>
      @endif

      {{-- ── THREE COLUMNS ─────────────────────────────────────────────────
           3 / 6 / 3 at xl. At lg the right rail drops to a full-width strip of
           three cards beneath the pipeline (col-span-12 + its own 3-col subgrid),
           which keeps the centre column readable instead of squeezing three rails
           into 1024px. Below lg everything stacks in source order. --}}
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 lg:gap-6 items-start">

        {{-- ══ LEFT RAIL — identity and the tools to refer ══ --}}
        <aside class="lg:col-span-4 xl:col-span-3 space-y-5 xl:sticky xl:top-6">

          <div class="bg-surface-white rounded-card-lg ring-1 ring-slate-200 p-5">
            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 block mb-1">Your referral link</span>
            {{-- A short curated list, not every page on the site. The default is the hiring
                 page so a referrer who never opens the picker still sends prospects somewhere
                 that carries the offer. --}}
            <div class="flex items-baseline justify-between gap-2 mb-3">
              <p class="text-[11px] text-slate-500 min-w-0 truncate">
                Sends to <strong class="font-bold text-slate-700">{{ $selectedPageName }}</strong>
              </p>
              <button
                type="button"
                wire:click="openPagePicker"
                class="shrink-0 text-[11px] font-bold text-brand-purple hover:underline cursor-pointer"
              >
                Select a different page
              </button>
            </div>

            <div class="rounded-card bg-slate-50 ring-1 ring-slate-200 p-2.5 mb-2">
              <input
                type="text"
                id="rl-referral-link-input"
                readonly
                value="{{ $referralUrl }}"
                aria-label="Your referral link"
                class="w-full bg-transparent border-0 p-0 text-[11px] font-mono text-slate-700 select-all focus:ring-0"
              />
            </div>

            <button
              type="button"
              onclick="navigator.clipboard.writeText(document.getElementById('rl-referral-link-input').value); this.querySelector('span').textContent='Copied to clipboard';"
              class="w-full py-2.5 rounded-card bg-brand-purple hover:bg-brand-purple-deep text-white text-xs font-bold transition flex items-center justify-center gap-1.5 cursor-pointer"
            >
              <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
              </svg>
              <span>Copy link</span>
            </button>

            <div class="mt-4 pt-4 border-t border-slate-100">
              <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 block mb-2">Share via</span>
              <div class="flex items-center gap-2">
                <a href="https://api.whatsapp.com/send?text={{ urlencode('Scale your team with top 1% Latin American talent: ' . $referralUrl) }}" target="_blank" rel="noopener" class="flex-1 py-2 rounded-card bg-emerald-50 text-emerald-600 hover:bg-emerald-100 transition flex items-center justify-center" title="Share on WhatsApp" aria-label="Share on WhatsApp">
                  <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z"/></svg>
                </a>
                <a href="https://www.linkedin.com/sharing/share-offsite/?url={{ urlencode($referralUrl) }}" target="_blank" rel="noopener" class="flex-1 py-2 rounded-card bg-blue-50 text-blue-600 hover:bg-blue-100 transition flex items-center justify-center" title="Share on LinkedIn" aria-label="Share on LinkedIn">
                  <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.238-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.79-1.75-1.764s.784-1.764 1.75-1.764 1.75.79 1.75 1.764-.783 1.764-1.75 1.764zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z"/></svg>
                </a>
                <a href="https://twitter.com/intent/tweet?text={{ urlencode('Scale your business operations with Remote Leverage: ' . $referralUrl) }}" target="_blank" rel="noopener" class="flex-1 py-2 rounded-card bg-slate-100 text-slate-700 hover:bg-slate-200 transition flex items-center justify-center" title="Share on X" aria-label="Share on X">
                  <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                </a>
                <a href="mailto:?subject={{ urlencode('Talent Solution Recommendation') }}&body={{ urlencode('Take a look at Remote Leverage for dedicated remote staffing: ' . $referralUrl) }}" class="flex-1 py-2 rounded-card bg-purple-50 text-brand-purple hover:bg-purple-100 transition flex items-center justify-center" title="Share via email" aria-label="Share via email">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                </a>
              </div>
            </div>
          </div>

          <button
            type="button"
            wire:click="openSubmitLeadModal"
            class="w-full py-3 rounded-card-lg bg-lavender-surface hover:bg-purple-100 text-brand-purple font-bold text-xs uppercase tracking-wider transition ring-1 ring-brand-purple/20 flex items-center justify-center gap-2 cursor-pointer"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
            <span>Submit a prospect</span>
          </button>

          <div class="px-1 text-[11px] text-slate-400">
            Attribution code <strong class="text-slate-600 font-mono font-bold">{{ $referrerCode }}</strong>
          </div>
        </aside>

        {{-- ══ CENTRE — the pipeline ══ --}}
        <main class="lg:col-span-8 xl:col-span-6 space-y-5">

          {{-- Funnel. Five equal KPI tiles were a wall of unrelated integers; these are one
               progression, and the bar makes the drop-off between stages legible at a glance. --}}
          @php
            $funnel = [
                ['label' => 'Link clicks', 'value' => $metrics['reach'],      'bar' => 'bg-blue-400'],
                ['label' => 'Referrals',   'value' => $metrics['referrals'],  'bar' => 'bg-brand-purple'],
                ['label' => 'In progress', 'value' => $metrics['qualified'],  'bar' => 'bg-indigo-400'],
                ['label' => 'Deals closed','value' => $metrics['fulfilled'],  'bar' => 'bg-emerald-500'],
            ];
            $funnelMax = max(1, ...array_column($funnel, 'value'));
          @endphp

          <div class="bg-surface-white rounded-card-lg ring-1 ring-slate-200 p-5">
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
              @foreach ($funnel as $stage)
                <div>
                  <div class="text-2xl font-bold text-brand-hero font-display tracking-tight leading-none">{{ $stage['value'] }}</div>
                  <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mt-1.5 mb-2">{{ $stage['label'] }}</div>
                  <div class="h-1 rounded-pill bg-slate-100 overflow-hidden">
                    {{-- A non-zero stage always paints, so "1 of 400" is visibly present rather
                         than rounding to an empty bar that reads as zero. --}}
                    <div class="h-full rounded-pill {{ $stage['bar'] }}" style="width: {{ $stage['value'] > 0 ? max(4, round($stage['value'] / $funnelMax * 100)) : 0 }}%"></div>
                  </div>
                </div>
              @endforeach
            </div>
          </div>

          {{-- Pipeline list --}}
          <div class="bg-surface-white rounded-card-lg ring-1 ring-slate-200 overflow-hidden">
            <div class="p-5 border-b border-slate-100">
              <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                <div>
                  <h2 class="text-sm font-bold text-brand-hero font-display">Your referrals</h2>
                  <p class="text-[11px] text-slate-500 mt-0.5">
                    Select any row for its full history. Anything without movement for {{ $staleDays }} days is flagged.
                  </p>
                </div>
                <input
                  type="search"
                  wire:model.live.debounce.300ms="search"
                  placeholder="Search name or email"
                  aria-label="Search referrals"
                  class="w-full sm:w-48 shrink-0 px-3 py-2 rounded-card ring-1 ring-slate-200 border-0 text-xs text-slate-900 focus:ring-2 focus:ring-brand-purple"
                />
              </div>

              @php
                /*
                 * Built from $statusStyles rather than restated, so a chip and the badges it
                 * returns can never again disagree about what a status is called — which is
                 * how "Closed" came to filter to rows badged "Deal closed".
                 *
                 * `all` and `stale` bracket it because neither is a status: `all` clears the
                 * filter, and `stale` cuts across the others on time since the last change.
                 * `rejected` is the one real status with no chip, as before — a referrer has
                 * no use for a shortcut to their dead referrals, and "All" still lists them.
                 */
                $filters = ['all' => 'All'];
                foreach ($statusStyles as $filterStatus => $filterStyle) {
                    if ($filterStatus !== 'rejected') {
                        $filters[$filterStatus] = $filterStyle['label'];
                    }
                }
                $filters['stale'] = 'Needs a nudge';
              @endphp
              <div class="flex flex-wrap items-center gap-1.5 mt-4" role="group" aria-label="Filter referrals by status">
                @foreach ($filters as $value => $label)
                  <button
                    type="button"
                    wire:click="setStatusFilter('{{ $value }}')"
                    aria-pressed="{{ $statusFilter === $value ? 'true' : 'false' }}"
                    {{-- Sentence case, not the uppercase-with-tracking these chips used to
                         carry. Measured in Chrome at 1440px against the built CSS: the six
                         labels come to 720px in caps and wrap onto a second row inside a 636px
                         card, and 569px here, which fits on one. A nineteen-character phrase in
                         wide-tracked caps is a hard read besides. --}}
                    class="px-2.5 py-1 rounded-pill text-[11px] font-bold transition cursor-pointer ring-1
                      {{ $statusFilter === $value
                          ? 'bg-brand-hero text-white ring-brand-hero'
                          : 'bg-white text-slate-500 ring-slate-200 hover:text-brand-purple hover:ring-brand-purple/40' }}"
                  >{{ $label }}</button>
                @endforeach
              </div>
            </div>

            <div class="divide-y divide-slate-100">
              @forelse ($referrals as $row)
                @php
                  $style = $statusStyles[$row['status']] ?? $statusStyles['pending'];
                  $isOpen = $expandedReferralId === $row['id'];
                @endphp

                <div class="{{ $isOpen ? 'bg-slate-50/50' : '' }}">
                  <button
                    type="button"
                    wire:click="toggleTimeline({{ $row['id'] }})"
                    class="w-full text-left p-4 sm:px-5 hover:bg-slate-50/70 transition cursor-pointer flex items-start gap-4"
                    aria-expanded="{{ $isOpen ? 'true' : 'false' }}"
                  >
                    <div class="flex-1 min-w-0">
                      <div class="flex flex-wrap items-center gap-2">
                        <span class="font-semibold text-sm text-slate-900 truncate">{{ $row['name'] }}</span>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-pill text-[11px] font-bold ring-1 {{ $style['class'] }}">
                          {{ $style['label'] }}
                        </span>
                        @if ($row['is_stale'])
                          <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-pill text-[11px] font-bold bg-amber-50 text-amber-700 ring-1 ring-amber-200">
                            <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                            Quiet {{ $row['days_since_change'] }}d
                          </span>
                        @endif
                      </div>

                      <div class="text-[11px] text-slate-500 mt-1 flex flex-wrap items-center gap-x-3 gap-y-0.5">
                        <span>{{ $row['email'] }}</span>
                        <span>Referred {{ $row['date'] }}</span>
                        @if ($row['stage'])
                          <span class="font-semibold text-slate-700">{{ $row['stage'] }}</span>
                        @elseif ($row['awaiting_sync'])
                          {{-- Not the same as "nothing has happened": an unsynced lead has no
                               known stage, and showing it as stalled would blame the prospect
                               for an integration that has not run yet. --}}
                          <span class="italic text-slate-400">Awaiting first sync</span>
                        @endif
                      </div>
                    </div>

                    {{-- Amount, or nothing. An em-dash placeholder for every unearned row put a
                         stray mark on most of the list, and the "History"/"Hide" caption beneath
                         restated what the chevron already says. --}}
                    <div class="shrink-0 flex items-center gap-3 pt-0.5">
                      @if ($row['payout'] > 0)
                        <span class="text-sm font-bold text-emerald-600">{{ $money($row['payout'], $row['currency']) }}</span>
                      @endif
                      <svg
                        class="w-4 h-4 text-slate-400 transition-transform duration-200 {{ $isOpen ? 'rotate-180' : '' }}"
                        fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"
                      >
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                      </svg>
                    </div>
                  </button>

                  @if ($isOpen)
                    <div class="px-4 sm:px-5 pb-5">
                      <div class="rounded-card bg-white ring-1 ring-slate-200 p-4">
                        @if (count($row['timeline']) > 0)
                          <ol class="relative space-y-3.5">
                            @foreach ($row['timeline'] as $entry)
                              <li class="flex items-start gap-3">
                                <span class="w-2 h-2 rounded-full mt-1.5 shrink-0 ring-2 ring-white {{ $toneStyles[$entry['tone']] ?? 'bg-slate-300' }}"></span>
                                <div class="flex-1 min-w-0 flex flex-wrap items-baseline justify-between gap-x-3">
                                  <span class="text-xs text-slate-700">{{ $entry['label'] }}</span>
                                  <time datetime="{{ $entry['iso'] }}" class="text-[11px] text-slate-400">{{ $entry['at'] }}</time>
                                </div>
                              </li>
                            @endforeach
                          </ol>
                        @else
                          <p class="text-[11px] text-slate-500">No activity recorded for this referral yet.</p>
                        @endif

                        @if ($row['is_stale'])
                          <p class="text-[11px] text-amber-800 mt-4 pt-3 border-t border-slate-100">
                            Last movement {{ $row['last_change_at'] }}, {{ $row['days_since_change'] }} days ago. A short check-in with your contact often restarts these.
                          </p>
                        @endif
                      </div>
                    </div>
                  @endif
                </div>

              @empty
                <div class="py-14 text-center px-5">
                  @if ($totalReferrals > 0)
                    <p class="text-sm text-slate-900 font-semibold">No referrals match this filter.</p>
                    <p class="text-xs text-slate-500 mt-1">Try a different status, or clear your search.</p>
                    <button type="button" wire:click="setStatusFilter('all')" class="mt-4 px-4 py-2 rounded-card ring-1 ring-slate-200 text-xs font-semibold text-slate-600 hover:ring-brand-purple/40 hover:text-brand-purple transition cursor-pointer">
                      Show all referrals
                    </button>
                  @else
                    <div class="w-11 h-11 rounded-full bg-lavender-surface text-brand-purple mx-auto flex items-center justify-center mb-3">
                      <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2M12 11a4 4 0 100-8 4 4 0 000 8z"/></svg>
                    </div>
                    <p class="text-sm text-slate-900 font-semibold">No referrals yet</p>
                    <p class="text-xs text-slate-500 mt-1 max-w-xs mx-auto">
                      Copy your referral link from the left, or submit a prospect directly. Both land here and you will see every step of their progress.
                    </p>
                  @endif
                </div>
              @endforelse
            </div>

            @if ($hasMore)
              <div class="p-4 border-t border-slate-100 text-center">
                <button type="button" wire:click="loadMore" class="px-4 py-2 rounded-card ring-1 ring-slate-200 text-xs font-semibold text-slate-600 hover:ring-brand-purple/40 hover:text-brand-purple transition cursor-pointer">
                  Show more ({{ $filteredCount - count($referrals) }} remaining)
                </button>
              </div>
            @endif
          </div>
        </main>

        {{-- ══ RIGHT RAIL — outcomes and what needs the referrer ══ --}}
        <aside class="lg:col-span-12 xl:col-span-3 xl:sticky xl:top-6">
          <div class="grid grid-cols-1 lg:grid-cols-3 xl:grid-cols-1 gap-5">

            {{-- Commissions --}}
            <div class="bg-surface-white rounded-card-lg ring-1 ring-slate-200 p-5">
              <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 block">Commissions</span>

              <div class="text-3xl font-bold text-brand-hero font-display tracking-tight mt-2">
                {{ $money($earnings['total'], $earnings['currency']) }}
              </div>
              <div class="text-[11px] text-slate-500">Earned to date</div>

              @php
                $paidShare = $earnings['total'] > 0 ? round($earnings['issued'] / $earnings['total'] * 100) : 0;
              @endphp
              <div class="mt-4 h-1.5 rounded-pill bg-slate-100 overflow-hidden flex">
                <div class="h-full bg-slate-400" style="width: {{ $paidShare }}%"></div>
                <div class="h-full bg-emerald-500" style="width: {{ 100 - $paidShare }}%"></div>
              </div>

              <div class="grid grid-cols-2 gap-3 mt-3">
                <div>
                  <div class="text-sm font-bold text-emerald-600">{{ $money($earnings['due'], $earnings['currency']) }}</div>
                  <div class="text-[11px] text-slate-500 flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>Awaiting payout</div>
                </div>
                <div>
                  <div class="text-sm font-bold text-slate-600">{{ $money($earnings['issued'], $earnings['currency']) }}</div>
                  <div class="text-[11px] text-slate-500 flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>Already paid</div>
                </div>
              </div>

              {{-- Stripe Connect is off (STRIPE_CONNECT_ENABLED unset), so this must not
                   promise self-serve payout the portal cannot deliver. --}}
              <p class="text-[11px] text-slate-500 mt-4 pt-3 border-t border-slate-100 leading-relaxed">
                Settled by the partnerships team once a deal completes. Reply to your welcome email with any payout question.
              </p>
            </div>

            {{-- Needs attention — the names, not a count --}}
            <div class="bg-surface-white rounded-card-lg ring-1 ring-slate-200 p-5">
              <div class="flex items-center justify-between mb-3">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Needs a nudge</span>
                @if (count($needsAttention) > 0)
                  <span class="px-1.5 py-0.5 rounded-pill bg-amber-50 text-amber-700 ring-1 ring-amber-200 text-[11px] font-bold">{{ $metrics['stale'] }}</span>
                @endif
              </div>

              @forelse ($needsAttention as $row)
                <button
                  type="button"
                  wire:click="focusReferral({{ $row['id'] }})"
                  class="w-full text-left py-2 flex items-center justify-between gap-3 group cursor-pointer border-b border-slate-100 last:border-0"
                >
                  <span class="text-xs text-slate-700 truncate group-hover:text-brand-purple transition">{{ $row['name'] }}</span>
                  <span class="text-[11px] font-bold text-amber-700 shrink-0">{{ $row['days_since_change'] }}d</span>
                </button>
              @empty
                <p class="text-[11px] text-slate-500">
                  Nothing has gone quiet. Every open referral has moved within {{ $staleDays }} days.
                </p>
              @endforelse
            </div>

            {{-- Merged activity feed --}}
            <div class="bg-surface-white rounded-card-lg ring-1 ring-slate-200 p-5">
              <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 block mb-3">Recent activity</span>

              @forelse ($recentActivity as $entry)
                <button
                  type="button"
                  wire:click="focusReferral({{ $entry['referral_id'] }})"
                  class="w-full text-left flex items-start gap-2.5 py-1.5 group cursor-pointer"
                >
                  <span class="w-1.5 h-1.5 rounded-full mt-1.5 shrink-0 {{ $toneStyles[$entry['tone']] ?? 'bg-slate-300' }}"></span>
                  <span class="flex-1 min-w-0">
                    <span class="block text-[11px] text-slate-700 group-hover:text-brand-purple transition truncate">{{ $entry['label'] }}</span>
                    <span class="block text-[11px] text-slate-400 truncate">{{ $entry['name'] }} · {{ $entry['at'] }}</span>
                  </span>
                </button>
              @empty
                <p class="text-[11px] text-slate-500">Nothing has happened yet.</p>
              @endforelse
            </div>

          </div>
        </aside>

      </div>
    </div>

    {{-- ── LANDING PAGE PICKER ───────────────────────────────────────────── --}}
    @if ($showPageModal)
      <div class="fixed inset-0 z-50 bg-brand-midnight/70 backdrop-blur-sm flex items-start sm:items-center justify-center p-4 overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="rl-page-picker-title">
        <div class="bg-surface-white rounded-card-lg max-w-4xl w-full my-8 sm:my-0 p-6 sm:p-8 shadow-card-elevated">

          <div class="flex items-start justify-between gap-4 pb-4 border-b border-slate-100">
            <div>
              <h3 id="rl-page-picker-title" class="text-base font-bold text-brand-hero font-display">Choose where your link goes</h3>
              <p class="text-[11px] text-slate-500 mt-0.5">
                Your prospect sees the welcome offer on every one of these. Previewing a page does not count as a click on your link.
              </p>
            </div>
            <button type="button" wire:click="closePagePicker" aria-label="Close" class="shrink-0 text-slate-400 hover:text-slate-700 text-xl font-bold cursor-pointer leading-none">&times;</button>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 pt-5">
            @foreach ($destinations as $destination)
              {{-- Flex column with the text block growing, so the preview link sits on the
                   same baseline in every card however many lines the blurb takes. --}}
              <div class="flex flex-col rounded-card-lg ring-1 overflow-hidden transition {{ $destination['selected'] ? 'ring-2 ring-brand-purple' : 'ring-slate-200 hover:ring-brand-purple/40' }}">
                <button
                  type="button"
                  wire:click="selectPage('{{ $destination['path'] }}')"
                  class="flex flex-col flex-1 w-full text-left cursor-pointer"
                  aria-pressed="{{ $destination['selected'] ? 'true' : 'false' }}"
                >
                  <span class="block relative aspect-[8/5] bg-slate-100 overflow-hidden">
                    <img
                      src="{{ \App\Support\BlockDefaults::pageImg('referral-links', $destination['image']) }}"
                      alt="{{ $destination['name'] }} hero"
                      width="640" height="400" loading="lazy" decoding="async"
                      class="w-full h-full object-cover object-top"
                    />
                    @if ($destination['selected'])
                      <span class="absolute top-2 right-2 inline-flex items-center gap-1 px-2 py-0.5 rounded-pill bg-brand-purple text-white text-[11px] font-bold">
                        <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7"/></svg>
                        Selected
                      </span>
                    @endif
                  </span>

                  <span class="block flex-1 px-4 pt-3">
                    <span class="block text-sm font-bold text-slate-900">{{ $destination['name'] }}</span>
                    <span class="block text-[11px] text-slate-500 mt-0.5">{{ $destination['blurb'] }}</span>
                  </span>
                </button>

                <div class="px-4 pb-3 pt-2">
                  <a
                    href="{{ $destination['preview_url'] }}"
                    target="_blank"
                    rel="noopener"
                    class="inline-flex items-center gap-1 text-[11px] font-bold text-brand-purple hover:underline"
                  >
                    Preview page
                    <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6M15 3h6v6M10 14 21 3"/>
                    </svg>
                  </a>
                </div>
              </div>
            @endforeach
          </div>

          <div class="pt-5 mt-5 border-t border-slate-100 flex justify-end">
            <button type="button" wire:click="closePagePicker" class="px-4 py-2 rounded-card bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold cursor-pointer transition">Done</button>
          </div>
        </div>
      </div>
    @endif

    {{-- ── SUBMIT PROSPECT MODAL ─────────────────────────────────────────── --}}
    @if ($showLeadModal)
      <div class="fixed inset-0 z-50 bg-brand-midnight/70 backdrop-blur-sm flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="rl-lead-modal-title">
        <div class="bg-surface-white rounded-card-lg max-w-lg w-full p-6 sm:p-8 shadow-card-elevated relative">

          <div class="flex items-center justify-between pb-4 border-b border-slate-100">
            <div class="flex items-center gap-2.5">
              <div class="w-8 h-8 rounded-card bg-brand-purple/10 text-brand-purple flex items-center justify-center">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                  <circle cx="12" cy="7" r="4"></circle>
                </svg>
              </div>
              <h3 id="rl-lead-modal-title" class="text-base font-bold text-brand-hero font-display">Submit a prospect</h3>
            </div>
            <button type="button" wire:click="closeSubmitLeadModal" aria-label="Close" class="text-slate-400 hover:text-slate-700 text-xl font-bold cursor-pointer leading-none">&times;</button>
          </div>

          <div class="py-4 space-y-4">
            <p class="text-xs text-slate-500">
              Refer a prospective client directly. Once the deal completes, your commission is credited automatically.
            </p>

            @if ($leadModalError)
              <div class="p-3 rounded-card bg-rose-50 ring-1 ring-rose-200 text-rose-700 text-xs">{{ $leadModalError }}</div>
            @endif

            @if ($leadModalSuccess)
              <div class="p-3 rounded-card bg-emerald-50 ring-1 ring-emerald-200 text-emerald-700 text-xs">{{ $leadModalSuccess }}</div>
            @endif

            <div class="space-y-3">
              <div>
                <label for="rl-lead-name" class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1">Full name *</label>
                <input id="rl-lead-name" type="text" wire:model="leadModalName" placeholder="e.g. David Vance" class="w-full px-3 py-2 rounded-card ring-1 ring-slate-200 border-0 text-xs text-slate-900 focus:ring-2 focus:ring-brand-purple" />
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                  <label for="rl-lead-email" class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1">Email</label>
                  <input id="rl-lead-email" type="email" wire:model="leadModalEmail" placeholder="david@company.com" class="w-full px-3 py-2 rounded-card ring-1 ring-slate-200 border-0 text-xs text-slate-900 focus:ring-2 focus:ring-brand-purple" />
                </div>
                <div>
                  <label for="rl-lead-phone" class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1">Phone</label>
                  <input id="rl-lead-phone" type="tel" wire:model="leadModalPhone" placeholder="+1 (555) 000-0000" class="w-full px-3 py-2 rounded-card ring-1 ring-slate-200 border-0 text-xs text-slate-900 focus:ring-2 focus:ring-brand-purple" />
                </div>
              </div>
              <p class="text-[11px] text-slate-400">Either an email or a phone number is required.</p>

              <div>
                <label for="rl-lead-service" class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1">Target service</label>
                <select id="rl-lead-service" wire:model="leadModalLandingPage" class="w-full px-3 py-2 rounded-card ring-1 ring-slate-200 border-0 text-xs text-slate-900 bg-white cursor-pointer focus:ring-2 focus:ring-brand-purple">
                  @foreach ($landingPages as $lp)
                    <option value="{{ $lp['name'] }}">{{ $lp['name'] }}</option>
                  @endforeach
                </select>
              </div>

              <div>
                <label for="rl-lead-notes" class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1">Notes (optional)</label>
                <textarea id="rl-lead-notes" wire:model="leadModalNotes" rows="2" placeholder="Role requirements, headcount needed, or timeline..." class="w-full px-3 py-2 rounded-card ring-1 ring-slate-200 border-0 text-xs text-slate-900 focus:ring-2 focus:ring-brand-purple"></textarea>
              </div>
            </div>

            <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-3">
              <button type="button" wire:click="closeSubmitLeadModal" class="px-4 py-2 rounded-card bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold cursor-pointer transition">Cancel</button>
              <button type="button" wire:click="submitDirectLead" class="px-5 py-2 rounded-card bg-brand-purple hover:bg-brand-purple-deep text-white text-xs font-bold cursor-pointer transition">Submit prospect</button>
            </div>
          </div>

        </div>
      </div>
    @endif

  @endif
</div>
