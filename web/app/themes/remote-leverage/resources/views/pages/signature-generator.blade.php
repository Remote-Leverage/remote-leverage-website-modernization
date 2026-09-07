@extends('layouts.app')

@section('content')
  <div class="py-12 sm:py-16 bg-surface-white min-h-175">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
      
      <div class="text-center mb-10">
        <span class="inline-flex items-center gap-2 px-3.5 py-1 rounded-pill bg-purple-100 text-brand-purple text-xs font-bold uppercase tracking-wider mb-3">
          Internal Utilities
        </span>
        <h1 class="text-3xl sm:text-4xl font-extrabold font-display text-slate-900 tracking-tight">
          Email Signature Generator
        </h1>
        <p class="mt-3 text-base text-slate-600">
          Generate a standardized, brand-compliant HTML email signature for Outlook, Apple Mail, and Gmail.
        </p>
      </div>

      {{-- Embed Email Signature Generator --}}
      <livewire:utilities.email-signature-generator />

    </div>
  </div>
@endsection
