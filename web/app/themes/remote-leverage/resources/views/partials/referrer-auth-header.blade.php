{{--
  Shared page header for the two halves of the referrer program.

  Login and Apply are tabs of one flow (see partials/referrer-auth-tabs), so switching
  between them must not move anything but the words. They previously each carried their own
  copy of this markup and had drifted apart in both directions: the login header was left
  aligned at text-2xl/3xl with a border-b, the apply header centred at text-3xl/4xl without
  one. One partial, one scale, centred on both.

  The measure is pinned to max-w-3xl rather than inherited: pages.referrer-portal sits in a
  max-w-7xl container (the authenticated dashboard needs it), and a centred paragraph run
  that wide would not line up with the apply page's.

  @param string $eyebrow
  @param string $title
  @param string $subtitle
--}}
<div class="max-w-3xl mx-auto text-center mb-10">
  <span class="inline-flex items-center gap-2 px-3.5 py-1 rounded-pill bg-purple-100 text-brand-purple text-xs font-bold uppercase tracking-wider mb-3">
    {{ $eyebrow }}
  </span>
  <h1 class="text-3xl sm:text-4xl font-bold font-display text-slate-900 tracking-tight">
    {{ $title }}
  </h1>
  <p class="mt-3 text-base text-slate-600">
    {{ $subtitle }}
  </p>
</div>
