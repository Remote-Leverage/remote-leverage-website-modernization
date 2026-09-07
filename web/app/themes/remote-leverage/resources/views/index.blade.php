@extends('layouts.app')

@section('content')
  {{-- Hero Header --}}
  <section class="relative overflow-hidden bg-gradient-to-b from-brand-midnight via-brand-hero to-brand-midnight text-white pt-24 pb-16 sm:pt-28 sm:pb-20">
    {{-- Glow background accents --}}
    <div class="pointer-events-none absolute -top-32 left-1/3 h-80 w-80 rounded-full bg-brand-purple/20 blur-3xl"></div>
    <div class="pointer-events-none absolute -bottom-16 right-10 h-72 w-72 rounded-full bg-brand-magenta/15 blur-3xl"></div>

    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center space-y-5">
      {{-- Badge --}}
      <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-pill bg-white/10 border border-white/15 text-white text-xs font-semibold backdrop-blur-sm">
        <svg class="w-3.5 h-3.5 text-brand-purple" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
        </svg>
        <span>Knowledge Hub & Tactical Guides</span>
      </div>

      {{-- Heading --}}
      <h1 class="text-3xl sm:text-5xl font-black font-display tracking-tight text-white max-w-3xl mx-auto leading-tight">
        Scale Intelligently with <span class="text-transparent bg-clip-text bg-gradient-to-r from-brand-purple via-pink-400 to-brand-magenta">Proven Playbooks</span>
      </h1>

      <p class="text-sm sm:text-base text-slate-300 max-w-2xl mx-auto leading-relaxed">
        Explore executive SOPs, hiring blueprints, and operational workflows designed to help growing companies hire and manage elite remote talent.
      </p>
    </div>
  </section>

  {{-- Interactive Guide Index with Livewire --}}
  <section class="py-12 bg-bg-light min-h-[500px]">
    <livewire:blog.guide-index-filter />
  </section>

  {{-- Strategic Booking Section --}}
  <section class="py-16 bg-surface-white border-t border-slate-200/80">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 text-center space-y-6">
      <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-pill bg-brand-purple/10 text-brand-purple text-xs font-bold uppercase tracking-wider">
        Ready to take action?
      </div>
      <h2 class="text-2xl sm:text-3xl font-black font-display text-brand-hero">
        Need a Tailored Remote Talent Architecture?
      </h2>
      <p class="text-sm text-text-muted max-w-xl mx-auto">
        Speak directly with a Remote Leverage Managing Director to assess your organizational bottlenecks and receive custom talent profiles within 48 hours.
      </p>
      <div class="pt-2 flex flex-wrap justify-center items-center gap-4">
        <a
          href="{{ home_url('/book-consultation') }}"
          class="px-6 py-3 rounded-pill bg-gradient-to-r from-brand-purple to-brand-magenta hover:opacity-95 text-white font-bold text-sm shadow-btn transition cursor-pointer"
        >
          Schedule Strategic Call &rarr;
        </a>
        <livewire:scheduling.instant-live-call-button label="Request Instant Callback" variant="outline" />
      </div>
    </div>
  </section>
@endsection
