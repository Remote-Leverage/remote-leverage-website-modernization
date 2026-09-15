{{-- Stacked cards of candidate video introductions with play overlays and durations. --}}
<section class="w-full bg-[#F4F6FC] py-12 sm:py-16 lg:py-20">
    <div class="w-full max-w-[1380px] mx-auto px-4 sm:px-6 lg:px-8">
        {{-- Video Cards Stack Matching 1:1 Production (No extra title or subtitle) --}}
        <div class="flex flex-col gap-6 sm:gap-8">
            @foreach ($cards as $idx => $card)
                @php
                    $posterSrc = '';
                    if (!empty($card['poster_file'])) {
                        $clean = ltrim(str_replace('public/', '', $card['poster_file']), '/');
                        if (file_exists(get_theme_file_path('public/' . $clean))) {
                            $posterSrc = get_theme_file_uri('public/' . $clean);
                        } elseif (file_exists(get_theme_file_path($card['poster_file']))) {
                            $posterSrc = get_theme_file_uri($card['poster_file']);
                        }
                    }
                    if (empty($posterSrc) && !empty($card['poster_url'])) {
                        $base = basename($card['poster_url']);
                        if (file_exists(get_theme_file_path('public/images/samples/posters/' . $base))) {
                            $posterSrc = get_theme_file_uri('public/images/samples/posters/' . $base);
                        } else {
                            $posterSrc = $card['poster_url'];
                        }
                    }
                    $videoSrc = $card['video_url'] ?? '';
                    $resumeUrl = !empty($card['resume_url']) ? $card['resume_url'] : '#booking-footer';

                    $countryName = $card['country'] ?? '';
                    $flagSlug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $countryName), '-'));
                    $flagPng = "public/images/flags/{$flagSlug}.png";
                    $flagSvg = "public/images/flags/{$flagSlug}.svg";
                    $flagSrc = file_exists(get_theme_file_path($flagPng))
                        ? get_theme_file_uri($flagPng)
                        : (file_exists(get_theme_file_path($flagSvg)) ? get_theme_file_uri($flagSvg) : '');
                    $hasFlag = !empty($flagSrc);
                @endphp

                <div 
                    x-data="{ playing: false }" 
                    class="bg-white rounded-[10px] shadow-[0_4px_50px_rgba(138,43,226,0.12)] border border-purple-100/40 p-5 sm:p-6 transition hover:shadow-xl"
                >
                    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                        
                        {{-- Video Column (Left) --}}
                        <div class="lg:col-span-6 w-full">
                            <div class="aspect-video rounded-[6px] overflow-hidden relative bg-slate-900 shadow-inner flex items-center justify-center group">
                                <video 
                                    x-ref="videoPlayer"
                                    class="w-full h-full object-cover" 
                                    playsinline
                                    controlslist="nodownload"
                                    :controls="playing"
                                    preload="none"
                                    @if ($posterSrc) poster="{{ $posterSrc }}" @endif
                                    @ended="playing = false"
                                >
                                    <source src="{{ $videoSrc }}" type="video/mp4" />
                                    Your browser does not support HTML5 video.
                                </video>

                                {{-- Poster Image & Play Button Overlay --}}
                                <div 
                                    x-show="!playing" 
                                    @click="playing = true; $refs.videoPlayer.play()"
                                    class="absolute inset-0 cursor-pointer flex items-center justify-center bg-black/20 group-hover:bg-black/10 transition z-10"
                                >
                                    @if ($posterSrc)
                                        <img 
                                            src="{{ $posterSrc }}" 
                                            alt="{{ $card['name'] ?? 'Candidate' }}" 
                                            class="absolute inset-0 w-full h-full object-cover pointer-events-none"
                                            loading="lazy"
                                        />
                                    @endif
                                    
                                    {{-- Center Circular Play Button --}}
                                    <div class="relative z-10 w-14 h-14 sm:w-16 sm:h-16 rounded-full bg-white/40 backdrop-blur-md flex items-center justify-center shadow-lg transition transform group-hover:scale-110">
                                        <svg class="w-6 h-6 text-black fill-current ml-0.5" viewBox="0 0 24 24">
                                            <path d="M8 5v14l11-7z"/>
                                        </svg>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Details Column (Right) --}}
                        <div class="lg:col-span-6 flex flex-col justify-between self-stretch pt-1 sm:pt-2">
                            <div>
                                {{-- Row 1: Name + Verified Icon & Green Rate Badge --}}
                                <div class="flex items-center justify-between gap-3 mb-1">
                                    <div class="flex items-center gap-2">
                                        <h3 class="font-display text-2xl sm:text-3xl font-bold text-slate-900 tracking-tight">
                                            {{ $card['name'] ?? '' }}
                                        </h3>
                                        {{-- Blue Verified Checkmark SVG --}}
                                        <svg class="w-5 h-5 shrink-0 text-[#4A7EFF]" viewBox="0 0 19 20" fill="currentColor">
                                            <path d="M19 10.0002C19 10.8541 18.0299 11.5625 17.8301 12.3499C17.6231 13.1647 18.1208 14.2876 17.7286 15.001C17.3307 15.7248 16.1546 15.8301 15.5968 16.4173C15.0386 17.0048 14.9389 18.2428 14.2513 18.6613C13.5637 19.0799 12.5068 18.5506 11.7328 18.7681C10.9847 18.9785 10.3118 19.9996 9.50051 19.9996C8.68925 19.9996 8.01632 18.9785 7.26825 18.7681C6.49422 18.5502 5.42745 19.0741 4.74974 18.6613C4.06213 18.2425 3.96205 17.0045 3.40424 16.4173C2.84609 15.8298 1.67001 15.7248 1.27241 15.001C0.880265 14.2876 1.37761 13.1647 1.17095 12.3499C0.970102 11.5625 0 10.8541 0 10.0002C0 9.14622 0.970102 8.43788 1.16993 7.65043C1.37693 6.83566 0.879241 5.71274 1.27138 4.99937C1.66933 4.27557 2.84541 4.17022 3.40321 3.58305C3.96102 2.99588 4.06111 1.75755 4.74872 1.33901C5.42642 0.926236 6.4932 1.44976 7.26723 1.23222C8.01598 1.02116 8.68891 0 9.50017 0C10.3114 0 10.9844 1.02116 11.7324 1.2315C12.5065 1.4494 13.5732 0.925516 14.2509 1.3383C14.9386 1.75719 15.0386 2.99516 15.5964 3.58233C16.1546 4.16986 17.3307 4.27485 17.7283 4.99865C18.1204 5.71203 17.6231 6.83494 17.8297 7.64971C18.0296 8.43716 18.9997 9.1455 18.9997 9.99946L19 10.0002Z"/>
                                            <path d="M8.17966 14.4422C7.86198 14.4422 7.55763 14.3095 7.33287 14.0729L4.36244 10.9462C3.89481 10.4539 3.89481 9.65606 4.36244 9.16382C4.83007 8.67158 5.58805 8.67158 6.05568 9.16382L8.17966 11.3996L12.3193 7.04203C12.787 6.54979 13.5449 6.54979 14.0126 7.04203C14.4802 7.53428 14.4802 8.33215 14.0126 8.82439L9.02645 14.0729C8.80203 14.3092 8.49733 14.4422 8.17966 14.4422Z" fill="white"/>
                                        </svg>
                                    </div>

                                    @if (!empty($card['rate']))
                                        <span class="font-display inline-flex items-center px-5 sm:px-6 py-1.5 rounded-[5px] text-sm sm:text-base font-bold bg-[#00D982] text-slate-900 tracking-tight whitespace-nowrap shadow-xs">
                                            {{ $card['rate'] }}
                                        </span>
                                    @endif
                                </div>

                                {{-- Row 2: Role --}}
                                @if (!empty($card['role']))
                                    <div class="text-base sm:text-lg font-normal text-slate-900 mb-1">
                                        {{ $card['role'] }}
                                    </div>
                                @endif

                                {{-- Row 3: Country Flag & Name --}}
                                @if (!empty($countryName))
                                    <div class="flex items-center gap-2 text-xs sm:text-sm text-slate-500 font-normal mb-3">
                                        @if ($hasFlag)
                                            <img 
                                                src="{{ $flagSrc }}" 
                                                alt="{{ $countryName }} flag" 
                                                class="w-[19px] h-[19px] rounded-full object-cover shrink-0" 
                                                width="19" 
                                                height="19" 
                                                loading="lazy"
                                            />
                                        @endif
                                        <span>{{ $countryName }}</span>
                                    </div>
                                @endif

                                {{-- Row 4: Bio Description --}}
                                @if (!empty($card['bio']))
                                    <p class="text-xs sm:text-sm text-slate-700 leading-relaxed line-clamp-4 mb-4">
                                        {{ $card['bio'] }}
                                    </p>
                                @endif
                            </div>

                            {{-- Row 5: View Resume Full-Width Black Button (hovers to orange) --}}
                            <a 
                                href="{{ $resumeUrl }}" 
                                target="_blank" 
                                rel="noopener noreferrer"
                                class="font-display w-full block bg-black text-white text-center font-bold text-sm sm:text-base py-3 rounded-[5px] hover:bg-brand-orange active:scale-[0.99] transition-colors duration-200 shadow"
                            >
                                View Resume
                            </a>
                        </div>

                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
