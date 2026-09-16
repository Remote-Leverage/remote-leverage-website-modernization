<?php

use App\Support\BlockDefaults;

/**
 * Title: Homepage Why Remote Leverage
 * Slug: remote-leverage/homepage-why-rl
 * Categories: remote-leverage
 * Description: Six frosted benefit cards over the homepage's mesh-gradient band.
 */

/*
 * The band is a mesh, not a vertical ramp: sampling Homepage V1.png across a 5x5 grid between
 * y=755 and y=1563 gives a pale blue at top-centre (#C6CFFB), a violet just right of it
 * (#DBC6F8), a pink wash along the whole bottom edge (#E9CAEC to #F1BBE4), and the page ground
 * #F4F6FC at every corner. Three soft radial stops over that ground reproduce it; a single
 * linear-gradient cannot, because the colour varies horizontally as much as vertically.
 */
$band = implode(',', [
    'radial-gradient(70% 55% at 50% 0%, #C6CFFB 0%, rgba(198,207,251,0) 70%)',
    'radial-gradient(52% 48% at 76% 0%, #DBC6F8 0%, rgba(219,198,248,0) 72%)',
    'radial-gradient(85% 42% at 50% 100%, #EFBFE6 0%, rgba(239,191,230,0) 78%)',
]);

$cards = [
    [
        'title' => 'Top-tier Latin<br>American talent',
        'desc' => 'Remote Leverage finds the top 1% of talent for US businesses. Tight time zone overlap, strong cultural alignment, and fluent English.',
    ],
    [
        'title' => 'No contracts,<br>no ongoing fees',
        'desc' => "You're not locked into long-term commitments. And, there are no ongoing fees. Ever.",
    ],
    [
        'title' => 'Hire in 48 hours',
        'desc' => "We move fast, just like you. We'll have a shortlist of candidates for you to interview in 48 hours. Hire in less than a week.",
    ],
    [
        'title' => '$0 to get started',
        'desc' => "Pay nothing unless you hire. There's no risk to get started, plus you're covered by our 12-month guarantee.",
    ],
    [
        'title' => 'Background<br>checks included',
        'desc' => 'We receive thousands of applicants a day and only accept the best after rigorous vetting, background, and employment checks.',
    ],
    [
        'title' => 'Productivity<br>management',
        'desc' => 'We provide productivity monitoring, time management solutions, and training support – so you hire with confidence.',
    ],
];
?>
<!-- wp:group {"align":"full","className":"rl-home-why-band rl-screen flex flex-col justify-center px-4 sm:px-6 lg:px-8","style":{"spacing":{"padding":{"top":"6.75rem","bottom":"6.5rem"}}},"layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull rl-home-why-band rl-screen flex flex-col justify-center px-4 sm:px-6 lg:px-8" style="padding-top:6.75rem;padding-bottom:6.5rem;background-color:#F4F6FC;background-image:<?= esc_attr($band) ?>">
    <!-- wp:heading {"textAlign":"center","level":2,"style":{"typography":{"lineHeight":"1.08","letterSpacing":"-0.02em"},"spacing":{"margin":{"bottom":"3.75rem"}}},"fontSize":"huge"} -->
    <h2 class="wp-block-heading has-text-align-center has-huge-font-size" style="letter-spacing:-0.02em;line-height:1.08;margin-bottom:3.75rem">Why Remote Leverage</h2>
    <!-- /wp:heading -->

    <?= BlockDefaults::renderFeatureCards('3', [], $cards, '', 'flush', 'frosted') ?>
</div>
<!-- /wp:group -->
