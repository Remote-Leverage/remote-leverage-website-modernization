<div class="relative w-full bg-[#250D4A] overflow-hidden">
    {{-- Subtle background glow effects --}}
    <div class="absolute -top-40 right-10 w-96 h-96 bg-[#8A2BE2]/20 rounded-full blur-[140px] pointer-events-none"></div>
    <div class="absolute -bottom-20 left-10 w-80 h-80 bg-[#581FB0]/30 rounded-full blur-[120px] pointer-events-none"></div>

    <div class="relative w-full max-w-[1380px] mx-auto px-4 sm:px-6 lg:px-8 pt-12 pb-16 lg:pt-20 lg:pb-24">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 lg:gap-8 items-center">
            {{-- Left Content --}}
            <div class="lg:col-span-7 flex flex-col items-start z-10">
                <h1 class="font-display text-4xl sm:text-5xl lg:text-[56px] font-bold text-white tracking-[-0.03em] leading-[1.1] mb-6">
                    {!! $headline !!}
                </h1>

                <div class="text-base sm:text-lg text-white/80 leading-relaxed mb-8 max-w-xl">
                    {!! $subtitle !!}
                </div>

                @if (! empty($buttonText))
                    <a href="{{ $buttonUrl ?: '#booking-footer' }}"
                        class="inline-flex items-center gap-3 bg-[#8028E0] hover:bg-[#6e1ec7] text-white text-sm font-bold uppercase tracking-[0.06em] px-8 py-4 rounded-full transition-all duration-200 shadow-[0_4px_24px_rgba(128,40,224,0.45)] hover:shadow-[0_6px_28px_rgba(128,40,224,0.6)]">
                        <span>{{ $buttonText }}</span>
                        <span class="w-6 h-6 rounded-full border border-white/50 flex items-center justify-center bg-white/10">
                            <svg class="w-3.5 h-3.5 translate-x-[0.5px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                            </svg>
                        </span>
                    </a>
                @endif
            </div>

            {{-- Right Graphic: 3D Dotted Globe & Talent Pins --}}
            <div class="lg:col-span-5 relative flex flex-col items-center justify-center">
                <div class="relative w-full max-w-[420px] aspect-square flex items-center justify-center">
                    {{-- Dotted Globe Canvas/Image --}}
                    @if (! empty($globeImage))
                        <img src="{{ $globeImage }}" alt="Remote Leverage Global Network" width="420" height="420"
                            loading="eager" decoding="async" class="w-full h-auto object-contain drop-shadow-[0_10px_35px_rgba(0,0,0,0.5)]">
                    @else
                        <div class="w-full h-full rounded-full border border-white/10 bg-radial from-purple-900/40 to-transparent"></div>
                    @endif

                    {{-- Floating Talent Avatars --}}
                    {{-- Avatar 1: North America --}}
                    <div class="absolute top-[28%] right-[22%] w-10 h-10 sm:w-11 sm:h-11 rounded-full p-[2px] bg-white shadow-[0_4px_14px_rgba(0,0,0,0.4)] transition-transform duration-300 hover:scale-110">
                        <img src="{{ \App\Support\BlockDefaults::resolveImageUrl(\App\Support\BlockDefaults::getAttachmentId('Person_04.png')) ?: 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?w=150&auto=format&fit=crop&q=80' }}"
                            alt="Talent" class="w-full h-full rounded-full object-cover">
                    </div>

                    {{-- Avatar 2: Latin America (Left) --}}
                    <div class="absolute top-[42%] left-[12%] w-10 h-10 sm:w-11 sm:h-11 rounded-full p-[2px] bg-white shadow-[0_4px_14px_rgba(0,0,0,0.4)] transition-transform duration-300 hover:scale-110">
                        <img src="{{ \App\Support\BlockDefaults::resolveImageUrl(\App\Support\BlockDefaults::getAttachmentId('person_01.png')) ?: 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=150&auto=format&fit=crop&q=80' }}"
                            alt="Talent" class="w-full h-full rounded-full object-cover">
                    </div>

                    {{-- Avatar 3: Latin America (Center) --}}
                    <div class="absolute top-[52%] left-[36%] w-10 h-10 sm:w-11 sm:h-11 rounded-full p-[2px] bg-white shadow-[0_4px_14px_rgba(0,0,0,0.4)] transition-transform duration-300 hover:scale-110">
                        <img src="{{ \App\Support\BlockDefaults::resolveImageUrl(\App\Support\BlockDefaults::getAttachmentId('person_02.png')) ?: 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=150&auto=format&fit=crop&q=80' }}"
                            alt="Talent" class="w-full h-full rounded-full object-cover">
                    </div>

                    {{-- Avatar 4: South America (Bottom) --}}
                    <div class="absolute bottom-[24%] left-[24%] w-10 h-10 sm:w-11 sm:h-11 rounded-full p-[2px] bg-white shadow-[0_4px_14px_rgba(0,0,0,0.4)] transition-transform duration-300 hover:scale-110">
                        <img src="{{ \App\Support\BlockDefaults::resolveImageUrl(\App\Support\BlockDefaults::getAttachmentId('Person_03.png')) ?: 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=150&auto=format&fit=crop&q=80' }}"
                            alt="Talent" class="w-full h-full rounded-full object-cover">
                    </div>
                </div>

                {{-- Bottom Overlapping 2.5K+ Trust Badge --}}
                <div class="mt-2 flex items-center gap-3 self-center sm:self-end sm:mr-6">
                    <div class="flex -space-x-2">
                        <img class="inline-block h-8 w-8 rounded-full ring-2 ring-[#250D4A] object-cover"
                            src="{{ \App\Support\BlockDefaults::resolveImageUrl(\App\Support\BlockDefaults::getAttachmentId('person_01.png')) ?: 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=100' }}"
                            alt="">
                        <img class="inline-block h-8 w-8 rounded-full ring-2 ring-[#250D4A] object-cover"
                            src="{{ \App\Support\BlockDefaults::resolveImageUrl(\App\Support\BlockDefaults::getAttachmentId('person_02.png')) ?: 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=100' }}"
                            alt="">
                        <img class="inline-block h-8 w-8 rounded-full ring-2 ring-[#250D4A] object-cover"
                            src="{{ \App\Support\BlockDefaults::resolveImageUrl(\App\Support\BlockDefaults::getAttachmentId('Person_04.png')) ?: 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?w=100' }}"
                            alt="">
                    </div>
                    <div class="flex items-center gap-2">
                        {{-- Verified Blue Badge --}}
                        <svg class="w-5 h-5 flex-shrink-0" viewBox="0 0 27 27" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path fill-rule="evenodd" clip-rule="evenodd" d="M11.4568 0.738669C11.9039 0.292175 12.5021 0.029451 13.1334 0.00232988C13.7646 -0.0247913 14.3831 0.18566 14.8669 0.592164L15.0285 0.739932L17.4282 3.13832H20.8205C21.4576 3.13843 22.071 3.37926 22.538 3.81254C23.0051 4.24582 23.2911 4.83956 23.3389 5.47482L23.3465 5.66426V9.05661L25.7461 11.4563C26.193 11.9034 26.4559 12.5018 26.483 13.1334C26.5101 13.765 26.2995 14.3837 25.8926 14.8675L25.7448 15.0279L23.3452 17.4276V20.8199C23.3454 21.4572 23.1047 22.071 22.6714 22.5383C22.2381 23.0055 21.6442 23.2918 21.0087 23.3396L20.8205 23.3459H17.4294L15.0298 25.7455C14.5826 26.1924 13.9842 26.4553 13.3526 26.4824C12.7211 26.5096 12.1023 26.2989 11.6185 25.892L11.4581 25.7455L9.05845 23.3459H5.66484C5.02757 23.3461 4.41378 23.1054 3.9465 22.6721C3.47923 22.2388 3.193 21.6449 3.14521 21.0094L3.13889 20.8199V17.4276L0.739245 15.0279C0.292399 14.5808 0.0294519 13.9824 0.00232816 13.3508C-0.0247956 12.7192 0.185875 12.1005 0.592741 11.6167L0.739245 11.4563L3.13889 9.05661V5.66426C3.13901 5.02721 3.37983 4.41374 3.81311 3.94673C4.2464 3.47972 4.84014 3.19367 5.47539 3.14589L5.66484 3.13832H9.05718L11.4568 0.738669Z" fill="#0067FF"/>
                            <path fill-rule="evenodd" clip-rule="evenodd" d="M17.1315 9.43168L11.7714 14.7917L9.53848 12.5588C9.30149 12.322 8.98014 12.189 8.64511 12.1891C8.31008 12.1892 7.98882 12.3224 7.752 12.5594C7.51518 12.7964 7.38221 13.1178 7.38232 13.4528C7.38244 13.7878 7.51565 14.1091 7.75263 14.3459L10.7888 17.3821C10.9178 17.5112 11.071 17.6136 11.2396 17.6834C11.4082 17.7533 11.5889 17.7892 11.7714 17.7892C11.9539 17.7892 12.1346 17.7533 12.3032 17.6834C12.4718 17.6136 12.625 17.5112 12.754 17.3821L18.9173 11.2175C19.1474 10.9793 19.2747 10.6603 19.2718 10.3291C19.2689 9.998 19.1361 9.68123 18.9019 9.44706C18.6678 9.2129 18.351 9.08007 18.0198 9.0772C17.6887 9.07432 17.3697 9.20162 17.1315 9.43168Z" fill="white"/>
                        </svg>
                        <span class="text-xs sm:text-sm font-bold text-white tracking-wide">{!! $badgeText !!}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Bottom Logobar Band --}}
        <div class="mt-16 sm:mt-24 pt-8 border-t border-white/10">
            @if (! empty($trustedTitle))
                <div class="text-center mb-8">
                    <span class="text-xs font-bold uppercase tracking-[0.14em] text-white/60">
                        {{ $trustedTitle }}
                    </span>
                </div>
            @endif

            {{-- Client Logos Marquee --}}
            <div class="relative w-full overflow-hidden [mask-image:linear-gradient(to_right,transparent,black_10%,black_90%,transparent)]">
                <div class="flex items-center gap-12 sm:gap-16 whitespace-nowrap animate-marquee">
                    @foreach ($logos as $logo)
                        <div class="inline-flex items-center justify-center flex-shrink-0 opacity-80 hover:opacity-100 transition-opacity">
                            <img src="{{ $logo['src'] ?? $logo['url'] ?? '' }}" alt="{{ $logo['alt'] ?? $logo['name'] ?? '' }}" width="120" height="40"
                                loading="lazy" decoding="async" class="h-8 sm:h-9 w-auto object-contain brightness-0 invert">
                        </div>
                    @endforeach
                    {{-- Duplicate for continuous marquee loop --}}
                    @foreach ($logos as $logo)
                        <div class="inline-flex items-center justify-center flex-shrink-0 opacity-80 hover:opacity-100 transition-opacity" aria-hidden="true">
                            <img src="{{ $logo['src'] ?? $logo['url'] ?? '' }}" alt="" width="120" height="40"
                                loading="lazy" decoding="async" class="h-8 sm:h-9 w-auto object-contain brightness-0 invert">
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
