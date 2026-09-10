<div class="w-full">
    {{-- Top Header Row --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-6 pb-6">
        <div>
            @if (! empty($titlePrefix))
                <span class="block font-display text-2xl sm:text-3xl font-bold text-black tracking-tight mb-1">
                    {{ $titlePrefix }}
                </span>
            @endif
            @if (! empty($title))
                <h2 class="font-display text-3xl sm:text-4xl lg:text-[44px] font-bold text-black tracking-[-0.03em] leading-tight">
                    {{ $title }}
                </h2>
            @endif
        </div>

        {{-- Dark Purple Pill Badge with Brand Swirl Icons --}}
        <div class="self-start sm:self-center">
            <div class="inline-flex items-center gap-3 bg-[#250D4A] rounded-full px-5 py-3 shadow-[0_4px_20px_rgba(37,13,74,0.2)]">
                {{-- Swirl Icon Left (Gradient) --}}
                <div class="w-8 h-8 rounded-full bg-gradient-to-tr from-yellow-400 via-pink-500 to-indigo-500 p-[1px] flex items-center justify-center">
                    <div class="w-full h-full rounded-full bg-[#250D4A] flex items-center justify-center overflow-hidden">
                        <div class="w-5 h-5 rounded-full bg-gradient-to-tr from-yellow-400 via-pink-500 to-purple-600"></div>
                    </div>
                </div>

                {{-- Text --}}
                <span class="text-sm sm:text-base font-bold text-white tracking-wide">
                    {{ $badgeText }}
                </span>

                {{-- Swirl Icon Right (White) --}}
                <div class="w-6 h-6 flex items-center justify-center text-white opacity-90">
                    <svg class="w-5 h-5" viewBox="0 0 47 47" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M39.356 0C43.5723 0 47 3.43013 47 7.64398C47 9.82464 46.08 11.793 44.6111 13.1869C46.1389 16.3022 47 19.8028 47 23.5C47 36.4594 36.4571 47 23.5 47C16.9316 47 10.9863 44.2918 6.71899 39.9338C6.48922 39.7155 6.26071 39.4895 6.04482 39.2502C5.09305 38.2401 4.25597 37.1217 3.55497 35.9134C1.30301 32.3093 4.02234e-07 28.0548 0 23.5C0 10.5406 10.5429 0 23.5 0C27.1971 3.27236e-07 30.6977 0.860669 33.8128 2.38834C35.205 0.919448 37.1757 3.55796e-06 39.356 0ZM23.5 5.10067C17.7214 5.10067 12.5571 7.7812 9.18212 11.9636C12.0262 10.0715 15.437 8.96831 19.1021 8.96828C29.0076 8.96828 37.0651 17.0258 37.0651 26.9313C37.0651 32.186 34.7958 36.9204 31.1872 40.2081C37.5042 37.2924 41.8993 30.9008 41.8993 23.5V23.4978C41.8993 20.5372 41.1914 17.7421 39.9422 15.2626C39.7491 15.2776 39.5533 15.2855 39.356 15.2855C35.1398 15.2855 31.7121 11.8556 31.712 7.64182C31.712 7.44445 31.72 7.24855 31.735 7.0554C29.2555 5.80835 26.4605 5.10067 23.5 5.10067ZM19.1045 14.0714C13.9334 14.0714 9.46617 17.1394 7.42529 21.552C9.52417 19.8529 12.1949 18.8327 15.1 18.8327C21.838 18.8327 27.3193 24.3137 27.3193 31.0516C27.3193 33.8332 26.3804 36.3997 24.8084 38.4559C29.0467 36.3486 31.9666 31.9758 31.9666 26.9337C31.9666 19.8417 26.1965 14.0714 19.1045 14.0714ZM15.1 23.9312C11.1756 23.9312 7.98174 27.1251 7.98174 31.0495C7.98175 32.6229 8.49438 34.0757 9.36085 35.2546C9.66069 35.614 9.97404 35.9603 10.3007 36.2959C10.3169 36.3112 10.3323 36.3262 10.3463 36.3397C10.3624 36.3552 10.3769 36.3691 10.3914 36.3826C11.6067 37.4588 13.1889 38.1254 14.9237 38.1678H15.1C19.0245 38.1678 22.2183 34.9739 22.2184 31.0495C22.2184 27.125 19.0245 23.9312 15.1 23.9312Z" fill="currentColor"/>
                    </svg>
                </div>
            </div>
        </div>
    </div>

    {{-- Large Statement Text --}}
    @if (! empty($leadStatement))
        <div class="font-display text-2xl sm:text-3xl lg:text-[32px] font-bold text-black tracking-[-0.02em] leading-snug my-8 sm:my-10">
            {!! $leadStatement !!}
        </div>
    @endif

    {{-- 2-Column Detail Paragraphs --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-8 lg:gap-14 text-base sm:text-lg text-black/75 leading-relaxed">
        @if (! empty($colLeft))
            <div>
                {!! nl2br($colLeft) !!}
            </div>
        @endif

        @if (! empty($colRight))
            <div>
                {!! nl2br($colRight) !!}
            </div>
        @endif
    </div>
</div>
