{{-- Faithful port of production's single-article layout (the rl_article_* Elementor
     widgets). Class names are production's so the styles in blog.css, which were
     ported from its stylesheet, apply unchanged.

     What lives where: the Quick Summary and the inline "Also read" box are per-post
     editorial content and come through post_content; the FAQ is per-post but always
     terminal, so it is post meta rendered here; the header, TOC, talent carousel,
     booking wizard and author bio are furniture and belong to this template. --}}
@php
  use App\Support\DocumentOutline;
  use App\Support\ReadingTime;

  $postId = get_the_ID();

  $document = new DocumentOutline(apply_filters('the_content', get_the_content()));
  $sections = $document->sections();
  $body = $document->content();

  // Production drops the talent carousel between the intro and the first H2, so the
  // body is split there rather than rendered as one run. Matching on the id is what
  // keeps the summary box's own "Quick Summary" heading from being mistaken for it.
  $split = preg_match('/<h2[^>]*\sid="/i', $body, $m, PREG_OFFSET_CAPTURE)
    ? $m[0][1]
    : strlen($body);
  $intro = substr($body, 0, $split);
  $rest = substr($body, $split);

  $categories = get_the_category();
  $faqs = get_post_meta($postId, 'rl_faqs', true) ?: [];
  $thumbnail = get_the_post_thumbnail_url($postId, 'full');

  $authorId = (int) get_the_author_meta('ID');
  $authorAvatar = get_the_author_meta('rl_avatar', $authorId);
  $authorBio = get_the_author_meta('description', $authorId);
@endphp

