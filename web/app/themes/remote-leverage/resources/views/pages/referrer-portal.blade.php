@extends('layouts.app')

@section('content')
  <div class="py-12 sm:py-16 bg-surface-white min-h-175">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

      {{-- The "New Referrer Application" button that used to sit opposite this heading is
           gone: the Log in / Apply switcher inside the Livewire component covers it for
           signed-out visitors, and it meant nothing to a signed-in referrer. --}}
      <div class="mb-8 border-b border-slate-200 pb-6">
        <div>
          <span class="inline-flex items-center gap-2 px-3 py-1 rounded-pill bg-purple-100 text-brand-purple text-2xs font-bold uppercase tracking-wider mb-2">
            Strategic Referral Network
          </span>
          <h1 class="text-2xl sm:text-3xl font-bold font-display text-slate-900 tracking-tight">
            Referrer Portal Dashboard
          </h1>
          <p class="mt-1 text-sm text-slate-600">
            Monitor click attribution, active leads, and Stripe Connect commission payouts.
          </p>
        </div>
      </div>

      {{-- Embed Reactive Referrer Portal Dashboard --}}
      <livewire:referrer.referrer-portal-dashboard />

    </div>
  </div>
@endsection
