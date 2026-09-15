<?php

declare(strict_types=1);

namespace App\Blocks;

use App\Support\BlockDefaults;
use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;

/**
 * Production's post-payment confirmation banner, shown at the top of
 * /referral-program-thank-you-page-deposit/: a check-circle mark, "Payment Successful!" and
 * two lines of reassurance, above the page's normal content.
 *
 * Checked before building: acf/cta-banner is a conversion band with a CTA rather than a
 * confirmation state, and acf/impact-report-hero is a lead-capture hero. Nothing in the
 * inventory renders a transaction receipt state.
 */
class PaymentSuccessBannerBlock extends Block
{
    public $name = 'Payment Success Banner';

    public $slug = 'payment-success-banner';

    public $description = 'Confirmation banner shown after a successful payment.';

    public $category = 'remote-leverage';

    public $icon = 'yes-alt';

    public $keywords = ['payment', 'success', 'thank you', 'confirmation', 'receipt'];

    public $view = 'blocks.payment-success-banner';

    public $example = [
        'attributes' => [
            'mode' => 'preview',
            'data' => [
                'headline' => 'Payment Successful!',
                'is_preview' => true,
            ],
        ],
    ];

    public function with(): array
    {
        return [
            'headline' => BlockDefaults::cleanText(get_field('headline') ?: 'Payment Successful!'),
            'message' => BlockDefaults::cleanText(get_field('message') ?: 'Thank you for your purchase. Your transaction has been completed successfully.'),
            'submessage' => BlockDefaults::cleanText(get_field('submessage') ?: 'A confirmation email will be sent to you shortly.'),
        ];
    }

    public function fields(): array
    {
        $fields = Builder::make('payment_success_banner_block');

        $fields
            ->addText('headline', [
                'label' => 'Headline',
                'default_value' => 'Payment Successful!',
            ])
            ->addTextarea('message', [
                'label' => 'Message',
                'rows' => 2,
                'default_value' => 'Thank you for your purchase. Your transaction has been completed successfully.',
            ])
            ->addTextarea('submessage', [
                'label' => 'Secondary message',
                'rows' => 2,
                'default_value' => 'A confirmation email will be sent to you shortly.',
            ]);

        return $fields->build();
    }
}
