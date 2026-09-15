{{-- Faithful port of production's blog index (the rl_blog_index_* Elementor widgets),
     shared by the posts page and the category archives, which use the same design on
     production — only the header text and the per-page count differ.

     Expects: $indexTitle, $indexDescription, $activeCategory (a WP_Term or null). --}}
@php
  use App\Support\ResponsiveImage;

  // The card art is `width:100%; height:280px; object-fit:cover` inside a grid that is
  // one column below 600px, two to 1024px and three above it, capped at 1320px with a
  // 30px gap — so the widest a card image is ever painted is (1320 - 60) / 3 = 420px.
  // Without this the browser assumed 100vw and took the 768px PNG every time.
  $cardSizes = '(min-width: 1024px) 420px, (min-width: 600px) 50vw, calc(100vw - 40px)';
  $cardIndex = 0;

  // Production's filter bar is a curated list, not every category: case-studies and
  // real-estate are deliberately absent from it.
  $filters = [
    ['label' => __('All Articles', 'remote-leverage'), 'slug' => null],
    ['label' => __('News', 'remote-leverage'), 'slug' => 'news'],
    ['label' => __('Salary Guides', 'remote-leverage'), 'slug' => 'salary-guides'],
    ['label' => __('Outsourcing', 'remote-leverage'), 'slug' => 'outsourcing'],
    ['label' => __('Business Growth', 'remote-leverage'), 'slug' => 'business-growth'],
    ['label' => __('Live Sessions ↗', 'remote-leverage'), 'slug' => 'live-sessions'],
  ];

  $activeSlug = $activeCategory?->slug;
@endphp

<div class="rl-blog-index-header">
  <div class="rl-index-header-inner">
    <div class="rl-index-label">{{ __('Remote Leverage Blog', 'remote-leverage') }}</div>
    <h1 class="rl-index-title">{{ $indexTitle }}</h1>
    @if ($indexDescription)
      <p class="rl-index-description">{!! $indexDescription !!}</p>
    @endif
  </div>
</div>

<div class="rl-blog-index-filter-bar">
  <div class="rl-filter-inner">
    <ul class="rl-filter-list">
      @foreach ($filters as $filter)
        @php
          $term = $filter['slug'] ? get_category_by_slug($filter['slug']) : null;
          $url = $filter['slug'] ? ($term ? get_category_link($term) : home_url('/blog/category/'.$filter['slug'])) : home_url('/blog/');
          // Production marks nothing active on category pages even though it styles
          // for it; the current category is highlighted here instead.
          $isActive = $filter['slug'] === $activeSlug;
        @endphp
        <li class="rl-filter-item">
          <a href="{{ $url }}" class="rl-filter-link{{ $isActive ? ' rl-active-filter' : '' }}">{{ $filter['label'] }}</a>
        </li>
      @endforeach
    </ul>
  </div>
</div>

<div class="rl-blog-index-grid-wrapper">
  @if (have_posts())
    <div class="rl-posts-grid">
      @while (have_posts())
        @php
          the_post();
          $cardId = get_the_ID();
          $cardThumbId = get_post_thumbnail_id($cardId) ?: null;
          $cardThumb = $cardThumbId ? ResponsiveImage::attributes($cardThumbId, 'medium_large', $cardSizes) : '';
          $cardCats = get_the_category();
          $cardIndex++;
        @endphp

        <div class="rl-post-card">
          <a href="{{ get_permalink() }}" class="rl-post-image-link">
            @if ($cardThumb)
              {{-- The first card is the LCP element on `/blog/`; lazy-loading it deferred the
                   only image that is above the fold at every breakpoint. --}}
              <img {!! $cardThumb !!} alt="{{ the_title_attribute(['echo' => false]) }}" class="rl-post-image"
                   @if ($cardIndex === 1) fetchpriority="high" decoding="async" @else loading="lazy" decoding="async" @endif />
            @else
              <span class="rl-post-image-placeholder"></span>
            @endif
          </a>

          <div class="rl-post-meta">
            @if ($primary = ($cardCats[0] ?? null))
              <span class="rl-post-category">{{ strtoupper($primary->name) }}</span>
            @endif
            <span class="rl-post-date">{{ get_the_date('M j, Y') }}</span>
          </div>

          <h3 class="rl-post-title">
            <a href="{{ get_permalink() }}">{!! get_the_title() !!}</a>
          </h3>
        </div>
      @endwhile
    </div>

    @php
      $links = paginate_links([
        'type' => 'list',
        'prev_text' => '&laquo;',
        'next_text' => '&raquo;',
      ]);
    @endphp

    @if ($links)
      <nav class="rl-pagination" aria-label="{{ __('Blog pagination', 'remote-leverage') }}">
        {!! $links !!}
      </nav>
    @endif
  @else
    <p>{{ __('No articles found.', 'remote-leverage') }}</p>
  @endif
</div>
