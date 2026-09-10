@extends('layouts.app')

@section('content')
    @php
        $testimonials = \App\Support\BlockDefaults::vaThankYouTestimonials();
    @endphp

    {{-- ═══════════════════════════════════════════════════════════ --}}
    {{-- CONFIRMATION HERO — headline, countdown, 2-step confirm, video --}}
    {{-- ═══════════════════════════════════════════════════════════ --}}
    <section class="w-full bg-bg-light py-12 sm:py-16 lg:py-20">
        <div class="w-full max-w-[1380px] mx-auto px-4 sm:px-6 lg:px-8">
          <div class="w-[90%] mx-auto">
            <div class="text-center mb-10 sm:mb-14">
                <h1 class="font-display text-[32px] sm:text-[40px] lg:text-[48px] font-bold text-black tracking-[-0.02em] leading-tight mb-4">
                    Almost There - Final Step Required
                </h1>
                <p class="text-base sm:text-[17px] text-black/80 leading-relaxed">
                    <strong class="text-black">Your time slot is temporarily reserved for the next 10 minutes.</strong><br>
                    Complete the verification steps below to secure your call.
                </p>
            </div>

            <div class="flex flex-col gap-6">
                {{-- Countdown + progress --}}
                <div class="w-full">
                    <div class="flex justify-between items-end gap-3 mb-2">
                        <div class="text-left">
                            <span class="block text-[13.5px] font-medium text-black mb-0.5">Time slot reserved for:</span>
                            <span id="rl-ty-timer" style="color: orange" class="font-display block text-4xl sm:text-5xl font-bold leading-none tracking-tight tabular-nums">10:00</span>
                        </div>
                        <div class="text-right">
                            <span class="block text-[13.5px] font-medium text-black mb-0.5">Booking Progress</span>
                            <span class="font-display block text-4xl sm:text-5xl font-bold leading-none tracking-tight text-black tabular-nums">97%</span>
                        </div>
                    </div>
                    <div class="w-full h-2 rounded-pill bg-slate-200 overflow-hidden">
                        <div class="w-[97%] h-full bg-black rounded-pill"></div>
                    </div>
                </div>

                {{-- 2 Quick Steps --}}
                <div class="flex flex-col items-center gap-0 w-full">
                    <div class="w-full bg-white border border-slate-200 rounded-card p-card text-center font-bold text-[19px] text-black shadow-[0_4px_15px_rgba(0,0,0,0.03)] tracking-[-0.01em]">
                        2 Quick Steps to Confirm
                    </div>
                    <div class="relative z-10 w-1 h-4 bg-black shrink-0"></div>
                    <div class="w-full bg-white border border-slate-200 rounded-card px-5 py-4 flex items-center gap-3.5 shadow-[0_4px_15px_rgba(0,0,0,0.03)]">
                        <div class="flex items-center gap-2 shrink-0">
                            <svg class="w-5 h-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                            <span class="text-[15.5px] font-bold text-black tracking-wide">STEP 1.</span>
                        </div>
                        <span class="text-[15.5px] text-black">Open the invitation sent to your email inbox</span>
                    </div>
                    <div class="relative z-10 w-1 h-4 bg-black shrink-0"></div>
                    <div class="w-full bg-white border border-slate-200 rounded-card px-5 py-4 flex items-center gap-3.5 shadow-[0_4px_15px_rgba(0,0,0,0.03)]">
                        <div class="flex items-center gap-2 shrink-0">
                            <svg class="w-5 h-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line><path d="M9 16l2 2 4-4"></path></svg>
                            <span class="text-[15.5px] font-bold text-black tracking-wide">STEP 2.</span>
                        </div>
                        <span class="text-[15.5px] text-black">Click &ldquo;Accept&rdquo; or &ldquo;Yes&rdquo; to lock in attendance</span>
                    </div>
                </div>

                {{-- Walkthrough video --}}
                <div class="w-full rounded-card overflow-hidden shadow-[0_12px_32px_rgba(0,0,0,0.08)] bg-black aspect-video">
                    <video
                        src="{{ get_template_directory_uri() }}/public/videos/home/booking-confirmation-walkthrough.mp4"
                        autoplay muted loop playsinline controls
                        class="w-full h-full object-cover"
                    ></video>
                </div>

                {{-- What Happens Next --}}
                <div>
                    <h3 class="font-display text-xl font-bold text-black tracking-[-0.01em] mb-3">What Happens Next?</h3>
                    <ul class="list-disc pl-5 text-[15px] text-black leading-relaxed space-y-2">
                        <li>Calendar invitation dispatched instantly to your email</li>
                        <li>Dedicated VA Matching Specialist assigned to your call</li>
                        <li>100% free consultation &ndash; tailored strategy for your business</li>
                    </ul>
                </div>
            </div>
          </div>
        </div>
    </section>

    {{-- ═══════════════════════════════════════════════════════════ --}}
    {{-- CLIENT REVIEWS — full testimonial archive --}}
    {{-- ═══════════════════════════════════════════════════════════ --}}
    <section class="w-full bg-white py-16 sm:py-20 lg:py-24">
        <div class="w-full max-w-[1380px] mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-wrap justify-between items-start gap-8 mb-10 sm:mb-14">
                <h2 class="font-display text-3xl sm:text-4xl font-bold text-black tracking-[-0.02em]">Client Reviews</h2>
                <p class="max-w-sm text-sm text-black/70 leading-relaxed">
                    Don&rsquo;t just take our word for it, hear from business owners who&rsquo;ve hired through Remote Leverage. See why quality makes all the difference!
                </p>
            </div>

            @include('blocks.testimonials', ['testimonials' => $testimonials])
        </div>
    </section>

    <script>
        (function () {
            var duration = 10 * 60;
            var key = 'rl_thankyou_cd';
            var endTime = sessionStorage.getItem(key);
            var now = Math.floor(Date.now() / 1000);
            if (!endTime || parseInt(endTime, 10) < now) {
                endTime = now + duration;
                sessionStorage.setItem(key, endTime);
            } else {
                endTime = parseInt(endTime, 10);
            }
            function updateTimer() {
                var current = Math.floor(Date.now() / 1000);
                var remaining = Math.max(0, endTime - current);
                var m = Math.floor(remaining / 60);
                var s = remaining % 60;
                var el = document.getElementById('rl-ty-timer');
                if (el) {
                    el.textContent = (m < 10 ? '0' + m : m) + ':' + (s < 10 ? '0' + s : s);
                }
            }
            updateTimer();
            setInterval(updateTimer, 1000);
        })();
    </script>
@endsection
