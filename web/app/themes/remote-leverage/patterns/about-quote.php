<?php
/**
 * Title: About Testimonial Quote
 * Slug: remote-leverage/about-quote
 * Categories: remote-leverage
 */
?>
<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"4rem","bottom":"4rem"}}},"layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull" style="padding-top:4rem;padding-bottom:4rem">
    <div class="w-full max-w-[1380px] mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-white rounded-3xl p-8 sm:p-12 border border-black/5 shadow-[0_4px_24px_rgba(0,0,0,0.03)] flex flex-col md:flex-row items-center gap-8 lg:gap-12">
            <div class="w-full md:w-5/12 flex-shrink-0">
                <img src="<?= \App\Support\BlockDefaults::resolveImageUrl(\App\Support\BlockDefaults::getAttachmentId('magnific_EqBRpqJuuO-1-1.png')) ?: 'http://remoteleverage-v2.test/app/uploads/2026/09/magnific_EqBRpqJuuO-1-1.png' ?>"
                    alt="Client testimonial" class="w-full h-auto rounded-2xl object-cover shadow-sm" loading="lazy" decoding="async">
            </div>
            <div class="w-full md:w-7/12 flex flex-col gap-4">
                <span class="text-[#250D4A] text-4xl sm:text-5xl font-serif font-black leading-none select-none">“</span>
                <blockquote class="font-display text-2xl sm:text-3xl font-bold tracking-[-0.02em] text-black leading-snug">
                    "It wasn't about paying less for an employee – it was really about finding someone with work ethic."
                </blockquote>
                <cite class="text-base text-black/70 font-semibold not-italic mt-2">
                    Stacy Do, Indoor Air Programs, Cincinnati, Ohio
                </cite>
            </div>
        </div>
    </div>
</div>
<!-- /wp:group -->
