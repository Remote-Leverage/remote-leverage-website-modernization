@extends('layouts.app')

@section('content')
  {{-- Archive Hero Header --}}
  <section class="relative overflow-hidden bg-gradient-to-b from-brand-midnight via-brand-hero to-brand-midnight text-white pt-24 pb-16 sm:pt-28 sm:pb-20">
    <div class="pointer-events-none absolute -top-32 right-1/4 h-80 w-80 rounded-full bg-brand-purple/20 blur-3xl"></div>
    <div class="pointer-events-none absolute -bottom-16 left-10 h-72 w-72 rounded-full bg-brand-magenta/15 blur-3xl"></div>

    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center space-y-4">
      {{-- Breadcrumb --}}
      <nav class="flex items-center justify-center gap-2 text-xs text-slate-300 font-medium">
        <a href="{{ home_url('/') }}" class="hover:text-white transition">Home</a>
        <span>/</span>
        <a href="{{ home_url('/blog') }}" class="hover:text-white transition">Resources</a>
        <span>/</span>
        <span class="text-brand-magenta font-semibold">{{ get_the_archive_title() }}</span>
      </nav>

      {{-- Archive Title --}}
      <h1 class="text-3xl sm:text-5xl font-black font-display tracking-tight text-white max-w-3xl mx-auto leading-tight">
        {!! get_the_archive_title() !!}
      </h1>

      @if (get_the_archive_description())
        <div class="text-sm sm:text-base text-slate-300 max-w-2xl mx-auto leading-relaxed">
          {!! get_the_archive_description() !!}
        </div>
      @endif
    </div>
  </section>

  {{-- Archive Posts Grid --}}
  <section class="py-16 bg-bg-light min-h-[500px]">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      @if (! have_posts())
        <div class="text-center py-16 bg-surface-white rounded-card-lg border border-slate-200/80 p-8 max-w-md mx-auto">
          <div class="w-12 h-12 mx-auto rounded-full bg-slate-100 flex items-center justify-center text-text-muted mb-3">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
          </div>
          <h4 class="text-base font-bold text-text-body">No articles found</h4>
          <p class="text-xs text-text-muted mt-1">
            There are currently no published articles in this archive. Check back soon or browse our full guide index.
          </p>
          <div class="mt-4">
            <a href="{{ home_url('/blog') }}" class="text-xs font-bold text-brand-purple hover:underline">
              &larr; View all resources
            </a>
          </div>
        </div>
      @else
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
          @while (have_posts())
            @php
              the_post();

              $postId = get_the_ID();
              $thumb = get_the_post_thumbnail_url($postId, 'medium_large');
              $cats = get_the_category();
              $primaryCat = ! empty($cats) ? $cats[0]->name : 'Resource';
            @endphp

            <article class="group bg-surface-white rounded-card-lg border border-slate-200/80 hover:border-brand-purple/40 shadow-card hover:shadow-xl transition-all duration-300 flex flex-col justify-between overflow-hidden">
              <div>
                {{-- Card Image / Thumbnail --}}
                <div class="relative h-48 w-full bg-slate-100 overflow-hidden">
                  @if ($thumb)
                    <img
                      src="{{ $thumb }}"
                      alt="{{ get_the_title() }}"
                      class="h-full w-full object-cover group-hover:scale-105 transition-transform duration-300"
                      loading="lazy"
                    />
                  @else
                    <div class="h-full w-full flex items-center justify-center bg-gradient-to-br from-brand-midnight/10 to-brand-purple/10 text-brand-purple font-display font-black text-2xl">
                      Remote Leverage
                    </div>
                  @endif

                  <div class="absolute top-3 left-3">
                    <span class="px-2.5 py-1 rounded-pill bg-brand-midnight/80 backdrop-blur-sm text-white text-2xs font-bold uppercase tracking-wider">
                      {{ $primaryCat }}
                    </span>
                  </div>
                </div>

                {{-- Content Body --}}
                <div class="p-6 space-y-3">
                  <div class="flex items-center gap-2 text-2xs text-text-muted">
                    <time datetime="{{ get_the_date('c') }}">{{ get_the_date('M j, Y') }}</time>
                    <span>&bull;</span>
                    <span>5 min read</span>
                  </div>

                  <h3 class="text-lg font-bold font-display text-brand-hero group-hover:text-brand-purple transition leading-snug line-clamp-2">
                    <a href="{{ get_permalink() }}">
                      {!! get_the_title() !!}
                    </a>
                  </h3>

                  <p class="text-xs text-text-muted line-clamp-3 leading-relaxed">
                    {!! get_the_excerpt() ?: wp_trim_words(get_the_content(), 20) !!}
                  </p>
                </div>
              </div>

              {{-- Footer Link --}}
              <div class="p-6 pt-0">
                <a
                  href="{{ get_permalink() }}"
                  class="inline-flex items-center gap-1.5 text-xs font-bold text-brand-purple group-hover:text-brand-purple-deep transition"
                >
                  <span>Read Article</span>
                  <svg class="w-3.5 h-3.5 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                  </svg>
                </a>
              </div>
            </article>
          @endwhile
        </div>

        {{-- Pagination --}}
        <div class="mt-12 flex justify-center">
          {!! get_the_posts_navigation([
            'prev_text' => '&larr; Previous Articles',
            'next_text' => 'Next Articles &rarr;',
            'screen_reader_text' => 'Posts navigation',
          ]) !!}
        </div>
      @endif
    </div>
  </section>
@endsection