<article @php(post_class('rl-blog-article-template h-entry'))>

  {{-- ── Header ─────────────────────────────────────────────────────────── --}}
  <div class="rl-article-header-wrapper">
    <div class="rl-header-inner">
      @if ($thumbnail)
        <div class="rl-header-left">
          <img class="rl-header-featured-image" src="{{ $thumbnail }}" alt="{{ the_title_attribute(['echo' => false]) }}" fetchpriority="high" />
        </div>
      @endif

      <div class="rl-header-right">
        <div class="rl-header-breadcrumbs">
          <a href="{{ home_url('/blog/') }}">{{ __('Blog', 'remote-leverage') }} &gt;
            @foreach ($categories as $category)<span>{{ $category->name }}</span>@if (! $loop->last), @endif @endforeach
          </a>
        </div>

        <h1 class="rl-header-title p-name">{!! $title !!}</h1>

        <div class="rl-header-meta">
          <div class="rl-header-meta-item rl-header-author">
            @if ($authorAvatar)
              <img class="rl-author-avatar" src="{{ $authorAvatar }}" alt="{{ get_the_author() }}" />
            @endif
            <div class="rl-meta-details">
              <span class="rl-meta-label">{{ __('Written by:', 'remote-leverage') }}</span>
              <span class="rl-meta-value p-author">{{ get_the_author() }}</span>
            </div>
          </div>

          <div class="rl-header-meta-item rl-header-published">
            <div class="rl-meta-details">
              <span class="rl-meta-label">{{ __('Published:', 'remote-leverage') }}</span>
              <span class="rl-meta-value">
                <time class="dt-published" datetime="{{ get_post_time('c', true) }}">{{ get_the_date('F j, Y') }}</time>
              </span>
            </div>
          </div>

          <div class="rl-header-meta-item rl-header-updated">
            <div class="rl-meta-details">
              <span class="rl-meta-label">{{ __('Updated:', 'remote-leverage') }}</span>
              <span class="rl-meta-value">
                <time datetime="{{ get_the_modified_date('c') }}">{{ get_the_modified_date('F j, Y') }}</time>
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- ── Body ───────────────────────────────────────────────────────────── --}}
  <div class="rl-article-layout">
    <aside class="rl-article-sidebar rl-article-sidebar--toc">
      @if (count($sections) > 1)
        <div class="rl-table-of-content" x-data="rlDocumentToc()" x-init="observe()">
          <div class="rl-toc-header">
            <span class="rl-toc-read-time">{{ ReadingTime::label($postId) }}</span>
            <button type="button"
                    class="rl-toc-copy-link"
                    x-data="{ copied: false }"
                    @click="navigator.clipboard?.writeText(window.location.href).then(() => { copied = true; setTimeout(() => copied = false, 2000) })"
                    x-text="copied ? '{{ __('Copied!', 'remote-leverage') }}' : '{{ __('Copy link', 'remote-leverage') }}'">{{ __('Copy link', 'remote-leverage') }}</button>
          </div>

          <ul class="rl-toc-list">
            @foreach ($sections as $section)
              <li class="rl-toc-item">
                <a href="#{{ $section['id'] }}" data-toc-link="{{ $section['id'] }}">{{ $section['text'] }}</a>
              </li>
            @endforeach
          </ul>
        </div>
      @endif

    </aside>

    <div class="rl-article-main">
      <div class="rl-article-body e-content">
        {!! $intro !!}
      </div>

      @include('partials.talent-carousel')

      @if (trim($rest) !== '')
        <div class="rl-article-body e-content">
          {!! $rest !!}
        </div>
      @endif

      @if ($pagination())
        <nav class="page-nav" aria-label="{{ __('Page', 'remote-leverage') }}">{!! $pagination !!}</nav>
      @endif

      @if ($faqs)
        <div class="rl-faq-section layout-default rl-article-faq" x-data="{ open: null }">
          <h3 class="rl-faq-headline">{{ __('FAQs', 'remote-leverage') }}</h3>

          <div class="rl-accordion">
            @foreach ($faqs as $i => $faq)
              <div class="rl-accordion-item">
                <button type="button"
                        class="rl-accordion-header"
                        :aria-expanded="open === {{ $i }} ? 'true' : 'false'"
                        aria-expanded="false"
                        @click="open = open === {{ $i }} ? null : {{ $i }}">
                  <span class="rl-accordion-question">{{ $faq['question'] }}</span>
                  <span class="rl-icon-toggle">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                      <path d="M6 9L12 15L18 9" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                  </span>
                </button>

                {{-- Visibility is driven by CSS off the header's aria-expanded, as on
                     production. The inner wrapper exists so the open/close can animate:
                     the grid row goes 0fr -> 1fr, which transitions to the answer's
                     natural height without needing a hardcoded max-height. --}}
                <div class="rl-accordion-content">
                  <div class="rl-accordion-answer">
                    {!! $faq['answer'] !!}
                  </div>
                </div>
              </div>
            @endforeach
          </div>
        </div>
      @endif

      @if ($authorBio)
        <div class="rl-author-bio-card">
          @if ($authorAvatar)
            <div class="rl-author-avatar">
              <img src="{{ $authorAvatar }}" alt="{{ get_the_author() }}" loading="lazy" />
            </div>
          @endif

          <div class="rl-author-info">
            <h4 class="rl-author-name">{{ get_the_author() }}</h4>
            <p class="rl-author-role">{{ get_the_author_meta('rl_role', $authorId) }}</p>
            <p class="rl-author-text">{{ $authorBio }}</p>
          </div>
        </div>
      @endif
    </div>

    {{-- Second sidebar: a narrow pink shell around the theme's booking wizard in its
         chrome-less skin — no session host card, no step indicator, compact fields. --}}
    <aside class="rl-article-sidebar rl-article-sidebar--form">
      <div class="rl-lead-form-wrapper">
        <p class="rl-lead-form-headline">
          {{ __('Hire direct with', 'remote-leverage') }}
          <span>{{ __('Remote Leverage', 'remote-leverage') }}</span>
        </p>

        <livewire:booking.multistep-booking-wizard
          skin="naked"
          :hide-profile-header="true"
          :hide-progress-bar="true"
          :compact-fields="true"
          button-text="Book a Consultation"
          :lazy="false" />
      </div>
    </aside>
  </div>
</article>
