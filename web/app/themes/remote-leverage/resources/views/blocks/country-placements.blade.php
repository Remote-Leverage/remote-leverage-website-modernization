{{-- Production's Impact Report "Country placements" table: a full-width dark #250D4A
     banner carrying the heading, then flag + country + right-aligned count rows filling
     three columns left-to-right (not column-by-column), each row separated by a hairline
     rule. Used on /impact-report-2026/. --}}
<section class="w-full bg-white pb-16 lg:pb-20">
    <div class="w-full px-4 sm:px-6 lg:px-8">
        <div class="w-full max-w-[1380px] mx-auto">

            @if ($headline)
                <div class="rounded-xl py-4 px-6 mb-4 bg-brand-dark-violet">
                    <h3 class="font-display font-semibold text-[26px] sm:text-[35px] leading-[40px] tracking-[-1.054px] text-white text-center">
                        {{ $headline }}
                    </h3>
                </div>
            @endif

            <div class="grid grid-cols-1 md:grid-cols-3 gap-x-8">
                @foreach ($rows as $row)
                    <div class="flex items-center gap-3 py-3 border-b border-black/10">
                        @if (! empty($row['flag']))
                            <img src="{{ $row['flag'] }}" alt="" width="26" height="26" loading="lazy" decoding="async"
                                class="w-[26px] h-[26px] rounded-full object-cover shrink-0">
                        @endif

                        <span class="text-[16px] leading-[26px] text-black/85 flex-1 min-w-0 truncate">{{ $row['country'] }}</span>

                        <h4 class="font-display font-semibold text-[24px] sm:text-[35px] leading-[40px] tracking-[-1.054px] text-black">
                            {{ $row['count'] }}
                        </h4>
                    </div>
                @endforeach
            </div>

        </div>
    </div>
</section>
