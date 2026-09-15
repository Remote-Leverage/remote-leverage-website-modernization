<!doctype html>
<html @php(language_attributes()) class="h-full scroll-smooth">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#8A2BE2">
    <meta name="description" content="Hire pre-vetted bilingual virtual assistants and remote professionals across Latin America and Europe. No contracts, zero salary markup, and a 12-month replacement guarantee.">

    <link rel="icon" type="image/svg+xml" href="{{ Vite::asset('resources/images/logo-icon-black.svg') }}">

    <script type="application/ld+json">
    {
      "@@context": "https://schema.org",
      "@@graph": [
        {
          "@type": "Organization",
          "@id": "{{ home_url('/#organization') }}",
          "name": "Remote Leverage",
          "url": "{{ home_url('/') }}",
          "logo": {
            "@type": "ImageObject",
            "url": "{{ Vite::asset('resources/images/logo.svg') }}"
          },
          "description": "Remote Leverage connects growing businesses with exceptional pre-vetted bilingual remote talent."
        },
        {
          "@type": "WebSite",
          "@id": "{{ home_url('/#website') }}",
          "url": "{{ home_url('/') }}",
          "name": "Remote Leverage",
          "publisher": {
            "@id": "{{ home_url('/#organization') }}"
          }
        }
      ]
    }
    </script>

    @php(do_action('get_header'))
    @php(wp_head())
    @livewireStyles

    <script>
      window.APP_ENV = '{{ env('APP_ENV', 'production') }}';
      @if (config('sentry.dsn'))
        window.SENTRY_DSN = '{{ config('sentry.dsn') }}';
      @endif
      @if (config('services.posthog.api_key'))
        window.POSTHOG_API_KEY = '{{ config('services.posthog.api_key') }}';
        window.POSTHOG_HOST = '{{ config('services.posthog.host', 'https://us.i.posthog.com') }}';
      @endif
    </script>

    {{-- Font preload. `font-display: swap` means an un-preloaded face paints a fallback
         first and reflows when the real font arrives; the faces below are only discovered
         after app.css has downloaded and parsed, so that reflow is guaranteed on a cold visit.

         Exactly these two, and no more. Measured on 2026-09-15 across `/`, `/hire-va-4/`,
         `/case-study/`, `/blog/`, `/about-us/` and a blog article: every one of them requests
         these two faces and only these two. The other three in app.css are deliberately left
         alone —
           - `inter-display-latin-ext.woff2` (125 KB) and `inter-latin-ext-wght-normal.woff2`
             cover U+0100+ and were not requested by any page measured;
           - `inter-latin-wght-italic.woff2` is requested on `/about-us/` only, and nothing
             above the fold there is italic.
         Preloading those would push ~125 KB of never-parsed bytes onto the critical path of
         every visit, which is a bigger regression than the swap this fixes.

         Resolved through the Vite manifest rather than hardcoded: these filenames are
         content-hashed build output and change on every font rebuild. A hardcoded hash would
         silently 404 and, worse, still look correct in the markup. --}}

    @vite(['resources/css/app.css', 'resources/js/app.js'])
  </head>

  <body @php(body_class('min-h-full flex flex-col bg-bg-light text-text-body font-sans antialiased selection:bg-brand-purple selection:text-white'))>
    @php(wp_body_open())

    <div id="app" class="relative flex min-h-screen flex-col">
      <a class="sr-only focus:not-sr-only focus:fixed focus:top-4 focus:left-4 focus:z-50 focus:rounded-pill focus:bg-brand-purple focus:px-6 focus:py-3 focus:font-semibold focus:text-white focus:shadow-xl focus:outline-none" href="#main">
        {{ __('Skip to content', 'remote-leverage') }}
      </a>

      {{-- The hire-va landing pages carry no site nav on production — a conversion page
           deliberately offers no way out — so they get the CTA-only header instead. --}}
      @if (get_page_template_slug() !== 'template-landing.blade.php' && ! is_page_template('template-landing.blade.php'))
        @includeWhen(\App\Support\PageChrome::usesCtaOnlyHeader(), 'sections.header-cta')
        @includeUnless(\App\Support\PageChrome::usesCtaOnlyHeader(), 'sections.header')
      @endif

      <main id="main" class="main flex-1 w-full">
        {{-- The case-study/talent/reviews tab bar sits between the header and the page's
             own hero on production. Rendered here rather than from each template so the
             archive, the single case-study view and /reviews/ share one copy of it;
             App\Support\CaseStudySubnav decides whether this request is one of its
             surfaces and which tab is current. --}}
        @include('partials.case-study-subnav')

        @yield('content')
      </main>

      @hasSection('sidebar')
        <aside class="sidebar w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
          @yield('sidebar')
        </aside>
      @endif

      @include('sections.footer')
    </div>

    @php(do_action('get_footer'))
    @php(wp_footer())
    {{-- Cloned into the document when a Livewire/Alpine island is near the viewport
         so livewire.min.js (and Alpine) stay off the TTI critical path. --}}
    <template id="rl-livewire-scripts">
      @livewireScripts
    </template>
  </body>
</html>
