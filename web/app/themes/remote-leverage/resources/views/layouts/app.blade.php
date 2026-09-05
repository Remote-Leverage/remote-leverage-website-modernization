<!doctype html>
<html @php(language_attributes()) class="h-full scroll-smooth">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

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
