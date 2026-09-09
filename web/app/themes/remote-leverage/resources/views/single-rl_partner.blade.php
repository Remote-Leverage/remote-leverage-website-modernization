@extends('layouts.app')

@section('content')
@php
  $postId = get_the_ID();
  $partnerPermalink = get_permalink($postId);

  $enableComarketing = get_post_meta($postId, '_rl_enable_comarketing', true);
  $hasComarketing = \App\Domains\PartnerHub\Services\PartnerHubTabResolver::isComarketingEnabled($enableComarketing);

  // Active Tab Routing (supports /partners/{slug}/{tab} rewrite and ?tab=)
  $rawTab = get_query_var('rl_tab') ?: (isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'overview');
  $validTabs = \App\Domains\PartnerHub\Services\PartnerHubTabResolver::resolveTabs($hasComarketing);
  $currentTab = \App\Domains\PartnerHub\Services\PartnerHubTabResolver::resolveCurrentTab($rawTab, $validTabs);

  // Branding
  $partnerCode = get_post_meta($postId, '_rl_partner_code', true) ?: 'RL-PARTNER';
  $partnerName = get_post_meta($postId, '_rl_partner_name', true) ?: get_the_title();
  $partnerLogo = get_post_meta($postId, '_rl_partner_logo_url', true);
  $partnerCover = get_post_meta($postId, '_rl_partner_cover_url', true);
  $partnerWebsite = get_post_meta($postId, '_rl_partner_website', true);

  // Terms & Agreement Specs
  $partnershipType = get_post_meta($postId, '_rl_partnership_type', true) ?: 'Strategic Referral Partner';
  $territory = get_post_meta($postId, '_rl_territory', true) ?: 'Worldwide';
  $reportingPeriod = get_post_meta($postId, '_rl_reporting_period', true) ?: 'Quarterly';
  $initialTerm = get_post_meta($postId, '_rl_initial_term', true) ?: '12 months';
  $renewalTerms = get_post_meta($postId, '_rl_renewal_terms', true) ?: 'Automatic 12-month renewal, subject to the partnership agreement';

  // Direction A: Partner -> Remote Leverage
  $referralFormUrl = get_post_meta($postId, '_rl_referral_form_url', true);
  $referralDriveUrl = get_post_meta($postId, '_rl_referral_drive_url', true);
  $introEmail = get_post_meta($postId, '_rl_intro_email', true) ?: 'partnerships@remoteleverage.com';
  $partnerToRlFee = get_post_meta($postId, '_rl_partner_to_rl_fee', true) ?: "{$partnerName} receives 10% of the net Remote Leverage placement fee actually collected from an eligible referred customer. One-time referral fee, not recurring.";

  // Direction B: Remote Leverage -> Partner
  $partnerReferralLabel = get_post_meta($postId, '_rl_partner_referral_label', true) ?: "Refer a Client to {$partnerName}";
  $partnerReferralEmail = get_post_meta($postId, '_rl_partner_referral_email', true);
  $partnerReferralUrl = get_post_meta($postId, '_rl_partner_referral_url', true);
  $rlToPartnerFee = get_post_meta($postId, '_rl_rl_to_partner_fee', true) ?: "Remote Leverage receives 10% of eligible net subscription fees actually collected by {$partnerName} from an eligible referred customer, up to 12 months.";

  // Dedicated Resources
  $rlResourceTitle = get_post_meta($postId, '_rl_rl_resource_title', true) ?: 'Remote Leverage Partner Assets';
  $rlResourceUrl = get_post_meta($postId, '_rl_rl_resource_url', true);
  $partnerResourceTitle = get_post_meta($postId, '_rl_partner_resource_title', true) ?: "{$partnerName} Partner Hub";
  $partnerResourceUrl = get_post_meta($postId, '_rl_partner_resource_url', true);
  $onePagerPdf = get_post_meta($postId, '_rl_one_pager_pdf_url', true);
  $agreementPdf = get_post_meta($postId, '_rl_agreement_pdf_url', true);

  // Co-Marketing
  $comarketingDefaults = \App\Domains\PartnerHub\Services\PartnerHubGlobalData::getDefaultComarketing();
  $comarketingText = get_post_meta($postId, '_rl_comarketing_text', true) ?: $comarketingDefaults['lead_text'];
  $comarketingNotice = get_post_meta($postId, '_rl_comarketing_approval_note', true) ?: $comarketingDefaults['approval_note'];

  // Manager Contact
  $managerName = get_post_meta($postId, '_rl_manager_name', true) ?: 'Partnerships Team';
  $managerEmail = get_post_meta($postId, '_rl_manager_email', true) ?: 'partnerships@remoteleverage.com';
  $managerTitle = get_post_meta($postId, '_rl_manager_title', true) ?: 'Partnerships Director';

  // Content Overrides (blank = fall back to global defaults)
  $overrideWelcome = get_post_meta($postId, '_rl_override_welcome_text', true);
  $overrideCompanyDesc = get_post_meta($postId, '_rl_override_company_desc', true);
  $overrideRules = get_post_meta($postId, '_rl_override_referral_rules', true);
  $overrideCommission = get_post_meta($postId, '_rl_override_commission_terms', true);

  // Per-Section PDF Attachments
  $allAttachments = get_post_meta($postId, '_rl_section_attachments', true);
  $currentAttachments = (is_array($allAttachments) && isset($allAttachments[$currentTab]) && is_array($allAttachments[$currentTab])) ? $allAttachments[$currentTab] : [];

  // Global Default Content Library (WR-120)
  $whyChooseRl = \App\Domains\PartnerHub\Services\PartnerHubGlobalData::getWhyChooseRl();
  $comparisonMatrix = \App\Domains\PartnerHub\Services\PartnerHubGlobalData::getComparisonMatrix();
  $targetIndustries = \App\Domains\PartnerHub\Services\PartnerHubGlobalData::getTargetIndustries();
  $geographicMarkets = \App\Domains\PartnerHub\Services\PartnerHubGlobalData::getGeographicMarkets();
  $services = \App\Domains\PartnerHub\Services\PartnerHubGlobalData::getServices();
  $lifecycleStages = \App\Domains\PartnerHub\Services\PartnerHubGlobalData::getDefaultLifecycleStages();
  $defaultRules = \App\Domains\PartnerHub\Services\PartnerHubGlobalData::getDefaultReferralRules();
  $caseStudies = \App\Domains\PartnerHub\Services\PartnerHubGlobalData::getCaseStudies();
  $faqs = \App\Domains\PartnerHub\Services\PartnerHubGlobalData::getFaqs();

  function getTabUrl($permalink, $tab) {
      if ($tab === 'overview') {
          return esc_url($permalink);
      }
      return esc_url(add_query_arg('tab', $tab, $permalink));
  }
