@extends('layouts.app')

@section('content')
  <section class="relative min-h-[75vh] flex items-center justify-center overflow-hidden bg-gradient-to-b from-brand-midnight via-brand-hero to-brand-midnight text-white py-24 px-4 sm:px-6 lg:px-8">
    {{-- Glow background effects --}}
    <div class="pointer-events-none absolute top-1/4 left-1/4 h-96 w-96 rounded-full bg-brand-purple/20 blur-3xl"></div>
    <div class="pointer-events-none absolute bottom-1/4 right-1/4 h-96 w-96 rounded-full bg-brand-magenta/15 blur-3xl"></div>

    <div class="relative max-w-2xl mx-auto text-center space-y-8">
      {{-- 404 Visual Indicator --}}
      <div class="relative inline-block">
        <span class="text-8xl sm:text-9xl font-black font-display tracking-tighter text-transparent bg-clip-text bg-gradient-to-r from-brand-purple via-pink-400 to-brand-magenta select-none">
          404
        </span>
        <div class="absolute -bottom-2 left-1/2 -translate-x-1/2 px-3 py-1 rounded-pill bg-white/10 backdrop-blur-md border border-white/20 text-2xs font-bold uppercase tracking-widest text-white">
          Page Not Found
        </div>
      </div>

      {{-- Heading & Subheading --}}
      <div class="space-y-3">
        <h1 class="text-2xl sm:text-4xl font-bold font-display text-white tracking-tight">
          Looks like this page went remote.
        </h1>
        <p class="text-sm sm:text-base text-slate-300 max-w-lg mx-auto leading-relaxed">
          The page you are looking for might have been moved, renamed, or temporarily unavailable. Let’s get you back on track.
        </p>
      </div>

      {{-- Search Form --}}
      <form role="search" method="get" action="{{ home_url('/') }}" class="max-w-md mx-auto">
        <div class="relative">
          <input
            type="search"
            name="s"
            placeholder="Search guides, services, partners..."
            value="{{ get_search_query() }}"
            class="w-full text-sm pl-11 pr-28 py-3.5 rounded-pill bg-white/10 backdrop-blur-md border border-white/20 text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-brand-purple focus:border-transparent transition shadow-lg"
          />
          <svg class="w-5 h-5 text-slate-400 absolute left-4 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
          </svg>
          <button
            type="submit"
            class="absolute right-1.5 top-1/2 -translate-y-1/2 px-4 py-2 rounded-pill bg-brand-purple hover:bg-brand-purple-deep text-white text-xs font-bold transition shadow-sm cursor-pointer"
          >
            Search
          </button>
        </div>
      </form>

      {{-- Quick Helpful Links --}}
      <div class="pt-4 border-t border-white/10 space-y-3">
        <p class="text-xs uppercase font-bold tracking-wider text-slate-400">Popular Destinations</p>
        <div class="flex flex-wrap items-center justify-center gap-3">
          <a
            href="{{ home_url('/') }}"
            class="px-4 py-2 rounded-pill bg-white/5 hover:bg-white/10 border border-white/10 text-xs font-semibold text-white transition"
          >
            &larr; Return Home
          </a>
          <a
            href="{{ home_url('/partners') }}"
            class="px-4 py-2 rounded-pill bg-white/5 hover:bg-white/10 border border-white/10 text-xs font-semibold text-white transition"
          >
            Partner Network
          </a>
          <a
            href="{{ home_url('/blog') }}"
            class="px-4 py-2 rounded-pill bg-white/5 hover:bg-white/10 border border-white/10 text-xs font-semibold text-white transition"
          >
            Tactical Guides
          </a>
          <a
            href="{{ home_url('/book-consultation') }}"
            class="px-4 py-2 rounded-pill bg-brand-magenta/20 hover:bg-brand-magenta/30 border border-brand-magenta/40 text-xs font-semibold text-white transition"
          >
            Book Consultation &rarr;
          </a>
        </div>
      </div>
    </div>
  </section>
@endsection
