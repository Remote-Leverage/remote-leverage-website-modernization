<?php

use App\Support\BlockDefaults;

/**
 * Title: Full Page - VA Client Onboarding Form
 * Slug: remote-leverage/vaonboardingform
 * Categories: remote-leverage
 * Description: Production's /vaonboardingform/ client onboarding page — a hosted JotForm on a themed band (migrated 2026-09-15).
 */

// Production (page 7322) is one bare Elementor HTML widget holding JotForm 242937701106049 —
// the 21-question "Client Onboarding Form" card — with no heading or copy around it.
//
// The heading, intro and card are additions beyond production, added on request 2026-09-15 so
// the page reads like the rest of the site. The form's interior is JotForm's own and cannot be
// styled from this side.
echo BlockDefaults::renderJotformEmbed('242937701106049', [
    'title' => 'Client Onboarding Form',
    'heading' => 'Tell Us About Your Ideal Hire',
    'intro' => 'A few questions about your ideal hire so your Hiring Manager can start vetting candidates. It takes about five minutes.',
    'background' => 'light',
    'card' => true,
    'max_width' => 900,
    'min_height' => 700,
]);
