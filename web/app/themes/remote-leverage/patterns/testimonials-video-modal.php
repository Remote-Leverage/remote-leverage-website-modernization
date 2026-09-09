<?php
/**
 * Title: Social Proof - Video Testimonials Grid
 * Slug: remote-leverage/testimonials-video-modal
 * Categories: remote-leverage, remote-leverage-sections
 * Description: 3-column video review cards with verified client ratings and interactive video modal lightbox.
 */
?>
<!-- wp:group {"className":"rl-testimonials-grid my-16 py-8","layout":{"type":"constrained"}} -->
<div class="wp-block-group rl-testimonials-grid my-16 py-8" x-data="{ openModal: false, videoSrc: '', clientName: '' }">
  <div class="text-center max-w-2xl mx-auto mb-12">
    <span class="inline-flex items-center gap-1.5 text-xs font-bold uppercase tracking-widest text-brand-purple bg-violet-50 px-3 py-1 rounded-full border border-violet-100 mb-3">
      Client Video Proof
    </span>
    <h2 class="font-display text-3xl sm:text-4xl font-extrabold tracking-tight text-zinc-900">
      Real Founders. Real Scale. Zero Regrets.
    </h2>
    <p class="text-zinc-600 text-base mt-3">
      Watch how scaling agency owners and tech startups cut 20+ hours of operational drag every single week.
    </p>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
    <div class="group relative flex flex-col rounded-2xl border border-zinc-200/80 bg-white p-6 shadow-sm hover:shadow-xl hover:border-brand-purple/40 transition-all duration-300">
      <div class="relative mb-5 overflow-hidden rounded-xl bg-zinc-900 aspect-video flex items-center justify-center cursor-pointer"
           @click="openModal = true; videoSrc = 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?autoplay=1'; clientName = 'Alex Rivera, Agency Founder'">
        <div class="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent"></div>
        <button type="button" class="relative z-10 flex h-12 w-12 items-center justify-center rounded-full bg-white/90 text-brand-purple shadow-md group-hover:scale-110 transition-transform">
          <svg class="h-5 w-5 fill-current ml-0.5" viewBox="0 0 24 24">
            <path d="M8 5v14l11-7z" />
          </svg>
        </button>
        <span class="absolute bottom-2.5 left-3 text-xs font-medium text-white/90">Click to Play Case Study (2 min)</span>
      </div>

      <div class="flex items-center gap-1 text-amber-400 mb-2">
        <svg class="h-4 w-4 fill-current" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
        <svg class="h-4 w-4 fill-current" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
        <svg class="h-4 w-4 fill-current" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
        <svg class="h-4 w-4 fill-current" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
        <svg class="h-4 w-4 fill-current" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
      </div>

      <blockquote class="text-sm font-medium text-zinc-700 leading-relaxed">
        &ldquo;We hired two bilingual executive assistants through Remote Leverage. Within 3 weeks, our client response time dropped under 4 minutes and our churn hit an all-time low.&rdquo;
      </blockquote>

      <div class="mt-auto pt-4 border-t border-zinc-100 flex items-center gap-3">
        <div class="h-9 w-9 rounded-full bg-violet-100 text-brand-purple font-display font-bold flex items-center justify-center text-xs">
          AR
        </div>
        <div>
          <p class="text-sm font-bold text-zinc-900">Alex Rivera</p>
          <p class="text-xs text-zinc-500">Founder, ScaleMedia Agency</p>
        </div>
      </div>
    </div>

    <div class="group relative flex flex-col rounded-2xl border border-zinc-200/80 bg-white p-6 shadow-sm hover:shadow-xl hover:border-brand-purple/40 transition-all duration-300">
      <div class="relative mb-5 overflow-hidden rounded-xl bg-zinc-900 aspect-video flex items-center justify-center cursor-pointer"
           @click="openModal = true; videoSrc = 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?autoplay=1'; clientName = 'Marcus Vance, SaaS COO'">
        <div class="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent"></div>
        <button type="button" class="relative z-10 flex h-12 w-12 items-center justify-center rounded-full bg-white/90 text-brand-purple shadow-md group-hover:scale-110 transition-transform">
          <svg class="h-5 w-5 fill-current ml-0.5" viewBox="0 0 24 24">
            <path d="M8 5v14l11-7z" />
          </svg>
        </button>
        <span class="absolute bottom-2.5 left-3 text-xs font-medium text-white/90">Click to Play Case Study (3 min)</span>
      </div>

      <div class="flex items-center gap-1 text-amber-400 mb-2">
        <svg class="h-4 w-4 fill-current" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
        <svg class="h-4 w-4 fill-current" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
        <svg class="h-4 w-4 fill-current" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
        <svg class="h-4 w-4 fill-current" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
        <svg class="h-4 w-4 fill-current" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
      </div>

      <blockquote class="text-sm font-medium text-zinc-700 leading-relaxed">
        &ldquo;The time zone alignment is what makes this unbeatable. Having a senior operations VA working the exact same 9-to-5 hours as my US team revolutionized our sprints.&rdquo;
      </blockquote>

      <div class="mt-auto pt-4 border-t border-zinc-100 flex items-center gap-3">
        <div class="h-9 w-9 rounded-full bg-violet-100 text-brand-purple font-display font-bold flex items-center justify-center text-xs">
          MV
        </div>
        <div>
          <p class="text-sm font-bold text-zinc-900">Marcus Vance</p>
          <p class="text-xs text-zinc-500">COO, PipelineAI SaaS</p>
        </div>
      </div>
    </div>

    <div class="group relative flex flex-col rounded-2xl border border-zinc-200/80 bg-white p-6 shadow-sm hover:shadow-xl hover:border-brand-purple/40 transition-all duration-300">
      <div class="relative mb-5 overflow-hidden rounded-xl bg-zinc-900 aspect-video flex items-center justify-center cursor-pointer"
           @click="openModal = true; videoSrc = 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?autoplay=1'; clientName = 'Sarah Lin, E-Commerce Director'">
        <div class="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent"></div>
        <button type="button" class="relative z-10 flex h-12 w-12 items-center justify-center rounded-full bg-white/90 text-brand-purple shadow-md group-hover:scale-110 transition-transform">
          <svg class="h-5 w-5 fill-current ml-0.5" viewBox="0 0 24 24">
            <path d="M8 5v14l11-7z" />
          </svg>
        </button>
        <span class="absolute bottom-2.5 left-3 text-xs font-medium text-white/90">Click to Play Case Study (2 min)</span>
      </div>

      <div class="flex items-center gap-1 text-amber-400 mb-2">
        <svg class="h-4 w-4 fill-current" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
        <svg class="h-4 w-4 fill-current" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
        <svg class="h-4 w-4 fill-current" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
        <svg class="h-4 w-4 fill-current" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
        <svg class="h-4 w-4 fill-current" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
      </div>

      <blockquote class="text-sm font-medium text-zinc-700 leading-relaxed">
        &ldquo;Their vetting process is real. We interviewed 3 candidates and all 3 were phenomenal. We ended up hiring two because we couldn't pass on the talent.&rdquo;
      </blockquote>

      <div class="mt-auto pt-4 border-t border-zinc-100 flex items-center gap-3">
        <div class="h-9 w-9 rounded-full bg-violet-100 text-brand-purple font-display font-bold flex items-center justify-center text-xs">
          SL
        </div>
        <div>
          <p class="text-sm font-bold text-zinc-900">Sarah Lin</p>
          <p class="text-xs text-zinc-500">VP Growth, PureCraft Brands</p>
        </div>
      </div>
    </div>
  </div>

  <!-- Video Modal Lightbox -->
  <div x-show="openModal"
       x-cloak
       class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm"
       @keydown.escape.window="openModal = false; videoSrc = ''">
    <div class="relative w-full max-w-3xl rounded-2xl bg-zinc-950 p-2 shadow-2xl border border-white/10"
         @click.outside="openModal = false; videoSrc = ''">
      <div class="flex items-center justify-between px-4 py-2 text-white">
        <span class="text-sm font-semibold" x-text="clientName"></span>
        <button type="button" @click="openModal = false; videoSrc = ''" class="text-zinc-400 hover:text-white p-1">
          <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>
      <div class="aspect-video w-full rounded-xl overflow-hidden bg-black">
        <iframe :src="videoSrc" class="w-full h-full" frameborder="0" allow="autoplay; encrypted-media" allowfullscreen></iframe>
      </div>
    </div>
  </div>
</div>
<!-- /wp:group -->
