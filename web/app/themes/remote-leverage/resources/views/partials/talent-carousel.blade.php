{{-- Production's sales talent carousel, ported from the rl_article_sales_talent_carousel
     widget. The five cards are identical on all 88 posts that carry it, so they live
     here rather than in per-post content. Images were sideloaded from production and
     are resolved by filename because the uploads folder differs per environment.

     Ricardo's flag is brazil.png on production, which 404s there; he reuses the same
     Brazil flag as Carlos rather than rendering a broken image. --}}
@php
  use App\Support\MediaLibrary;

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
        @php($photo = MediaLibrary::url($person['photo']))
        <div class="rl-article-talent-card" data-rl-carousel-card>
          @if ($photo)
            <img src="{{ $photo }}" class="rl-talent-image" alt="{{ $person['name'] }}" loading="lazy" />
          @endif

          <div class="rl-talent-info-banner">
            @php($flag = MediaLibrary::url($person['flag']))
            @if ($flag)
              <div class="rl-talent-flag">
                <img src="{{ $flag }}" class="rl-talent-country" alt="" loading="lazy" />
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
