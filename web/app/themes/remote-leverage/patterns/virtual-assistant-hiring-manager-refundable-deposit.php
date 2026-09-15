<?php

use App\Support\BlockDefaults;

/**
 * Title: Full Page - VA Hiring Manager Refundable Deposit
 * Slug: remote-leverage/virtual-assistant-hiring-manager-refundable-deposit
 * Categories: remote-leverage
 * Description: Production's /virtual-assistant-hiring-manager-refundable-deposit/ — the $100 refundable deposit checkout (migrated 2026-09-15).
 */

// Production page 38897 is a single bespoke `rl_payment_gateway` Elementor widget from the
// rl-elementor-blocks plugin — a Stripe Payment Element checkout — and nothing else. The
// widget's controls are ported one-for-one onto acf/payment-gateway; the amount is resolved
// server-side from these values, never from the browser. See docs/stripe-payments.md.
echo BlockDefaults::renderPaymentGateway([
    'product_title' => 'Virtual Assistant Hiring Manager Refundable Deposit',
    'product_price' => 100,
    'product_currency' => 'USD',
    'payment_terms_title' => 'Payment Terms',
    'payment_terms_text' => 'Client may request a refund of 100% their full deposit at any time prior to choosing an applicant to work with.',
    'payment_description_template' => '[Salvatori Payment Form] Remote Leverage Onboarding + Applicant Criteria with {name}',
    'layout' => 'two_columns',
]);
