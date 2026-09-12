{{--
  Template Name: Legal Document
--}}

@extends('layouts.app')

@section('content')
  @while(have_posts())
    @php
      the_post();

      $document = new \App\Support\DocumentOutline(apply_filters('the_content', get_the_content()));
      $sections = $document->sections();

      $lastUpdated = get_post_meta(get_the_ID(), 'legal_last_updated', true)
          ?: get_the_modified_date('F j, Y');
    @endphp

    {{-- Hero --}}
    <div class="w-full bg-[linear-gradient(65deg,#270028_0%,#4E1450_100%)] pt-14 pb-16 sm:pt-16 sm:pb-20 print:bg-none print:pb-8">
      <div class="w-full max-w-[1380px] mx-auto px-4 sm:px-6 lg:px-8">
        <span class="inline-flex items-center rounded-pill border border-white/25 bg-white/10 px-4 py-1.5 text-xs font-bold uppercase tracking-[0.1em] text-white print:hidden">
          {{ __('Legal', 'remote-leverage') }}
        </span>

        <h1 class="font-display text-4xl sm:text-5xl lg:text-[56px] font-bold text-white tracking-[-0.02em] leading-tight mt-6 mb-5 print:text-black">
          {{ get_the_title() }}
        </h1>

        <div class="flex flex-wrap items-center gap-x-6 gap-y-3">
          <p class="max-w-[620px] text-base sm:text-lg text-white/70 leading-relaxed print:text-black">
            {{ __('Please read this document carefully. It governs your use of Remote Leverage and the services we provide.', 'remote-leverage') }}
          </p>
        </div>

        @if ($lastUpdated)
          <div class="mt-7 inline-flex items-center gap-2.5 rounded-pill bg-white/10 border border-white/20 px-4 py-2 text-sm text-white/85 print:text-black">
            <svg class="h-4 w-4 shrink-0 text-white/70" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 2m6-2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span>{{ __('Last updated', 'remote-leverage') }} <time datetime="{{ get_the_modified_date('c') }}">{{ $lastUpdated }}</time></span>
          </div>
        @endif
      </div>
    </div>

    {{-- Body --}}
    <div class="w-full bg-bg-light pb-20 sm:pb-28 print:bg-white print:pb-0">
      <div class="w-full max-w-[1380px] mx-auto px-4 sm:px-6 lg:px-8">
        <div class="lg:grid lg:grid-cols-[300px_minmax(0,1fr)] lg:gap-10 lg:items-start">

          {{-- Sidebar navigation --}}
          @if (count($sections) > 1)
            <aside class="hidden lg:block lg:sticky lg:top-28 -mt-10 print:hidden"
                   x-data="rlDocumentToc()"
                   x-init="observe()">
              <nav class="rounded-card border border-slate-200/80 bg-white p-6 shadow-sm max-h-[calc(100vh-9rem)] overflow-y-auto"
                   aria-label="{{ __('On this page', 'remote-leverage') }}">
                <h2 class="font-display text-sm font-bold uppercase tracking-[0.08em] text-brand-navy mb-4 pb-4 border-b border-slate-100">
                  {{ __('On this page', 'remote-leverage') }}
                </h2>

                <ul class="space-y-1">
                  @foreach ($sections as $section)
                    <li>
                      <a href="#{{ $section['id'] }}"
                         data-toc-link="{{ $section['id'] }}"
                         :class="active === '{{ $section['id'] }}'
                            ? 'border-brand-purple bg-lavender-surface text-brand-purple font-semibold'
                            : 'border-transparent text-black/60 hover:text-brand-purple hover:bg-slate-50'"
                         class="block border-l-2 py-2 pl-3 pr-2 text-sm leading-snug transition-colors">
                        {{ $section['text'] }}
                      </a>
                    </li>
                  @endforeach
                </ul>
              </nav>
            </aside>
          @else
            <div class="hidden lg:block" aria-hidden="true"></div>
          @endif

          {{-- Document --}}
          <article class="-mt-10 rounded-card border border-slate-200/80 bg-white px-6 py-10 shadow-sm sm:px-10 sm:py-12 lg:px-14 print:border-0 print:shadow-none print:p-0 print:mt-0">
            {{-- Mobile navigation: the sidebar is desktop-only, but these documents
                 are long enough that phone readers need a way to jump too. --}}
            @if (count($sections) > 1)
              <details class="group mb-10 rounded-card border border-slate-200/80 bg-bg-light lg:hidden print:hidden">
                <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-5 py-4 font-display text-sm font-bold uppercase tracking-[0.08em] text-brand-navy">
                  {{ __('On this page', 'remote-leverage') }}
                  <svg class="h-4 w-4 shrink-0 text-brand-purple transition-transform group-open:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                  </svg>
                </summary>

                <ul class="border-t border-slate-200/80 px-5 py-3">
                  @foreach ($sections as $section)
                    <li>
                      <a href="#{{ $section['id'] }}" class="block py-2 text-sm leading-snug text-black/60 hover:text-brand-purple">
                        {{ $section['text'] }}
                      </a>
                    </li>
                  @endforeach
                </ul>
              </details>
            @endif

            <div class="rl-legal-doc max-w-[760px]">
              {!! $document->content() !!}
            </div>

            <div class="mt-14 border-t border-slate-200/80 pt-8 print:hidden">
              <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-[15px] text-black/60">
                  {{ __('Questions about this document?', 'remote-leverage') }}
                  <a href="mailto:admin@remoteleverage.com" class="font-semibold text-brand-purple underline decoration-brand-purple/40 underline-offset-2 hover:decoration-brand-purple">admin@remoteleverage.com</a>
                </p>

                <a href="#main" class="inline-flex shrink-0 items-center gap-2 text-sm font-semibold text-black/50 transition-colors hover:text-brand-purple">
                  <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7" />
                  </svg>
                  {{ __('Back to top', 'remote-leverage') }}
                </a>
              </div>
            </div>
          </article>
        </div>
      </div>
    </div>
  @endwhile
@endsection
