@extends('layouts.app')

@section('content')
  <div class="py-12 sm:py-16 bg-surface-white min-h-175">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

      {{-- The "New Referrer Application" button that used to sit opposite this heading is
           gone: the Log in / Apply switcher inside the Livewire component covers it for
           signed-out visitors, and it meant nothing to a signed-in referrer. --}}
      @include('partials.referrer-auth-header', [
          'eyebrow' => 'Strategic Referral Network',
          'title' => 'Referrer Portal Dashboard',
          'subtitle' => 'Monitor click attribution, active leads, and commission payouts.',
      ])

      {{-- Embed Reactive Referrer Portal Dashboard --}}
      <livewire:referrer.referrer-portal-dashboard />

    </div>
  </div>
@endsection
