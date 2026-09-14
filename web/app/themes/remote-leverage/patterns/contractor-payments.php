<?php
/**
 * Title: Full Page - Contractor Payments
 * Slug: remote-leverage/contractor-payments
 * Categories: remote-leverage
 * Description: 1:1 complete Contractor Payments product landing page.
 */
?>
<!-- wp:acf/product-hero {"name":"acf/product-hero","data":{"badge":"Global Cross-Border Payouts","headline":"Contractor Payments for Your Global Team, Simplified","subtitle":"Pay international contractors across borders, all in one platform. Transparent currency conversion, automated tax reporting, and zero payment delays.","cta_primary_text":"Schedule Consultation","cta_primary_url":"#booking-footer","cta_secondary_text":"How It Works","cta_secondary_url":"#payment-flow","stats":[{"value":"2,000+","label":"Contractors paid"},{"value":"25+","label":"Countries covered"},{"value":"01","label":"Invoice per pay cycle"}]},"align":"full","mode":"preview"} /-->

<!-- wp:acf/client-logos-marquee {"name":"acf/client-logos-marquee","align":"full","mode":"preview"} /-->

<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"5rem","bottom":"5rem"}}},"backgroundColor":"bg-light","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull has-bg-light-background-color has-background" style="padding-top:5rem;padding-bottom:5rem">
    <!-- wp:acf/feature-cards {"name":"acf/feature-cards","data":{"headline":"Clear on currency, clear on cost. Always.","columns":"3","cards":[{"title":"Fund in USD, Pay Locally","desc":"Contractors are paid in USD or their local currency via direct bank deposit, with guaranteed mid-market exchange rates.","badge":"Multi-Currency"},{"title":"Verified Timesheets & Milestones","desc":"Review and approve verified hours, tracked deliverables, and contractor invoices before releasing payment.","badge":"Verification"},{"title":"Full Audit Trail & Receipts","desc":"Every payout generates a legally binding, downloadable invoice and tax receipt with full transaction traceability.","badge":"Compliance"}]},"align":"full","mode":"preview"} /-->
</div>
<!-- /wp:group -->

<div id="payment-flow">
<?= \App\Support\BlockDefaults::renderProcessSteps([
    'headline' => 'How cross-border payouts work',
    'badge' => 'Seamless Pay Cycle',
    'steps' => [
        [
            'number' => '01',
            'title' => 'Contractors log verified hours',
            'desc' => 'Your remote team logs time and project milestones through the platform or your existing timesheet tool.',
        ],
        [
            'number' => '02',
            'title' => 'You approve one invoice',
            'desc' => 'Receive a single, consolidated invoice in USD for your entire global roster with zero hidden fees.',
        ],
        [
            'number' => '03',
            'title' => 'All contractors get paid',
            'desc' => 'Funds disburse directly into your contractors bank accounts across Latin America and worldwide.',
        ],
    ],
]) ?>
</div>

<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"5rem","bottom":"5rem"}}},"backgroundColor":"white","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull has-white-background-color has-background" style="padding-top:5rem;padding-bottom:5rem">
    <!-- wp:acf/feature-cards {"name":"acf/feature-cards","data":{"headline":"Paying a contractor is easy. Paying them correctly is not.","columns":"3","cards":[{"title":"Proper Worker Classification","desc":"We shield your company from misclassification penalties by establishing clear independent contractor status.","badge":"Classification"},{"title":"Bilingual Localized Agreements","desc":"Custom contracts compliant with local jurisdictional labor regulations, IP assignment, and confidentiality laws.","badge":"Legal"},{"title":"Automated W-8BEN Reporting","desc":"Collect, validate, and store mandatory international tax documentation to ensure seamless IRS audit compliance.","badge":"Tax Reporting"}]},"align":"full","mode":"preview"} /-->
</div>
<!-- /wp:group -->

<!-- wp:pattern {"slug":"remote-leverage/hire-va-4-testimonials"} /-->

<!-- wp:pattern {"slug":"remote-leverage/hire-va-4-faq"} /-->

<!-- wp:pattern {"slug":"remote-leverage/hire-va-4-booking-footer"} /-->
