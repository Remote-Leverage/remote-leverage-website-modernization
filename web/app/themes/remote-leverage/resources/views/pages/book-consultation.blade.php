@extends('layouts.app')

@section('content')
  <div class="py-12 sm:py-16 bg-surface-white min-h-175">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      
      {{-- Offline Notification Alert if redirected from instant call --}}
      @if (request()->query('offline'))
        <div class="mb-8 p-4 rounded-xl bg-purple-50 border border-brand-purple/20 text-brand-midnight flex items-center gap-3">
          <span class="w-3 h-3 rounded-full bg-status-warning"></span>
          <p class="text-sm font-medium">
            {{ request()->query('notice', 'Our consultants are currently in active sessions. Please choose a guaranteed time slot below!') }}
          </p>
        </div>
      @endif

      <div class="text-center max-w-3xl mx-auto mb-10">
        <span class="inline-flex items-center gap-2 px-3.5 py-1 rounded-pill bg-purple-100 text-brand-purple text-xs font-bold uppercase tracking-wider mb-3">
          Strategy Consultation
        </span>
        <h1 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold font-display text-slate-900 tracking-tight">
          Book Your 15-Minute Alignment Call
        </h1>
        <p class="mt-4 text-base sm:text-lg text-slate-600">
          Lock in a direct session with our operations director to define your hiring requirements and review vetted candidate profiles.
        </p>
      </div>

      {{-- Embed Reactive Multistep Booking Funnel --}}
      <livewire:booking.multistep-booking-wizard />

    </div>
  </div>
@endsection
