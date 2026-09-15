<?php

use App\Support\BlockDefaults;

/**
 * Title: Full Page - Sample Applicant Recordings
 * Slug: remote-leverage/samples-content
 * Categories: remote-leverage
 * Description: 1:1 reproduction of candidate sample recordings page with video introductions, filterable audio voice auditions, and booking funnel.
 */
?>
<!-- wp:html -->
<section class="rl-samples-hero-banner relative w-full overflow-hidden min-h-[420px] lg:min-h-[480px] flex items-center bg-[#18112C]" style="background-image: url('<?= get_theme_file_uri('public/images/samples/Header-samples.jpg') ?>'); background-position: right center; background-repeat: no-repeat; background-size: cover;">
    <!-- Dark Curved Overlay matching Production -->
    <div class="absolute inset-0 bg-gradient-to-r from-[#18112C] via-[#18112C]/90 lg:via-[#18112C]/85 to-transparent z-0 pointer-events-none"></div>

    <div class="relative z-10 w-full max-w-[1380px] mx-auto px-4 sm:px-6 lg:px-8 py-12 sm:py-16 lg:py-20">
        <div class="max-w-2xl text-left">
            <h1 class="font-display text-3xl sm:text-4xl lg:text-[50px] font-bold text-white tracking-tight leading-[1.1] mb-4" style="color: #ffffff !important;">
                Sample Applicant<br class="hidden sm:inline"> Recordings
            </h1>
            
            <p class="text-sm sm:text-base lg:text-[18px] text-white/90 font-normal leading-relaxed mb-8 max-w-xl" style="color: rgba(255, 255, 255, 0.9) !important;">
                Meet top 1% talent ready to help your business grow. We have thousands of English-fluent virtual assistants who are vetted, experienced, and available to onboard now.
            </p>

            <div>
                <a 
                    href="/vacalendar" 
                    class="inline-flex items-center gap-3 px-8 py-4 rounded-full bg-[#7B2BF9] text-white font-bold text-sm sm:text-base tracking-wider uppercase shadow-xl hover:bg-[#6D28D9] transition duration-200"
                >
                    <span>FIND MY NEXT HIRE</span>
                    <span class="w-6 h-6 rounded-full border border-white/40 flex items-center justify-center">
                        <svg class="w-3.5 h-3.5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                            <polyline points="12 5 19 12 12 19"></polyline>
                        </svg>
                    </span>
                </a>
            </div>
        </div>
    </div>
</section>
<!-- /wp:html -->

<?= BlockDefaults::renderSampleApplicantVideos() ?>

<?= BlockDefaults::renderSampleApplicantAudio() ?>

<!-- wp:pattern {"slug":"remote-leverage/hire-va-4-booking-footer"} /-->
