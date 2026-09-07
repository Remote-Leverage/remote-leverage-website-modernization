@extends('layouts.app')

@section('content')
  <div class="py-12 sm:py-16 bg-surface-white min-h-175">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      
      <div class="mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-200 pb-6">
        <div>
          <span class="inline-flex items-center gap-2 px-3 py-1 rounded-pill bg-purple-100 text-brand-purple text-2xs font-bold uppercase tracking-wider mb-2">
            Strategic Affiliate Network
          </span>
          <h1 class="text-2xl sm:text-3xl font-extrabold font-display text-slate-900 tracking-tight">
            Partner Portal Dashboard
          </h1>
          <p class="mt-1 text-sm text-slate-600">
            Monitor click attribution, active leads, and Stripe Connect commission payouts.
          </p>
        </div>

        <div>
          <a
            href="{{ route('partner.register') }}"
            class="px-5 py-2.5 rounded-pill bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition inline-flex items-center gap-2"
          >
            <span>New Partner Application</span>
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7m0 0l-7 7m7-7H3"/></svg>
          </a>
        </div>
      </div>

      {{-- Embed Reactive Partner Portal Dashboard --}}
      <livewire:partner.partner-portal-dashboard />

    </div>
  </div>
@endsection
