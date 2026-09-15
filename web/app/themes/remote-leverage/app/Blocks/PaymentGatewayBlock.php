<?php

declare(strict_types=1);

namespace App\Blocks;

use App\Domains\Payment\Services\StripePaymentIntentGateway;
use App\Support\BlockDefaults;
use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;

/**
 * Production's refundable-deposit checkout — the Stripe Elements card form on
 * /virtual-assistant-hiring-manager-refundable-deposit/ (page 38897), ported from the
 * rl-elementor-blocks `rl_payment_gateway` Elementor widget.
 *
 * Checked before building: nothing in the theme takes money. acf/booking and
 * acf/booking-footer embed the Livewire scheduler, acf/impact-report-hero and
 * acf/hire-va-hero capture name/email into the Lead domain, and acf/jotform-embed renders a
 * hosted JotForm. None of them mount Stripe Elements, hold a server-resolved price, or have
 * a success state to reveal, so none could take an option to become this.
 *
 * The `rl-` class names are load-bearing: resources/js/payment-gateway.js selects the card,
 * the form, the columns, the success state and the submit button by them.
 */
class PaymentGatewayBlock extends Block
{
    public $name = 'Payment Gateway';

    public $slug = 'payment-gateway';

    public $description = 'Stripe Elements card checkout: customer details and a server-priced deposit.';

    public $category = 'remote-leverage';

    public $icon = 'money-alt';

    public $keywords = ['payment', 'stripe', 'checkout', 'deposit', 'card'];

    public $view = 'blocks.payment-gateway';

    public $example = [
        'attributes' => [
            'mode' => 'preview',
            'data' => [
                'product_title' => 'Enter Details',
                'product_price' => 100,
                'product_currency' => 'USD',
                'payment_terms_title' => 'Payment Terms',
                'layout' => 'two_columns',
                'is_preview' => true,
            ],
        ],
    ];

    /**
     * Occurrence counter for this render pass.
     *
     * The client posts this index and the server re-derives the price from the matching block
     * in the post's block tree (PaymentGatewayBlockResolver). It replaces the Elementor widget
     * id the legacy widget used as its handle, which Gutenberg has no equivalent of: ACF's own
     * `$block['id']` is generated per render and is not something the resolver can recompute
     * from parsed markup.
     */
    protected static int $instances = 0;

    public function with(): array
    {
        $index = self::$instances++;
        $gateway = app(StripePaymentIntentGateway::class);

        $successUrl = get_field('success_url');
        $successUrl = is_string($successUrl) ? trim($successUrl) : '';

        if ($successUrl === '') {
            $successUrl = (string) (config('services.stripe.default_thankyou_url') ?? '');
        }

        $postId = (int) ($this->post_id ?: (get_the_ID() ?: 0));

        return [
            'blockIndex' => $index,
            'blockId' => $this->domId($index),
            'postId' => $postId,
            'publishableKey' => $gateway->publishableKey(),
            'intentUrl' => $this->intentUrl(),
            'successUrl' => $successUrl,
            'isPreview' => (bool) $this->preview,

            'productTitle' => BlockDefaults::cleanText(get_field('product_title') ?: 'Enter Details'),
            'productPrice' => $this->price(),
            'productCurrency' => BlockDefaults::cleanText(get_field('product_currency') ?: 'USD'),
            'termsTitle' => BlockDefaults::cleanText(get_field('payment_terms_title') ?: 'Payment Terms'),
            'termsText' => BlockDefaults::cleanText(get_field('payment_terms_text') ?: $this->defaultTermsText()),
            'agreementText' => (string) (get_field('agreement_text') ?: $this->defaultAgreementText()),
            'layout' => get_field('layout') ?: 'one_column',
        ];
    }

    /**
     * Display price only. The charge is resolved server-side from this block's stored
     * attributes and is never read back off the page.
     */
    protected function price(): string
    {
        $price = get_field('product_price');

        if (! is_numeric($price) || (float) $price <= 0) {
            $price = 100;
        }

        $price = (float) $price;

        // Whole amounts read as "$100", not "$100.00", which is how production renders it.
        return $price === floor($price)
            ? (string) (int) $price
            : number_format($price, 2, '.', ',');
    }

    /**
     * Named route first; a hand-built URL if the route table is not available (block rendered
     * outside a dispatched request). A payment form that cannot reach its endpoint is dead.
     */
    protected function intentUrl(): string
    {
        try {
            return route('api.payments.intent');
        } catch (\Throwable) {
            return home_url('/api/payments/intent');
        }
    }

    protected function domId(int $index): string
    {
        $id = is_object($this->block ?? null) ? (string) ($this->block->id ?? '') : '';
        $id = (string) preg_replace('/[^A-Za-z0-9_-]/', '', $id);

        return $id !== '' ? $id : 'rl-payment-'.$index;
    }

    protected function defaultTermsText(): string
    {
        return 'Client may request a refund of 100% their full deposit at any time prior to choosing an applicant to work with.';
    }

    protected function defaultAgreementText(): string
    {
        return "By proceeding, you confirm that you have read and agree to Remote Leverage's Terms and Privacy Notice.";
    }

    public function fields(): array
    {
        $fields = Builder::make('payment_gateway_block');

        $fields
            ->addText('product_title', [
                'label' => 'Main Title',
                'default_value' => 'Enter Details',
            ])
            ->addNumber('product_price', [
                'label' => 'Price',
                'instructions' => 'What the customer is charged. This value is what the server charges — the browser never sends an amount.',
                'default_value' => 100,
                'min' => 1,
            ])
            ->addText('product_currency', [
                'label' => 'Currency Code',
                'default_value' => 'USD',
            ])
            ->addText('payment_terms_title', [
                'label' => 'Payment Terms Title',
                'default_value' => 'Payment Terms',
            ])
            ->addTextarea('payment_terms_text', [
                'label' => 'Payment Terms Text',
                'default_value' => $this->defaultTermsText(),
                'rows' => 3,
            ])
            ->addTextarea('payment_description_template', [
                'label' => 'Stripe Payment Description',
                'instructions' => 'Shown in the Stripe dashboard. Use the {name} placeholder for the customer name.',
                'default_value' => '[Salvatori Payment Form] Remote Leverage Onboarding + Applicant Criteria with {name}',
                'rows' => 2,
            ])
            ->addWysiwyg('agreement_text', [
                'label' => 'Agreement Footer',
                'default_value' => $this->defaultAgreementText(),
                'media_upload' => 0,
                'toolbar' => 'basic',
            ])
            ->addUrl('success_url', [
                'label' => 'Success / Thank You Page URL',
                'instructions' => 'Where the customer lands after payment, and the return_url Stripe redirects to for Link / 3D Secure. Leave blank to use STRIPE_DEFAULT_THANKYOU_URL, or the inline success state when that is unset too.',
            ])
            ->addSelect('layout', [
                'label' => 'Layout',
                'choices' => [
                    'one_column' => 'Single Column',
                    'two_columns' => 'Two Columns',
                ],
                'default_value' => 'one_column',
            ]);

        return $fields->build();
    }
}