@endphp

<div class="min-h-screen bg-bg-light py-8 sm:py-12" x-data="{ mobileMenuOpen: false }">
  <div class="max-w-[1380px] mx-auto px-4 sm:px-6 lg:px-8">

    {{-- Top Co-Branded Hero Bar --}}
    <div
      class="rounded-card-lg bg-gradient-to-r from-brand-midnight via-brand-navy to-brand-hero text-white p-6 sm:p-8 shadow-card mb-8 border border-purple-900/40 relative overflow-hidden"
      @if ($partnerCover) style="background-image: linear-gradient(rgba(15,10,30,.75), rgba(15,10,30,.75)), url('{{ esc_url($partnerCover) }}'); background-size: cover; background-position: center;" @endif
    >
      <div class="absolute -right-16 -top-16 w-64 h-64 bg-brand-purple/20 rounded-full blur-3xl pointer-events-none"></div>

      <div class="relative z-10 flex flex-col md:flex-row md:items-center md:justify-between gap-6">
        <div class="space-y-2">
          {{-- Dual Logo Lockup --}}
          <div class="flex items-center gap-3">
            <span class="font-bold font-display text-lg sm:text-xl text-white tracking-tight">Remote Leverage</span>
            <span class="text-brand-magenta font-bold text-lg">&times;</span>
            @if ($partnerLogo)
              <img src="{{ $partnerLogo }}" alt="{{ $partnerName }}" class="h-7 max-w-[140px] object-contain brightness-0 invert" />
            @else
              <span class="font-bold text-lg sm:text-xl text-purple-200">{{ $partnerName }}</span>
            @endif
          </div>

          <p class="text-xs sm:text-sm text-slate-300">
            {{ $overrideWelcome ?: 'Co-Branded Partnership Knowledge Hub & Operational Directory' }}
          </p>
        </div>

        {{-- Partner Status Pill & Quick Action --}}
        <div class="flex flex-wrap items-center gap-3">
          <div class="px-3.5 py-1.5 rounded-pill bg-white/10 backdrop-blur-md border border-white/20 text-xs font-mono text-purple-200">
            <span class="text-slate-400">PARTNER CODE:</span> <strong class="text-white">{{ $partnerCode }}</strong>
          </div>

          @if ($partnerWebsite)
            <a
              href="{{ esc_url($partnerWebsite) }}"
              target="_blank"
              class="px-5 py-2 rounded-cta bg-white/10 hover:bg-white/15 border border-white/20 text-white text-xs sm:text-sm font-bold transition"
            >
              Visit {{ $partnerName }} &#8599;
            </a>
          @endif
        </div>
      </div>
    </div>

    {{-- Main 3-Column Documentation Grid: Nav / Content / Resources --}}
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
          <span class="text-2xs font-bold uppercase tracking-wider text-text-slate px-3">Getting Started</span>
          <nav class="mt-2 space-y-1">
            <a href="{{ getTabUrl($partnerPermalink, 'overview') }}" class="block px-3 py-2 rounded-card text-xs font-bold transition {{ $currentTab === 'overview' ? 'bg-brand-purple text-white shadow-sm' : 'text-text-body hover:bg-slate-50' }}">
              Overview & Actions
            </a>
          </nav>
        </div>

        <div>
          <span class="text-2xs font-bold uppercase tracking-wider text-text-slate px-3">Partnership Playbook</span>
          <nav class="mt-2 space-y-1">
            <a href="{{ getTabUrl($partnerPermalink, 'icp') }}" class="block px-3 py-2 rounded-card text-xs font-semibold transition {{ $currentTab === 'icp' ? 'bg-brand-purple text-white shadow-sm font-bold' : 'text-text-body hover:bg-slate-50' }}">
              Ideal Client Profile
            </a>
            <a href="{{ getTabUrl($partnerPermalink, 'services') }}" class="block px-3 py-2 rounded-card text-xs font-semibold transition {{ $currentTab === 'services' ? 'bg-brand-purple text-white shadow-sm font-bold' : 'text-text-body hover:bg-slate-50' }}">
              Services Overview
            </a>
            <a href="{{ getTabUrl($partnerPermalink, 'why-rl') }}" class="block px-3 py-2 rounded-card text-xs font-semibold transition {{ $currentTab === 'why-rl' ? 'bg-brand-purple text-white shadow-sm font-bold' : 'text-text-body hover:bg-slate-50' }}">
              Why Remote Leverage
            </a>
          </nav>
        </div>

        <div>
          <span class="text-2xs font-bold uppercase tracking-wider text-text-slate px-3">Program & Collaboration</span>
          <nav class="mt-2 space-y-1">
            <a href="{{ getTabUrl($partnerPermalink, 'referral-program') }}" class="block px-3 py-2 rounded-card text-xs font-semibold transition {{ $currentTab === 'referral-program' ? 'bg-brand-purple text-white shadow-sm font-bold' : 'text-text-body hover:bg-slate-50' }}">
              Referral Program & Fees
            </a>
            @if ($hasComarketing)
              <a href="{{ getTabUrl($partnerPermalink, 'comarketing') }}" class="block px-3 py-2 rounded-card text-xs font-semibold transition {{ $currentTab === 'comarketing' ? 'bg-brand-purple text-white shadow-sm font-bold' : 'text-text-body hover:bg-slate-50' }}">
                Co-Marketing
              </a>
            @endif
            <a href="{{ getTabUrl($partnerPermalink, 'case-studies') }}" class="block px-3 py-2 rounded-card text-xs font-semibold transition {{ $currentTab === 'case-studies' ? 'bg-brand-purple text-white shadow-sm font-bold' : 'text-text-body hover:bg-slate-50' }}">
              Case Studies
            </a>
          </nav>
        </div>

        <div>
          <span class="text-2xs font-bold uppercase tracking-wider text-text-slate px-3">Help & Support</span>
          <nav class="mt-2 space-y-1">
            <a href="{{ getTabUrl($partnerPermalink, 'faq') }}" class="block px-3 py-2 rounded-card text-xs font-semibold transition {{ $currentTab === 'faq' ? 'bg-brand-purple text-white shadow-sm font-bold' : 'text-text-body hover:bg-slate-50' }}">
              Partner FAQ
            </a>
            <a href="{{ getTabUrl($partnerPermalink, 'contact') }}" class="block px-3 py-2 rounded-card text-xs font-semibold transition {{ $currentTab === 'contact' ? 'bg-brand-purple text-white shadow-sm font-bold' : 'text-text-body hover:bg-slate-50' }}">
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

      {{-- Center Main Documentation Content Area --}}
      <main class="lg:col-span-6 bg-surface-white rounded-card-lg border border-slate-200/80 shadow-card p-6 sm:p-10 space-y-8">

        {{-- TAB 1: OVERVIEW --}}
        @if ($currentTab === 'overview')
          <div class="space-y-8 animate-fadeIn">
            <div>
              <span class="px-3 py-1 rounded-pill bg-brand-purple/10 text-brand-purple text-xs font-bold uppercase tracking-wider">Partnership Brief</span>
              <h1 class="text-2xl sm:text-3xl font-bold font-display text-brand-hero tracking-tight mt-2">
                Remote Leverage &times; {{ $partnerName }} Alliance
              </h1>
              <p class="text-text-muted text-sm sm:text-base mt-2 leading-relaxed">
                {{ $overrideCompanyDesc ?: 'Remote Leverage is a global recruitment and talent acquisition firm that connects growth-oriented companies with thoroughly vetted, top-tier international professionals. Under our direct-hire model, Remote Leverage sources and screens candidates, presents a curated shortlist, the client interviews and selects the candidate, and the client hires directly. Remote Leverage receives a one-time placement fee when a client hires.' }}
              </p>
            </div>

            {{-- Partnership Terms Summary --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
              <div class="p-4 rounded-card bg-bg-light border border-slate-200/60">
                <div class="text-2xs text-text-slate uppercase tracking-wider font-bold">Partnership Type</div>
                <div class="text-sm font-bold text-text-body mt-1">{{ $partnershipType }}</div>
              </div>
              <div class="p-4 rounded-card bg-bg-light border border-slate-200/60">
                <div class="text-2xs text-text-slate uppercase tracking-wider font-bold">Territory</div>
                <div class="text-sm font-bold text-text-body mt-1">{{ $territory }}</div>
              </div>
              <div class="p-4 rounded-card bg-bg-light border border-slate-200/60">
                <div class="text-2xs text-text-slate uppercase tracking-wider font-bold">Reporting Period</div>
                <div class="text-sm font-bold text-text-body mt-1">{{ $reportingPeriod }}</div>
              </div>
              <div class="p-4 rounded-card bg-bg-light border border-slate-200/60">
                <div class="text-2xs text-text-slate uppercase tracking-wider font-bold">Initial Term</div>
                <div class="text-sm font-bold text-text-body mt-1">{{ $initialTerm }}</div>
              </div>
              <div class="p-4 rounded-card bg-bg-light border border-slate-200/60 sm:col-span-2">
                <div class="text-2xs text-text-slate uppercase tracking-wider font-bold">Renewal Terms</div>
                <div class="text-sm font-bold text-text-body mt-1">{{ $renewalTerms }}</div>
              </div>
            </div>

            {{-- Bidirectional Referral Actions --}}
            <div class="space-y-4 pt-4 border-t border-slate-100">
              <h2 class="text-lg font-bold font-display text-brand-hero">Two-Way Referral Actions</h2>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                {{-- Direction A: Partner -> Remote Leverage --}}
                <div class="p-5 rounded-card border-2 border-brand-purple/40 space-y-3">
                  <span class="px-2 py-0.5 rounded-pill bg-brand-purple/10 text-brand-purple text-2xs font-bold uppercase tracking-wider">
                    {{ $partnerName }} &rarr; Remote Leverage
                  </span>
                  <p class="text-xs sm:text-sm text-text-muted">
                    Submit a candidate search introduction with referral code <code class="px-1.5 py-0.5 rounded bg-slate-100 font-mono text-brand-purple">{{ $partnerCode }}</code> or send a warm email intro.
                  </p>
                  <div class="flex flex-wrap gap-2">
                    @if ($referralFormUrl)
                      <a href="{{ esc_url($referralFormUrl) }}" target="_blank" class="px-3.5 py-2 rounded-cta bg-brand-purple hover:bg-brand-purple-deep text-white text-xs font-bold transition">
                        Submit via Online Form
                      </a>
                    @endif
                    <a href="mailto:{{ esc_attr($introEmail) }}?subject=Client%20Referral%20from%20{{ rawurlencode($partnerName) }}" class="px-3.5 py-2 rounded-cta bg-slate-100 hover:bg-slate-200 text-text-body text-xs font-semibold transition">
                      Intro Email
                    </a>
                    @if ($referralDriveUrl)
                      <a href="{{ esc_url($referralDriveUrl) }}" target="_blank" class="px-3.5 py-2 rounded-cta bg-slate-100 hover:bg-slate-200 text-text-body text-xs font-semibold transition">
                        Tracking Sheet
                      </a>
                    @endif
                  </div>
                </div>

                {{-- Direction B: Remote Leverage -> Partner --}}
                <div class="p-5 rounded-card border-2 border-sky-500/30 space-y-3">
                  <span class="px-2 py-0.5 rounded-pill bg-sky-50 text-sky-700 text-2xs font-bold uppercase tracking-wider">
                    Remote Leverage &rarr; {{ $partnerName }}
                  </span>
                  <p class="text-xs sm:text-sm text-text-muted">{{ $partnerReferralLabel }}</p>
                  <div class="flex flex-wrap gap-2">
                    @if ($partnerReferralUrl)
                      <a href="{{ esc_url($partnerReferralUrl) }}" target="_blank" class="px-3.5 py-2 rounded-cta bg-sky-600 hover:bg-sky-700 text-white text-xs font-bold transition">
                        Open {{ $partnerName }} Portal
                      </a>
                    @endif
                    @if ($partnerReferralEmail && strtolower($partnerReferralEmail) !== 'pending to define')
                      <a href="mailto:{{ esc_attr($partnerReferralEmail) }}?subject=Client%20Referral%20from%20Remote%20Leverage" class="px-3.5 py-2 rounded-cta bg-slate-100 hover:bg-slate-200 text-text-body text-xs font-semibold transition">
                        Email {{ $partnerName }} Team
                      </a>
                    @else
                      <span class="px-3 py-2 rounded-cta bg-amber-50 text-amber-700 text-2xs font-semibold">Referral destination: pending setup</span>
                    @endif
                  </div>
                </div>
              </div>
            </div>

            <div class="space-y-4 pt-4 border-t border-slate-100">
              <h2 class="text-lg font-bold font-display text-brand-hero">Core Operating Values</h2>
              <div class="overflow-x-auto rounded-card border border-slate-200/80">
                <table class="w-full text-xs sm:text-sm">
                  <thead class="bg-bg-light">
                    <tr>
                      <th class="text-left px-4 py-2 font-bold text-text-body">Value</th>
                      <th class="text-left px-4 py-2 font-bold text-text-body">Meaning</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-slate-100">
                    @foreach (\App\Domains\PartnerHub\Services\PartnerHubGlobalData::getCoreValues() as $value)
                      <tr>
                        <td class="px-4 py-2 font-bold text-text-body whitespace-nowrap">{{ $value['value'] }}</td>
                        <td class="px-4 py-2 text-text-muted">{{ $value['meaning'] }}</td>
                      </tr>
                    @endforeach
                  </tbody>
                </table>
              </div>
            </div>
          </div>

        {{-- TAB 2: IDEAL CLIENT PROFILE --}}
        @elseif ($currentTab === 'icp')
          <div class="space-y-8 animate-fadeIn">
            <div>
              <span class="px-3 py-1 rounded-pill bg-brand-purple/10 text-brand-purple text-xs font-bold uppercase tracking-wider">Target Audience</span>
              <h1 class="text-2xl sm:text-3xl font-bold font-display text-brand-hero tracking-tight mt-2">Ideal Client Profile (ICP)</h1>
              <p class="text-text-muted text-sm mt-2">Qualification criteria and target market guidelines to identify strong referral opportunities.</p>
            </div>

            <div class="p-5 rounded-card bg-emerald-50 border border-emerald-200">
              <p class="text-sm text-emerald-900 font-medium">
                The ideal Remote Leverage client is a business (SMB to Enterprise) seeking to hire qualified staff faster (2&ndash;4 weeks), with zero upfront placement fees and significant cost savings over traditional domestic recruiting.
              </p>
            </div>

            <div>
              <h3 class="font-bold text-base text-brand-hero mb-3">Target Industries</h3>
              <div class="flex flex-wrap gap-2">
                @foreach ($targetIndustries as $industry)
                  <span class="px-3 py-1 rounded-pill bg-slate-100 text-text-body text-xs font-medium">{{ $industry }}</span>
                @endforeach
              </div>
            </div>

            <div>
              <h3 class="font-bold text-base text-brand-hero mb-3">Geographic Coverage</h3>
              <div class="overflow-x-auto rounded-card border border-slate-200/80">
                <table class="w-full text-xs sm:text-sm">
                  <thead class="bg-bg-light">
                    <tr>
                      <th class="text-left px-4 py-2 font-bold text-text-body">Region</th>
                      <th class="text-left px-4 py-2 font-bold text-text-body">Market Characteristics</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-slate-100">
                    @foreach ($geographicMarkets as $region => $desc)
                      <tr>
                        <td class="px-4 py-2 font-bold text-text-body whitespace-nowrap">{{ $region }}</td>
                        <td class="px-4 py-2 text-text-muted">{{ $desc }}</td>
                      </tr>
                    @endforeach
                  </tbody>
                </table>
              </div>
            </div>
          </div>

        {{-- TAB 3: SERVICES OVERVIEW --}}
        @elseif ($currentTab === 'services')
          <div class="space-y-8 animate-fadeIn">
            <div>
              <span class="px-3 py-1 rounded-pill bg-brand-purple/10 text-brand-purple text-xs font-bold uppercase tracking-wider">Capabilities</span>
              <h1 class="text-2xl sm:text-3xl font-bold font-display text-brand-hero tracking-tight mt-2">Services Overview</h1>
              <p class="text-text-muted text-sm mt-2">Remote Leverage provides specialized direct-hire recruitment across key business functions.</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              @foreach ($services as $service)
                <div class="p-5 rounded-card border border-slate-200/80 space-y-2">
                  <h3 class="font-bold text-sm text-brand-hero">{{ $service['name'] }}</h3>
                  <span class="text-2xs font-semibold text-brand-purple">Best for: {{ $service['best_for'] }}</span>
                  <p class="text-xs sm:text-sm text-text-muted">{{ $service['desc'] }}</p>
                </div>
              @endforeach
            </div>
          </div>

        {{-- TAB 4: WHY REMOTE LEVERAGE --}}
        @elseif ($currentTab === 'why-rl')
          <div class="space-y-8 animate-fadeIn">
            <div>
              <span class="px-3 py-1 rounded-pill bg-brand-purple/10 text-brand-purple text-xs font-bold uppercase tracking-wider">Competitive Edge</span>
              <h1 class="text-2xl sm:text-3xl font-bold font-display text-brand-hero tracking-tight mt-2">Why Remote Leverage</h1>
              <p class="text-text-muted text-sm mt-2">Comparative analysis of Remote Leverage vs. traditional staffing agencies and in-house hiring.</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              @foreach ($whyChooseRl as $point)
                <div class="p-4 rounded-card bg-bg-light border border-slate-200/80">
                  <h3 class="font-bold text-sm text-brand-hero">{{ $point['title'] }}</h3>
                  <p class="text-xs sm:text-sm text-text-muted mt-1">{{ $point['desc'] }}</p>
                </div>
              @endforeach
            </div>

            <div class="overflow-x-auto rounded-card border border-slate-200/80">
              <table class="w-full text-xs sm:text-sm">
                <thead class="bg-bg-light">
                  <tr>
                    <th class="text-left px-4 py-2 font-bold text-text-body">Criteria</th>
                    <th class="text-left px-4 py-2 font-bold text-brand-purple">Remote Leverage</th>
                    <th class="text-left px-4 py-2 font-bold text-text-body">In-House / Job Boards</th>
                    <th class="text-left px-4 py-2 font-bold text-text-body">Traditional Staffing</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                  @foreach ($comparisonMatrix as $row)
                    <tr>
                      <td class="px-4 py-2 font-bold text-text-body whitespace-nowrap">{{ $row['feature'] }}</td>
                      <td class="px-4 py-2 font-semibold text-brand-purple">{{ $row['rl'] }}</td>
                      <td class="px-4 py-2 text-text-muted">{{ $row['inhouse'] }}</td>
                      <td class="px-4 py-2 text-text-muted">{{ $row['agency'] }}</td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          </div>

        {{-- TAB 5: REFERRAL PROGRAM & FEES --}}
        @elseif ($currentTab === 'referral-program')
          <div class="space-y-8 animate-fadeIn">
            <div>
              <span class="px-3 py-1 rounded-pill bg-brand-purple/10 text-brand-purple text-xs font-bold uppercase tracking-wider">Economics</span>
              <h1 class="text-2xl sm:text-3xl font-bold font-display text-brand-hero tracking-tight mt-2">Referral Program & Commission Terms</h1>
              <p class="text-text-muted text-sm mt-2">Transparent, bidirectional revenue-sharing terms for {{ $partnerName }}.</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div class="p-5 rounded-card border-2 border-brand-purple/40 space-y-2">
                <span class="text-2xs font-bold uppercase tracking-wider text-brand-purple">Direction A: {{ $partnerName }} &rarr; Remote Leverage</span>
                <p class="text-sm text-text-body leading-relaxed">{{ $partnerToRlFee }}</p>
              </div>
              <div class="p-5 rounded-card border-2 border-sky-500/30 space-y-2">
                <span class="text-2xs font-bold uppercase tracking-wider text-sky-700">Direction B: Remote Leverage &rarr; {{ $partnerName }}</span>
                <p class="text-sm text-text-body leading-relaxed">{{ $rlToPartnerFee }}</p>
              </div>
            </div>

            <div>
              <h3 class="font-bold text-base text-brand-hero mb-3">Referral Eligibility Rules</h3>
              <div class="p-5 rounded-card bg-bg-light border border-slate-200/80">
                @if ($overrideRules)
                  <p class="text-xs sm:text-sm text-text-muted whitespace-pre-line">{{ $overrideRules }}</p>
                @else
                  <ul class="text-xs sm:text-sm text-text-muted space-y-2 list-disc list-inside">
                    @foreach ($defaultRules as $rule)
                      <li>{{ $rule }}</li>
                    @endforeach
                  </ul>
                @endif
              </div>
            </div>

            <div>
              <h3 class="font-bold text-base text-brand-hero mb-3">Referral Lifecycle Stages</h3>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                @foreach ($lifecycleStages as $idx => $stage)
                  <div class="flex items-start gap-3 p-3 rounded-card border border-slate-200/80">
                    <span class="w-6 h-6 shrink-0 rounded-full bg-brand-purple text-white flex items-center justify-center font-bold text-2xs">{{ $idx + 1 }}</span>
                    <div>
                      <div class="font-bold text-xs text-text-body">{{ $stage['status'] }}</div>
                      <div class="text-2xs text-text-muted mt-0.5">{{ $stage['desc'] }}</div>
                    </div>
                  </div>
                @endforeach
              </div>
            </div>

            @if ($overrideCommission)
              <div class="p-5 rounded-card bg-amber-50 border border-amber-200">
                <h3 class="font-bold text-sm text-amber-900 mb-1">Special Partnership Terms</h3>
                <p class="text-xs sm:text-sm text-amber-800 whitespace-pre-line">{{ $overrideCommission }}</p>
              </div>
            @endif
          </div>

        {{-- TAB 6: CO-MARKETING (conditional) --}}
        @elseif ($currentTab === 'comarketing' && $hasComarketing)
          <div class="space-y-8 animate-fadeIn">
            <div>
              <span class="px-3 py-1 rounded-pill bg-brand-purple/10 text-brand-purple text-xs font-bold uppercase tracking-wider">Collaborative Growth</span>
              <h1 class="text-2xl sm:text-3xl font-bold font-display text-brand-hero tracking-tight mt-2">Co-Marketing Opportunities & Guidelines</h1>
              <p class="text-text-muted text-sm mt-2">{{ $comarketingText }}</p>
            </div>

            <div class="p-5 rounded-card bg-amber-50 border border-amber-200">
              <p class="text-xs sm:text-sm font-semibold text-amber-900">{{ $comarketingNotice }}</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              @foreach ($comarketingDefaults['opportunities'] as $opp)
                <div class="p-5 rounded-card border border-slate-200/80 space-y-2">
                  <h3 class="font-bold text-brand-hero text-sm">{{ $opp['title'] }}</h3>
                  <p class="text-xs sm:text-sm text-text-muted">{{ $opp['desc'] }}</p>
                </div>
              @endforeach
            </div>

            <div class="p-5 rounded-card bg-bg-light border border-slate-200/80">
              <p class="text-xs sm:text-sm text-text-muted">
                To propose a joint webinar, case study, or co-branded piece, email your dedicated manager at
                <a href="mailto:{{ $managerEmail }}" class="text-brand-purple font-semibold hover:underline">{{ $managerEmail }}</a>.
              </p>
            </div>
          </div>

        {{-- TAB 7: CASE STUDIES --}}
        @elseif ($currentTab === 'case-studies')
          <div class="space-y-8 animate-fadeIn">
            <div>
              <span class="px-3 py-1 rounded-pill bg-brand-purple/10 text-brand-purple text-xs font-bold uppercase tracking-wider">Track Record</span>
              <h1 class="text-2xl sm:text-3xl font-bold font-display text-brand-hero tracking-tight mt-2">Industry Case Studies</h1>
              <p class="text-text-muted text-sm mt-2">Real candidate placement results across key business sectors.</p>
            </div>

            <div class="space-y-6">
              @foreach ($caseStudies as $cs)
                <div class="p-6 rounded-card border border-slate-200/80 bg-bg-light space-y-3">
                  <div class="flex items-center justify-between text-xs">
                    <span class="font-bold text-brand-purple">{{ $cs['industry'] }}</span>
                  </div>
                  <h3 class="text-base font-bold text-brand-hero">{{ $cs['client'] }}</h3>

                  <div>
                    <div class="text-2xs font-bold uppercase tracking-wider text-text-slate mb-1">Challenge</div>
                    <ul class="text-xs sm:text-sm text-text-muted list-disc list-inside space-y-1">
                      @foreach ($cs['challenge'] as $point)
                        <li>{{ $point }}</li>
                      @endforeach
                    </ul>
                  </div>

                  <div>
                    <div class="text-2xs font-bold uppercase tracking-wider text-text-slate mb-1">Solution</div>
                    <ul class="text-xs sm:text-sm text-text-muted list-disc list-inside space-y-1">
                      @foreach ($cs['solution'] as $point)
                        <li>{{ $point }}</li>
                      @endforeach
                    </ul>
                  </div>

                  <div>
                    <div class="text-2xs font-bold uppercase tracking-wider text-emerald-700 mb-1">Key Outcomes</div>
                    <ul class="text-xs sm:text-sm text-emerald-900 font-medium list-disc list-inside space-y-1">
                      @foreach ($cs['outcome'] as $point)
                        <li>{{ $point }}</li>
                      @endforeach
                    </ul>
                  </div>
                </div>
              @endforeach
            </div>
          </div>

        {{-- TAB 8: FAQ --}}
        @elseif ($currentTab === 'faq')
          <div class="space-y-8 animate-fadeIn">
            <div>
              <span class="px-3 py-1 rounded-pill bg-brand-purple/10 text-brand-purple text-xs font-bold uppercase tracking-wider">FAQ</span>
              <h1 class="text-2xl sm:text-3xl font-bold font-display text-brand-hero tracking-tight mt-2">Partner Frequently Asked Questions</h1>
            </div>

            <div class="space-y-3">
              @foreach ($faqs as $faq)
                <details class="p-5 rounded-card border border-slate-200/80 bg-bg-light">
                  <summary class="font-bold text-sm text-brand-hero cursor-pointer">{{ $faq['q'] }}</summary>
                  <p class="text-xs sm:text-sm text-text-muted mt-3">{{ $faq['a'] }}</p>
                </details>
              @endforeach
            </div>
          </div>

        {{-- TAB 9: CONTACT --}}
        @elseif ($currentTab === 'contact')
          <div class="space-y-8 animate-fadeIn">
            <div>
              <span class="px-3 py-1 rounded-pill bg-brand-purple/10 text-brand-purple text-xs font-bold uppercase tracking-wider">Partner Support</span>
              <h1 class="text-2xl sm:text-3xl font-bold font-display text-brand-hero tracking-tight mt-2">Dedicated Partnerships Contact</h1>
              <p class="text-text-muted text-sm mt-2">Direct access to our team for custom client inquiries, co-marketing requests, or billing questions.</p>
            </div>

            <div class="p-8 rounded-card-lg border border-slate-200/80 bg-bg-light max-w-md space-y-4">
              <div class="w-12 h-12 rounded-full bg-brand-purple text-white flex items-center justify-center font-bold text-lg">
                {{ substr($managerName, 0, 1) }}
              </div>
              <div>
                <h3 class="font-bold font-display text-lg text-brand-hero">{{ $managerName }}</h3>
                <p class="text-xs text-text-slate">{{ $managerTitle }}</p>
              </div>
              <div class="pt-4 border-t border-slate-200/60">
                <a href="mailto:{{ $managerEmail }}" class="text-brand-purple hover:underline text-xs font-semibold">{{ $managerEmail }}</a>
              </div>
            </div>
          </div>
        @endif

        {{-- SECTION ATTACHMENTS & PDF DOWNLOADS --}}
        @if (! empty($currentAttachments))
          <div class="pt-6 border-t border-slate-100">
            <h3 class="font-bold text-sm text-brand-hero mb-3">{{ $validTabs[$currentTab] }} &mdash; Attachments & PDFs</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              @foreach ($currentAttachments as $attachment)
                <a href="{{ esc_url($attachment['url']) }}" target="_blank" class="flex items-center gap-3 p-3 rounded-card border border-slate-200/80 hover:border-brand-purple/40 transition">
                  <span class="text-red-600 text-lg">&#128196;</span>
                  <div class="flex-1 min-w-0">
                    <div class="text-xs font-bold text-text-body truncate">{{ $attachment['title'] }}</div>
                    <div class="text-2xs text-text-muted">{{ $attachment['size'] ?: 'PDF Document' }}</div>
                  </div>
                </a>
              @endforeach
            </div>
          </div>
        @endif

      </main>

      {{-- Right Sidebar: Dedicated Resources --}}
      <aside class="hidden lg:block lg:col-span-3 space-y-4 sticky top-8">
        <div class="bg-surface-white rounded-card-lg border border-slate-200/80 shadow-card p-5 space-y-3">
          <span class="text-2xs font-bold uppercase tracking-wider text-text-slate">Partnership Resources</span>

          @if ($rlResourceUrl)
            <a href="{{ esc_url($rlResourceUrl) }}" target="_blank" class="flex items-center gap-2 p-3 rounded-card border border-slate-200/80 hover:border-brand-purple/40 transition">
              <div class="flex-1 min-w-0">
                <div class="text-xs font-bold text-text-body truncate">{{ $rlResourceTitle }}</div>
                <div class="text-2xs text-text-muted">Remote Leverage Assets</div>
              </div>
            </a>
          @endif

          @if ($partnerResourceUrl)
            <a href="{{ esc_url($partnerResourceUrl) }}" target="_blank" class="flex items-center gap-2 p-3 rounded-card border border-slate-200/80 hover:border-brand-purple/40 transition">
              <div class="flex-1 min-w-0">
                <div class="text-xs font-bold text-text-body truncate">{{ $partnerResourceTitle }}</div>
                <div class="text-2xs text-text-muted">Partner Workspace</div>
              </div>
            </a>
          @endif

          @if ($onePagerPdf)
            <a href="{{ esc_url($onePagerPdf) }}" target="_blank" class="flex items-center gap-2 p-3 rounded-card border border-slate-200/80 hover:border-brand-purple/40 transition">
              <div class="flex-1 min-w-0">
                <div class="text-xs font-bold text-text-body truncate">Overview One-Pager</div>
                <div class="text-2xs text-text-muted">PDF Document</div>
              </div>
            </a>
          @endif

          @if ($agreementPdf)
            <a href="{{ esc_url($agreementPdf) }}" target="_blank" class="flex items-center gap-2 p-3 rounded-card border border-slate-200/80 hover:border-brand-purple/40 transition">
              <div class="flex-1 min-w-0">
                <div class="text-xs font-bold text-text-body truncate">Partner Agreement</div>
                <div class="text-2xs text-text-muted">PDF Document</div>
              </div>
            </a>
          @endif

          @if (! $rlResourceUrl && ! $partnerResourceUrl && ! $onePagerPdf && ! $agreementPdf)
            <p class="text-2xs text-text-muted">No dedicated resources configured yet.</p>
          @endif
        </div>

        <div class="bg-surface-white rounded-card-lg border border-slate-200/80 shadow-card p-5 space-y-2">
          <span class="text-2xs font-bold uppercase tracking-wider text-text-slate">Quick Actions</span>
          @if ($referralFormUrl)
            <a href="{{ esc_url($referralFormUrl) }}" target="_blank" class="block w-full text-center py-2 rounded-cta bg-brand-purple hover:bg-brand-purple-deep text-white text-xs font-bold transition">
              Refer to Remote Leverage
            </a>
          @endif
          @if ($referralDriveUrl)
            <a href="{{ esc_url($referralDriveUrl) }}" target="_blank" class="block w-full text-center py-2 rounded-cta bg-slate-100 hover:bg-slate-200 text-text-body text-xs font-semibold transition">
              View Tracking Sheet
            </a>
          @endif
        </div>

        <div class="bg-surface-white rounded-card-lg border border-slate-200/80 shadow-card p-5">
          <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-full bg-brand-purple text-white flex items-center justify-center font-bold text-sm shrink-0">
              {{ substr($managerName, 0, 1) }}
            </div>
            <div class="min-w-0">
              <div class="text-xs font-bold text-text-body truncate">{{ $managerName }}</div>
              <div class="text-2xs text-text-muted truncate">{{ $managerTitle }}</div>
            </div>
          </div>
          <a href="mailto:{{ $managerEmail }}" class="mt-3 block w-full text-center py-1.5 rounded-cta bg-slate-100 hover:bg-slate-200 text-text-body text-2xs font-semibold transition">
            Direct Email
          </a>
        </div>
      </aside>

    </div>
  </div>
</div>
@endsection
