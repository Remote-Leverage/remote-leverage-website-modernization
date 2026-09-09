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

  // Branding — logo/cover are ACF Image fields (return_format: url), read via get_field()
  $partnerCode = get_post_meta($postId, '_rl_partner_code', true) ?: 'RL-PARTNER';
  $partnerName = get_post_meta($postId, '_rl_partner_name', true) ?: get_the_title();
  $partnerLogo = get_field('_rl_partner_logo_url', $postId);
  $partnerCover = get_field('_rl_partner_cover_url', $postId);
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
  $onePagerPdf = get_field('_rl_one_pager_pdf_url', $postId);
  $agreementPdf = get_field('_rl_agreement_pdf_url', $postId);

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

  // Per-Section PDF Attachments — ACF repeater, one row per file, each tagged with its tab
  $allAttachments = get_field('_rl_section_attachments', $postId) ?: [];
  $currentAttachments = array_values(array_filter($allAttachments, fn ($row) => ($row['section'] ?? null) === $currentTab));

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

  function formatFileSize($bytes) {
      $bytes = (int) $bytes;
      if ($bytes <= 0) {
          return 'PDF Document';
      }
      return $bytes > 1048576
          ? round($bytes / 1048576, 1) . ' MB'
          : round($bytes / 1024) . ' KB';
  }

  // Small inline icon library matching the site's stroke-icon-in-tinted-chip pattern
  $rlIcon = function (string $name, string $class = 'w-5 h-5') {
      $paths = [
          'form' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>',
          'mail' => '<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>',
          'sheet' => '<rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/>',
          'external' => '<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/>',
          'drive' => '<path d="M4 15l4-7h8l4 7"/><path d="M4 15h16l-2 4H6z"/>',
          'pdf' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="9" y1="13" x2="15" y2="13"/><line x1="9" y1="17" x2="15" y2="17"/>',
          'check-circle' => '<circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/>',
          'alert' => '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>',
          'megaphone' => '<path d="M3 11l18-5v12L3 13v-2z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/>',
          'briefcase' => '<rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>',
          'globe' => '<circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>',
          'target' => '<circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/>',
          'trophy' => '<path d="M8 21h8M12 17v4M7 4h10v5a5 5 0 0 1-10 0z"/><path d="M17 5h3a2 2 0 0 1-2 4M7 5H4a2 2 0 0 0 2 4"/>',
          'chat' => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
          'users' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
          'arrow-right' => '<path d="M5 12h14M12 5l7 7-7 7"/>',
          'building' => '<rect x="4" y="2" width="16" height="20" rx="1"/><path d="M9 22v-4h6v4M9 7h1M14 7h1M9 11h1M14 11h1M9 15h1M14 15h1"/>',
          'phone-pending' => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
      ];

      return '<svg class="'.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'.($paths[$name] ?? $paths['check-circle']).'</svg>';
  };
@endphp

