<!doctype html>
<html @php(language_attributes()) class="h-full scroll-smooth">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#8A2BE2">
    <meta name="description" content="Hire pre-vetted bilingual virtual assistants and remote professionals across Latin America and Europe. No contracts, zero salary markup, and a 12-month replacement guarantee.">

    <link rel="icon" type="image/svg+xml" href="{{ Vite::asset('resources/images/logo-icon-black.svg') }}">

    {{-- Latin Inter variable (headings + body). Hashed by Vite so CloudFront can cache it. --}}
    <link rel="preload" as="font" type="font/woff2" crossorigin href="{{ Vite::asset('resources/fonts/inter-latin-wght-normal.woff2') }}">

    @if (is_front_page())
        {{-- Match .rl-hero-group::before. Preload the WebP used as the hero background
             so Chrome does not fetch a leftover PNG from an older preload. --}}
        <link rel="preload" as="image" href="/app/themes/remote-leverage/public/images/home/Map.webp" fetchpriority="high">
    @endif

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

    @vite(['resources/css/app.css', 'resources/js/app.js'])
  </head>

  <body @php(body_class('min-h-full flex flex-col bg-bg-light text-text-body font-sans antialiased selection:bg-brand-purple selection:text-white'))>
    @php(wp_body_open())

    <div id="app" class="flex min-h-screen flex-col">
      <a class="sr-only focus:not-sr-only focus:fixed focus:top-4 focus:left-4 focus:z-50 focus:rounded-pill focus:bg-brand-purple focus:px-6 focus:py-3 focus:font-semibold focus:text-white focus:shadow-xl focus:outline-none" href="#main">
        {{ __('Skip to content', 'remote-leverage') }}
      </a>

      @if (get_page_template_slug() !== 'template-landing.blade.php' && ! is_page_template('template-landing.blade.php'))
        @include('sections.header')
      @endif

      <main id="main" class="main flex-1 w-full">
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
