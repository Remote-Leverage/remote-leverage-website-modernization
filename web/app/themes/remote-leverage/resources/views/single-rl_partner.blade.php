@extends('layouts.app')

@section('content')
@php
  $postId = get_the_ID();
  $partnerPermalink = get_permalink($postId);

  $enableComarketing = get_post_meta($postId, '_rl_enable_comarketing', true);
  $hasComarketing = \App\Domains\PartnerHub\Services\PartnerHubTabResolver::isComarketingEnabled($enableComarketing);

  // Active Tab Routing (supports /partners/{slug}/{tab} rewrite and ?tab=)
  // Branding — logo/cover are ACF Image fields (return_format: url), read via get_field()
  $partnerCode = get_post_meta($postId, '_rl_partner_code', true) ?: 'RL-PARTNER';
  $partnerName = get_post_meta($postId, '_rl_partner_name', true) ?: get_the_title();

  // Page Copy — every fixed string on the page, each overridable per partner as `_rl_copy_<key>`
  // (PartnerHubFields "Page Copy" tab). Blank keeps the PartnerHubGlobalData default.
  $resolver = \App\Domains\PartnerHub\Services\PartnerHubContentResolver::class;
  $copyDefaults = \App\Domains\PartnerHub\Services\PartnerHubGlobalData::getCopyDefaults();
  $copyTokens = ['{partner}' => $partnerName, '{code}' => $partnerCode];
  $copy = $resolver::resolveCopy(
      $copyDefaults,
      array_combine(
          array_keys($copyDefaults),
          array_map(fn ($key) => get_post_meta($postId, \App\Fields\PartnerHubFields::COPY_PREFIX.$key, true), array_keys($copyDefaults)),
      ),
      $copyTokens,
  );

  $rawTab = get_query_var('rl_tab') ?: (isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'overview');
  $tabLabels = [];
  foreach (array_keys(\App\Domains\PartnerHub\Services\PartnerHubTabResolver::resolveTabs(true)) as $tabKey) {
      $tabLabels[$tabKey] = $copy['tab_'.str_replace('-', '_', $tabKey)];
  }
  $validTabs = \App\Domains\PartnerHub\Services\PartnerHubTabResolver::resolveTabs($hasComarketing, $tabLabels);
  $currentTab = \App\Domains\PartnerHub\Services\PartnerHubTabResolver::resolveCurrentTab($rawTab, $validTabs);
  $partnerLogo = get_field('_rl_partner_logo_url', $postId);
  $partnerCover = get_field('_rl_partner_cover_url', $postId);
  $partnerWebsite = get_post_meta($postId, '_rl_partner_website', true);

  // WR-73: the link that identifies this partnership on every lead it sends. Built rather than
  // authored, so it cannot drift from the code above it or point at a retired landing page.
  $partnerTrackedLink = \App\Domains\PartnerHub\Support\PartnerLink::for($partnerCode);

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
  $defaultFees = \App\Domains\PartnerHub\Services\PartnerHubGlobalData::getDefaultFees();
  $partnerToRlFee = $resolver::interpolate($resolver::resolveText(get_post_meta($postId, '_rl_partner_to_rl_fee', true), $defaultFees['partner_to_rl']), $copyTokens);

  // Direction B: Remote Leverage -> Partner
  $partnerReferralLabel = get_post_meta($postId, '_rl_partner_referral_label', true) ?: "Refer a Client to {$partnerName}";
  $partnerReferralEmail = get_post_meta($postId, '_rl_partner_referral_email', true);
  $partnerReferralUrl = get_post_meta($postId, '_rl_partner_referral_url', true);
  $rlToPartnerFee = $resolver::interpolate($resolver::resolveText(get_post_meta($postId, '_rl_rl_to_partner_fee', true), $defaultFees['rl_to_partner']), $copyTokens);

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
  $welcomeText = $resolver::resolveText(
      get_post_meta($postId, '_rl_override_welcome_text', true),
      \App\Domains\PartnerHub\Services\PartnerHubGlobalData::getWelcomeText(),
  );
  $companyDesc = $resolver::resolveText(
      get_post_meta($postId, '_rl_override_company_desc', true),
      \App\Domains\PartnerHub\Services\PartnerHubGlobalData::getCompanyDescription(),
  );
  $overrideCommission = get_post_meta($postId, '_rl_override_commission_terms', true);

  // Per-Section PDF Attachments — ACF repeater, one row per file, each tagged with its tab
  $allAttachments = get_field('_rl_section_attachments', $postId) ?: [];
  $currentAttachments = array_values(array_filter($allAttachments, fn ($row) => ($row['section'] ?? null) === $currentTab));

  // Global Default Content Library (WR-120), with the per-partner overrides in front of it —
  // a non-empty override replaces its default wholesale, it never merges into it.
  $coreValues = $resolver::resolveRows(
      get_field('_rl_core_values', $postId),
      \App\Domains\PartnerHub\Services\PartnerHubGlobalData::getCoreValues(),
  );
  $whyChooseRl = $resolver::resolveRows(
      get_field('_rl_why_rl', $postId),
      \App\Domains\PartnerHub\Services\PartnerHubGlobalData::getWhyChooseRl(),
  );
  $comparisonMatrix = $resolver::resolveRows(
      get_field('_rl_comparison_matrix', $postId),
      \App\Domains\PartnerHub\Services\PartnerHubGlobalData::getComparisonMatrix(),
  );
  $defaultMarkets = \App\Domains\PartnerHub\Services\PartnerHubGlobalData::getGeographicMarkets();
  $geographicMarkets = $resolver::resolveRows(
      get_field('_rl_geographic_markets', $postId),
      array_map(fn ($region, $desc) => ['region' => $region, 'desc' => $desc], array_keys($defaultMarkets), $defaultMarkets),
  );
  $referralRules = $resolver::resolveList(
      get_post_meta($postId, '_rl_override_referral_rules', true),
      \App\Domains\PartnerHub\Services\PartnerHubGlobalData::getDefaultReferralRules(),
  );
  $comarketingOpportunities = $resolver::resolveRows(
      get_field('_rl_comarketing_opportunities', $postId),
      $comarketingDefaults['opportunities'],
  );
  $caseStudies = $resolver::resolveCaseStudies(
      get_field('_rl_case_studies', $postId),
      \App\Domains\PartnerHub\Services\PartnerHubGlobalData::getCaseStudies(),
  );
  $faqs = $resolver::resolveRows(
      get_field('_rl_faqs', $postId),
      \App\Domains\PartnerHub\Services\PartnerHubGlobalData::getFaqs(),
  );

  $services = $resolver::resolveRows(
      get_field('_rl_services', $postId),
      \App\Domains\PartnerHub\Services\PartnerHubGlobalData::getServices(),
  );
  $servicesDesc = $resolver::resolveText(
      get_post_meta($postId, '_rl_services_desc', true),
      \App\Domains\PartnerHub\Services\PartnerHubGlobalData::getServicesDesc(),
  );
  $lifecycleStages = $resolver::resolveRows(
      get_field('_rl_lifecycle_stages', $postId),
      \App\Domains\PartnerHub\Services\PartnerHubGlobalData::getDefaultLifecycleStages(),
  );
  $targetIndustries = $resolver::resolveList(
      get_post_meta($postId, '_rl_target_industries', true),
      \App\Domains\PartnerHub\Services\PartnerHubGlobalData::getTargetIndustries(),
  );

  $valuePropTitle = $copy['value_prop_title'];
  $valuePropDesc = $resolver::resolveText(
      get_post_meta($postId, '_rl_override_value_prop', true),
      \App\Domains\PartnerHub\Services\PartnerHubGlobalData::getValueProposition()['desc'],
  );
  $targetFitTitle = $copy['target_fit_title'];
  $targetFitDesc = $resolver::resolveText(
      get_post_meta($postId, '_rl_override_target_fit', true),
      \App\Domains\PartnerHub\Services\PartnerHubGlobalData::getTargetFit()['desc'],
  );

  function getTabUrl($permalink, $tab) {
      if ($tab === 'overview') {
          return esc_url($permalink);
      }
      return esc_url(add_query_arg('tab', $tab, $permalink));
  }

  function formatFileSize($bytes, $fallback) {
      $bytes = (int) $bytes;
      if ($bytes <= 0) {
          return $fallback;
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
          'info' => '<circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>',
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
            <span class="font-bold font-display text-lg sm:text-xl text-white tracking-tight">{{ $copy['hero_wordmark'] }}</span>
            <span class="text-brand-magenta font-bold text-lg">&times;</span>
            @if ($partnerLogo)
              <img src="{{ $partnerLogo }}" alt="{{ $partnerName }}" class="h-7 max-w-[140px] object-contain brightness-0 invert" />
            @else
              <span class="font-bold text-lg sm:text-xl text-purple-200">{{ $partnerName }}</span>
            @endif
          </div>

          <p class="text-xs sm:text-sm text-slate-300 max-w-lg">
            {{ $welcomeText }}
          </p>
        </div>

        {{-- Partner Status Pill & Quick Action --}}
        <div class="flex flex-wrap items-center gap-3">
          <div class="px-3.5 py-1.5 rounded-pill bg-white/10 backdrop-blur-md border border-white/20 text-xs font-mono text-purple-200">
            <span class="text-slate-400">{{ $copy['hero_code_label'] }}</span> <strong class="text-white">{{ $partnerCode }}</strong>
          </div>

          @if ($partnerWebsite)
            <a
              href="{{ esc_url($partnerWebsite) }}"
              target="_blank"
              class="inline-flex items-center gap-2 px-5 py-2 rounded-pill bg-white/10 hover:bg-white/20 border border-white/20 text-white text-xs sm:text-sm font-bold transition-all duration-200 hover:scale-[1.03]"
            >
              <span>{{ $copy['hero_visit_cta'] }}</span>
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
        <label class="block text-xs font-semibold text-text-slate mb-1 uppercase tracking-wider">{{ $copy['nav_mobile_label'] }}</label>
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
        @foreach (\App\Domains\PartnerHub\Services\PartnerHubTabResolver::navGroups() as $groupKey => $group)
          @php($groupTabs = array_values(array_filter($group['tabs'], fn ($tabKey) => isset($validTabs[$tabKey]))))
          @if ($groupTabs)
            <div>
              <span class="text-2xs font-bold uppercase tracking-wider text-text-slate px-3">{{ $copy['nav_group_'.$groupKey] }}</span>
              <nav class="mt-2 space-y-1">
                @foreach ($groupTabs as $tabKey)
                  @if ($tabKey === 'overview')
                    <a href="{{ getTabUrl($partnerPermalink, $tabKey) }}" class="block px-3 py-2 rounded-card text-xs font-bold transition {{ $currentTab === $tabKey ? 'bg-brand-purple text-white shadow-[0_4px_14px_rgba(138,43,226,0.35)]' : 'text-text-body hover:bg-slate-50' }}">
                  @else
                    <a href="{{ getTabUrl($partnerPermalink, $tabKey) }}" class="block px-3 py-2 rounded-card text-xs font-semibold transition {{ $currentTab === $tabKey ? 'bg-brand-purple text-white shadow-[0_4px_14px_rgba(138,43,226,0.35)] font-bold' : 'text-text-body hover:bg-slate-50' }}">
                  @endif
                    {{ $validTabs[$tabKey] }}
                  </a>
                @endforeach
              </nav>
            </div>
          @endif
        @endforeach

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
              <span class="px-3 py-1 rounded-pill bg-brand-purple/10 text-brand-purple text-xs font-bold uppercase tracking-wider">{{ $copy['overview_badge'] }}</span>
              <h1 class="text-2xl sm:text-3xl font-bold font-display text-brand-hero tracking-tight mt-3">
                {{ $copy['overview_heading'] }}
              </h1>
              <p class="text-text-muted text-sm sm:text-base mt-2 leading-relaxed">
                {{ $companyDesc }}
              </p>

              {{-- Direct-Hire Value Proposition callout (parity with the legacy plugin's overview tab) --}}
              <div class="mt-5 p-5 rounded-card-md bg-brand-purple/5 border border-brand-purple/15 flex items-start gap-3">
                <div class="w-8 h-8 rounded-lg bg-brand-purple/10 flex items-center justify-center shrink-0 text-brand-purple mt-0.5">
                  {!! $rlIcon('info', 'w-4 h-4') !!}
                </div>
                <div>
                  <div class="font-bold text-xs uppercase tracking-wider text-brand-purple">{{ $valuePropTitle }}</div>
                  <p class="text-sm text-text-body font-medium leading-relaxed mt-1">{{ $valuePropDesc }}</p>
                </div>
              </div>
            </div>

            {{-- Partnership Terms Summary — unified spec strip so short values never mismatch height with the long renewal sentence --}}
            <div class="rounded-card-md bg-white border border-black/5 shadow-[0_4px_24px_rgba(0,0,0,0.03)] overflow-hidden">
              <div class="grid grid-cols-2 sm:grid-cols-4 divide-x divide-y sm:divide-y-0 divide-slate-100">
                <div class="p-4 sm:p-5">
                  <div class="text-2xs text-text-slate uppercase tracking-wider font-bold">{{ $copy['spec_type_label'] }}</div>
                  <div class="text-sm font-bold text-text-body mt-1.5">{{ $partnershipType }}</div>
                </div>
                <div class="p-4 sm:p-5">
                  <div class="text-2xs text-text-slate uppercase tracking-wider font-bold">{{ $copy['spec_territory_label'] }}</div>
                  <div class="text-sm font-bold text-text-body mt-1.5">{{ $territory }}</div>
                </div>
                <div class="p-4 sm:p-5">
                  <div class="text-2xs text-text-slate uppercase tracking-wider font-bold">{{ $copy['spec_reporting_label'] }}</div>
                  <div class="text-sm font-bold text-text-body mt-1.5">{{ $reportingPeriod }}</div>
                </div>
                <div class="p-4 sm:p-5">
                  <div class="text-2xs text-text-slate uppercase tracking-wider font-bold">{{ $copy['spec_term_label'] }}</div>
                  <div class="text-sm font-bold text-text-body mt-1.5">{{ $initialTerm }}</div>
                </div>
              </div>
              <div class="px-4 sm:px-5 py-3.5 bg-bg-light border-t border-black/5">
                <span class="text-2xs text-text-slate uppercase tracking-wider font-bold">{{ $copy['spec_renewal_label'] }}</span>
                <span class="text-xs sm:text-sm text-text-body font-medium ml-2">{{ $renewalTerms }}</span>
              </div>
            </div>

            {{-- Bidirectional Referral Actions --}}
            <div class="space-y-4 pt-4 border-t border-slate-100">
              <h2 class="text-lg font-bold font-display text-brand-hero">{{ $copy['referral_actions_heading'] }}</h2>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                {{-- Direction A: Partner -> Remote Leverage --}}
                <div class="p-5 rounded-card-md bg-white border border-black/5 shadow-[0_4px_24px_rgba(0,0,0,0.03)] hover:-translate-y-1 hover:shadow-[0_12px_32px_rgba(0,0,0,0.06)] transition-all duration-300 space-y-3">
                  <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-brand-purple/10 flex items-center justify-center shrink-0 text-brand-purple">
                      {!! $rlIcon('arrow-right') !!}
                    </div>
                    <span class="text-2xs font-bold uppercase tracking-wider text-brand-purple leading-tight">
                      {{ $copy['direction_a_label'] }}
                    </span>
                  </div>
                  <p class="text-xs sm:text-sm text-text-muted">
                    {{-- The code is set in a <code> chip wherever it appears in the (escaped) text. --}}
                    {!! str_replace(e($partnerCode), '<code class="px-1.5 py-0.5 rounded bg-slate-100 font-mono text-brand-purple">'.e($partnerCode).'</code>', e($copy['direction_a_intro'])) !!}
                  </p>

                  {{--
                    WR-73. The link carries `?partner={{ $partnerCode }}`, which AttributionCollector
                    already stamps onto the lead and HubSpotGateway now sends as `partnership_id` —
                    so a lead is attributed to this partnership without anyone quoting a code by
                    hand. PartnerLink::for() builds it; do not hand-write this URL.
                  --}}
                  <div x-data="{ copied: false }" class="space-y-1.5 pt-1">
                    <div class="text-2xs font-bold uppercase tracking-wider text-text-slate">{{ $copy['tracked_link_label'] }}</div>
                    <div class="flex items-center gap-2">
                      <input
                        type="text"
                        readonly
                        value="{{ esc_attr($partnerTrackedLink) }}"
                        class="w-full min-w-0 text-2xs sm:text-xs font-mono py-2 px-3 rounded-card bg-slate-50 border border-slate-200 text-text-body select-all"
                      />
                      <button
                        type="button"
                        @click="navigator.clipboard.writeText(@js($partnerTrackedLink)); copied = true; setTimeout(() => copied = false, 2500);"
                        class="px-3.5 py-2 rounded-cta bg-brand-purple hover:bg-brand-purple-deep text-white text-xs font-bold shrink-0 transition cursor-pointer"
                      >
                        <span x-show="!copied">{{ $copy['copy_button'] }}</span>
                        <span x-show="copied" x-cloak>{{ $copy['copied_button'] }}</span>
                      </button>
                    </div>
                    <p class="text-2xs text-text-muted">
                      {{ $copy['tracked_link_note'] }}
                    </p>
                  </div>
                  <div class="flex flex-wrap gap-2 pt-1">
                    @if ($referralFormUrl)
                      <a href="{{ esc_url($referralFormUrl) }}" target="_blank" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-cta bg-brand-purple hover:bg-brand-purple-deep text-white text-xs font-bold transition">
                        {!! $rlIcon('form', 'w-3.5 h-3.5') !!} {{ $copy['cta_submit_form'] }}
                      </a>
                    @endif
                    <a href="mailto:{{ esc_attr($introEmail) }}?subject={{ rawurlencode($copy['intro_email_subject']) }}" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-cta bg-slate-100 hover:bg-slate-200 text-text-body text-xs font-semibold transition">
                      {!! $rlIcon('mail', 'w-3.5 h-3.5') !!} {{ $copy['cta_intro_email'] }}
                    </a>
                    @if ($referralDriveUrl)
                      <a href="{{ esc_url($referralDriveUrl) }}" target="_blank" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-cta bg-slate-100 hover:bg-slate-200 text-text-body text-xs font-semibold transition">
                        {!! $rlIcon('sheet', 'w-3.5 h-3.5') !!} {{ $copy['cta_tracking_sheet'] }}
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
                      {{ $copy['direction_b_label'] }}
                    </span>
                  </div>
                  <p class="text-xs sm:text-sm text-text-muted">{{ $partnerReferralLabel }}</p>
                  <div class="flex flex-wrap gap-2 pt-1">
                    @if ($partnerReferralUrl)
                      <a href="{{ esc_url($partnerReferralUrl) }}" target="_blank" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-cta bg-sky-600 hover:bg-sky-700 text-white text-xs font-bold transition">
                        {!! $rlIcon('external', 'w-3.5 h-3.5') !!} {{ $copy['cta_partner_portal'] }}
                      </a>
                    @endif
                    @if ($partnerReferralEmail && strtolower($partnerReferralEmail) !== 'pending to define')
                      <a href="mailto:{{ esc_attr($partnerReferralEmail) }}?subject={{ rawurlencode($copy['partner_email_subject']) }}" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-cta bg-slate-100 hover:bg-slate-200 text-text-body text-xs font-semibold transition">
                        {!! $rlIcon('mail', 'w-3.5 h-3.5') !!} {{ $copy['cta_partner_email'] }}
                      </a>
                    @else
                      <span class="inline-flex items-center gap-1.5 px-3 py-2 rounded-cta bg-amber-50 text-amber-700 text-2xs font-semibold">
                        {!! $rlIcon('phone-pending', 'w-3.5 h-3.5') !!} {{ $copy['pending_badge'] }}
                      </span>
                    @endif
                  </div>
                </div>
              </div>
            </div>

            <div class="space-y-4 pt-4 border-t border-slate-100">
              <h2 class="text-lg font-bold font-display text-brand-hero">{{ $copy['core_values_heading'] }}</h2>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                @foreach ($coreValues as $value)
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
              <span class="px-3 py-1 rounded-pill bg-brand-purple/10 text-brand-purple text-xs font-bold uppercase tracking-wider">{{ $copy['icp_badge'] }}</span>
              <h1 class="text-2xl sm:text-3xl font-bold font-display text-brand-hero tracking-tight mt-3">{{ $copy['icp_heading'] }}</h1>
              <p class="text-text-muted text-sm mt-2">{{ $copy['icp_subtitle'] }}</p>
            </div>

            <div class="p-5 rounded-card-md bg-emerald-50 border border-emerald-100 flex items-start gap-3">
              <div class="w-8 h-8 rounded-lg bg-emerald-100 flex items-center justify-center shrink-0 text-emerald-700 mt-0.5">
                {!! $rlIcon('target', 'w-4 h-4') !!}
              </div>
              <div>
                <div class="font-bold text-xs uppercase tracking-wider text-emerald-700">{{ $targetFitTitle }}</div>
                <p class="text-sm text-emerald-900 font-medium leading-relaxed mt-1">{{ $targetFitDesc }}</p>
              </div>
            </div>

            <div>
              <h3 class="font-bold text-base text-brand-hero mb-3 flex items-center gap-2">
                <span class="w-7 h-7 rounded-lg bg-brand-purple/10 flex items-center justify-center text-brand-purple">{!! $rlIcon('building', 'w-3.5 h-3.5') !!}</span>
                {{ $copy['industries_heading'] }}
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
                {{ $copy['geo_heading'] }}
              </h3>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                @foreach ($geographicMarkets as $market)
                  <div class="p-4 rounded-card bg-white border border-black/5 shadow-[0_4px_24px_rgba(0,0,0,0.03)]">
                    <div class="font-bold text-xs sm:text-sm text-text-body">{{ $market['region'] }}</div>
                    <p class="text-2xs sm:text-xs text-text-muted mt-1 leading-relaxed">{{ $market['desc'] }}</p>
                  </div>
                @endforeach
              </div>
            </div>
          </div>

        {{-- TAB 3: SERVICES OVERVIEW --}}
        @elseif ($currentTab === 'services')
          <div class="space-y-8 animate-fadeIn">
            <div>
              <span class="px-3 py-1 rounded-pill bg-brand-purple/10 text-brand-purple text-xs font-bold uppercase tracking-wider">{{ $copy['services_badge'] }}</span>
              <h1 class="text-2xl sm:text-3xl font-bold font-display text-brand-hero tracking-tight mt-3">{{ $copy['services_heading'] }}</h1>
              <p class="text-text-muted text-sm mt-2">{{ $servicesDesc }}</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              @foreach ($services as $service)
                <div class="p-5 rounded-card-md bg-white border border-black/5 shadow-[0_4px_24px_rgba(0,0,0,0.03)] hover:-translate-y-1 hover:shadow-[0_12px_32px_rgba(0,0,0,0.06)] transition-all duration-300 space-y-2">
                  <div class="w-10 h-10 rounded-xl bg-brand-purple/10 flex items-center justify-center text-brand-purple mb-1">
                    {!! $rlIcon('briefcase') !!}
                  </div>
                  <h3 class="font-bold text-sm text-brand-hero">{{ $service['name'] }}</h3>
                  <span class="block text-xs text-brand-purple font-medium leading-snug"><span class="font-bold text-2xs uppercase tracking-wide">{{ $copy['services_best_for_label'] }}</span> {{ $service['best_for'] }}</span>
                  <p class="text-xs sm:text-sm text-text-muted leading-relaxed">{{ $service['desc'] }}</p>
                </div>
              @endforeach
            </div>
          </div>

        {{-- TAB 4: WHY REMOTE LEVERAGE --}}
        @elseif ($currentTab === 'why-rl')
          <div class="space-y-8 animate-fadeIn">
            <div>
              <span class="px-3 py-1 rounded-pill bg-brand-purple/10 text-brand-purple text-xs font-bold uppercase tracking-wider">{{ $copy['why_badge'] }}</span>
              <h1 class="text-2xl sm:text-3xl font-bold font-display text-brand-hero tracking-tight mt-3">{{ $copy['why_heading'] }}</h1>
              <p class="text-text-muted text-sm mt-2">{{ $copy['why_subtitle'] }}</p>
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
                      <th class="text-left px-4 py-3 font-bold text-text-body">{{ $copy['matrix_col_criteria'] }}</th>
                      <th class="text-left px-4 py-3 font-bold text-brand-purple">{{ $copy['matrix_col_rl'] }}</th>
                      <th class="text-left px-4 py-3 font-bold text-text-body">{{ $copy['matrix_col_inhouse'] }}</th>
                      <th class="text-left px-4 py-3 font-bold text-text-body">{{ $copy['matrix_col_agency'] }}</th>
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
              <span class="px-3 py-1 rounded-pill bg-brand-purple/10 text-brand-purple text-xs font-bold uppercase tracking-wider">{{ $copy['referral_badge'] }}</span>
              <h1 class="text-2xl sm:text-3xl font-bold font-display text-brand-hero tracking-tight mt-3">{{ $copy['referral_heading'] }}</h1>
              <p class="text-text-muted text-sm mt-2">{{ $copy['referral_subtitle'] }}</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div class="p-5 rounded-card-md bg-white border border-black/5 shadow-[0_4px_24px_rgba(0,0,0,0.03)] space-y-2">
                <div class="flex items-center gap-2">
                  <div class="w-8 h-8 rounded-lg bg-brand-purple/10 flex items-center justify-center text-brand-purple shrink-0">{!! $rlIcon('arrow-right', 'w-4 h-4') !!}</div>
                  <span class="text-2xs font-bold uppercase tracking-wider text-brand-purple">{{ $copy['fee_a_label'] }}</span>
                </div>
                <p class="text-sm text-text-body leading-relaxed">{{ $partnerToRlFee }}</p>
              </div>
              <div class="p-5 rounded-card-md bg-white border border-black/5 shadow-[0_4px_24px_rgba(0,0,0,0.03)] space-y-2">
                <div class="flex items-center gap-2">
                  <div class="w-8 h-8 rounded-lg bg-sky-50 flex items-center justify-center text-sky-600 shrink-0">{!! $rlIcon('users', 'w-4 h-4') !!}</div>
                  <span class="text-2xs font-bold uppercase tracking-wider text-sky-700">{{ $copy['fee_b_label'] }}</span>
                </div>
                <p class="text-sm text-text-body leading-relaxed">{{ $rlToPartnerFee }}</p>
              </div>
            </div>

            <div>
              <h3 class="font-bold text-base text-brand-hero mb-3">{{ $copy['rules_heading'] }}</h3>
              <div class="p-5 rounded-card-md bg-bg-light border border-black/5">
                <ul class="text-xs sm:text-sm text-text-muted space-y-2.5">
                  @foreach ($referralRules as $rule)
                    <li class="flex items-start gap-2.5">
                      <span class="text-emerald-600 shrink-0 mt-0.5">{!! $rlIcon('check-circle', 'w-4 h-4') !!}</span>
                      <span>{{ $rule }}</span>
                    </li>
                  @endforeach
                </ul>
              </div>
            </div>

            <div>
              <h3 class="font-bold text-base text-brand-hero mb-3">{{ $copy['lifecycle_heading'] }}</h3>
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
                  <h3 class="font-bold text-sm text-amber-900 mb-1">{{ $copy['special_terms_heading'] }}</h3>
                  <p class="text-xs sm:text-sm text-amber-800 whitespace-pre-line leading-relaxed">{{ $overrideCommission }}</p>
                </div>
              </div>
            @endif
          </div>

        {{-- TAB 6: CO-MARKETING (conditional) --}}
        @elseif ($currentTab === 'comarketing' && $hasComarketing)
          <div class="space-y-8 animate-fadeIn">
            <div>
              <span class="px-3 py-1 rounded-pill bg-brand-purple/10 text-brand-purple text-xs font-bold uppercase tracking-wider">{{ $copy['comarketing_badge'] }}</span>
              <h1 class="text-2xl sm:text-3xl font-bold font-display text-brand-hero tracking-tight mt-3">{{ $copy['comarketing_heading'] }}</h1>
              <p class="text-text-muted text-sm mt-2">{{ $comarketingText }}</p>
            </div>

            <div class="p-5 rounded-card-md bg-amber-50 border border-amber-100 flex items-start gap-3">
              <div class="w-8 h-8 rounded-lg bg-amber-100 flex items-center justify-center shrink-0 text-amber-700 mt-0.5">
                {!! $rlIcon('alert', 'w-4 h-4') !!}
              </div>
              <p class="text-xs sm:text-sm font-semibold text-amber-900 leading-relaxed">{{ $comarketingNotice }}</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              @foreach ($comarketingOpportunities as $opp)
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
                {{ $copy['comarketing_cta_text'] }}
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
              <span class="px-3 py-1 rounded-pill bg-brand-purple/10 text-brand-purple text-xs font-bold uppercase tracking-wider">{{ $copy['cs_badge'] }}</span>
              <h1 class="text-2xl sm:text-3xl font-bold font-display text-brand-hero tracking-tight mt-3">{{ $copy['cs_heading'] }}</h1>
              <p class="text-text-muted text-sm mt-2">{{ $copy['cs_subtitle'] }}</p>
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
                      <div class="text-2xs font-bold uppercase tracking-wider text-text-slate mb-1.5">{{ $copy['cs_challenge_label'] }}</div>
                      <ul class="text-xs sm:text-sm text-text-muted space-y-1.5 list-disc list-inside">
                        @foreach ($cs['challenge'] as $point)
                          <li>{{ $point }}</li>
                        @endforeach
                      </ul>
                    </div>

                    <div>
                      <div class="text-2xs font-bold uppercase tracking-wider text-text-slate mb-1.5">{{ $copy['cs_solution_label'] }}</div>
                      <ul class="text-xs sm:text-sm text-text-muted space-y-1.5 list-disc list-inside">
                        @foreach ($cs['solution'] as $point)
                          <li>{{ $point }}</li>
                        @endforeach
                      </ul>
                    </div>

                    <div class="p-3 rounded-card bg-emerald-50 border border-emerald-100">
                      <div class="text-2xs font-bold uppercase tracking-wider text-emerald-700 mb-1.5">{{ $copy['cs_outcome_label'] }}</div>
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
              <span class="px-3 py-1 rounded-pill bg-brand-purple/10 text-brand-purple text-xs font-bold uppercase tracking-wider">{{ $copy['faq_badge'] }}</span>
              <h1 class="text-2xl sm:text-3xl font-bold font-display text-brand-hero tracking-tight mt-3">{{ $copy['faq_heading'] }}</h1>
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
              <span class="px-3 py-1 rounded-pill bg-brand-purple/10 text-brand-purple text-xs font-bold uppercase tracking-wider">{{ $copy['contact_badge'] }}</span>
              <h1 class="text-2xl sm:text-3xl font-bold font-display text-brand-hero tracking-tight mt-3">{{ $copy['contact_heading'] }}</h1>
              <p class="text-text-muted text-sm mt-2">{{ $copy['contact_subtitle'] }}</p>
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
            <h3 class="font-bold text-sm text-brand-hero mb-3">{{ str_replace('{tab}', $validTabs[$currentTab], $copy['attachments_heading']) }}</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              @foreach ($currentAttachments as $attachment)
                <a href="{{ esc_url($attachment['file']['url'] ?? '') }}" target="_blank" class="flex items-center gap-3 p-3 rounded-card bg-white border border-black/5 shadow-[0_4px_24px_rgba(0,0,0,0.03)] hover:-translate-y-0.5 hover:shadow-[0_8px_24px_rgba(0,0,0,0.06)] transition-all duration-200">
                  <div class="w-9 h-9 rounded-lg bg-red-50 flex items-center justify-center shrink-0 text-red-600">
                    {!! $rlIcon('pdf', 'w-4 h-4') !!}
                  </div>
                  <div class="flex-1 min-w-0">
                    <div class="text-xs font-bold text-text-body truncate">{{ $attachment['title'] ?: ($attachment['file']['filename'] ?? 'Attachment') }}</div>
                    <div class="text-2xs text-text-muted">{{ formatFileSize($attachment['file']['filesize'] ?? 0, $copy['pdf_caption']) }}</div>
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
          <span class="text-2xs font-bold uppercase tracking-wider text-text-slate">{{ $copy['resources_heading'] }}</span>

          @if ($rlResourceUrl)
            <a href="{{ esc_url($rlResourceUrl) }}" target="_blank" class="flex items-center gap-3 p-3 rounded-card hover:bg-bg-light transition">
              <div class="w-9 h-9 rounded-lg bg-brand-purple/10 flex items-center justify-center shrink-0 text-brand-purple">{!! $rlIcon('drive', 'w-4 h-4') !!}</div>
              <div class="flex-1 min-w-0">
                <div class="text-xs font-bold text-text-body leading-snug">{{ $rlResourceTitle }}</div>
                <div class="text-2xs text-text-muted">{{ $copy['rl_resource_caption'] }}</div>
              </div>
            </a>
          @endif

          @if ($partnerResourceUrl)
            <a href="{{ esc_url($partnerResourceUrl) }}" target="_blank" class="flex items-center gap-3 p-3 rounded-card hover:bg-bg-light transition">
              <div class="w-9 h-9 rounded-lg bg-sky-50 flex items-center justify-center shrink-0 text-sky-600">{!! $rlIcon('external', 'w-4 h-4') !!}</div>
              <div class="flex-1 min-w-0">
                <div class="text-xs font-bold text-text-body leading-snug">{{ $partnerResourceTitle }}</div>
                <div class="text-2xs text-text-muted">{{ $copy['partner_resource_caption'] }}</div>
              </div>
            </a>
          @endif

          @if ($onePagerPdf)
            <a href="{{ esc_url($onePagerPdf) }}" target="_blank" class="flex items-center gap-3 p-3 rounded-card hover:bg-bg-light transition">
              <div class="w-9 h-9 rounded-lg bg-red-50 flex items-center justify-center shrink-0 text-red-600">{!! $rlIcon('pdf', 'w-4 h-4') !!}</div>
              <div class="flex-1 min-w-0">
                <div class="text-xs font-bold text-text-body truncate">{{ $copy['one_pager_title'] }}</div>
                <div class="text-2xs text-text-muted">{{ $copy['pdf_caption'] }}</div>
              </div>
            </a>
          @endif

          @if ($agreementPdf)
            <a href="{{ esc_url($agreementPdf) }}" target="_blank" class="flex items-center gap-3 p-3 rounded-card hover:bg-bg-light transition">
              <div class="w-9 h-9 rounded-lg bg-red-50 flex items-center justify-center shrink-0 text-red-600">{!! $rlIcon('pdf', 'w-4 h-4') !!}</div>
              <div class="flex-1 min-w-0">
                <div class="text-xs font-bold text-text-body truncate">{{ $copy['agreement_title'] }}</div>
                <div class="text-2xs text-text-muted">{{ $copy['pdf_caption'] }}</div>
              </div>
            </a>
          @endif

          @if (! $rlResourceUrl && ! $partnerResourceUrl && ! $onePagerPdf && ! $agreementPdf)
            <p class="text-2xs text-text-muted px-3">{{ $copy['resources_empty'] }}</p>
          @endif
        </div>

        <div class="space-y-2.5 pt-2 border-t border-slate-200">
          <span class="text-2xs font-bold uppercase tracking-wider text-text-slate">{{ $copy['quick_actions_heading'] }}</span>
          @if ($referralFormUrl)
            <a href="{{ esc_url($referralFormUrl) }}" target="_blank" class="flex items-center justify-center gap-2 w-full py-2.5 rounded-cta bg-brand-purple hover:bg-brand-purple-deep text-white text-xs font-bold transition-all duration-200 hover:scale-[1.02] shadow-[0_4px_14px_rgba(138,43,226,0.3)]">
              {!! $rlIcon('form', 'w-3.5 h-3.5') !!} {{ $copy['qa_refer'] }}
            </a>
          @endif
          @if ($referralDriveUrl)
            <a href="{{ esc_url($referralDriveUrl) }}" target="_blank" class="flex items-center justify-center gap-2 w-full py-2.5 rounded-cta bg-slate-100 hover:bg-slate-200 text-text-body text-xs font-semibold transition">
              {!! $rlIcon('sheet', 'w-3.5 h-3.5') !!} {{ $copy['qa_tracking'] }}
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
            {!! $rlIcon('mail', 'w-3 h-3') !!} {{ $copy['qa_email'] }}
          </a>
        </div>
      </aside>

    </div>
    </div>
  </section>
</div>
@endsection