<div class="min-h-screen bg-bg-light" x-data="{ mobileMenuOpen: false }">

  {{-- Top Co-Branded Hero Bar (full-bleed section, matching hire-va-4 hero pattern) --}}
  <section
    class="w-full bg-gradient-to-r from-brand-midnight via-brand-navy to-brand-hero text-white relative overflow-hidden"
    @if ($partnerCover) style="background-image: linear-gradient(rgba(15,10,30,.75), rgba(15,10,30,.75)), url('{{ esc_url($partnerCover) }}'); background-size: cover; background-position: center;" @endif
  >
    <div class="absolute -right-16 -top-16 w-64 h-64 bg-brand-purple/25 rounded-full blur-3xl pointer-events-none"></div>
    <div class="absolute left-1/3 -bottom-20 w-56 h-56 bg-brand-magenta/15 rounded-full blur-3xl pointer-events-none"></div>

    <div class="w-full max-w-[1380px] mx-auto px-4 sm:px-6 lg:px-8 py-10 sm:py-14 relative z-10">
      <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-6">
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

          <p class="text-xs sm:text-sm text-slate-300 max-w-lg">
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
              class="inline-flex items-center gap-2 px-5 py-2 rounded-pill bg-white/10 hover:bg-white/20 border border-white/20 text-white text-xs sm:text-sm font-bold transition-all duration-200 hover:scale-[1.03]"
            >
              <span>Visit {{ $partnerName }}</span>
              {!! $rlIcon('external', 'w-3.5 h-3.5') !!}
            </a>
          @endif
        </div>
      </div>
    </div>
  </section>

  {{-- Main 3-Column Documentation Area: Nav / Content / Resources --}}
  <section class="w-full py-10 sm:py-14">
    <div class="w-full max-w-[1380px] mx-auto px-4 sm:px-6 lg:px-8">
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-10 items-start">

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
      <aside class="hidden lg:block lg:col-span-3 lg:border-r lg:border-slate-200 lg:pr-6 sticky top-8 space-y-6">
        <div>
          <span class="text-2xs font-bold uppercase tracking-wider text-text-slate px-3">Getting Started</span>
          <nav class="mt-2 space-y-1">
            <a href="{{ getTabUrl($partnerPermalink, 'overview') }}" class="block px-3 py-2 rounded-card text-xs font-bold transition {{ $currentTab === 'overview' ? 'bg-brand-purple text-white shadow-[0_4px_14px_rgba(138,43,226,0.35)]' : 'text-text-body hover:bg-slate-50' }}">
              Overview & Actions
            </a>
          </nav>
        </div>

        <div>
          <span class="text-2xs font-bold uppercase tracking-wider text-text-slate px-3">Partnership Playbook</span>
          <nav class="mt-2 space-y-1">
            <a href="{{ getTabUrl($partnerPermalink, 'icp') }}" class="block px-3 py-2 rounded-card text-xs font-semibold transition {{ $currentTab === 'icp' ? 'bg-brand-purple text-white shadow-[0_4px_14px_rgba(138,43,226,0.35)] font-bold' : 'text-text-body hover:bg-slate-50' }}">
              Ideal Client Profile
            </a>
            <a href="{{ getTabUrl($partnerPermalink, 'services') }}" class="block px-3 py-2 rounded-card text-xs font-semibold transition {{ $currentTab === 'services' ? 'bg-brand-purple text-white shadow-[0_4px_14px_rgba(138,43,226,0.35)] font-bold' : 'text-text-body hover:bg-slate-50' }}">
              Services Overview
            </a>
            <a href="{{ getTabUrl($partnerPermalink, 'why-rl') }}" class="block px-3 py-2 rounded-card text-xs font-semibold transition {{ $currentTab === 'why-rl' ? 'bg-brand-purple text-white shadow-[0_4px_14px_rgba(138,43,226,0.35)] font-bold' : 'text-text-body hover:bg-slate-50' }}">
              Why Remote Leverage
            </a>
          </nav>
        </div>

        <div>
          <span class="text-2xs font-bold uppercase tracking-wider text-text-slate px-3">Program & Collaboration</span>
          <nav class="mt-2 space-y-1">
            <a href="{{ getTabUrl($partnerPermalink, 'referral-program') }}" class="block px-3 py-2 rounded-card text-xs font-semibold transition {{ $currentTab === 'referral-program' ? 'bg-brand-purple text-white shadow-[0_4px_14px_rgba(138,43,226,0.35)] font-bold' : 'text-text-body hover:bg-slate-50' }}">
              Referral Program & Fees
            </a>
            @if ($hasComarketing)
              <a href="{{ getTabUrl($partnerPermalink, 'comarketing') }}" class="block px-3 py-2 rounded-card text-xs font-semibold transition {{ $currentTab === 'comarketing' ? 'bg-brand-purple text-white shadow-[0_4px_14px_rgba(138,43,226,0.35)] font-bold' : 'text-text-body hover:bg-slate-50' }}">
                Co-Marketing
              </a>
            @endif
            <a href="{{ getTabUrl($partnerPermalink, 'case-studies') }}" class="block px-3 py-2 rounded-card text-xs font-semibold transition {{ $currentTab === 'case-studies' ? 'bg-brand-purple text-white shadow-[0_4px_14px_rgba(138,43,226,0.35)] font-bold' : 'text-text-body hover:bg-slate-50' }}">
              Case Studies
            </a>
          </nav>
        </div>

        <div>
          <span class="text-2xs font-bold uppercase tracking-wider text-text-slate px-3">Help & Support</span>
          <nav class="mt-2 space-y-1">
            <a href="{{ getTabUrl($partnerPermalink, 'faq') }}" class="block px-3 py-2 rounded-card text-xs font-semibold transition {{ $currentTab === 'faq' ? 'bg-brand-purple text-white shadow-[0_4px_14px_rgba(138,43,226,0.35)] font-bold' : 'text-text-body hover:bg-slate-50' }}">
              Partner FAQ
            </a>
            <a href="{{ getTabUrl($partnerPermalink, 'contact') }}" class="block px-3 py-2 rounded-card text-xs font-semibold transition {{ $currentTab === 'contact' ? 'bg-brand-purple text-white shadow-[0_4px_14px_rgba(138,43,226,0.35)] font-bold' : 'text-text-body hover:bg-slate-50' }}">
              Contact Team
            </a>
          </nav>
        </div>

        {{-- Partner Support Card --}}
        <div class="pt-4 border-t border-slate-100 flex items-center gap-3">
          <div class="w-9 h-9 rounded-full bg-brand-purple/10 text-brand-purple flex items-center justify-center font-bold text-sm shrink-0">
            {{ substr($managerName, 0, 1) }}
          </div>
          <div class="min-w-0">
            <span class="block font-bold text-text-body text-xs truncate">{{ $managerName }}</span>
            <a href="mailto:{{ $managerEmail }}" class="text-brand-purple hover:underline text-2xs block leading-snug break-all">
              {{ $managerEmail }}
            </a>
          </div>
        </div>
      </aside>

      {{-- Center Main Documentation Content Area --}}
      <main class="lg:col-span-6 space-y-8">

        {{-- TAB 1: OVERVIEW --}}
        @if ($currentTab === 'overview')
          <div class="space-y-8 animate-fadeIn">
            <div>
              <span class="px-3 py-1 rounded-pill bg-brand-purple/10 text-brand-purple text-xs font-bold uppercase tracking-wider">Partnership Brief</span>
              <h1 class="text-2xl sm:text-3xl font-bold font-display text-brand-hero tracking-tight mt-3">
                Remote Leverage &times; {{ $partnerName }} Alliance
              </h1>
              <p class="text-text-muted text-sm sm:text-base mt-2 leading-relaxed">
                {{ $overrideCompanyDesc ?: 'Remote Leverage is a global recruitment and talent acquisition firm that connects growth-oriented companies with thoroughly vetted, top-tier international professionals. Under our direct-hire model, Remote Leverage sources and screens candidates, presents a curated shortlist, the client interviews and selects the candidate, and the client hires directly. Remote Leverage receives a one-time placement fee when a client hires.' }}
              </p>
            </div>

            {{-- Partnership Terms Summary — unified spec strip so short values never mismatch height with the long renewal sentence --}}
            <div class="rounded-card-md bg-white border border-black/5 shadow-[0_4px_24px_rgba(0,0,0,0.03)] overflow-hidden">
              <div class="grid grid-cols-2 sm:grid-cols-4 divide-x divide-y sm:divide-y-0 divide-slate-100">
                <div class="p-4 sm:p-5">
                  <div class="text-2xs text-text-slate uppercase tracking-wider font-bold">Partnership Type</div>
                  <div class="text-sm font-bold text-text-body mt-1.5">{{ $partnershipType }}</div>
                </div>
                <div class="p-4 sm:p-5">
                  <div class="text-2xs text-text-slate uppercase tracking-wider font-bold">Territory</div>
                  <div class="text-sm font-bold text-text-body mt-1.5">{{ $territory }}</div>
                </div>
                <div class="p-4 sm:p-5">
                  <div class="text-2xs text-text-slate uppercase tracking-wider font-bold">Reporting</div>
                  <div class="text-sm font-bold text-text-body mt-1.5">{{ $reportingPeriod }}</div>
                </div>
                <div class="p-4 sm:p-5">
                  <div class="text-2xs text-text-slate uppercase tracking-wider font-bold">Initial Term</div>
                  <div class="text-sm font-bold text-text-body mt-1.5">{{ $initialTerm }}</div>
                </div>
              </div>
              <div class="px-4 sm:px-5 py-3.5 bg-bg-light border-t border-black/5">
                <span class="text-2xs text-text-slate uppercase tracking-wider font-bold">Renewal Terms</span>
                <span class="text-xs sm:text-sm text-text-body font-medium ml-2">{{ $renewalTerms }}</span>
              </div>
            </div>

            {{-- Bidirectional Referral Actions --}}
            <div class="space-y-4 pt-4 border-t border-slate-100">
              <h2 class="text-lg font-bold font-display text-brand-hero">Two-Way Referral Actions</h2>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                {{-- Direction A: Partner -> Remote Leverage --}}
                <div class="p-5 rounded-card-md bg-white border border-black/5 shadow-[0_4px_24px_rgba(0,0,0,0.03)] hover:-translate-y-1 hover:shadow-[0_12px_32px_rgba(0,0,0,0.06)] transition-all duration-300 space-y-3">
                  <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-brand-purple/10 flex items-center justify-center shrink-0 text-brand-purple">
                      {!! $rlIcon('arrow-right') !!}
                    </div>
                    <span class="text-2xs font-bold uppercase tracking-wider text-brand-purple leading-tight">
                      {{ $partnerName }} &rarr; Remote Leverage
                    </span>
                  </div>
                  <p class="text-xs sm:text-sm text-text-muted">
                    Submit a candidate search introduction with referral code <code class="px-1.5 py-0.5 rounded bg-slate-100 font-mono text-brand-purple">{{ $partnerCode }}</code> or send a warm email intro.
                  </p>
                  <div class="flex flex-wrap gap-2 pt-1">
                    @if ($referralFormUrl)
                      <a href="{{ esc_url($referralFormUrl) }}" target="_blank" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-cta bg-brand-purple hover:bg-brand-purple-deep text-white text-xs font-bold transition">
                        {!! $rlIcon('form', 'w-3.5 h-3.5') !!} Submit via Form
                      </a>
                    @endif
                    <a href="mailto:{{ esc_attr($introEmail) }}?subject=Client%20Referral%20from%20{{ rawurlencode($partnerName) }}" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-cta bg-slate-100 hover:bg-slate-200 text-text-body text-xs font-semibold transition">
                      {!! $rlIcon('mail', 'w-3.5 h-3.5') !!} Intro Email
                    </a>
                    @if ($referralDriveUrl)
                      <a href="{{ esc_url($referralDriveUrl) }}" target="_blank" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-cta bg-slate-100 hover:bg-slate-200 text-text-body text-xs font-semibold transition">
                        {!! $rlIcon('sheet', 'w-3.5 h-3.5') !!} Tracking Sheet
                      </a>
                    @endif
                  </div>
                </div>

                {{-- Direction B: Remote Leverage -> Partner --}}
                <div class="p-5 rounded-card-md bg-white border border-black/5 shadow-[0_4px_24px_rgba(0,0,0,0.03)] hover:-translate-y-1 hover:shadow-[0_12px_32px_rgba(0,0,0,0.06)] transition-all duration-300 space-y-3">
                  <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-sky-50 flex items-center justify-center shrink-0 text-sky-600">
                      {!! $rlIcon('users') !!}
                    </div>
                    <span class="text-2xs font-bold uppercase tracking-wider text-sky-700 leading-tight">
                      Remote Leverage &rarr; {{ $partnerName }}
                    </span>
                  </div>
                  <p class="text-xs sm:text-sm text-text-muted">{{ $partnerReferralLabel }}</p>
                  <div class="flex flex-wrap gap-2 pt-1">
                    @if ($partnerReferralUrl)
                      <a href="{{ esc_url($partnerReferralUrl) }}" target="_blank" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-cta bg-sky-600 hover:bg-sky-700 text-white text-xs font-bold transition">
                        {!! $rlIcon('external', 'w-3.5 h-3.5') !!} Open {{ $partnerName }} Portal
                      </a>
                    @endif
                    @if ($partnerReferralEmail && strtolower($partnerReferralEmail) !== 'pending to define')
                      <a href="mailto:{{ esc_attr($partnerReferralEmail) }}?subject=Client%20Referral%20from%20Remote%20Leverage" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-cta bg-slate-100 hover:bg-slate-200 text-text-body text-xs font-semibold transition">
                        {!! $rlIcon('mail', 'w-3.5 h-3.5') !!} Email {{ $partnerName }} Team
                      </a>
                    @else
                      <span class="inline-flex items-center gap-1.5 px-3 py-2 rounded-cta bg-amber-50 text-amber-700 text-2xs font-semibold">
                        {!! $rlIcon('phone-pending', 'w-3.5 h-3.5') !!} Referral destination: pending setup
                      </span>
                    @endif
                  </div>
                </div>
              </div>
            </div>

            <div class="space-y-4 pt-4 border-t border-slate-100">
              <h2 class="text-lg font-bold font-display text-brand-hero">Core Operating Values</h2>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                @foreach (\App\Domains\PartnerHub\Services\PartnerHubGlobalData::getCoreValues() as $value)
                  <div class="flex items-start gap-3 p-4 rounded-card bg-bg-light border border-black/5">
                    <div class="w-8 h-8 rounded-lg bg-brand-purple/10 flex items-center justify-center shrink-0 text-brand-purple mt-0.5">
                      {!! $rlIcon('trophy', 'w-4 h-4') !!}
                    </div>
                    <div>
                      <div class="font-bold text-xs sm:text-sm text-text-body">{{ $value['value'] }}</div>
                      <p class="text-2xs sm:text-xs text-text-muted mt-0.5 leading-relaxed">{{ $value['meaning'] }}</p>
                    </div>
                  </div>
                @endforeach
              </div>
            </div>
          </div>

        {{-- TAB 2: IDEAL CLIENT PROFILE --}}
        @elseif ($currentTab === 'icp')
          <div class="space-y-8 animate-fadeIn">
            <div>
              <span class="px-3 py-1 rounded-pill bg-brand-purple/10 text-brand-purple text-xs font-bold uppercase tracking-wider">Target Audience</span>
              <h1 class="text-2xl sm:text-3xl font-bold font-display text-brand-hero tracking-tight mt-3">Ideal Client Profile (ICP)</h1>
              <p class="text-text-muted text-sm mt-2">Qualification criteria and target market guidelines to identify strong referral opportunities.</p>
            </div>

            <div class="p-5 rounded-card-md bg-emerald-50 border border-emerald-100 flex items-start gap-3">
              <div class="w-8 h-8 rounded-lg bg-emerald-100 flex items-center justify-center shrink-0 text-emerald-700 mt-0.5">
                {!! $rlIcon('target', 'w-4 h-4') !!}
              </div>
              <p class="text-sm text-emerald-900 font-medium leading-relaxed">
                The ideal Remote Leverage client is a business (SMB to Enterprise) seeking to hire qualified staff faster (2&ndash;4 weeks), with zero upfront placement fees and significant cost savings over traditional domestic recruiting.
              </p>
            </div>

            <div>
              <h3 class="font-bold text-base text-brand-hero mb-3 flex items-center gap-2">
                <span class="w-7 h-7 rounded-lg bg-brand-purple/10 flex items-center justify-center text-brand-purple">{!! $rlIcon('building', 'w-3.5 h-3.5') !!}</span>
                Target Industries
              </h3>
              <div class="flex flex-wrap gap-2">
                @foreach ($targetIndustries as $industry)
                  <span class="px-3 py-1.5 rounded-pill bg-slate-100 hover:bg-slate-200 text-text-body text-xs font-medium transition">{{ $industry }}</span>
                @endforeach
              </div>
            </div>

            <div>
              <h3 class="font-bold text-base text-brand-hero mb-3 flex items-center gap-2">
                <span class="w-7 h-7 rounded-lg bg-brand-purple/10 flex items-center justify-center text-brand-purple">{!! $rlIcon('globe', 'w-3.5 h-3.5') !!}</span>
                Geographic Coverage
              </h3>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                @foreach ($geographicMarkets as $region => $desc)
                  <div class="p-4 rounded-card bg-white border border-black/5 shadow-[0_4px_24px_rgba(0,0,0,0.03)]">
                    <div class="font-bold text-xs sm:text-sm text-text-body">{{ $region }}</div>
                    <p class="text-2xs sm:text-xs text-text-muted mt-1 leading-relaxed">{{ $desc }}</p>
                  </div>
                @endforeach
              </div>
            </div>
          </div>

        {{-- TAB 3: SERVICES OVERVIEW --}}
        @elseif ($currentTab === 'services')
          <div class="space-y-8 animate-fadeIn">
            <div>
              <span class="px-3 py-1 rounded-pill bg-brand-purple/10 text-brand-purple text-xs font-bold uppercase tracking-wider">Capabilities</span>
              <h1 class="text-2xl sm:text-3xl font-bold font-display text-brand-hero tracking-tight mt-3">Services Overview</h1>
              <p class="text-text-muted text-sm mt-2">Remote Leverage provides specialized direct-hire recruitment across key business functions.</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              @foreach ($services as $service)
                <div class="p-5 rounded-card-md bg-white border border-black/5 shadow-[0_4px_24px_rgba(0,0,0,0.03)] hover:-translate-y-1 hover:shadow-[0_12px_32px_rgba(0,0,0,0.06)] transition-all duration-300 space-y-2">
                  <div class="w-10 h-10 rounded-xl bg-brand-purple/10 flex items-center justify-center text-brand-purple mb-1">
                    {!! $rlIcon('briefcase') !!}
                  </div>
                  <h3 class="font-bold text-sm text-brand-hero">{{ $service['name'] }}</h3>
                  <span class="block text-xs text-brand-purple font-medium leading-snug"><span class="font-bold text-2xs uppercase tracking-wide">Best for</span> {{ $service['best_for'] }}</span>
                  <p class="text-xs sm:text-sm text-text-muted leading-relaxed">{{ $service['desc'] }}</p>
                </div>
              @endforeach
            </div>
          </div>

        {{-- TAB 4: WHY REMOTE LEVERAGE --}}
        @elseif ($currentTab === 'why-rl')
          <div class="space-y-8 animate-fadeIn">
            <div>
              <span class="px-3 py-1 rounded-pill bg-brand-purple/10 text-brand-purple text-xs font-bold uppercase tracking-wider">Competitive Edge</span>
              <h1 class="text-2xl sm:text-3xl font-bold font-display text-brand-hero tracking-tight mt-3">Why Remote Leverage</h1>
              <p class="text-text-muted text-sm mt-2">Comparative analysis of Remote Leverage vs. traditional staffing agencies and in-house hiring.</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              @foreach ($whyChooseRl as $point)
                <div class="flex items-start gap-3 p-4 rounded-card bg-bg-light border border-black/5">
                  <div class="w-8 h-8 rounded-lg bg-brand-purple/10 flex items-center justify-center shrink-0 text-brand-purple mt-0.5">
                    {!! $rlIcon('check-circle', 'w-4 h-4') !!}
                  </div>
                  <div>
                    <h3 class="font-bold text-xs sm:text-sm text-brand-hero">{{ $point['title'] }}</h3>
                    <p class="text-2xs sm:text-xs text-text-muted mt-0.5 leading-relaxed">{{ $point['desc'] }}</p>
                  </div>
                </div>
              @endforeach
            </div>

            <div class="rounded-card-md bg-white border border-black/5 shadow-[0_8px_30px_rgba(0,0,0,0.04)] overflow-hidden">
              <div class="overflow-x-auto">
                <table class="w-full text-xs sm:text-sm">
                  <thead class="bg-bg-light">
                    <tr>
                      <th class="text-left px-4 py-3 font-bold text-text-body">Criteria</th>
                      <th class="text-left px-4 py-3 font-bold text-brand-purple">Remote Leverage</th>
                      <th class="text-left px-4 py-3 font-bold text-text-body">In-House / Job Boards</th>
                      <th class="text-left px-4 py-3 font-bold text-text-body">Traditional Staffing</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-slate-100">
                    @foreach ($comparisonMatrix as $row)
                      <tr>
                        <td class="px-4 py-3 font-bold text-text-body whitespace-nowrap">{{ $row['feature'] }}</td>
                        <td class="px-4 py-3 font-semibold text-brand-purple bg-brand-purple/5">{{ $row['rl'] }}</td>
                        <td class="px-4 py-3 text-text-muted">{{ $row['inhouse'] }}</td>
                        <td class="px-4 py-3 text-text-muted">{{ $row['agency'] }}</td>
                      </tr>
                    @endforeach
                  </tbody>
                </table>
              </div>
            </div>
          </div>

        {{-- TAB 5: REFERRAL PROGRAM & FEES --}}
        @elseif ($currentTab === 'referral-program')
          <div class="space-y-8 animate-fadeIn">
            <div>
              <span class="px-3 py-1 rounded-pill bg-brand-purple/10 text-brand-purple text-xs font-bold uppercase tracking-wider">Economics</span>
              <h1 class="text-2xl sm:text-3xl font-bold font-display text-brand-hero tracking-tight mt-3">Referral Program & Commission Terms</h1>
              <p class="text-text-muted text-sm mt-2">Transparent, bidirectional revenue-sharing terms for {{ $partnerName }}.</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div class="p-5 rounded-card-md bg-white border border-black/5 shadow-[0_4px_24px_rgba(0,0,0,0.03)] space-y-2">
                <div class="flex items-center gap-2">
                  <div class="w-8 h-8 rounded-lg bg-brand-purple/10 flex items-center justify-center text-brand-purple shrink-0">{!! $rlIcon('arrow-right', 'w-4 h-4') !!}</div>
                  <span class="text-2xs font-bold uppercase tracking-wider text-brand-purple">Direction A: {{ $partnerName }} &rarr; RL</span>
                </div>
                <p class="text-sm text-text-body leading-relaxed">{{ $partnerToRlFee }}</p>
              </div>
              <div class="p-5 rounded-card-md bg-white border border-black/5 shadow-[0_4px_24px_rgba(0,0,0,0.03)] space-y-2">
                <div class="flex items-center gap-2">
                  <div class="w-8 h-8 rounded-lg bg-sky-50 flex items-center justify-center text-sky-600 shrink-0">{!! $rlIcon('users', 'w-4 h-4') !!}</div>
                  <span class="text-2xs font-bold uppercase tracking-wider text-sky-700">Direction B: RL &rarr; {{ $partnerName }}</span>
                </div>
                <p class="text-sm text-text-body leading-relaxed">{{ $rlToPartnerFee }}</p>
              </div>
            </div>

            <div>
              <h3 class="font-bold text-base text-brand-hero mb-3">Referral Eligibility Rules</h3>
              <div class="p-5 rounded-card-md bg-bg-light border border-black/5">
                @if ($overrideRules)
                  <p class="text-xs sm:text-sm text-text-muted whitespace-pre-line">{{ $overrideRules }}</p>
                @else
                  <ul class="text-xs sm:text-sm text-text-muted space-y-2.5">
                    @foreach ($defaultRules as $rule)
                      <li class="flex items-start gap-2.5">
                        <span class="text-emerald-600 shrink-0 mt-0.5">{!! $rlIcon('check-circle', 'w-4 h-4') !!}</span>
                        <span>{{ $rule }}</span>
                      </li>
                    @endforeach
                  </ul>
                @endif
              </div>
            </div>

            <div>
              <h3 class="font-bold text-base text-brand-hero mb-3">Referral Lifecycle Stages</h3>
              <div class="relative">
                <div class="hidden sm:block absolute left-[15px] top-3 bottom-3 w-px bg-slate-200"></div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 relative">
                  @foreach ($lifecycleStages as $idx => $stage)
                    <div class="flex items-start gap-3">
                      <span class="w-8 h-8 shrink-0 rounded-full bg-brand-purple text-white flex items-center justify-center font-bold text-xs shadow-[0_4px_14px_rgba(138,43,226,0.3)] relative z-10">{{ $idx + 1 }}</span>
                      <div class="pt-1">
                        <div class="font-bold text-xs sm:text-sm text-text-body">{{ $stage['status'] }}</div>
                        <div class="text-2xs sm:text-xs text-text-muted mt-0.5 leading-relaxed">{{ $stage['desc'] }}</div>
                      </div>
                    </div>
                  @endforeach
                </div>
              </div>
            </div>

            @if ($overrideCommission)
              <div class="p-5 rounded-card-md bg-amber-50 border border-amber-100 flex items-start gap-3">
                <div class="w-8 h-8 rounded-lg bg-amber-100 flex items-center justify-center shrink-0 text-amber-700 mt-0.5">{!! $rlIcon('alert', 'w-4 h-4') !!}</div>
                <div>
                  <h3 class="font-bold text-sm text-amber-900 mb-1">Special Partnership Terms</h3>
                  <p class="text-xs sm:text-sm text-amber-800 whitespace-pre-line leading-relaxed">{{ $overrideCommission }}</p>
                </div>
              </div>
            @endif
          </div>

        {{-- TAB 6: CO-MARKETING (conditional) --}}
        @elseif ($currentTab === 'comarketing' && $hasComarketing)
          <div class="space-y-8 animate-fadeIn">
            <div>
              <span class="px-3 py-1 rounded-pill bg-brand-purple/10 text-brand-purple text-xs font-bold uppercase tracking-wider">Collaborative Growth</span>
              <h1 class="text-2xl sm:text-3xl font-bold font-display text-brand-hero tracking-tight mt-3">Co-Marketing Opportunities & Guidelines</h1>
              <p class="text-text-muted text-sm mt-2">{{ $comarketingText }}</p>
            </div>

            <div class="p-5 rounded-card-md bg-amber-50 border border-amber-100 flex items-start gap-3">
              <div class="w-8 h-8 rounded-lg bg-amber-100 flex items-center justify-center shrink-0 text-amber-700 mt-0.5">
                {!! $rlIcon('alert', 'w-4 h-4') !!}
              </div>
              <p class="text-xs sm:text-sm font-semibold text-amber-900 leading-relaxed">{{ $comarketingNotice }}</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              @foreach ($comarketingDefaults['opportunities'] as $opp)
                <div class="p-5 rounded-card-md bg-white border border-black/5 shadow-[0_4px_24px_rgba(0,0,0,0.03)] hover:-translate-y-1 hover:shadow-[0_12px_32px_rgba(0,0,0,0.06)] transition-all duration-300 space-y-2">
                  <div class="w-10 h-10 rounded-xl bg-brand-magenta/10 flex items-center justify-center text-brand-magenta mb-1">
                    {!! $rlIcon('megaphone') !!}
                  </div>
                  <h3 class="font-bold text-brand-hero text-sm">{{ $opp['title'] }}</h3>
                  <p class="text-xs sm:text-sm text-text-muted leading-relaxed">{{ $opp['desc'] }}</p>
                </div>
              @endforeach
            </div>

            <div class="p-5 rounded-card-md bg-gradient-to-r from-brand-midnight to-brand-hero text-white flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
              <p class="text-xs sm:text-sm text-slate-200 leading-relaxed">
                To propose a joint webinar, case study, or co-branded piece, email your dedicated manager.
              </p>
              <a href="mailto:{{ $managerEmail }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-cta bg-brand-magenta hover:bg-brand-magenta-hover text-white text-xs font-bold transition whitespace-nowrap">
                {!! $rlIcon('mail', 'w-3.5 h-3.5') !!} {{ $managerEmail }}
              </a>
            </div>
          </div>

        {{-- TAB 7: CASE STUDIES --}}
        @elseif ($currentTab === 'case-studies')
          <div class="space-y-8 animate-fadeIn">
            <div>
              <span class="px-3 py-1 rounded-pill bg-brand-purple/10 text-brand-purple text-xs font-bold uppercase tracking-wider">Track Record</span>
              <h1 class="text-2xl sm:text-3xl font-bold font-display text-brand-hero tracking-tight mt-3">Industry Case Studies</h1>
              <p class="text-text-muted text-sm mt-2">Real candidate placement results across key business sectors.</p>
            </div>

            <div class="space-y-5">
              @foreach ($caseStudies as $cs)
                <div class="p-6 rounded-card-md bg-white border border-black/5 shadow-[0_4px_24px_rgba(0,0,0,0.03)] space-y-4">
                  <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-brand-purple/10 flex items-center justify-center shrink-0 text-brand-purple">
                      {!! $rlIcon('briefcase') !!}
                    </div>
                    <div>
                      <span class="inline-block px-2.5 py-0.5 rounded-pill bg-brand-purple/10 text-brand-purple text-2xs font-bold uppercase tracking-wider">{{ $cs['industry'] }}</span>
                      <h3 class="text-base font-bold text-brand-hero mt-1">{{ $cs['client'] }}</h3>
                    </div>
                  </div>

                  <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-1">
                    <div>
                      <div class="text-2xs font-bold uppercase tracking-wider text-text-slate mb-1.5">Challenge</div>
                      <ul class="text-xs sm:text-sm text-text-muted space-y-1.5 list-disc list-inside">
                        @foreach ($cs['challenge'] as $point)
                          <li>{{ $point }}</li>
                        @endforeach
                      </ul>
                    </div>

                    <div>
                      <div class="text-2xs font-bold uppercase tracking-wider text-text-slate mb-1.5">Solution</div>
                      <ul class="text-xs sm:text-sm text-text-muted space-y-1.5 list-disc list-inside">
                        @foreach ($cs['solution'] as $point)
                          <li>{{ $point }}</li>
                        @endforeach
                      </ul>
                    </div>

                    <div class="p-3 rounded-card bg-emerald-50 border border-emerald-100">
                      <div class="text-2xs font-bold uppercase tracking-wider text-emerald-700 mb-1.5">Key Outcomes</div>
                      <ul class="text-xs sm:text-sm text-emerald-900 font-medium space-y-1.5 list-disc list-inside">
                        @foreach ($cs['outcome'] as $point)
                          <li>{{ $point }}</li>
                        @endforeach
                      </ul>
                    </div>
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
              <h1 class="text-2xl sm:text-3xl font-bold font-display text-brand-hero tracking-tight mt-3">Partner Frequently Asked Questions</h1>
            </div>

            <div class="rounded-card-md bg-white border border-black/5 shadow-[0_4px_24px_rgba(0,0,0,0.03)] px-5 sm:px-6" x-data="{ activeFaq: null }">
              @foreach ($faqs as $idx => $faq)
                <div class="{{ ! $loop->last ? 'border-b border-slate-100' : '' }}">
                  <button type="button"
                    :aria-expanded="activeFaq === {{ $idx }} ? 'true' : 'false'"
                    @click="activeFaq = (activeFaq === {{ $idx }} ? null : {{ $idx }})"
                    class="w-full py-5 text-left flex items-center justify-between gap-4 cursor-pointer focus:outline-none group">
                    <span class="font-bold text-sm text-brand-hero group-hover:text-brand-purple transition-colors pr-3">
                      {{ $faq['q'] }}
                    </span>
                    <div class="w-7 h-7 rounded-full bg-brand-purple/10 flex items-center justify-center shrink-0 transition-transform duration-300 text-brand-purple"
                      :class="activeFaq === {{ $idx }} ? 'rotate-180' : ''">
                      {!! $rlIcon('arrow-right', 'w-3.5 h-3.5 rotate-90') !!}
                    </div>
                  </button>
                  <div x-show="activeFaq === {{ $idx }}" x-collapse style="display:none;">
                    <p class="text-xs sm:text-sm text-text-muted leading-relaxed pb-5">{{ $faq['a'] }}</p>
                  </div>
                </div>
              @endforeach
            </div>
          </div>

        {{-- TAB 9: CONTACT --}}
        @elseif ($currentTab === 'contact')
          <div class="space-y-8 animate-fadeIn">
            <div>
              <span class="px-3 py-1 rounded-pill bg-brand-purple/10 text-brand-purple text-xs font-bold uppercase tracking-wider">Partner Support</span>
              <h1 class="text-2xl sm:text-3xl font-bold font-display text-brand-hero tracking-tight mt-3">Dedicated Partnerships Contact</h1>
              <p class="text-text-muted text-sm mt-2">Direct access to our team for custom client inquiries, co-marketing requests, or billing questions.</p>
            </div>

            <div class="p-8 rounded-card-lg bg-gradient-to-br from-brand-midnight to-brand-hero text-white max-w-md space-y-4 shadow-[0_20px_50px_rgba(19,19,47,0.25)] relative overflow-hidden">
              <div class="absolute -right-10 -top-10 w-40 h-40 bg-brand-purple/25 rounded-full blur-3xl pointer-events-none"></div>
              <div class="relative z-10 space-y-4">
                <div class="w-14 h-14 rounded-full bg-brand-purple flex items-center justify-center font-bold text-xl shadow-[0_4px_14px_rgba(138,43,226,0.4)]">
                  {{ substr($managerName, 0, 1) }}
                </div>
                <div>
                  <h3 class="font-bold font-display text-lg text-white">{{ $managerName }}</h3>
                  <p class="text-xs text-purple-200">{{ $managerTitle }}</p>
                </div>
                <a href="mailto:{{ $managerEmail }}" class="inline-flex items-center gap-2 pt-4 border-t border-white/15 mt-2 text-white hover:text-purple-200 text-xs font-semibold transition">
                  {!! $rlIcon('mail', 'w-4 h-4') !!} {{ $managerEmail }}
                </a>
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
                <a href="{{ esc_url($attachment['file']['url'] ?? '') }}" target="_blank" class="flex items-center gap-3 p-3 rounded-card bg-white border border-black/5 shadow-[0_4px_24px_rgba(0,0,0,0.03)] hover:-translate-y-0.5 hover:shadow-[0_8px_24px_rgba(0,0,0,0.06)] transition-all duration-200">
                  <div class="w-9 h-9 rounded-lg bg-red-50 flex items-center justify-center shrink-0 text-red-600">
                    {!! $rlIcon('pdf', 'w-4 h-4') !!}
                  </div>
                  <div class="flex-1 min-w-0">
                    <div class="text-xs font-bold text-text-body truncate">{{ $attachment['title'] ?: ($attachment['file']['filename'] ?? 'Attachment') }}</div>
                    <div class="text-2xs text-text-muted">{{ formatFileSize($attachment['file']['filesize'] ?? 0) }}</div>
                  </div>
                </a>
              @endforeach
            </div>
          </div>
        @endif

      </main>

      {{-- Right Sidebar: Dedicated Resources --}}
      <aside class="hidden lg:block lg:col-span-3 lg:border-l lg:border-slate-200 lg:pl-6 space-y-6 sticky top-8">
        <div class="space-y-1">
          <span class="text-2xs font-bold uppercase tracking-wider text-text-slate">Partnership Resources</span>

          @if ($rlResourceUrl)
            <a href="{{ esc_url($rlResourceUrl) }}" target="_blank" class="flex items-center gap-3 p-3 rounded-card hover:bg-bg-light transition">
              <div class="w-9 h-9 rounded-lg bg-brand-purple/10 flex items-center justify-center shrink-0 text-brand-purple">{!! $rlIcon('drive', 'w-4 h-4') !!}</div>
              <div class="flex-1 min-w-0">
                <div class="text-xs font-bold text-text-body leading-snug">{{ $rlResourceTitle }}</div>
                <div class="text-2xs text-text-muted">Remote Leverage Assets</div>
              </div>
            </a>
          @endif

          @if ($partnerResourceUrl)
            <a href="{{ esc_url($partnerResourceUrl) }}" target="_blank" class="flex items-center gap-3 p-3 rounded-card hover:bg-bg-light transition">
              <div class="w-9 h-9 rounded-lg bg-sky-50 flex items-center justify-center shrink-0 text-sky-600">{!! $rlIcon('external', 'w-4 h-4') !!}</div>
              <div class="flex-1 min-w-0">
                <div class="text-xs font-bold text-text-body leading-snug">{{ $partnerResourceTitle }}</div>
                <div class="text-2xs text-text-muted">Partner Workspace</div>
              </div>
            </a>
          @endif

          @if ($onePagerPdf)
            <a href="{{ esc_url($onePagerPdf) }}" target="_blank" class="flex items-center gap-3 p-3 rounded-card hover:bg-bg-light transition">
              <div class="w-9 h-9 rounded-lg bg-red-50 flex items-center justify-center shrink-0 text-red-600">{!! $rlIcon('pdf', 'w-4 h-4') !!}</div>
              <div class="flex-1 min-w-0">
                <div class="text-xs font-bold text-text-body truncate">Overview One-Pager</div>
                <div class="text-2xs text-text-muted">PDF Document</div>
              </div>
            </a>
          @endif

          @if ($agreementPdf)
            <a href="{{ esc_url($agreementPdf) }}" target="_blank" class="flex items-center gap-3 p-3 rounded-card hover:bg-bg-light transition">
              <div class="w-9 h-9 rounded-lg bg-red-50 flex items-center justify-center shrink-0 text-red-600">{!! $rlIcon('pdf', 'w-4 h-4') !!}</div>
              <div class="flex-1 min-w-0">
                <div class="text-xs font-bold text-text-body truncate">Partner Agreement</div>
                <div class="text-2xs text-text-muted">PDF Document</div>
              </div>
            </a>
          @endif

          @if (! $rlResourceUrl && ! $partnerResourceUrl && ! $onePagerPdf && ! $agreementPdf)
            <p class="text-2xs text-text-muted px-3">No dedicated resources configured yet.</p>
          @endif
        </div>

        <div class="space-y-2.5 pt-2 border-t border-slate-200">
          <span class="text-2xs font-bold uppercase tracking-wider text-text-slate">Quick Actions</span>
          @if ($referralFormUrl)
            <a href="{{ esc_url($referralFormUrl) }}" target="_blank" class="flex items-center justify-center gap-2 w-full py-2.5 rounded-cta bg-brand-purple hover:bg-brand-purple-deep text-white text-xs font-bold transition-all duration-200 hover:scale-[1.02] shadow-[0_4px_14px_rgba(138,43,226,0.3)]">
              {!! $rlIcon('form', 'w-3.5 h-3.5') !!} Refer to Remote Leverage
            </a>
          @endif
          @if ($referralDriveUrl)
            <a href="{{ esc_url($referralDriveUrl) }}" target="_blank" class="flex items-center justify-center gap-2 w-full py-2.5 rounded-cta bg-slate-100 hover:bg-slate-200 text-text-body text-xs font-semibold transition">
              {!! $rlIcon('sheet', 'w-3.5 h-3.5') !!} View Tracking Sheet
            </a>
          @endif
        </div>

        <div class="pt-4 border-t border-slate-200">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-brand-purple/10 text-brand-purple flex items-center justify-center font-bold text-sm shrink-0">
              {{ substr($managerName, 0, 1) }}
            </div>
            <div class="min-w-0">
              <div class="text-xs font-bold text-text-body truncate">{{ $managerName }}</div>
              <div class="text-2xs text-text-muted truncate">{{ $managerTitle }}</div>
            </div>
          </div>
          <a href="mailto:{{ $managerEmail }}" class="mt-3 flex items-center justify-center gap-1.5 w-full py-2 rounded-cta bg-slate-100 hover:bg-slate-200 text-text-body text-2xs font-semibold transition">
            {!! $rlIcon('mail', 'w-3 h-3') !!} Direct Email
          </a>
        </div>
      </aside>

    </div>
    </div>
  </section>
</div>
@endsection
