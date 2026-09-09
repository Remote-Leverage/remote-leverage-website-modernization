@extends('layouts.app')

@section('content')
  <div class="py-12 sm:py-16 bg-surface-white min-h-175">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">

      <div class="text-center mb-10">
        <span class="inline-flex items-center gap-2 px-3.5 py-1 rounded-pill bg-purple-100 text-brand-purple text-xs font-bold uppercase tracking-wider mb-3">
          Join the Network
        </span>
        <h1 class="text-3xl sm:text-4xl font-extrabold font-display text-slate-900 tracking-tight">
          Referrer Registration
        </h1>
        <p class="mt-3 text-base text-slate-600">
          Refer clients who need remote executive talent to Remote Leverage and earn recurring commissions on every signup.
        </p>
      </div>

      {{-- Embed Reactive Referrer Registration Form --}}
      <livewire:referrer.referrer-registration-form />

    </div>
  </div>
@endsection
