<?php

use App\Support\BlockDefaults;

/**
 * Title: Full Page - Independent Contractor Agreement
 * Slug: remote-leverage/contractoragreement
 * Categories: remote-leverage
 * Description: Production's /contractoragreement/ e-sign page — a hosted JotForm Sign document on a themed band (migrated 2026-09-15).
 */

// Production (page 10183) is one bare Elementor container holding a single iframe and nothing
// else. It is a JotForm *Sign* document (242478488540063), not a classic form: signable
// documents are not served from form.jotform.com — that host answers "Form is missing" — so the
// embed points at www.jotform.com/sign/<id>/invite/<token>?signEmbed=1, the same URL production
// hard-codes. acf/jotform-embed gained a `product` option for this rather than the page growing
// a hand-written iframe; `product: form` remains the default and /payment/ and
// /vaonboardingform/ are untouched.
//
// The heading, intro and card are additions beyond production, matching the shape /payment/ and
// /vaonboardingform/ gained on 2026-09-15 so the funnel pages read like the rest of the site.
// The document's interior is JotForm's own and cannot be styled from this side.
//
// max_width 1170 is production's own container width for the embed, measured with
// getComputedStyle at 1440px. min_height 5000 is the height production's iframe settles at:
// the frame is scrolling="no", so under-reserving would clip the contract rather than scroll
// it. The postMessage listener in the block shrinks it if JotForm reports a smaller document.
echo BlockDefaults::renderJotformEmbed('242478488540063', [
    'product' => 'sign',
    'sign_invite' => '01j703pb6g46c5fff73807bfcf',
    'title' => 'Independent Contractor Agreement',
    'heading' => 'Independent Contractor Agreement',
    'intro' => 'Read the agreement below and sign it electronically. You’ll receive a signed copy by email as soon as you finish.',
    'background' => 'light',
    'card' => true,
    'max_width' => 1170,
    'min_height' => 5000,
]);
