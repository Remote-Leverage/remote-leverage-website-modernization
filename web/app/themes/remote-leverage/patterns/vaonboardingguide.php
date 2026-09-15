<?php

use App\Support\BlockDefaults;

/**
 * Title: Full Page - VA Onboarding Guide (Client Video Guide)
 * Slug: remote-leverage/vaonboardingguide
 * Categories: remote-leverage
 * Description: Production's /vaonboardingguide/ — the six-video client onboarding guide that /services/ links out to.
 */

/*
 * Production page 6830, template `elementor_header_footer`. Migrated 1:1 on 2026-09-15.
 * Copy transcribed verbatim from the live page; tokens read off it with getComputedStyle.
 *
 * THIS IS NOT /onboardingguide/. The two are different pages with overlapping video
 * libraries and they must not be merged:
 *   - /onboardingguide/  → patterns/onboardingguide.php, 28 videos, the cold-calling /
 *     Mojo programme guide, FAQ + reference links, noindex on production.
 *   - /vaonboardingguide/ → this file, 6 videos, the general VA onboarding hand-off that
 *     the /services/ onboarding card links to, indexed on production.
 * Videos 983685750 and 983685143 appear on both. The captions here were cross-checked
 * against that pattern's Part 2 rows, which is how the caption↔video pairing was settled:
 * production emits each caption *after* its own player inside the same card container.
 *
 * WHAT IS NOT REPRODUCED, AND WHY
 *
 * 1. Nothing is omitted — every band, card, link and button on the live page is here. The
 *    page has no hidden-at-every-breakpoint containers (checked with getComputedStyle),
 *    unlike /services/, whose mobile stack is separate stale content. There is no page art
 *    either: every element is an embed or type, so this page has no resources/images/pages/ dir.
 *
 * 1b. THE ONE DELIBERATE DEVIATION is the phone gutter. At 1440px this page is pixel-exact
 *    against production — band heights, the 1170px column, every card's width, height and
 *    origin. At 400px production runs its columns edge-to-edge (e-con-inner left=0, width=400)
 *    so the intro paragraph's text touches both screen edges; the 20px gutter below keeps it
 *    off them, which also matches patterns/onboardingguide.php. Same reason the 24px intro and
 *    32px section heading step down one notch on small screens, where production holds both.
 *
 * PRODUCTION DEFECTS REPRODUCED DELIBERATELY — do not "fix" these in passing
 *
 * 2. The sixth embed, 1108525394 ("Lead Generation Platforms"), carries NO `h=` privacy
 *    hash on production, and so does not play there either: Vimeo answers with "We couldn't
 *    verify the security of your connection." The other five play. The src below is
 *    production's verbatim — inventing a hash would be guessing at a credential. Getting the
 *    real hash out of the Vimeo account fixes this page and production at the same time.
 *
 * 3. The "Phone System For Your Virtual Assistant" caption still links to openphone.com
 *    (with a leftover `gclid=test` query parameter) while its own accordion row and CTA
 *    button below it both point at the renamed get.quo.com. Production's inconsistency,
 *    transcribed as-is.
 *
 * 3b. Production puts the Payroll Sheet card's CTA 14px under its caption and the Phone System
 *    card's CTA 20px under its own — the same card shape authored two ways in Elementor.
 *    acf/video-card-grid uses 14px for both, so the last row is 6px shorter than production's
 *    422px. Encoding an authoring slip as a block field would cost more than the 6px is worth.
 *
 * INDEXING
 *
 * 4. No `rl:noindex` marker: production serves this URL `index, follow`, and it is the one
 *    page in its family that does. /services/ and /store/ are `noindex, nofollow`;
 *    /payment/, /vaonboardingform/, /signedup/ and /onboardingguide/ are `noindex, follow`.
 *    That is deliberate on the evidence — /services/ prints this URL in body copy as an
 *    ungated lead magnet — so the migration follows production rather than the family.
 *
 * Design tokens read off production at 1440px with getComputedStyle:
 *   container 1170px (e-con-inner max-width min(100%, 1170px))
 *   intro band linear-gradient(200deg, #342567, #6200A4), 60px inner padding, h4 24/24 500 white, left
 *   links band #FAFAFA, 40px inner padding, heading 32/32 700 #342567 centred
 *   accordion rows white, 14px/20px padding, 2px #EEEEEE bottom rule, 16px/600 #1F2124,
 *     purple #6200A4 plus/minus icon, open body #EEEEEE with 20px padding and 16/24 #333
 *   video band white, 30px inner padding; card and CTA tokens live on acf/video-card-grid
 *
 * Production sets headings in League Spartan and body in Poppins; the theme dropped both in
 * favour of Inter Display (see the @font-face note in resources/css/app.css), so everything
 * below uses `font-display` or the theme sans.
 */

