@extends('layouts.app')

@section('content')
  <div class="py-12 sm:py-16 bg-surface-white min-h-175">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">

      @include('partials.referrer-auth-header', [
          'eyebrow' => 'Join the Network',
          'title' => 'Referrer Registration',
          'subtitle' => 'Refer clients who need remote executive talent to Remote Leverage and earn recurring commissions on every signup.',
      ])

      {{-- Embed Reactive Referrer Registration Form --}}
      <livewire:referrer.referrer-registration-form />

    </div>
  </div>
@endsection
