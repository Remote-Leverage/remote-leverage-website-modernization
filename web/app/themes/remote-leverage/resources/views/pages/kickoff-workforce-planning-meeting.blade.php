{{--
  Kickoff & Workforce Planning Meeting — the post-sale booking page.

  Recovered from production post 51128 (an Elementor html widget) on 2026-09-18; the original
  is kept verbatim in `docs/recovered/kickoff-workforce-planning-meeting/`.

  **Why this was missed at cutover.** The page was created on 2026-09-15 10:35, after the
  migration audit had frozen its scope from `page-sitemap.xml`. It was in neither that list nor
  the non-indexed sweep, so it got no v2 page and no 301 — one of exactly two legacy URLs still
  returning a hard 404 when all 357 were swept on 2026-09-18.

  It is a revenue router, not a content page: pick a monthly-revenue band and the matching
  Calendly event embeds inline. Six options collapse onto three tiers, and "Prefer not to say"
  deliberately routes to the T0 calendar — that mapping is production's and must not be
  "tidied".

  ## What changed from the original, and what did not

  The **script is production's, unchanged** — so the tier mapping, the embed URL construction
  and the back/new-tab behaviour are byte-identical. The form's ids, the `revenue` input name
  and its three values are the script's contract and are preserved exactly.

  The **presentation was rebuilt on v2 tokens** (2026-09-18) because the original shipped its
  own stylesheet hardcoding `#6b21b8`, which is not this site's purple (`#8A2BE2`), so it read
  as an unstyled third-party form dropped into the page. Two other reasons it could not be kept
  as-is: it pulled a logo from `remoteleverage.com/wp-content/uploads/...`, a legacy URL that
  dies with that host, and it duplicated a logo the site header already renders.

  The **iframe sizing CSS is kept verbatim** in a verbatim block. Those breakpoints are not
  decoration: Calendly switches to a two-column layout above ~1000px and stacks below it, so
  the frame height has to change with it or the calendar is cropped. The block is verbatim
  because Blade would read a CSS media query as a directive.
--}}
@extends('layouts.app')

