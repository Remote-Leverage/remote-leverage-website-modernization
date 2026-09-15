<?php

use App\Support\BlockDefaults;

/**
 * Title: Full Page - Payment
 * Slug: remote-leverage/payment
 * Categories: remote-leverage
 * Description: Production's /payment/ balance-payment page — a hosted JotForm on a themed band (migrated 2026-09-15).
 */

// Production (page 10768) is one bare Elementor HTML widget holding JotForm 242638425990061 —
// no heading, no copy. That form is configured for Stripe Checkout (payment_type
// "stripeCheckout"), so submitting hands the customer to Stripe's hosted payment page and the
// record lives in the JotForm account, not here.
//
// The heading, intro and card are additions beyond production, added on request 2026-09-15 so
// the page reads like the rest of the site. The form's interior is JotForm's own and cannot be
// styled from this side.
echo BlockDefaults::renderJotformEmbed('242638425990061', [
    'title' => 'Remote Leverage payment form',
    'heading' => 'Complete Your Payment',
    'intro' => 'Enter the balance remaining on your quote. You’ll be taken to our secure payment provider to finish checking out.',
    'background' => 'light',
    'card' => true,
    'max_width' => 860,
    'min_height' => 620,
]);
