<section class="relative w-full bg-[#250D4A] overflow-hidden py-12 sm:py-16 lg:py-24 min-h-[calc(100vh-80px)] flex items-center" style="background-color: #250D4A; background-image: url('{{ get_theme_file_uri('public/images/home/Union-5.png') }}'); background-position: center center; background-repeat: no-repeat; background-size: contain;">
    <div class="relative z-10 w-full max-w-[1380px] mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-10 lg:gap-16 items-start">
            
            {{-- Left Column: Headline and paragraphs in pure white --}}
            <div class="flex flex-col justify-start items-start pt-2 lg:pt-8 text-white">
                <h1 class="font-display text-[30px] sm:text-[36px] lg:text-[53px] xl:text-[63px] font-bold leading-[1.1] tracking-[-0.03em] text-white mb-6 sm:mb-8">
                    {!! nl2br(e($headline)) !!}
                </h1>

                @if (!empty($paragraph_1))
                    <p class="text-base sm:text-[20px] lg:text-[24px] xl:text-[27px] font-normal leading-relaxed lg:leading-[34px] text-white/95 mb-6">
                        {!! nl2br(e($paragraph_1)) !!}
                    </p>
                @endif

                @if (!empty($paragraph_2))
                    <p class="text-base sm:text-[20px] lg:text-[24px] xl:text-[27px] font-normal leading-relaxed lg:leading-[34px] text-white/95">
                        {!! nl2br(e($paragraph_2)) !!}
                    </p>
                @endif
            </div>

            {{-- Right Column: Multistep Booking Funnel --}}
            <div class="w-full">
                <livewire:booking.multistep-booking-wizard :skin="$skin" :lazy="false" />
            </div>

        </div>
    </div>
</section>
