<div class="w-full">
  
  {{-- Partner Authentication Login Screen --}}
  @if (! $isAuthenticated)
    <div class="max-w-md mx-auto my-12 bg-surface-white rounded-card-lg border border-slate-200/80 p-8 shadow-card text-center">
      <div class="w-12 h-12 rounded-full bg-brand-purple/10 text-brand-purple mx-auto flex items-center justify-center mb-4">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
        </svg>
      </div>

      <h2 class="text-2xl font-bold font-display text-brand-hero tracking-tight">Partner Portal Login</h2>
      <p class="text-xs text-text-muted mt-2 mb-6">Enter your registered referral code or partner email address to access your live attribution dashboard.</p>

      @if ($loginError)
        <div class="mb-4 p-3 rounded-card bg-red-50 border border-red-200 text-status-alert text-xs text-left">
          {{ $loginError }}
        </div>
      @endif

      <form wire:submit.prevent="authenticatePartner" class="space-y-4">
        <div>
          <input 
            type="text" 
            wire:model="lookupCode"
            placeholder="e.g. RL-PARTNER-123 or partner@agency.com"
            class="w-full px-4 py-3 rounded-card border border-slate-200 text-sm text-text-body focus:ring-2 focus:ring-brand-purple/20 focus:border-brand-purple bg-white"
          />
        </div>

        <button 
          type="submit"
          class="w-full btn-primary cursor-pointer text-xs"
        >
          Access Dashboard
        </button>
      </form>

      <div class="mt-6 pt-6 border-t border-slate-100 text-xs text-text-muted">
        Don't have a partner account yet? 
        <a href="{{ route('partner.register') }}" class="font-bold text-brand-purple hover:underline">Apply here</a>.
      </div>
    </div>

  @else
    {{-- Authenticated 2-Column Referrer Dashboard --}}
    <div class="max-w-7xl mx-auto p-4 sm:p-6 lg:p-8" id="rl-dashboard-app">
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
        
        {{-- ── LEFT COLUMN: Brand Header, Link Generator & Direct Lead CTA ── --}}
        <div class="lg:col-span-5 space-y-6">
          
          <!-- Brand Header -->
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full overflow-hidden shrink-0 border border-slate-200 ring-2 ring-brand-purple/10">
              <img src="{{ Vite::asset('resources/images/avatar.png') }}" alt="Remote Leverage" class="w-full h-full object-cover" />
            </div>
            <div>
              <span class="font-extrabold text-sm text-brand-hero block font-display">Remote Leverage</span>
              <span class="text-2xs font-bold text-slate-400 tracking-wider uppercase block">YOUR PARTNER DASHBOARD</span>
            </div>
          </div>

          <!-- Greeting -->
          <div>
            <h1 class="text-2xl sm:text-3xl font-extrabold font-display text-brand-hero tracking-tight">
              Hi {{ $partner->name }}
            </h1>
            <p class="text-xs sm:text-sm text-text-muted mt-1">
              Use your unique referral link to refer clients and earn automated commissions on placed talent.
            </p>
          </div>

          <!-- Referral Link Generator Card -->
          <div class="bg-surface-white rounded-card-lg border border-slate-200/80 p-6 shadow-card space-y-4">
            <div>
              <label for="rl-landing-selector" class="block text-xs font-bold uppercase tracking-wider text-text-body">
                Select Landing Page to Share
              </label>
            </div>

            <div>
              <select 
                id="rl-landing-selector" 
                wire:change="updatedSelectedLandingUrl($event.target.value)"
                class="w-full px-3 py-2.5 rounded-card border border-slate-200 text-xs font-medium text-text-body bg-white focus:ring-2 focus:ring-brand-purple/20 focus:border-brand-purple cursor-pointer"
              >
                @foreach ($landingPages as $lp)
                  <option value="{{ $lp['base_url'] }}">{{ $lp['name'] }}</option>
                @endforeach
              </select>
            </div>

            <div class="flex items-center gap-2">
              <input 
                type="text" 
                id="rl-referral-link-input" 
                readonly 
                value="{{ $selectedLandingUrl }}" 
                class="flex-1 px-3 py-2.5 rounded-card border border-slate-200 bg-slate-50 text-xs font-mono text-text-body select-all" 
              />
              <button 
                type="button" 
                onclick="navigator.clipboard.writeText(document.getElementById('rl-referral-link-input').value); alert('Referral link copied to clipboard!');"
                class="px-4 py-2.5 rounded-card bg-brand-purple hover:bg-brand-purple-deep text-white text-xs font-bold transition flex items-center gap-1.5 cursor-pointer shadow-sm"
              >
                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                  <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                </svg>
                <span>Copy</span>
              </button>
            </div>

            <!-- Quick Share Buttons (WhatsApp, LinkedIn, X, Email) -->
            <div class="pt-2">
              <span class="text-2xs font-bold uppercase tracking-wider text-slate-400 block mb-2">Share via:</span>
              <div class="flex items-center gap-2.5">
                {{-- WhatsApp --}}
                <a 
                  href="https://api.whatsapp.com/send?text={{ urlencode('Scale your team with top 1% Latin American talent: ' . $selectedLandingUrl) }}" 
                  target="_blank" 
                  rel="noopener"
                  class="p-2 rounded-card bg-green-50 text-green-600 hover:bg-green-100 transition"
                  title="Share on WhatsApp"
                >
                  <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z"/></svg>
                </a>

                {{-- LinkedIn --}}
                <a 
                  href="https://www.linkedin.com/sharing/share-offsite/?url={{ urlencode($selectedLandingUrl) }}" 
                  target="_blank" 
                  rel="noopener"
                  class="p-2 rounded-card bg-blue-50 text-blue-600 hover:bg-blue-100 transition"
                  title="Share on LinkedIn"
                >
                  <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.238-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.79-1.75-1.764s.784-1.764 1.75-1.764 1.75.79 1.75 1.764-.783 1.764-1.75 1.764zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z"/></svg>
                </a>

                {{-- X / Twitter --}}
                <a 
                  href="https://twitter.com/intent/tweet?text={{ urlencode('Scale your business operations with Remote Leverage: ' . $selectedLandingUrl) }}" 
                  target="_blank" 
                  rel="noopener"
                  class="p-2 rounded-card bg-slate-100 text-slate-800 hover:bg-slate-200 transition"
                  title="Share on X"
                >
                  <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                </a>

                {{-- Email --}}
                <a 
                  href="mailto:?subject={{ urlencode('Talent Solution Recommendation') }}&body={{ urlencode('Take a look at Remote Leverage for dedicated remote staffing: ' . $selectedLandingUrl) }}" 
                  class="p-2 rounded-card bg-purple-50 text-brand-purple hover:bg-purple-100 transition"
                  title="Share via Email"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                </a>
              </div>
            </div>

            <!-- Direct Lead Submission CTA Button -->
            <div class="pt-4 border-t border-slate-100">
              <button 
                type="button" 
                wire:click="openSubmitLeadModal"
                class="w-full py-3 rounded-card bg-lavender-surface hover:bg-purple-100 text-brand-purple font-bold text-xs uppercase tracking-wider transition border border-brand-purple/20 flex items-center justify-center gap-2 cursor-pointer shadow-sm"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                <span>+ Submit Direct Prospect Lead</span>
              </button>
            </div>

          </div>

          <!-- Footer Links -->
          <div class="flex items-center justify-between text-xs text-text-muted">
            <span>Attribution Code: <strong class="text-text-body font-mono">{{ $partnerCode }}</strong></span>
            <button type="button" wire:click="logout" class="text-status-alert hover:underline inline-flex items-center gap-1 cursor-pointer">
              <span>Log Out</span>
            </button>
          </div>

        </div>

        {{-- ── RIGHT COLUMN: 3 KPI Cards & Activity Table ── --}}
        <div class="lg:col-span-7 space-y-6">
          
          <!-- 3 KPI Stat Boxes -->
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            
            <!-- REACH -->
            <div class="bg-surface-white rounded-card-lg border border-slate-200/80 p-5 shadow-card-subtle">
              <div class="w-8 h-8 rounded-card bg-blue-50 text-blue-600 flex items-center justify-center mb-3">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                  <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline>
                  <polyline points="17 6 23 6 23 12"></polyline>
                </svg>
              </div>
              <div class="text-2xl sm:text-3xl font-black text-brand-hero font-display tracking-tight">{{ $reachCount }}</div>
              <div class="text-2xs font-bold text-slate-400 uppercase tracking-wider mt-1">REACH (CLICKS)</div>
            </div>

            <!-- RECENT REFERRALS -->
            <div class="bg-surface-white rounded-card-lg border border-slate-200/80 p-5 shadow-card-subtle">
              <div class="w-8 h-8 rounded-card bg-purple-50 text-brand-purple flex items-center justify-center mb-3">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                  <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                  <circle cx="12" cy="7" r="4"></circle>
                </svg>
              </div>
              <div class="text-2xl sm:text-3xl font-black text-brand-hero font-display tracking-tight">{{ $recentReferralsCount }}</div>
              <div class="text-2xs font-bold text-slate-400 uppercase tracking-wider mt-1">RECENT REFERRALS</div>
            </div>

            <!-- DEALS FULFILLED -->
            <div class="bg-surface-white rounded-card-lg border border-slate-200/80 p-5 shadow-card-subtle">
              <div class="w-8 h-8 rounded-card bg-green-50 text-status-success flex items-center justify-center mb-3">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                  <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                  <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
              </div>
              <div class="text-2xl sm:text-3xl font-black text-brand-hero font-display tracking-tight">{{ $dealsFulfilledCount }}</div>
              <div class="text-2xs font-bold text-slate-400 uppercase tracking-wider mt-1">DEALS FULFILLED</div>
            </div>

          </div>

          <!-- Activity / Referral History Table -->
          <div class="bg-surface-white rounded-card-lg border border-slate-200/80 shadow-card overflow-hidden">
            <div class="p-5 border-b border-slate-100 flex items-center justify-between">
              <div>
                <h3 class="text-sm font-bold text-brand-hero font-display">Referred Prospects & Lead Activity</h3>
                <p class="text-2xs text-text-muted">Live feed of clients attributed to your partnership code.</p>
              </div>
              <span class="px-2.5 py-1 rounded-pill bg-slate-100 text-slate-600 text-2xs font-bold uppercase tracking-wider">
                {{ count($recentActivity) }} Records
              </span>
            </div>

            <div class="overflow-x-auto">
              <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 text-slate-500 font-bold uppercase text-2xs tracking-wider border-b border-slate-100">
                  <tr>
                    <th class="py-3 px-4">Date</th>
                    <th class="py-3 px-4">Contact</th>
                    <th class="py-3 px-4">Status</th>
                    <th class="py-3 px-4 text-right">Commission</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                  @forelse ($recentActivity as $act)
                    <tr class="hover:bg-slate-50/60 transition">
                      <td class="py-3.5 px-4 text-text-muted whitespace-nowrap">{{ $act['date'] }}</td>
                      <td class="py-3.5 px-4 font-semibold text-text-body">
                        <div>{{ $act['name'] }}</div>
                        <div class="text-2xs text-text-muted font-normal">{{ $act['email'] }}</div>
                      </td>
                      <td class="py-3.5 px-4">
                        @if ($act['status'] === 'fulfilled' || $act['status'] === 'closed_won')
                          <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-2xs font-bold bg-green-100 text-green-800">
                            Fulfilled
                          </span>
                        @elseif ($act['status'] === 'qualified')
                          <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-2xs font-bold bg-blue-100 text-blue-800">
                            Qualified
                          </span>
                        @else
                          <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-2xs font-bold bg-amber-100 text-amber-800">
                            Pending Review
                          </span>
                        @endif
                      </td>
                      <td class="py-3.5 px-4 text-right font-bold text-text-body">{{ $act['payout'] }}</td>
                    </tr>
                  @empty
                    <tr>
                      <td colspan="4" class="py-8 text-center text-text-muted">
                        No referrals recorded yet. Share your link above to begin tracking client leads.
                      </td>
                    </tr>
                  @endforelse
                </tbody>
              </table>
            </div>
          </div>

        </div>

      </div>
    </div>

    {{-- ── DIRECT LEAD SUBMISSION MODAL ── --}}
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
              Directly refer a prospective client to Remote Leverage. Once verified and qualified, commission is automatically credited to your account.
            </p>

            @if ($leadModalError)
              <div class="p-3 rounded-card bg-red-50 border border-red-200 text-status-alert text-xs">
                {{ $leadModalError }}
              </div>
            @endif

            @if ($leadModalSuccess)
              <div class="p-3 rounded-card bg-green-50 border border-green-200 text-status-success text-xs">
                {{ $leadModalSuccess }}
              </div>
            @endif

            <div class="space-y-3">
              <div>
                <label class="block text-2xs font-bold uppercase tracking-wider text-text-body mb-1">Lead Full Name *</label>
                <input 
                  type="text" 
                  wire:model="leadModalName" 
                  placeholder="e.g. David Vance" 
                  class="w-full px-3 py-2 rounded-card border border-slate-200 text-xs text-text-body focus:ring-2 focus:ring-brand-purple/20 focus:border-brand-purple"
                />
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                  <label class="block text-2xs font-bold uppercase tracking-wider text-text-body mb-1">Lead Email</label>
                  <input 
                    type="email" 
                    wire:model="leadModalEmail" 
                    placeholder="david@company.com" 
                    class="w-full px-3 py-2 rounded-card border border-slate-200 text-xs text-text-body focus:ring-2 focus:ring-brand-purple/20 focus:border-brand-purple"
                  />
                </div>
                <div>
                  <label class="block text-2xs font-bold uppercase tracking-wider text-text-body mb-1">Lead Phone</label>
                  <input 
                    type="tel" 
                    wire:model="leadModalPhone" 
                    placeholder="+1 (555) 000-0000" 
                    class="w-full px-3 py-2 rounded-card border border-slate-200 text-xs text-text-body focus:ring-2 focus:ring-brand-purple/20 focus:border-brand-purple"
                  />
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
                <textarea 
                  wire:model="leadModalNotes" 
                  rows="2" 
                  placeholder="Role requirements, headcount needed, or timeline..."
                  class="w-full px-3 py-2 rounded-card border border-slate-200 text-xs text-text-body focus:ring-2 focus:ring-brand-purple/20 focus:border-brand-purple"
                ></textarea>
              </div>
            </div>

            <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-3">
              <button 
                type="button" 
                wire:click="closeSubmitLeadModal"
                class="px-4 py-2 rounded-card bg-slate-100 hover:bg-slate-200 text-text-body text-xs font-semibold cursor-pointer transition"
              >
                Cancel
              </button>
              <button 
                type="button" 
                wire:click="submitDirectLead"
                class="px-5 py-2 rounded-card bg-brand-purple hover:bg-brand-purple-deep text-white text-xs font-bold cursor-pointer transition shadow-sm"
              >
                Submit Lead
              </button>
            </div>
          </div>

        </div>
      </div>
    @endif

  @endif
</div>
