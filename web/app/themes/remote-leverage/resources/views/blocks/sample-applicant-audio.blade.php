<section class="w-full bg-[#F4F6FC] py-12 sm:py-16 lg:py-20">
    <div class="w-full max-w-[1380px] mx-auto px-4 sm:px-6 lg:px-8">
        {{-- 4-Column Grid Matching 1:1 Production --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 sm:gap-6">
            @foreach ($items as $item)
                @php
                    $audioSrc = $item['audio_url'] ?? '';
                    $resumeUrl = !empty($item['resume_url']) ? $item['resume_url'] : '#booking-footer';
                    $duration = !empty($item['duration']) ? $item['duration'] : '0:45';
                @endphp

                <div 
                    x-data="rlAudioPlayer('{{ $duration }}')"
                    class="bg-white rounded-[10px] shadow-[0_4px_30px_rgba(138,43,226,0.08)] border border-purple-100/30 p-5 flex flex-col justify-between transition hover:shadow-lg relative group"
                >
                    <audio 
                        x-ref="audio" 
                        preload="none" 
                        src="{{ $audioSrc }}"
                        @loadedmetadata="onLoadedMetadata()"
                        @timeupdate="onTimeUpdate()"
                        @ended="onEnded()"
                        class="hidden"
                    ></audio>

                    <div>
                        {{-- Row 1: Country on left, Rate pill on right (hovers to green) --}}
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-xs sm:text-sm font-semibold text-slate-500">
                                {{ $item['country'] ?? 'Latin America' }}
                            </span>
                            @if (!empty($item['rate']))
                                <span 
                                    class="inline-flex items-center px-3.5 py-1 rounded-[5px] text-xs sm:text-sm font-bold tracking-tight transition-colors duration-200"
                                    :class="playing ? 'bg-table-leverage text-black' : 'bg-step-numeral text-white group-hover:bg-table-leverage group-hover:text-black'"
                                >
                                    {{ $item['rate'] }}
                                </span>
                            @endif
                        </div>

                        {{-- Row 2: Name + Blue Verified Badge & Circular Play Button (hovers to orange) --}}
                        <div class="flex items-start justify-between gap-2 mb-2">
                            <div>
                                <div class="flex items-center gap-1.5">
                                    <span class="font-display text-xl sm:text-2xl font-bold text-slate-900 tracking-tight">
                                        {{ $item['name'] ?? '' }}
                                    </span>
                                    {{-- Blue Verified Checkmark SVG --}}
                                    <svg class="w-4 h-4 flex-shrink-0 text-[#4A7EFF]" viewBox="0 0 19 20" fill="currentColor">
                                        <path d="M19 10.0002C19 10.8541 18.0299 11.5625 17.8301 12.3499C17.6231 13.1647 18.1208 14.2876 17.7286 15.001C17.3307 15.7248 16.1546 15.8301 15.5968 16.4173C15.0386 17.0048 14.9389 18.2428 14.2513 18.6613C13.5637 19.0799 12.5068 18.5506 11.7328 18.7681C10.9847 18.9785 10.3118 19.9996 9.50051 19.9996C8.68925 19.9996 8.01632 18.9785 7.26825 18.7681C6.49422 18.5502 5.42745 19.0741 4.74974 18.6613C4.06213 18.2425 3.96205 17.0045 3.40424 16.4173C2.84609 15.8298 1.67001 15.7248 1.27241 15.001C0.880265 14.2876 1.37761 13.1647 1.17095 12.3499C0.970102 11.5625 0 10.8541 0 10.0002C0 9.14622 0.970102 8.43788 1.16993 7.65043C1.37693 6.83566 0.879241 5.71274 1.27138 4.99937C1.66933 4.27557 2.84541 4.17022 3.40321 3.58305C3.96102 2.99588 4.06111 1.75755 4.74872 1.33901C5.42642 0.926236 6.4932 1.44976 7.26723 1.23222C8.01598 1.02116 8.68891 0 9.50017 0C10.3114 0 10.9844 1.02116 11.7324 1.2315C12.5065 1.4494 13.5732 0.925516 14.2509 1.3383C14.9386 1.75719 15.0386 2.99516 15.5964 3.58233C16.1546 4.16986 17.3307 4.27485 17.7283 4.99865C18.1204 5.71203 17.6231 6.83494 17.8297 7.64971C18.0296 8.43716 18.9997 9.1455 18.9997 9.99946L19 10.0002Z"/>
                                        <path d="M8.17966 14.4422C7.86198 14.4422 7.55763 14.3095 7.33287 14.0729L4.36244 10.9462C3.89481 10.4539 3.89481 9.65606 4.36244 9.16382C4.83007 8.67158 5.58805 8.67158 6.05568 9.16382L8.17966 11.3996L12.3193 7.04203C12.787 6.54979 13.5449 6.54979 14.0126 7.04203C14.4802 7.53428 14.4802 8.33215 14.0126 8.82439L9.02645 14.0729C8.80203 14.3092 8.49733 14.4422 8.17966 14.4422Z" fill="white"/>
                                    </svg>
                                </div>
                                <div class="text-xs sm:text-sm text-slate-600 font-normal mt-0.5 line-clamp-1">
                                    {{ $item['role'] ?? '' }}
                                </div>
                            </div>

                            {{-- Circular Black Play/Pause Button (hovers to orange, white icon) --}}
                            <button 
                                type="button" 
                                @click="toggle()"
                                class="w-11 h-11 rounded-full bg-black text-white flex items-center justify-center shrink-0 cursor-pointer hover:bg-brand-orange active:scale-95 transition duration-200 shadow-sm"
                                :aria-label="playing ? 'Pause' : 'Play'"
                            >
                                <svg 
                                    :class="playing ? 'hidden' : 'block'" 
                                    class="w-6 h-6 ml-0.5 fill-white text-white" 
                                    viewBox="0 0 24 24" 
                                    fill="currentColor"
                                    aria-hidden="true"
                                >
                                    <path d="M8 5v14l11-7z" fill="currentColor"/>
                                </svg>
                                <svg 
                                    :class="playing ? 'block' : 'hidden'" 
                                    class="w-6 h-6 fill-white text-white hidden" 
                                    viewBox="0 0 24 24" 
                                    fill="currentColor"
                                    aria-hidden="true"
                                >
                                    <path d="M6 5h4v14H6zM14 5h4v14h-4z" fill="currentColor"/>
                                </svg>
                            </button>
                        </div>

                        {{-- Row 3: Scrubber Track & Time --}}
                        <div class="my-3">
                            <div 
                                x-ref="track" 
                                @click="seek($event)"
                                class="h-[3px] bg-[#d9d9d9] rounded relative cursor-pointer"
                            >
                                <div 
                                    class="h-full bg-black rounded" 
                                    :style="'width: ' + progress + '%'"
                                ></div>
                                <div 
                                    class="w-3 h-3 bg-black rounded-full absolute top-1/2 -translate-y-1/2 -translate-x-1/2 shadow-xs pointer-events-none"
                                    :style="'left: ' + progress + '%'"
                                ></div>
                            </div>

                            <div class="flex items-center justify-between text-xs text-slate-600 font-mono mt-2">
                                <span x-text="currentTime">0:00</span>
                                <span x-text="durationTime">{{ $duration }}</span>
                            </div>
                        </div>
                    </div>

                    {{-- Row 4: View Resume Full-Width Black Button (hovers to orange) --}}
                    <a 
                        href="{{ $resumeUrl }}" 
                        target="_blank" 
                        rel="noopener noreferrer"
                        class="w-full block bg-black text-white text-center font-bold text-xs sm:text-sm py-2.5 rounded-[5px] hover:bg-brand-orange active:scale-[0.99] transition duration-200 shadow-xs mt-2"
                    >
                        View Resume
                    </a>
                </div>
            @endforeach
        </div>
    </div>
</section>