// 1210 - 2×20 = production's 1170px inner column.
$wrap = 'w-full max-w-[1210px] mx-auto px-5';

// Elementor accordion rows: white, 14px/20px padding, 2px #EEE bottom rule, 16px/600 #1F2124.
$accRow = 'group bg-white';
$accSummary = 'border-b-2 border-[#EEEEEE] flex cursor-pointer list-none items-center justify-between gap-2.5 px-5 py-[14px] text-[16px] font-semibold leading-6 text-[#1F2124] [&::-webkit-details-marker]:hidden';
$accBody = 'bg-[#EEEEEE] p-5 text-[16px] leading-6 text-[#333] [&_p]:mb-4 [&_p:last-child]:mb-0 [&_a]:text-[#6200A4] [&_a]:no-underline [&_a:hover]:underline';

// Font Awesome plus / minus, #6200A4 at 16px — production swaps one for the other on open.
$plus = '<svg class="h-4 w-4 shrink-0 fill-[#6200A4] group-open:hidden" viewBox="0 0 448 512" aria-hidden="true"><path d="M416 208H272V64c0-17.67-14.33-32-32-32h-32c-17.67 0-32 14.33-32 32v144H32c-17.67 0-32 14.33-32 32v32c0 17.67 14.33 32 32 32h144v144c0 17.67 14.33 32 32 32h32c17.67 0 32-14.33 32-32V304h144c17.67 0 32-14.33 32-32v-32c0-17.67-14.33-32-32-32z"/></svg>';
$minus = '<svg class="hidden h-4 w-4 shrink-0 fill-[#6200A4] group-open:block" viewBox="0 0 448 512" aria-hidden="true"><path d="M416 208H32c-17.67 0-32 14.33-32 32v32c0 17.67 14.33 32 32 32h384c17.67 0 32-14.33 32-32v-32c0-17.67-14.33-32-32-32z"/></svg>';

/*
 * Reference links. Both rows are a single "Click Here" link; production ships them closed.
 * The first row's target is the same timesheet download the Payroll Sheet card links to.
 */
$links = [
    ['Sample VA Timesheet', 'https://remoteleverage.com/download/2783/?tmstv=1698705237'],
    ['Quo (Previously Known As OpenPhone)', 'https://get.quo.com/1adhzhzvcw2l'],
];

/*
 * The six training embeds, in production order, each with its exact player URL.
 *
 * The `h=` hash is part of an unlisted video's address, not decoration: a player without it
 * renders a restriction notice rather than erroring, so these are transcribed from the live
 * markup, never reconstructed from an ID. 1108525394 has no hash on production — see note 2.
 *
 * Widths reproduce production's rhythm: two full-width heroes, then two pairs.
 */
$player = fn (string $id, string $hash = ''): string => 'https://player.vimeo.com/video/'.$id
    .'?color&autopause=0&loop=0&muted=0&title=1&portrait=1&byline=1'
    .($hash !== '' ? '&h='.$hash : '').'#t=';

