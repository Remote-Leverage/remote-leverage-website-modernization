@extends('layouts.app')

@section('content')
  {{-- Hero Section --}}
  <section class="relative overflow-hidden bg-gradient-to-b from-brand-midnight via-brand-hero to-brand-midnight text-white pt-24 pb-20 sm:pt-28 sm:pb-24">
    {{-- Glow background effects --}}
    <div class="pointer-events-none absolute -top-40 right-1/4 h-96 w-96 rounded-full bg-brand-purple/20 blur-3xl"></div>
    <div class="pointer-events-none absolute -bottom-20 left-10 h-80 w-80 rounded-full bg-brand-magenta/15 blur-3xl"></div>

    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center space-y-6">
      {{-- Badge --}}
      <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-pill bg-white/10 border border-white/15 text-white text-xs font-semibold backdrop-blur-sm">
        <svg class="w-3.5 h-3.5 text-brand-magenta" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
        </svg>
        <span>Verified Partner Ecosystem</span>
      </div>

      {{-- Heading --}}
      <h1 class="text-3xl sm:text-5xl lg:text-6xl font-black font-display tracking-tight text-white max-w-4xl mx-auto leading-tight">
        Enterprise <span class="text-transparent bg-clip-text bg-gradient-to-r from-brand-purple via-pink-400 to-brand-magenta">Partner Network</span>
      </h1>

      <p class="text-base sm:text-lg text-slate-300 max-w-2xl mx-auto leading-relaxed">
        Connect with our certified ecosystem of industry-leading agencies, technology platforms, and operational consultants delivering unparalleled scale with Remote Leverage.
      </p>

      {{-- Network Metrics Bar --}}
      <div class="pt-8 grid grid-cols-2 md:grid-cols-4 gap-4 sm:gap-6 max-w-4xl mx-auto">
        <div class="p-4 rounded-card bg-white/5 border border-white/10 backdrop-blur-sm text-center">
          <div class="text-2xl sm:text-3xl font-black font-display text-white">50+</div>
          <div class="text-xs text-slate-300 mt-0.5">Certified Partners</div>
        </div>
        <div class="p-4 rounded-card bg-white/5 border border-white/10 backdrop-blur-sm text-center">
          <div class="text-2xl sm:text-3xl font-black font-display text-brand-magenta">$14M+</div>
          <div class="text-xs text-slate-300 mt-0.5">Client Revenue Influenced</div>
        </div>
        <div class="p-4 rounded-card bg-white/5 border border-white/10 backdrop-blur-sm text-center">
          <div class="text-2xl sm:text-3xl font-black font-display text-white">99.4%</div>
          <div class="text-xs text-slate-300 mt-0.5">Placement Retention</div>
        </div>
        <div class="p-4 rounded-card bg-white/5 border border-white/10 backdrop-blur-sm text-center">
          <div class="text-2xl sm:text-3xl font-black font-display text-brand-purple">Tier-1</div>
          <div class="text-xs text-slate-300 mt-0.5">Direct Collaboration</div>
        </div>
      </div>
    </div>
  </section>

  {{-- Directory Search & Grid --}}
  <section class="py-12 bg-bg-light min-h-[500px]">
    <livewire:partner.partner-directory-grid />
  </section>

  {{-- Join Network CTA Banner --}}
  <section class="py-16 bg-surface-white border-t border-slate-200/70">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="relative overflow-hidden rounded-card-lg bg-gradient-to-r from-brand-midnight via-brand-hero to-brand-midnight text-white p-8 sm:p-12 lg:p-16 border border-brand-purple/30 shadow-2xl">
        <div class="pointer-events-none absolute -right-20 -bottom-20 w-80 h-80 rounded-full bg-brand-purple/25 blur-3xl"></div>
        <div class="pointer-events-none absolute left-10 -top-20 w-60 h-60 rounded-full bg-brand-magenta/20 blur-3xl"></div>

        <div class="relative max-w-3xl space-y-6">
          <div class="inline-flex items-center gap-2 px-3.5 py-1 rounded-pill bg-brand-magenta/20 text-brand-magenta text-xs font-bold uppercase tracking-wider">
            Partner Opportunities
          </div>

          <h2 class="text-2xl sm:text-4xl font-black font-display text-white tracking-tight">
            Deliver World-Class Talent to Your Clients & Earn Recurring Commissions
          </h2>

          <p class="text-sm sm:text-base text-slate-300 leading-relaxed max-w-2xl">
            Empower your customer base with top-tier nearshore executive assistants, real estate coordinators, and marketing experts. Gain up to 20% recurring referral share and dedicated co-branded landing pages.
          </p>

          <div class="pt-4 flex flex-wrap items-center gap-4">
            <a
              href="{{ home_url('/partner-dashboard#register') }}"
              class="px-6 py-3 rounded-pill bg-gradient-to-r from-brand-purple to-brand-magenta hover:opacity-95 text-white font-bold text-sm shadow-btn transition cursor-pointer"
            >
              Apply to Partner Network &rarr;
            </a>

            <a
              href="{{ home_url('/partner-dashboard') }}"
              class="px-6 py-3 rounded-pill bg-white/10 hover:bg-white/15 text-white font-semibold text-sm border border-white/20 transition cursor-pointer"
            >
              Partner Portal Login
            </a>
          </div>
        </div>
      </div>
    </div>
  </section>
@endsection
