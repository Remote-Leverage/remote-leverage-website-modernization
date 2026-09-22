{{--
  Sales-rep referral form — an internal tool, not a marketing page (WR-126).

  Reached by URL only: nothing links here, it is not in the nav, and the route calls
  `PageRobots::forceNoindex()`. The same posture as `live-transfer-contact-creation`, and for
  the same reason — an internal tool that writes to the CRM has no business in search results.

  The outer shell matches that page deliberately, so the two tools a rep opens mid-call look
  like the same product. The form itself is Tailwind rather than that page's ported Elementor
  CSS; there was nothing to recover verbatim here.
--}}
@extends('layouts.app')

@section('content')
  <div class="py-12 sm:py-16 bg-surface-white min-h-175">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
      <livewire:referrer.sales-referral-form />
    </div>
  </div>
@endsection