$cards = [
    [
        'video_url' => $player('983685750', '0a74ac3eed'),
        'title' => 'Virtual Assistant Payroll Sheet',
        'title_url' => 'https://remoteleverage.com/download/2783/?tmstv=1698705237',
        'width' => 'full',
        'cta_text' => 'Download Template',
        'cta_url' => 'https://remoteleverage.com/download/2783/?tmstv=1698705237',
    ],
    [
        'video_url' => $player('983685143', '00e5f1fc1f'),
        'title' => 'Virtual Assistant Work Schedule',
        'width' => 'full',
    ],
    [
        'video_url' => $player('983710844', 'bc80fdd4a8'),
        'title' => 'How to Train Your Virtual Assistant',
        'width' => 'half',
    ],
    [
        'video_url' => $player('1034356585', '83c383e1bb'),
        'title' => 'Incentivizing Your Virtual Assistant with Bonuses',
        'width' => 'half',
    ],
    [
        'video_url' => $player('1000120463', 'c971f97906'),
        'title' => 'Phone System For Your Virtual Assistant',
        // Production's own stale link: the caption still points at OpenPhone, with a leftover
        // `gclid=test`, while the button beside it points at the renamed Quo. See note 3.
        'title_url' => 'https://www.openphone.com/referral/AaZS_R4?utm_medium=ppc&utm_content=Email&gclid=test',
        'width' => 'half',
        'cta_text' => 'Click Here to Sign Up',
        'cta_url' => 'https://get.quo.com/1adhzhzvcw2l',
    ],
    [
        // No `h=` hash on production; this embed does not play there either. See note 2.
        'video_url' => $player('1108525394'),
        'title' => 'Lead Generation Platforms',
        'width' => 'half',
    ],
];
?>

<!-- ============ §1 INTRO BAND ============ -->
<!-- wp:group {"align":"full"} -->
<div class="wp-block-group alignfull" style="background:linear-gradient(200deg, var(--color-brand-navy) 0%, var(--color-brand-purple-deep) 100%)">
    <div class="<?= $wrap ?> py-10 lg:py-[60px]">
        <h4 class="font-display text-[18px] leading-[26px] sm:text-[24px] sm:leading-[24px] font-medium text-white">The videos in this onboarding guide are designed to address the most common questions and challenges you may encounter as you integrate your new Virtual Assistant. We highly recommend reviewing and implementing the strategies shared in these videos to ensure a smooth and successful onboarding experience.</h4>
    </div>
</div>
<!-- /wp:group -->

<!-- ============ §2 LINKS FOR FUTURE REFERENCE ============ -->
<!-- wp:group {"align":"full"} -->
<div class="wp-block-group alignfull bg-[#FAFAFA]">
    <div class="<?= $wrap ?> py-10">
        <h4 class="font-display text-[26px] leading-[30px] sm:text-[32px] sm:leading-[32px] font-bold text-center text-brand-navy">Links for Future Reference</h4>

        <?php /* @bespoke: acf/accordion-faq is the theme's only accordion and it is wrong here
                 on three counts — it forces two balanced columns (this is one column of two
                 rows), it emits Schema.org FAQPage structured data (these are download links,
                 not questions, and this page is indexed, so mislabelling them is a live SEO
                 claim), and its rows are black-ruled 18px bold, not production's white cards
                 with a 2px #EEE rule and a purple plus. acf/data-table and acf/next-steps-panel
                 render neither a disclosure nor a link list. Plain <details> keeps it
                 keyboard- and no-JS-accessible, and matches patterns/onboardingguide.php,
                 which reached the same conclusion for the same block. */ ?>
        <div class="mt-5">
            <?php foreach ($links as [$label, $href]) { ?>
                <details class="<?= $accRow ?>">
                    <summary class="<?= $accSummary ?>"><span><?= esc_html($label) ?></span><?= $plus.$minus ?></summary>
                    <div class="<?= $accBody ?>"><p><a href="<?= esc_url($href) ?>" rel="noopener" target="_blank">Click Here</a></p></div>
                </details>
            <?php } ?>
        </div>
    </div>
</div>
<!-- /wp:group -->

<!-- ============ §3 TRAINING VIDEOS (SIX VIMEO EMBEDS) ============ -->
<?= BlockDefaults::renderVideoCardGrid($cards) ?>
