<?php

use App\Support\BlockDefaults;

/**
 * Title: Admin VA Talent - The World's Best Administrative Talent
 * Slug: remote-leverage/admin-virtual-assistants-talent
 * Categories: remote-leverage
 * Description: Six frosted cards naming what an administrative VA takes off your desk, over the role page's mesh-gradient band.
 *
 * Structurally the homepage's "Why Remote Leverage" band — same frosted cards over the same kind
 * of mesh — but it carries a subheading the homepage has none of, and the mesh itself is not the
 * same mesh.
 */

/*
 * The band is a mesh, not a vertical ramp, and it is NOT the homepage's mesh: fitting the
 * homepage's three stops against 14,308 clean band pixels sampled off 01_Administrative_VAs.webp
 * leaves an RMS error of 8.52 with a max channel error of 33, failing in three structural ways.
 * The homepage has no right-hand pink at all, where this band's right margin is pink from its top
 * edge down (measured #F3DAF2 at x=1100, y=1164, against #F4F6FC predicted). The homepage puts
 * its pink peak at bottom-centre, where this band has a lavender valley there (#E8DBF4 at
 * x=518, y=1370) between two pink peaks at x≈900 and x≈134. And its top violet lobe sits too far
 * right and too broad.
 *
 * The four stops below fit the same pixels at RMS 2.57, max channel error 13. Dropping the
 * bottom-left lobe alone takes that back to 5.53, so it is load-bearing rather than decorative.
 * The band runs y≈651 to y≈1391 in the comp — 740px, not the 805 a first glance suggests, which
 * is why a sample at y=1440 reads as plain page ground.
 */
$band = implode(',', [
    'radial-gradient(30% 30% at 65% 0%, #D0BBF8 0%, rgba(208,187,248,0) 70%)',
    'radial-gradient(65% 75% at 45% 0%, #CAD5FA 0%, rgba(202,213,250,0) 75%)',
    'radial-gradient(45% 150% at 75% 100%, #EEC1E9 0%, rgba(238,193,233,0) 100%)',
    'radial-gradient(45% 70% at 25% 100%, #E9CBEC 0%, rgba(233,203,236,0) 75%)',
]);

$cards = [
    [
        'title' => 'Inbox &<br>Calendar Management',
        'desc' => 'Organizes schedules, manages email, and coordinates meetings so nothing falls through the cracks and your day runs on time.',
    ],
    [
        'title' => 'Data Entry &<br>CRM Management',
        'desc' => 'Updates records, maintains your CRM, and keeps business data accurate, current, and easy to find.',
    ],
    [
        'title' => 'Invoicing &<br>Payment Tracking',
        'desc' => 'Prepares invoices, tracks payments, and keeps financial records current and audit-ready.',
    ],
    [
        'title' => 'Call &<br>Email Handling',
        'desc' => 'Answers calls, responds to routine emails, and manages day-to-day communication with customers and vendors.',
    ],
    [
        'title' => 'Document &<br>Records Management',
        'desc' => 'Prepares, organizes, and updates spreadsheets, reports, and business files with accuracy and consistency.',
    ],
    [
        'title' => 'Custom<br>Support',
        'desc' => 'We can provide talent for custom roles to help with anything you need to keep daily operations on track and to grow.',
    ],
];
?>
<!-- wp:group {"align":"full","className":"rl-admin-va-talent-band flex flex-col justify-center px-4 sm:px-6 lg:px-8","style":{"spacing":{"padding":{"top":"6.75rem","bottom":"6.5rem"}}},"layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull rl-admin-va-talent-band flex flex-col justify-center px-4 sm:px-6 lg:px-8" style="padding-top:6.75rem;padding-bottom:6.5rem;background-color:#F4F6FC;background-image:<?= esc_attr($band) ?>">
    <!-- wp:heading {"textAlign":"center","level":2,"style":{"typography":{"lineHeight":"1.08","letterSpacing":"-0.02em"},"spacing":{"margin":{"bottom":"1.25rem"}}},"fontSize":"huge"} -->
    <h2 class="wp-block-heading has-text-align-center has-huge-font-size" style="letter-spacing:-0.02em;line-height:1.08;margin-bottom:1.25rem">The World's Best Administrative<br>Talent, Hired Directly for You</h2>
    <!-- /wp:heading -->

    <?php /* The comp sets this on three lines centred over the cards, spanning ~61% of the
         viewport. Left to the full 1380px container it runs as one near-full-width line. */ ?>
    <!-- wp:paragraph {"align":"center","className":"mx-auto max-w-[820px]","style":{"typography":{"fontSize":"1rem","lineHeight":"1.6"},"spacing":{"margin":{"bottom":"3.25rem"}}}} -->
    <p class="has-text-align-center mx-auto max-w-[820px]" style="font-size:1rem;line-height:1.6;margin-bottom:3.25rem">You hire talent directly into your business. No subscriptions, no monthly fees, and no markups on salary. <strong>Just deep-vetted professionals who keep your inbox, calendar, and daily operations running without the follow-up.</strong></p>
    <!-- /wp:paragraph -->

    <?= BlockDefaults::renderFeatureCards('3', [], $cards, '', 'flush', 'frosted') ?>
</div>
<!-- /wp:group -->
