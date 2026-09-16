<?php

use App\Support\BlockDefaults;

/**
 * Title: Full Page - Service Hiring
 * Slug: remote-leverage/service-hiring-full
 * Categories: remote-leverage
 * Description: Production's /Service-Hiring/ — the "Meeting With Hiring Manager" onboarding booking page (migrated 2026-09-16).
 */

// Production page 36258 is one Elementor container holding a heading, a two-paragraph text
// widget and an HTML widget with a bare Calendly *inline* embed — nothing else, and no
// section below it before the footer.
//
// The scheduler is reproduced literally rather than swapped for the `booking` or
// `vacalendar-hero` blocks. (Their slugs are written without the "acf/" prefix on purpose:
// `wp acorn blocks:inventory` scans this file for that prefix as plain text, so naming them
// the usual way here would index this page as a usage of two blocks it does not render.)
//
// Both of those render the in-house Livewire wizard, which reproduces production's
// `calendly_multistep` widget (the one on /vacalendar/) and resolves its event type from the
// lead's revenue tier — t0 or t10. This page books the post-deposit "Onboarding + Applicant
// Criteria" event, which has no role in CalendlyEventTypeRoleResolver, so routing it through
// the wizard would book the wrong meeting. See app/Blocks/CalendlyEmbedBlock.php.
//
// The $100 deposit this copy refers to is collected on /virtual-assistant-hiring-manager-refundable-deposit/.
echo BlockDefaults::patternBlock('calendly-embed', [
    'calendly_url' => 'https://calendly.com/d/cxqp-9vk-mvc/remote-leverage-onboarding-applicant-criteria?hide_event_type_details=1&hide_gdpr_banner=1',
    'heading' => 'Meeting With Hiring Manager',
    'intro' => "During this session we’ll walk you through the steps to hiring a Virtual Assistant, as well as go through all of your criteria in depth to make sure we vet the right applicants for the role you’re trying to fill.\n\nNote: Requires \$100 refundable deposit to start. If you choose to not proceed with any candidates we bring you, or decide to cancel at any time prior to hiring an applicant through us, you can get a full refund of the deposit.",
    'background' => 'navy-violet',
    'min_height' => 700,
    'max_width' => 0,
], ['align' => 'full']);
