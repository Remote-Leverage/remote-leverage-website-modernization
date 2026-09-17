@extends('layouts.app')

@section('content')
  {{--
    The component owns its own width, padding and page header.

    This page used to wrap it in `max-w-7xl mx-auto px-4 sm:px-6 lg:px-8` while the component
    applied the same constraint and padding again inside it — double gutters, and a hard 1280px
    ceiling that left no room for the dashboard's three columns.

    The signed-out header moved into the component for the reason partials/referrer-auth-tabs
    already documents: rendered from here it cannot track auth state, so a centred marketing
    header sat above the signed-in dashboard, costing ~200px before the fold and repeating the
    greeting directly beneath it.

    `bg-bg-light` rather than white: the dashboard is white cards, and white-on-white gave them
    no edge to sit against.
  --}}
  <div class="bg-bg-light min-h-175">
    <livewire:referrer.referrer-portal-dashboard />
  </div>
@endsection
