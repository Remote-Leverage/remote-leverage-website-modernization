<?php

/**
 * Shared /referral-program/ body.
 *
 * Not a block pattern. It lives outside the theme's patterns/ directory because WordPress
 * core scans that tree recursively and would reject a headerless file.
 *
 * Included by patterns/referral-program.php and
 * patterns/referral-program-thank-you-deposit.php. On production the thank-you page (50304)
 * is byte-for-byte the referral program page (30560) with a "Payment Successful!" banner
 * prepended, so the body lives here once rather than being duplicated and left to drift.
 *
 * Layout, type scale and colours were read off the live page with getComputedStyle
 * (2026-09-15). Production's Elementor container measures 1260px, but the theme's canonical
 * container is 1380px (page-migration SKILL.md §1) and that is what these sections use.
 */

use App\Support\BlockDefaults;

$wrap = 'w-full max-w-[1380px] mx-auto px-4 sm:px-6 lg:px-8';

// ── Hero: eyebrow, title, promise, earnings figures, CTA, returning-referrer link ──────
echo BlockDefaults::renderReferralProgramHero();

// ── How it Works ───────────────────────────────────────────────────────────────────────
// Production keeps these four steps as a plain list inside the hero. They render here through
// acf/process-steps — the theme's numbered timeline, already used by /affiliate-program/ and
// the partner pages — so the page reads in the brand's own language (refresh, 2026-09-15).
?>
<!-- wp:html -->
<section class="w-full bg-surface-white pt-16 sm:pt-20 lg:pt-24 pb-4">
    <div class="<?= esc_attr($wrap) ?> text-center">
        <h2 class="font-display font-bold text-brand-navy text-[30px] leading-[36px] sm:text-[42px] sm:leading-[50px] tracking-[-1.26px]">
            How it Works
        </h2>
        <p class="mt-4 text-[16px] leading-[26px] text-black/70 max-w-[640px] mx-auto">
            Four steps from signing up to getting paid. You&rsquo;ll qualify for the commission as
            soon as your referral hires with us.
        </p>
    </div>
</section>
<!-- /wp:html -->
<?php

echo BlockDefaults::renderReferralProgramSteps();

// ── Section heading above the four proof rows ──────────────────────────────────────────
?>
<!-- wp:html -->
<section class="w-full bg-surface-white pt-16 sm:pt-20 lg:pt-24 pb-4">
    <div class="<?= esc_attr($wrap) ?> text-center">
        <h2 class="font-display font-bold text-brand-navy text-[30px] leading-[36px] sm:text-[42px] sm:leading-[50px] lg:text-[52px] lg:leading-[60px] tracking-[-1.56px] max-w-[900px] mx-auto">
            Join Us As We Change the Way Employers Find Global Talents
        </h2>
    </div>
</section>
<!-- /wp:html -->
<?php

// ── Four alternating image/copy rows ───────────────────────────────────────────────────
// acf/media-copy already renders exactly this shape (image one side, heading + rich copy the
// other, alternating via image_position), so these reuse it rather than hand-rolling a grid.
foreach (BlockDefaults::referralProgramRows() as $row) {
    echo BlockDefaults::renderMediaCopy([
        'headline' => $row['headline'],
        'body' => $row['body'],
        'image' => $row['image'],
        'image_position' => $row['image_position'],
        'tone' => 'white',
        // Production aligns each row's copy toward its artwork and sets headings in brand-navy.
        'text_align' => $row['image_position'] === 'right' ? 'right' : 'left',
        'heading_color' => 'navy',
        // The art is 440px square on production; the block's 560px default would upscale it.
        'image_max_width' => 440,
        'cta_text' => '',
        'cta_url' => '',
    ]);
}

// ── Closing CTA band over production's wave artwork ────────────────────────────────────
// Reuses acf/cta-banner's 'band' variant; the wave is supplied through the background_image
// field added for this page, which leaves the flat-gradient default untouched elsewhere.
echo BlockDefaults::renderCtaBanner([
    'variant' => 'band',
    'headline' => 'Ready to Earn $1,000 Per Referral?',
    'subheadline' => 'Help growing businesses hire elite virtual assistants — and get rewarded for every client you send our way.',
    'cta_text' => 'Join Here',
    'cta_url' => '/referral-dashboard/?tab=sign-up',
    'background_image' => BlockDefaults::pageImg('referral-program', 'Partnership-CTA-BG-Image-Long.png'),
]);
