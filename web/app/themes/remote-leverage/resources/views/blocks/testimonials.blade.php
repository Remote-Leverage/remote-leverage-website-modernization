<section 
  x-data="{
    activeVideo: null,
    openVideo(url) {
      if (!url) return;
      this.activeVideo = url;
    },
    closeVideo() {
      this.activeVideo = null;
    }
  }"
  class="py-16 sm:py-24 bg-surface-white"
>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    {{-- Header --}}
    <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-6 mb-12">
      <div class="max-w-xl">
        <span class="px-3.5 py-1 rounded-pill bg-brand-purple/10 text-brand-purple text-xs font-bold uppercase tracking-wider">
          Proven Track Record
        </span>
        <h2 class="text-3xl sm:text-4xl font-extrabold font-display text-brand-hero tracking-tight mt-3">
          {{ $title }}
        </h2>
        @if ($description)
          <p class="text-text-muted text-sm sm:text-base mt-2 leading-relaxed">
            {{ $description }}
          </p>
        @endif
      </div>

      {{-- Carousel Nav Arrows --}}
      @if ($layout === 'carousel')
        <div class="flex items-center gap-2">
          <button
            type="button"
            @click="$refs.carousel.scrollBy({ left: -360, behavior: 'smooth' })"
            class="p-2.5 rounded-full border border-slate-200 hover:border-brand-purple hover:bg-brand-purple/5 text-text-body transition cursor-pointer"
            aria-label="Previous testimonials"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
          </button>
          <button
            type="button"
            @click="$refs.carousel.scrollBy({ left: 360, behavior: 'smooth' })"
            class="p-2.5 rounded-full border border-slate-200 hover:border-brand-purple hover:bg-brand-purple/5 text-text-body transition cursor-pointer"
            aria-label="Next testimonials"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
          </button>
        </div>
      @endif
    </div>

    {{-- Testimonials Container --}}
    <div 
      x-ref="carousel"
      class="{{ $layout === 'carousel' ? 'flex gap-6 overflow-x-auto scroll-smooth snap-x snap-mandatory pb-6 scrollbar-none' : 'grid grid-cols-1 md:grid-cols-2 lg:grid-cols-' . $columns . ' gap-6' }}"
    >
      @foreach ($testimonials as $t)
        <div class="group relative bg-bg-light rounded-card-lg border border-slate-200/80 hover:border-brand-purple/40 shadow-card hover:shadow-xl transition-all duration-300 p-6 sm:p-8 flex flex-col justify-between {{ $layout === 'carousel' ? 'w-80 sm:w-96 shrink-0 snap-start' : 'w-full' }}">
          
          <div class="space-y-4">
            {{-- Star Rating --}}
            <div class="flex items-center gap-1 text-amber-400">
              @for ($i = 0; $i < ($t['rating'] ?? 5); $i++)
                <svg class="w-4 h-4 fill-current" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
              @endfor
            </div>

            {{-- Quote --}}
            <p class="text-text-body text-sm sm:text-base leading-relaxed italic">
              &ldquo;{{ $t['quote'] }}&rdquo;
            </p>
          </div>

          {{-- Author Info & Video Trigger --}}
          <div class="pt-6 mt-6 border-t border-slate-200/60 flex items-center justify-between">
            <div class="flex items-center gap-3">
              {{-- Avatar --}}
              <div class="w-11 h-11 rounded-full bg-brand-purple/10 border border-brand-purple/20 overflow-hidden flex items-center justify-center shrink-0">
                @if (! empty($t['avatar']))
                  <img src="{{ $t['avatar'] }}" alt="{{ $t['author_name'] ?? 'Client' }}" class="w-full h-full object-cover" />
                @else
                  <span class="font-bold text-brand-purple text-xs">{{ substr($t['author_name'] ?? 'Client', 0, 2) }}</span>
                @endif
              </div>

              <div>
                <h4 class="text-sm font-bold text-brand-hero">{{ $t['author_name'] }}</h4>
                <p class="text-2xs text-text-muted">
                  {{ $t['role'] }} @if (! empty($t['company'])) &bull; {{ $t['company'] }} @endif
                </p>
              </div>
            </div>

            {{-- Video Play Button if present --}}
            @if (! empty($t['video_url']))
              <button
                type="button"
                @click="openVideo('{{ $t['video_url'] }}')"
                class="flex items-center gap-1.5 px-3 py-1.5 rounded-pill bg-brand-magenta/10 hover:bg-brand-magenta text-brand-magenta hover:text-white text-2xs font-bold transition cursor-pointer shrink-0"
              >
                <svg class="w-3.5 h-3.5 fill-current" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                <span>{{ $t['duration'] ?: 'Watch' }}</span>
              </button>
            @endif
          </div>

        </div>
      @endforeach
    </div>
  </div>

  {{-- Video Modal Player --}}
  <div 
    x-show="activeVideo" 
    x-cloak 
    @keydown.escape.window="closeVideo()"
    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm animate-fadeIn"
  >
    <div class="relative w-full max-w-3xl bg-black rounded-card-lg overflow-hidden shadow-2xl" @click.outside="closeVideo()">
      <button 
        type="button" 
        @click="closeVideo()" 
        class="absolute top-3 right-3 z-10 text-white/80 hover:text-white p-2 rounded-full bg-black/40 transition"
      >
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>

      <div class="aspect-video w-full">
        <template x-if="activeVideo">
          <iframe 
            :src="activeVideo.includes('watch?v=') ? activeVideo.replace('watch?v=', 'embed/') : activeVideo" 
            class="w-full h-full border-0" 
            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
            allowfullscreen
          ></iframe>
        </template>
      </div>
    </div>
  </div>
</section>
