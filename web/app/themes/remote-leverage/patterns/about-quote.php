<?php
/**
 * Title: About Testimonial Video Card (Stacy Do)
 * Slug: remote-leverage/about-quote
 * Categories: remote-leverage
 */
?>
<!-- wp:group {"align":"full","backgroundColor":"bg-light","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull has-bg-light-background-color has-background pb-8 sm:pb-12">
    <div class="w-full max-w-[1380px] mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-white rounded-3xl p-6 sm:p-10 lg:p-12 border border-black/6 shadow-[0_4px_30px_rgba(0,0,0,0.04)] grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-12 items-center">
            <!-- Left Column: Interactive Video Player -->
            <div class="lg:col-span-6 w-full">
                <div x-data="{ playing: false }" class="relative aspect-video w-full rounded-2xl overflow-hidden bg-black shadow-md group">
                    <video x-ref="video"
                        src="/app/themes/remote-leverage/public/videos/5-minute-VSL_Horizontal_V01.mp4"
                        poster="http://remoteleverage-v2.test/app/uploads/2026/09/magnific_EqBRpqJuuO-1-1.png"
                        class="w-full h-full object-cover"
                        playsinline
                        controls
                        @play="playing = true"
                        @pause="playing = false"
                        @ended="playing = false"
                    ></video>

                    <!-- Custom Play Overlay -->
                    <div x-show="!playing" @click="$refs.video.play(); playing = true"
                        class="absolute inset-0 bg-black/25 flex items-center justify-center cursor-pointer transition-all duration-300 group-hover:bg-black/35">
                        <div class="w-16 h-16 sm:w-20 sm:h-20 rounded-full bg-white/90 backdrop-blur-md flex items-center justify-center shadow-xl transition-transform duration-300 group-hover:scale-110">
                            <svg class="w-7 h-7 sm:w-8 sm:h-8 translate-x-[2px] fill-black" viewBox="0 0 24 24">
                                <path d="M8 5v14l11-7z" />
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Client Quote -->
            <div class="lg:col-span-6 flex flex-col justify-center gap-4">
                <span class="text-[#250D4A] text-4xl sm:text-5xl font-serif font-black leading-none select-none">“</span>
                <blockquote class="font-display text-2xl sm:text-3xl font-bold tracking-[-0.02em] text-black leading-snug">
                    “It wasn’t about paying less for an employee – it was really about finding someone with work ethic.”
                </blockquote>
                <cite class="text-base sm:text-lg text-black/70 font-semibold not-italic mt-2">
                    Stacy Do, Indoor Air Programs, Cincinnati, Ohio
                </cite>
            </div>
        </div>
    </div>
</div>
<!-- /wp:group -->
