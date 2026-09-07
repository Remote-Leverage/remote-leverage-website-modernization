<!doctype html>
<html @php(language_attributes()) class="h-full scroll-smooth">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#8A2BE2">
    <meta name="description" content="Hire pre-vetted bilingual virtual assistants and remote professionals across Latin America and Europe. No contracts, zero salary markup, and a 12-month replacement guarantee.">

    <link rel="icon" type="image/svg+xml" href="{{ Vite::asset('resources/images/logo-icon-black.svg') }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&family=Inter+Display:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&family=League+Spartan:wght@600;700;800&family=Poppins:ital,wght@0,400;0,500;0,600;0,700;1,400&display=swap">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&family=Inter+Display:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&family=League+Spartan:wght@600;700;800&family=Poppins:ital,wght@0,400;0,500;0,600;0,700;1,400&display=swap" media="print" onload="this.media='all'">
    <noscript>
      <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&family=Inter+Display:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&family=League+Spartan:wght@600;700;800&family=Poppins:ital,wght@0,400;0,500;0,600;0,700;1,400&display=swap">
    </noscript>

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

    @vite(['resources/css/app.css', 'resources/js/app.js'])
  </head>

  <body @php(body_class('min-h-full flex flex-col bg-bg-light text-text-body font-sans antialiased selection:bg-brand-purple selection:text-white'))>
    @php(wp_body_open())

    <div id="app" class="flex min-h-screen flex-col">
      <a class="sr-only focus:not-sr-only focus:fixed focus:top-4 focus:left-4 focus:z-50 focus:rounded-pill focus:bg-brand-purple focus:px-6 focus:py-3 focus:font-semibold focus:text-white focus:shadow-xl focus:outline-none" href="#main">
        {{ __('Skip to content', 'remote-leverage') }}
      </a>

      @include('sections.header')

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
    @livewireScripts
  </body>
</html>
