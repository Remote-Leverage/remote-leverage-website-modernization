<?php

use App\Support\BlockDefaults;

/**
 * Title: Full Page - Referral Program Thank You (Deposit)
 * Slug: remote-leverage/referral-program-thank-you-deposit
 * Categories: remote-leverage
 * Description: Production's /referral-program-thank-you-page-deposit/ — the referral program page behind a payment-confirmation banner (migrated 2026-09-15).
 */

// Production page 50304 is page 30560 with a "Payment Successful!" banner prepended and
// nothing else changed, so this composes the same shared body rather than copying it.
echo BlockDefaults::renderPaymentSuccessBanner();

require __DIR__.'/../resources/patterns/referral-program-body.php';
