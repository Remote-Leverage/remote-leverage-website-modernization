<div x-data="{
    activeVideo: null,
    openModal(url) {
        this.activeVideo = url;
        document.body.style.overflow = 'hidden';
    },
    closeModal() {
        this.activeVideo = null;
        document.body.style.overflow = '';
    }
}" class="w-full">
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-card mb-20">
        @foreach ($testimonials as $t)
            <div class="bg-transparent hover:bg-white rounded-card p-card flex flex-col justify-between border border-transparent hover:border-black/4 hover:shadow-[0_12px_32px_rgba(0,0,0,0.08)] hover:-translate-y-1 transition-all duration-300 cursor-pointer group"
                @click="openModal('{{ $t['video_url'] }}')">
                <div>
                    <div class="relative w-full aspect-4/3 rounded-xl overflow-hidden mb-4 bg-slate-900">
                        <img src="{{ $t['image'] }}" alt="{{ $t['company'] }}" width="314"
                            height="214" loading="lazy" decoding="async"
                            class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                        {{-- Center Glass Play Button --}}
                        <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                            <div
                                class="w-12 h-12 sm:w-14 sm:h-14 rounded-full bg-white/45 backdrop-blur-xs flex items-center justify-center shadow-lg transition-transform duration-300 group-hover:scale-110">
                                <svg class="w-5 h-5 sm:w-6 sm:h-6 text-white translate-x-0.5"
                                    fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M8 5v14l11-7z" />
                                </svg>
                            </div>
                        </div>
                        {{-- Duration Badge (bottom right) --}}
                        @if (! empty($t['duration']))
                            <span
                                class="absolute bottom-2.5 right-2.5 px-2 py-0.5 rounded bg-black/60 backdrop-blur-xs text-white text-[10px] sm:text-[11px] font-medium font-mono pointer-events-none">
                                {{ $t['duration'] }}
                            </span>
                        @endif
                    </div>
                    <div class="pt-1 pb-2">
                        <h3
                            class="font-display text-[16px] sm:text-[17px] font-bold text-black leading-[1.35] tracking-[-0.01em] mb-4">
                            {{ $t['quote'] }}
                        </h3>
                    </div>
                </div>
                <div class="pb-1 mt-auto text-right">
                    <span class="text-[11px] sm:text-[12px] text-black font-medium">
                        {{ $t['company'] }}
                    </span>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Self-Contained Modal for Vimeo Playback --}}
    <div x-show="activeVideo" x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/88 backdrop-blur-sm"
        style="display:none;" @keydown.escape.window="closeModal()">
        <div class="relative w-full max-w-4xl aspect-video bg-black rounded-2xl overflow-hidden"
            @click.outside="closeModal()">
            <button type="button" @click="closeModal()"
                class="absolute top-4 right-4 z-20 w-10 h-10 rounded-full bg-white/20 hover:bg-white/40 flex items-center justify-center text-white transition-colors focus:outline-none"
                aria-label="Close video player modal">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"
                    stroke-linecap="round" aria-hidden="true">
                    <line x1="18" y1="6" x2="6" y2="18" />
                    <line x1="6" y1="6" x2="18" y2="18" />
                </svg>
            </button>
            <template x-if="activeVideo">
                <iframe
                    :src="'https://player.vimeo.com/video/' + activeVideo.split('/').pop() +
                        '?autoplay=1&badge=0&autopause=0&player_id=0&app_id=58479'"
                    title="Vimeo video player" class="w-full h-full border-0"
                    allow="autoplay; fullscreen; picture-in-picture" allowfullscreen loading="lazy"></iframe>
            </template>
        </div>
    </div>
</div>
