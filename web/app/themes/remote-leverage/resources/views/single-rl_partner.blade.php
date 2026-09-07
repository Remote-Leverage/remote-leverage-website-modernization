@extends('layouts.app')

@section('content')
@php
  $postId = get_the_ID();
  $partnerPermalink = get_permalink($postId);

  // Active Tab Routing (supports /partners/{slug}/{tab} rewrite and ?tab=)
  $rawTab = get_query_var('rl_tab') ?: (isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'overview');
  $validTabs = [
      'overview' => 'Overview & Actions',
      'icp' => 'Ideal Client Profile',
      'services' => 'Services Overview',
      'why-rl' => 'Why Remote Leverage',
      'referral-program' => 'Referral Program & Fees',
      'comarketing' => 'Co-Marketing',
      'case-studies' => 'Case Studies',
      'faq' => 'Partner FAQ',
      'contact' => 'Contact Team',
  ];

  $currentTab = array_key_exists($rawTab, $validTabs) ? $rawTab : 'overview';

  // Partner Metadata
  $partnerCode = get_post_meta($postId, '_rl_partner_code', true) ?: 'RL-PARTNER';
  $partnerName = get_post_meta($postId, '_rl_partner_name', true) ?: get_the_title();
  $partnerLogo = get_post_meta($postId, '_rl_partner_logo_url', true);
  $partnerWebsite = get_post_meta($postId, '_rl_partner_website', true);
  $partnershipType = get_post_meta($postId, '_rl_partnership_type', true) ?: 'Strategic Referral Partner';
  $territory = get_post_meta($postId, '_rl_territory', true) ?: 'Worldwide';
  $partnerFee = get_post_meta($postId, '_rl_partner_to_rl_fee', true) ?: 'Partner receives 10% of the net Remote Leverage placement fee collected from an eligible referred client.';
  $managerName = get_post_meta($postId, '_rl_manager_name', true) ?: 'Adrian Salvatori';
  $managerEmail = get_post_meta($postId, '_rl_manager_email', true) ?: 'partnerships@remoteleverage.com';

  function getTabUrl($permalink, $tab) {
      if ($tab === 'overview') {
          return esc_url($permalink);
      }
      return esc_url(add_query_arg('tab', $tab, $permalink));
  }
@endphp

