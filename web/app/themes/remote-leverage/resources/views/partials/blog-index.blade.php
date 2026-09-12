{{-- Faithful port of production's blog index (the rl_blog_index_* Elementor widgets),
     shared by the posts page and the category archives, which use the same design on
     production — only the header text and the per-page count differ.

     Expects: $indexTitle, $indexDescription, $activeCategory (a WP_Term or null). --}}
@php
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
          $cardThumb = get_the_post_thumbnail_url($cardId, 'medium_large');
          $cardCats = get_the_category();
        @endphp

        <div class="rl-post-card">
          <a href="{{ get_permalink() }}" class="rl-post-image-link">
            @if ($cardThumb)
              <img src="{{ $cardThumb }}" alt="{{ the_title_attribute(['echo' => false]) }}" class="rl-post-image" loading="lazy" />
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