@section('content')
  <div class="bg-surface-white py-12 sm:py-16">
    <div id="rl-router" class="w-full max-w-[1380px] mx-auto px-4 sm:px-6 lg:px-8">

      {{-- STEP 1 — the revenue question --}}
      <form id="rl-form" class="mx-auto max-w-2xl rounded-2xl border border-slate-200 bg-white p-6 shadow-xl shadow-brand-midnight/5 sm:p-10">
        <div class="text-center">
          <h1 class="text-3xl font-bold tracking-tight text-brand-midnight sm:text-4xl">
            Kickoff &amp; Workforce Planning Meeting
          </h1>
          <p class="mx-auto mt-3 max-w-md text-[15px] leading-relaxed text-slate-600">
            Tell us your monthly revenue and we&rsquo;ll show you the right times to book.
          </p>
        </div>

        <fieldset class="mt-8">
          <legend class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">
            Monthly revenue <span class="text-brand-magenta" aria-hidden="true">*</span>
          </legend>

          <div class="grid gap-2.5 sm:grid-cols-2">
            <label class="group relative flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3.5 text-left transition-colors hover:border-brand-purple/50 hover:bg-purple-50/40 has-[:checked]:border-brand-purple has-[:checked]:bg-purple-50 has-[:checked]:ring-1 has-[:checked]:ring-brand-purple">
              <input type="radio" name="revenue" value="small" required
                     class="size-4 shrink-0 accent-[var(--color-brand-purple)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-purple">
              <span class="text-[15px] font-medium text-brand-midnight">0 to 5k Per Month</span>
            </label>
            <label class="group relative flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3.5 text-left transition-colors hover:border-brand-purple/50 hover:bg-purple-50/40 has-[:checked]:border-brand-purple has-[:checked]:bg-purple-50 has-[:checked]:ring-1 has-[:checked]:ring-brand-purple">
              <input type="radio" name="revenue" value="small"
                     class="size-4 shrink-0 accent-[var(--color-brand-purple)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-purple">
              <span class="text-[15px] font-medium text-brand-midnight">5k to 10k Per Month</span>
            </label>
            <label class="group relative flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3.5 text-left transition-colors hover:border-brand-purple/50 hover:bg-purple-50/40 has-[:checked]:border-brand-purple has-[:checked]:bg-purple-50 has-[:checked]:ring-1 has-[:checked]:ring-brand-purple">
              <input type="radio" name="revenue" value="mid"
                     class="size-4 shrink-0 accent-[var(--color-brand-purple)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-purple">
              <span class="text-[15px] font-medium text-brand-midnight">10k to 50k Per Month</span>
            </label>
            <label class="group relative flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3.5 text-left transition-colors hover:border-brand-purple/50 hover:bg-purple-50/40 has-[:checked]:border-brand-purple has-[:checked]:bg-purple-50 has-[:checked]:ring-1 has-[:checked]:ring-brand-purple">
              <input type="radio" name="revenue" value="enterprise"
                     class="size-4 shrink-0 accent-[var(--color-brand-purple)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-purple">
              <span class="text-[15px] font-medium text-brand-midnight">50k to 100k Per Month</span>
            </label>
            <label class="group relative flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3.5 text-left transition-colors hover:border-brand-purple/50 hover:bg-purple-50/40 has-[:checked]:border-brand-purple has-[:checked]:bg-purple-50 has-[:checked]:ring-1 has-[:checked]:ring-brand-purple">
              <input type="radio" name="revenue" value="enterprise"
                     class="size-4 shrink-0 accent-[var(--color-brand-purple)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-purple">
              <span class="text-[15px] font-medium text-brand-midnight">100k+ Per Month</span>
            </label>
            <label class="group relative flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3.5 text-left transition-colors hover:border-brand-purple/50 hover:bg-purple-50/40 has-[:checked]:border-brand-purple has-[:checked]:bg-purple-50 has-[:checked]:ring-1 has-[:checked]:ring-brand-purple">
              <input type="radio" name="revenue" value="small"
                     class="size-4 shrink-0 accent-[var(--color-brand-purple)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-purple">
              <span class="text-[15px] font-medium text-brand-midnight">Prefer not to say</span>
            </label>
          </div>
        </fieldset>

        <p id="rl-error" class="mt-4 text-sm font-medium text-brand-magenta" hidden>
          Pick a revenue range to see available times.
        </p>

        <button type="submit"
                class="mt-8 w-full rounded-xl bg-brand-purple px-6 py-4 text-base font-semibold text-white transition-colors hover:bg-brand-purple-deep focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-purple">
          See available times
        </button>
      </form>

      {{-- STEP 2 — the Calendly embed, revealed by the script below --}}
      <div id="rl-booking" class="rounded-2xl border border-slate-200 bg-white p-3 shadow-xl shadow-brand-midnight/5 sm:p-5" hidden>
        <div class="mb-3 flex flex-wrap items-center justify-between gap-x-5 gap-y-2 px-1">
          <button type="button" id="rl-back"
                  class="text-sm font-semibold text-brand-purple underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-purple">
            &larr; Change revenue range
          </button>
          <a id="rl-newtab" href="#" target="_blank" rel="noopener"
             class="text-sm font-medium text-slate-500 underline-offset-4 hover:text-brand-purple hover:underline">
            Open in a new tab
          </a>
        </div>
        <div id="rl-frame-wrap" class="rl-frame-wrap"></div>
      </div>

    </div>
  </div>

  @verbatim
  <style>
    /* Kept verbatim from production. Calendly runs a two-column layout above ~1000px and
       stacks below it, so the frame height has to track that or the calendar is cropped. */
    #rl-router .rl-frame-wrap { width: 100%; }
    #rl-router .rl-frame-wrap iframe { display: block; width: 100%; height: 700px; border: 0; }
    @media (max-width: 1100px) { #rl-router .rl-frame-wrap iframe { height: 1200px; } }
    @media (max-width: 700px)  { #rl-router .rl-frame-wrap iframe { height: 1450px; } }
  </style>

  <script>
  
  (function () {
  
    // Revenue tier -> Calendly event link
  
    var LINKS = {
  
      small:      'https://calendly.com/d/d2jq-z6m-5yt/kickoff-workforce-planning-meeting-t0',
  
      mid:        'https://calendly.com/d/dvxh-c8m-p4k/kickoff-workforce-planning-meeting-t10',
  
      enterprise: 'https://calendly.com/d/d2jp-krr-bng/kickoff-workforce-planning-meeting-t50'
  
    };
  
  
  
    var form    = document.getElementById('rl-form');
  
    var booking = document.getElementById('rl-booking');
  
    var wrap    = document.getElementById('rl-frame-wrap');
  
    var errorEl = document.getElementById('rl-error');
  
    var backBtn = document.getElementById('rl-back');
  
    var newTab  = document.getElementById('rl-newtab');
  
  
  
    function embedUrl(base) {
  
      return base
  
        + '?embed_domain=' + encodeURIComponent(window.location.hostname)
  
        + '&embed_type=Inline'
  
        + '&hide_gdpr_banner=1';
  
    }
  
  
  
    form.addEventListener('submit', function (e) {
  
      e.preventDefault();
  
  
  
      var picked = form.querySelector('input[name="revenue"]:checked');
  
      if (!picked) { errorEl.hidden = false; return; }
  
      errorEl.hidden = true;
  
  
  
      var base = LINKS[picked.value];
  
  
  
      var frame = document.createElement('iframe');
  
      frame.src = embedUrl(base);
  
      frame.title = 'Book a workforce planning call';
  
      frame.setAttribute('frameborder', '0');
  
      frame.allow = 'camera; microphone; fullscreen; payment';
  
  
  
      wrap.innerHTML = '';
  
      wrap.appendChild(frame);
  
  
  
      newTab.href = base;
  
  
  
      form.hidden = true;
  
      booking.hidden = false;
  
      booking.scrollIntoView({ behavior: 'smooth', block: 'start' });
  
    });
  
  
  
    backBtn.addEventListener('click', function () {
  
      wrap.innerHTML = '';
  
      booking.hidden = true;
  
      form.hidden = false;
  
      form.scrollIntoView({ behavior: 'smooth', block: 'start' });
  
    });
  
  })();
  
  </script>
  @endverbatim
@endsection