<div class="min-h-screen bg-bg-light py-8 sm:py-12" x-data="{ mobileMenuOpen: false }">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    
    {{-- Top Co-Branded Hero Bar --}}
    <div class="rounded-card-lg bg-gradient-to-r from-brand-midnight via-brand-navy to-brand-hero text-white p-6 sm:p-8 shadow-card mb-8 border border-purple-900/40 relative overflow-hidden">
      <div class="absolute -right-16 -top-16 w-64 h-64 bg-brand-purple/20 rounded-full blur-3xl pointer-events-none"></div>

      <div class="relative z-10 flex flex-col md:flex-row md:items-center md:justify-between gap-6">
        <div class="space-y-2">
          {{-- Dual Logo Lockup --}}
          <div class="flex items-center gap-3">
            <span class="font-extrabold font-display text-lg sm:text-xl text-white tracking-tight">Remote Leverage</span>
            <span class="text-brand-magenta font-bold text-lg">&times;</span>
            @if ($partnerLogo)
              <img src="{{ $partnerLogo }}" alt="{{ $partnerName }}" class="h-7 max-w-[140px] object-contain brightness-0 invert" />
            @else
              <span class="font-bold text-lg sm:text-xl text-purple-200">{{ $partnerName }}</span>
            @endif
          </div>

          <p class="text-xs sm:text-sm text-slate-300">
            Co-Branded Partnership Knowledge Hub & Operational Directory
          </p>
        </div>

        {{-- Partner Status Pill & Quick Action --}}
        <div class="flex flex-wrap items-center gap-3">
          <div class="px-3.5 py-1.5 rounded-pill bg-white/10 backdrop-blur-md border border-white/20 text-xs font-mono text-purple-200">
            <span class="text-slate-400">PARTNER CODE:</span> <strong class="text-white">{{ $partnerCode }}</strong>
          </div>

          <a
            href="#booking-wizard"
            class="px-5 py-2 rounded-cta bg-brand-magenta hover:bg-brand-magenta-hover text-white text-xs sm:text-sm font-bold shadow-md transition"
          >
            Submit Referral
          </a>
        </div>
      </div>
    </div>

    {{-- Main 2-Column Documentation Grid --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
      
      {{-- Mobile Tab Selector --}}
      <div class="lg:hidden col-span-1">
        <label class="block text-xs font-semibold text-text-slate mb-1 uppercase tracking-wider">Select Hub Section</label>
        <select
          onchange="window.location.href=this.value"
          class="w-full text-xs sm:text-sm rounded-card border-slate-200 py-2.5 px-3 bg-white text-text-body"
        >
          @foreach ($validTabs as $tabKey => $tabLabel)
            <option value="{{ getTabUrl($partnerPermalink, $tabKey) }}" {{ $currentTab === $tabKey ? 'selected' : '' }}>
              {{ $tabLabel }}
            </option>
          @endforeach
        </select>
      </div>

      {{-- Left Sidebar Navigation (Desktop) --}}
      <aside class="hidden lg:block lg:col-span-3 bg-surface-white rounded-card-lg border border-slate-200/80 shadow-card p-5 sticky top-8 space-y-6">
        <div>
          <span class="text-2xs font-extrabold uppercase tracking-wider text-text-slate px-3">
            Getting Started
          </span>
          <nav class="mt-2 space-y-1">
            <a
              href="{{ getTabUrl($partnerPermalink, 'overview') }}"
              class="block px-3 py-2 rounded-card text-xs font-bold transition {{ $currentTab === 'overview' ? 'bg-brand-purple text-white shadow-sm' : 'text-text-body hover:bg-slate-50' }}"
            >
              Overview & Actions
            </a>
          </nav>
        </div>

        <div>
          <span class="text-2xs font-extrabold uppercase tracking-wider text-text-slate px-3">
            Partnership Playbook
          </span>
          <nav class="mt-2 space-y-1">
            <a
              href="{{ getTabUrl($partnerPermalink, 'icp') }}"
              class="block px-3 py-2 rounded-card text-xs font-semibold transition {{ $currentTab === 'icp' ? 'bg-brand-purple text-white shadow-sm font-bold' : 'text-text-body hover:bg-slate-50' }}"
            >
              Ideal Client Profile
            </a>
            <a
              href="{{ getTabUrl($partnerPermalink, 'services') }}"
              class="block px-3 py-2 rounded-card text-xs font-semibold transition {{ $currentTab === 'services' ? 'bg-brand-purple text-white shadow-sm font-bold' : 'text-text-body hover:bg-slate-50' }}"
            >
              Services Overview
            </a>
            <a
              href="{{ getTabUrl($partnerPermalink, 'why-rl') }}"
              class="block px-3 py-2 rounded-card text-xs font-semibold transition {{ $currentTab === 'why-rl' ? 'bg-brand-purple text-white shadow-sm font-bold' : 'text-text-body hover:bg-slate-50' }}"
            >
              Why Remote Leverage
            </a>
          </nav>
        </div>

        <div>
          <span class="text-2xs font-extrabold uppercase tracking-wider text-text-slate px-3">
            Program & Collaboration
          </span>
          <nav class="mt-2 space-y-1">
            <a
              href="{{ getTabUrl($partnerPermalink, 'referral-program') }}"
              class="block px-3 py-2 rounded-card text-xs font-semibold transition {{ $currentTab === 'referral-program' ? 'bg-brand-purple text-white shadow-sm font-bold' : 'text-text-body hover:bg-slate-50' }}"
            >
              Referral Program & Fees
            </a>
            <a
              href="{{ getTabUrl($partnerPermalink, 'comarketing') }}"
              class="block px-3 py-2 rounded-card text-xs font-semibold transition {{ $currentTab === 'comarketing' ? 'bg-brand-purple text-white shadow-sm font-bold' : 'text-text-body hover:bg-slate-50' }}"
            >
              Co-Marketing
            </a>
            <a
              href="{{ getTabUrl($partnerPermalink, 'case-studies') }}"
              class="block px-3 py-2 rounded-card text-xs font-semibold transition {{ $currentTab === 'case-studies' ? 'bg-brand-purple text-white shadow-sm font-bold' : 'text-text-body hover:bg-slate-50' }}"
            >
              Case Studies
            </a>
            <a
              href="{{ getTabUrl($partnerPermalink, 'faq') }}"
              class="block px-3 py-2 rounded-card text-xs font-semibold transition {{ $currentTab === 'faq' ? 'bg-brand-purple text-white shadow-sm font-bold' : 'text-text-body hover:bg-slate-50' }}"
            >
              Partner FAQ
            </a>
            <a
              href="{{ getTabUrl($partnerPermalink, 'contact') }}"
              class="block px-3 py-2 rounded-card text-xs font-semibold transition {{ $currentTab === 'contact' ? 'bg-brand-purple text-white shadow-sm font-bold' : 'text-text-body hover:bg-slate-50' }}"
            >
              Contact Team
            </a>
          </nav>
        </div>

        {{-- Partner Support Card --}}
        <div class="pt-4 border-t border-slate-100 text-xs text-text-muted">
          <span class="block font-bold text-text-body">Partner Lead:</span>
          <p>{{ $managerName }}</p>
          <a href="mailto:{{ $managerEmail }}" class="text-brand-purple hover:underline text-2xs block mt-0.5">
            {{ $managerEmail }}
          </a>
        </div>
      </aside>

      {{-- Right Main Documentation Content Area --}}
      <main class="lg:col-span-9 bg-surface-white rounded-card-lg border border-slate-200/80 shadow-card p-6 sm:p-10 space-y-8">
        
        {{-- TAB 1: OVERVIEW --}}
        @if ($currentTab === 'overview')
          <div class="space-y-8 animate-fadeIn">
            <div>
              <span class="px-3 py-1 rounded-pill bg-brand-purple/10 text-brand-purple text-xs font-bold uppercase tracking-wider">
                Partnership Brief
              </span>
              <h1 class="text-2xl sm:text-3xl font-extrabold font-display text-brand-hero tracking-tight mt-2">
                Remote Leverage &times; {{ $partnerName }} Alliance
              </h1>
              <p class="text-text-muted text-sm sm:text-base mt-2 leading-relaxed">
                A strategic collaboration designed to connect {{ $partnerName }}'s client network with the top 1% of vetted nearshore talent in Latin America, eliminating hiring latency and operational bottlenecks.
              </p>
            </div>

            {{-- Partnership Snapshot Grid --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
              <div class="p-4 rounded-card bg-bg-light border border-slate-200/60">
                <div class="text-2xs text-text-slate uppercase tracking-wider font-bold">Relationship Type</div>
                <div class="text-sm font-bold text-text-body mt-1">{{ $partnershipType }}</div>
              </div>
              <div class="p-4 rounded-card bg-bg-light border border-slate-200/60">
                <div class="text-2xs text-text-slate uppercase tracking-wider font-bold">Target Territory</div>
                <div class="text-sm font-bold text-text-body mt-1">{{ $territory }}</div>
              </div>
              <div class="p-4 rounded-card bg-bg-light border border-slate-200/60">
                <div class="text-2xs text-text-slate uppercase tracking-wider font-bold">Referral Attribution</div>
                <div class="text-sm font-bold text-brand-purple mt-1">90-Day Deduplicated</div>
              </div>
            </div>

            {{-- What Remote Leverage Delivers --}}
            <div class="space-y-4 pt-4 border-t border-slate-100">
              <h2 class="text-lg font-bold font-display text-brand-hero">
                What Your Clients Receive
              </h2>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs sm:text-sm">
                <div class="p-4 rounded-card border border-slate-200/80 space-y-1">
                  <span class="font-bold text-text-body">Top 1% LatAm Candidates</span>
                  <p class="text-text-muted">Rigorous C1/C2 verbal assessment, cognitive checks, and technical role testing.</p>
                </div>
                <div class="p-4 rounded-card border border-slate-200/80 space-y-1">
                  <span class="font-bold text-text-body">100% US Timezone Alignment</span>
                  <p class="text-text-muted">Real-time collaboration during Eastern, Central, or Pacific standard hours.</p>
                </div>
                <div class="p-4 rounded-card border border-slate-200/80 space-y-1">
                  <span class="font-bold text-text-body">6-Month Free Replacement</span>
                  <p class="text-text-muted">Risk-free matching guarantee with zero placement fees for replacements.</p>
                </div>
                <div class="p-4 rounded-card border border-slate-200/80 space-y-1">
                  <span class="font-bold text-text-body">70% Cost Efficiency</span>
                  <p class="text-text-muted">High-performing executive specialists for $8–$15/hr with zero payroll overhead.</p>
                </div>
              </div>
            </div>

            {{-- Action Banner --}}
            <div class="p-6 rounded-card bg-gradient-to-r from-brand-midnight to-brand-hero text-white flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
              <div>
                <h3 class="font-bold text-base text-white">Have a client ready to scale?</h3>
                <p class="text-xs text-slate-300 mt-0.5">Introduce them via our tracked partner booking calendar.</p>
              </div>
              <a
                href="{{ getTabUrl($partnerPermalink, 'referral-program') }}"
                class="px-5 py-2.5 rounded-cta bg-brand-magenta hover:bg-brand-magenta-hover text-white text-xs font-bold transition shadow whitespace-nowrap self-start sm:self-auto"
              >
                View Referral Process &rarr;
              </a>
            </div>
          </div>

        {{-- TAB 2: IDEAL CLIENT PROFILE --}}
        @elseif ($currentTab === 'icp')
          <div class="space-y-8 animate-fadeIn">
            <div>
              <span class="px-3 py-1 rounded-pill bg-brand-purple/10 text-brand-purple text-xs font-bold uppercase tracking-wider">
                Target Audience
              </span>
              <h1 class="text-2xl sm:text-3xl font-extrabold font-display text-brand-hero tracking-tight mt-2">
                Ideal Client Profile (ICP)
              </h1>
              <p class="text-text-muted text-sm mt-2">
                Use these criteria to identify when your clients or network are prime candidates for Remote Leverage.
              </p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
              <div class="p-6 rounded-card border border-slate-200/80 bg-bg-light space-y-3">
                <div class="w-8 h-8 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center font-bold text-xs">
                  &check;
                </div>
                <h3 class="font-bold font-display text-base text-brand-hero">Strong ICP Indicators</h3>
                <ul class="text-xs sm:text-sm text-text-muted space-y-2 list-disc list-inside">
                  <li>Founder/CEO overwhelmed with calendar, email, and admin tasks.</li>
                  <li>Agency owner scaling client load without wanting full-time US payroll overhead.</li>
                  <li>Real estate team needing contract-to-close transaction coordination.</li>
                  <li>E-commerce brand requiring bilingual support reps across US hours.</li>
                </ul>
              </div>

              <div class="p-6 rounded-card border border-slate-200/80 bg-bg-light space-y-3">
                <div class="w-8 h-8 rounded-full bg-amber-100 text-amber-600 flex items-center justify-center font-bold text-xs">
                  !
                </div>
                <h3 class="font-bold font-display text-base text-brand-hero">Company Stage & Revenue</h3>
                <ul class="text-xs sm:text-sm text-text-muted space-y-2 list-disc list-inside">
                  <li>Annual Revenue: $250k - $10M+ ARR / GMV.</li>
                  <li>Team Size: 2 to 50 employees.</li>
                  <li>Tech Stack: Slack, Google Workspace, Notion, Asana, or ClickUp.</li>
                  <li>Timeline: Looking to onboard within the next 2–4 weeks.</li>
                </ul>
              </div>
            </div>
          </div>

        {{-- TAB 3: SERVICES OVERVIEW --}}
        @elseif ($currentTab === 'services')
          <div class="space-y-8 animate-fadeIn">
            <div>
              <span class="px-3 py-1 rounded-pill bg-brand-purple/10 text-brand-purple text-xs font-bold uppercase tracking-wider">
                Capabilities
              </span>
              <h1 class="text-2xl sm:text-3xl font-extrabold font-display text-brand-hero tracking-tight mt-2">
                Remote Leverage Talent Specializations
              </h1>
              <p class="text-text-muted text-sm mt-2">
                We supply dedicated, pre-vetted full-time and part-time specialists across four core departments.
              </p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
              <div class="p-6 rounded-card border border-slate-200/80 space-y-2">
                <span class="px-2.5 py-0.5 rounded-pill bg-purple-100 text-brand-purple text-2xs font-bold uppercase">Department 01</span>
                <h3 class="font-bold text-base text-brand-hero">Executive Assistance & Admin</h3>
                <p class="text-xs sm:text-sm text-text-muted">Daily calendar triage, meeting preparation briefs, travel logistics, customer correspondence, and executive workflow coordination.</p>
                <div class="text-xs font-bold text-brand-magenta pt-2">$8 – $12 / hr</div>
              </div>

              <div class="p-6 rounded-card border border-slate-200/80 space-y-2">
                <span class="px-2.5 py-0.5 rounded-pill bg-purple-100 text-brand-purple text-2xs font-bold uppercase">Department 02</span>
                <h3 class="font-bold text-base text-brand-hero">Sales & Outbound Growth</h3>
                <p class="text-xs sm:text-sm text-text-muted">BDR/SDR cold outreach, lead qualification, LinkedIn Sales Navigator messaging, CRM updating, and appointment setting.</p>
                <div class="text-xs font-bold text-brand-magenta pt-2">$9 – $14 / hr</div>
              </div>

              <div class="p-6 rounded-card border border-slate-200/80 space-y-2">
                <span class="px-2.5 py-0.5 rounded-pill bg-purple-100 text-brand-purple text-2xs font-bold uppercase">Department 03</span>
                <h3 class="font-bold text-base text-brand-hero">Real Estate Transaction Coordination</h3>
                <p class="text-xs sm:text-sm text-text-muted">Contract-to-close escrow tracking, MLS database maintenance, disclosures compliance, and seller communications.</p>
                <div class="text-xs font-bold text-brand-magenta pt-2">$10 – $15 / hr</div>
              </div>

              <div class="p-6 rounded-card border border-slate-200/80 space-y-2">
                <span class="px-2.5 py-0.5 rounded-pill bg-purple-100 text-brand-purple text-2xs font-bold uppercase">Department 04</span>
                <h3 class="font-bold text-base text-brand-hero">Operations & Marketing Support</h3>
                <p class="text-xs sm:text-sm text-text-muted">Podcast and video content repurposing, social media publishing, basic bookkeeping, and Shopify order logistics.</p>
                <div class="text-xs font-bold text-brand-magenta pt-2">$8 – $13 / hr</div>
              </div>
            </div>
          </div>

        {{-- TAB 4: WHY REMOTE LEVERAGE --}}
        @elseif ($currentTab === 'why-rl')
          <div class="space-y-8 animate-fadeIn">
            <div>
              <span class="px-3 py-1 rounded-pill bg-brand-purple/10 text-brand-purple text-xs font-bold uppercase tracking-wider">
                Competitive Edge
              </span>
              <h1 class="text-2xl sm:text-3xl font-extrabold font-display text-brand-hero tracking-tight mt-2">
                Why Nearshore Latin America Trumps Offshore BPOs
              </h1>
              <p class="text-text-muted text-sm mt-2">
                How our recruitment infrastructure delivers a dramatically superior client experience.
              </p>
            </div>

            <div class="space-y-4">
              <div class="p-5 rounded-card bg-bg-light border border-slate-200/80 flex items-start gap-4">
                <div class="w-8 h-8 rounded-full bg-brand-purple text-white flex items-center justify-center font-bold text-xs shrink-0 mt-0.5">1</div>
                <div>
                  <h3 class="font-bold text-sm text-brand-hero">Nearshore Timezone Synchronicity</h3>
                  <p class="text-xs sm:text-sm text-text-muted mt-1">Offshore teams in Southeast Asia work while your clients sleep, causing 12-hour delays for simple answers. Our team works side-by-side on Slack during US hours.</p>
                </div>
              </div>

              <div class="p-5 rounded-card bg-bg-light border border-slate-200/80 flex items-start gap-4">
                <div class="w-8 h-8 rounded-full bg-brand-purple text-white flex items-center justify-center font-bold text-xs shrink-0 mt-0.5">2</div>
                <div>
                  <h3 class="font-bold text-sm text-brand-hero">Natural English & Cultural Alignment</h3>
                  <p class="text-xs sm:text-sm text-text-muted mt-1">Our talent pool grows up consuming US media and business culture, resulting in natural colloquial English and executive presence.</p>
                </div>
              </div>

              <div class="p-5 rounded-card bg-bg-light border border-slate-200/80 flex items-start gap-4">
                <div class="w-8 h-8 rounded-full bg-brand-purple text-white flex items-center justify-center font-bold text-xs shrink-0 mt-0.5">3</div>
                <div>
                  <h3 class="font-bold text-sm text-brand-hero">Guaranteed Retention & Low Attrition</h3>
                  <p class="text-xs sm:text-sm text-text-muted mt-1">We pay our specialists top-percentile local salaries with direct contractor benefits, creating high loyalty and minimal churn on client accounts.</p>
                </div>
              </div>
            </div>
          </div>

        {{-- TAB 5: REFERRAL PROGRAM & FEES --}}
        @elseif ($currentTab === 'referral-program')
          <div class="space-y-8 animate-fadeIn">
            <div>
              <span class="px-3 py-1 rounded-pill bg-brand-purple/10 text-brand-purple text-xs font-bold uppercase tracking-wider">
                Economics
              </span>
              <h1 class="text-2xl sm:text-3xl font-extrabold font-display text-brand-hero tracking-tight mt-2">
                Referral Program & Commission Terms
              </h1>
              <p class="text-text-muted text-sm mt-2">
                Transparent revenue-sharing terms for {{ $partnerName }}.
              </p>
            </div>

            <div class="p-6 rounded-card bg-emerald-50 border border-emerald-200 space-y-2">
              <span class="text-xs font-bold uppercase tracking-wider text-emerald-800">Partner Compensation</span>
              <p class="text-sm sm:text-base font-semibold text-emerald-900 leading-relaxed">
                {{ $partnerFee }}
              </p>
            </div>

            <div class="space-y-4">
              <h3 class="font-bold text-base text-brand-hero">How to Introduce a Client</h3>
              <ol class="space-y-3 text-xs sm:text-sm text-text-muted list-decimal list-inside">
                <li>Share your tracked partner booking link: <code class="px-2 py-0.5 rounded bg-slate-100 font-mono text-brand-purple font-bold">https://remoteleverage.com/?via={{ $partnerCode }}</code></li>
                <li>Or send an email introduction connecting your client directly to <a href="mailto:{{ $managerEmail }}" class="text-brand-purple font-semibold hover:underline">{{ $managerEmail }}</a> mentioning code <span class="font-mono font-bold">{{ $partnerCode }}</span>.</li>
                <li>Our staffing director hosts the qualification session, scopes the role, and reports the status back to your team.</li>
                <li>Commission is paid automatically via Stripe Connect upon client placement.</li>
              </ol>
            </div>
          </div>

        {{-- TAB 6: CO-MARKETING --}}
        @elseif ($currentTab === 'comarketing')
          <div class="space-y-8 animate-fadeIn">
            <div>
              <span class="px-3 py-1 rounded-pill bg-brand-purple/10 text-brand-purple text-xs font-bold uppercase tracking-wider">
                Collaborative Growth
              </span>
              <h1 class="text-2xl sm:text-3xl font-extrabold font-display text-brand-hero tracking-tight mt-2">
                Co-Marketing Playbook & Joint Assets
              </h1>
              <p class="text-text-muted text-sm mt-2">
                Opportunities for joint webinars, content collaboration, and co-branded distribution.
              </p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs sm:text-sm">
              <div class="p-5 rounded-card border border-slate-200/80 space-y-2">
                <h3 class="font-bold text-brand-hero">Joint Webinar / Workshop</h3>
                <p class="text-text-muted">We co-host a 45-minute live tactical masterclass on "How to Automate & Delegate Operations with Nearshore Talent" for your community.</p>
              </div>

              <div class="p-5 rounded-card border border-slate-200/80 space-y-2">
                <h3 class="font-bold text-brand-hero">Co-Branded Case Study</h3>
                <p class="text-text-muted">Highlighting mutual client success stories published on both Remote Leverage and {{ $partnerName }} blogs and email newsletters.</p>
              </div>
            </div>
          </div>

        {{-- TAB 7: CASE STUDIES --}}
        @elseif ($currentTab === 'case-studies')
          <div class="space-y-8 animate-fadeIn">
            <div>
              <span class="px-3 py-1 rounded-pill bg-brand-purple/10 text-brand-purple text-xs font-bold uppercase tracking-wider">
                Track Record
              </span>
              <h1 class="text-2xl sm:text-3xl font-extrabold font-display text-brand-hero tracking-tight mt-2">
                Client Success Stories
              </h1>
              <p class="text-text-muted text-sm mt-2">
                Real operational outcomes delivered by Remote Leverage specialists.
              </p>
            </div>

            <div class="space-y-6">
              <div class="p-6 rounded-card border border-slate-200/80 bg-bg-light space-y-3">
                <div class="flex items-center justify-between text-xs">
                  <span class="font-bold text-brand-purple">Real Estate Brokerage</span>
                  <span class="text-text-slate">Miami, FL</span>
                </div>
                <h3 class="text-base font-bold text-brand-hero">Saved $54,000/year while doubling monthly escrow closing velocity</h3>
                <p class="text-xs sm:text-sm text-text-muted">By deploying two bilingual transaction coordinators in Colombia, the team removed administrative friction from top-producing agents.</p>
              </div>

              <div class="p-6 rounded-card border border-slate-200/80 bg-bg-light space-y-3">
                <div class="flex items-center justify-between text-xs">
                  <span class="font-bold text-brand-purple">B2B SaaS Growth Agency</span>
                  <span class="text-text-slate">Austin, TX</span>
                </div>
                <h3 class="text-base font-bold text-brand-hero">Scaled client roster from 8 to 22 accounts with 1 operational EA</h3>
                <p class="text-xs sm:text-sm text-text-muted">The founder eliminated 25 hours per week of manual client onboarding and reporting tasks.</p>
              </div>
            </div>
          </div>

        {{-- TAB 8: FAQ --}}
        @elseif ($currentTab === 'faq')
          <div class="space-y-8 animate-fadeIn">
            <div>
              <span class="px-3 py-1 rounded-pill bg-brand-purple/10 text-brand-purple text-xs font-bold uppercase tracking-wider">
                FAQ
              </span>
              <h1 class="text-2xl sm:text-3xl font-extrabold font-display text-brand-hero tracking-tight mt-2">
                Partner Frequently Asked Questions
              </h1>
            </div>

            <div class="space-y-4">
              <details class="p-5 rounded-card border border-slate-200/80 bg-bg-light">
                <summary class="font-bold text-sm text-brand-hero cursor-pointer">How are referred clients tracked to our partner account?</summary>
                <p class="text-xs sm:text-sm text-text-muted mt-3">Referred leads are attributed through unique URL parameters (`?via={{ $partnerCode }}`), dedicated calendar booking links, or email introductions. Attribution is locked for 90 days from the initial introduction.</p>
              </details>

              <details class="p-5 rounded-card border border-slate-200/80 bg-bg-light">
                <summary class="font-bold text-sm text-brand-hero cursor-pointer">When and how are referral commissions paid?</summary>
                <p class="text-xs sm:text-sm text-text-muted mt-3">Commissions are processed within 15 business days following client payment collection, transferred directly via Stripe Connect.</p>
              </details>

              <details class="p-5 rounded-card border border-slate-200/80 bg-bg-light">
                <summary class="font-bold text-sm text-brand-hero cursor-pointer">What happens if a client is unhappy with their hire?</summary>
                <p class="text-xs sm:text-sm text-text-muted mt-3">Our 6-month free replacement guarantee ensures we remap and onboard a replacement candidate immediately at zero cost.</p>
              </details>
            </div>
          </div>

        {{-- TAB 9: CONTACT --}}
        @elseif ($currentTab === 'contact')
          <div class="space-y-8 animate-fadeIn">
            <div>
              <span class="px-3 py-1 rounded-pill bg-brand-purple/10 text-brand-purple text-xs font-bold uppercase tracking-wider">
                Partner Support
              </span>
              <h1 class="text-2xl sm:text-3xl font-extrabold font-display text-brand-hero tracking-tight mt-2">
                Dedicated Partnerships Contact
              </h1>
              <p class="text-text-muted text-sm mt-2">
                Direct access to our leadership team for custom client inquiries, co-marketing requests, or billing questions.
              </p>
            </div>

            <div class="p-8 rounded-card-lg border border-slate-200/80 bg-bg-light max-w-md space-y-4">
              <div class="w-12 h-12 rounded-full bg-brand-purple text-white flex items-center justify-center font-bold text-lg">
                AS
              </div>
              <div>
                <h3 class="font-bold font-display text-lg text-brand-hero">{{ $managerName }}</h3>
                <p class="text-xs text-text-slate">Partnerships & Operations Director</p>
              </div>

              <div class="pt-4 border-t border-slate-200/60 space-y-2 text-xs">
                <div class="flex items-center gap-2 text-text-muted">
                  <span class="font-semibold text-text-body">Email:</span>
                  <a href="mailto:{{ $managerEmail }}" class="text-brand-purple hover:underline">{{ $managerEmail }}</a>
                </div>
                <div class="flex items-center gap-2 text-text-muted">
                  <span class="font-semibold text-text-body">Office:</span>
                  <span>+1 (800) 518-9128</span>
                </div>
                <div class="flex items-center gap-2 text-text-muted">
                  <span class="font-semibold text-text-body">Location:</span>
                  <span>Sheridan, WY &bull; Miami, FL</span>
                </div>
              </div>

              <div class="pt-4">
                <a
                  href="#booking-wizard"
                  class="block w-full text-center py-2.5 rounded-cta bg-brand-purple hover:bg-brand-purple-deep text-white text-xs font-bold shadow transition"
                >
                  Schedule Partner Sync
                </a>
              </div>
            </div>
          </div>
        @endif

      </main>

    </div>
  </div>
</div>
@endsection
