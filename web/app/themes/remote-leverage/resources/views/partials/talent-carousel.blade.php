{{-- Production's sales talent carousel, ported from the rl_article_sales_talent_carousel
     widget. The five cards are identical on all 88 posts that carry it, so they live
     here rather than in per-post content. Images were sideloaded from production and
     are resolved by filename because the uploads folder differs per environment.

     Ricardo's flag is brazil.png on production, which 404s there; he reuses the same
     Brazil flag as Carlos rather than rendering a broken image. --}}
@php
  use App\Support\MediaLibrary;
  use App\Support\ResponsiveImage;

  // Each card is a fixed 220px wide and the photo is painted at 220x265 with object-fit:
  // cover, at every breakpoint. The originals are 771x1024 PNGs of 200-665KB each, and all
  // five were being served untouched — 2.48MB of the single post's 3.06MB of images.
  $photoSizes = '220px';
  // The flag sits in a 24px circle. `thumbnail` (150px) is the smallest registered size.
  $flagSizes = '24px';

  $talent = [
    ['name' => 'Carlos M.',   'role' => 'Social Media Assistant',     'photo' => 'Carlos-M-1.png',  'flag' => 'brazil-1.png'],
    ['name' => 'Diana R',     'role' => 'Customer Support Assistant', 'photo' => 'Diana-R.png',     'flag' => 'colombia-1.png'],
    ['name' => 'Migue A.',    'role' => 'Sales Assistant',            'photo' => 'Miguel-A.png',    'flag' => 'mexico-1.png'],
    ['name' => 'Patricia G.', 'role' => 'Executive Assistant',        'photo' => 'Patricia-G.png',  'flag' => 'argentina-1.png'],
    ['name' => 'Ricardo P.',  'role' => 'eCommerce Assistant',        'photo' => 'Ricardo-P.png',   'flag' => 'brazil-1.png'],
  ];
@endphp

{{-- data-rl-carousel wires this to the theme's existing carousel in app.js:
     pointer drag, auto-advance and keyboard support, with native scroll-snap
     still doing the scrolling before the script runs. --}}
<div class="rl-talent-carousel-wrapper" data-rl-carousel>
  <div class="rl-talent-intro">
    <h3>{{ __('Remote Leverage Virtual Assistants', 'remote-leverage') }}</h3>
    <a href="{{ home_url('/vacalendar/') }}" class="rl-btn-primary">{{ __('Let Us Find Yours', 'remote-leverage') }}</a>
  </div>

  <div class="rl-talent-carousel">
    <div class="rl-swiper-talent" data-rl-carousel-track>
      @foreach ($talent as $person)
        @php($photo = ResponsiveImage::attributes(MediaLibrary::id($person['photo']), 'medium', $photoSizes, '', ['medium', 'medium_large']))
        <div class="rl-article-talent-card" data-rl-carousel-card>
          @if ($photo)
            <img {!! $photo !!} class="rl-talent-image" alt="{{ $person['name'] }}" loading="lazy" decoding="async" />
          @endif

          <div class="rl-talent-info-banner">
            @php($flag = ResponsiveImage::attributes(MediaLibrary::id($person['flag']), 'thumbnail', $flagSizes, '', ['thumbnail']))
            @if ($flag)
              <div class="rl-talent-flag">
                <img {!! $flag !!} class="rl-talent-country" alt="" loading="lazy" decoding="async" />
              </div>
            @endif

            <div class="rl-talent-details">
              <strong>{{ $person['name'] }}</strong>
              <span>{{ $person['role'] }}</span>
            </div>
          </div>
        </div>
      @endforeach
    </div>
  </div>
</div>
