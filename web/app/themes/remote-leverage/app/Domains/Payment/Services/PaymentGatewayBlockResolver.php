<?php

declare(strict_types=1);

namespace App\Domains\Payment\Services;

use Illuminate\Support\Facades\Log;

/**
 * Resolves what a payment-gateway block actually charges, from the block markup on the server.
 *
 * SECURITY: the amount is never taken from the browser. rl-elementor-blocks resolved it by
 * scanning Elementor's `_elementor_data` for the widget with the posted id
 * (Plugin::ajax_create_payment_intent); this is the Gutenberg equivalent — parse the post's
 * content, expand any pattern references, walk the block tree, and read the price off the
 * `acf/payment-gateway` block's own attributes. A client that names a block we cannot find
 * gets nothing: there is deliberately no path here that trusts a posted amount.
 *
 * Pattern expansion matters because v2 pages hold a single `<!-- wp:pattern -->` reference
 * (see CLAUDE.md), so the ACF block never appears in raw post_content — without
 * resolve_pattern_blocks() every real page would fail closed.
 */
class PaymentGatewayBlockResolver
{
    public const BLOCK_NAME = 'acf/payment-gateway';

    /**
     * ACF field defaults, mirrored from PaymentGatewayBlock::fields() and from the legacy
     * widget's control defaults. Used only when the block was found but the attribute is
     * absent (a block inserted in the editor and never edited stores nothing).
     */
    public const DEFAULT_PRICE = 100.0;

    public const DEFAULT_CURRENCY = 'USD';

    public const DEFAULT_DESCRIPTION_TEMPLATE = '[Salvatori Payment Form] Remote Leverage Onboarding + Applicant Criteria with {name}';

    /**
     * @return array{amount: int, currency: string, description_template: string, product_title: string}|null
     *                                                                                                        Null when the block cannot be located — the caller must fail the request.
     */
    public function resolve(int $postId, int $blockIndex = 0, string $blockId = ''): ?array
    {
        if ($postId <= 0) {
            return null;
        }

        $post = get_post($postId);

        if (! $post || ! is_string($post->post_content) || $post->post_content === '') {
            Log::warning('PaymentGatewayBlockResolver: post has no block content', ['post_id' => $postId]);

            return null;
        }

        $blocks = $this->paymentBlocks($post->post_content);

        if ($blocks === []) {
            Log::warning('PaymentGatewayBlockResolver: no payment-gateway block on post', [
                'post_id' => $postId,
                'block_id' => $blockId,
            ]);

            return null;
        }

        // One block on the page is the normal case, and then the posted index is irrelevant —
        // there is only one thing it could mean. With several, the index selects between them.
        $block = count($blocks) === 1
            ? $blocks[0]
            : ($blocks[$blockIndex] ?? null);

        if ($block === null) {
            Log::warning('PaymentGatewayBlockResolver: block index out of range', [
                'post_id' => $postId,
                'block_index' => $blockIndex,
                'found' => count($blocks),
            ]);

            return null;
        }

        $data = $block['attrs']['data'] ?? [];
        $data = is_array($data) ? $data : [];

        $price = $data['product_price'] ?? null;

        if (! is_numeric($price) || (float) $price <= 0) {
            // Legacy parity: PaymentGatewayWidget defaults the price control to 100 and
            // ajax_create_payment_intent falls back to 100 for an empty/zero value. This is a
            // server-side default, not a client-supplied one.
            Log::warning('PaymentGatewayBlockResolver: block has no usable price, using field default', [
                'post_id' => $postId,
                'raw_price' => $price,
            ]);
            $price = self::DEFAULT_PRICE;
        }

        $currency = $data['product_currency'] ?? '';
        $currency = is_string($currency) && trim($currency) !== '' ? trim($currency) : self::DEFAULT_CURRENCY;

        $template = $data['payment_description_template'] ?? '';
        $template = is_string($template) && trim($template) !== '' ? $template : self::DEFAULT_DESCRIPTION_TEMPLATE;

        $title = $data['product_title'] ?? '';

        return [
            // Stripe wants the minor unit. round() before cast so 49.99 * 100 does not land
            // on 4998 through binary float representation.
            'amount' => (int) round(((float) $price) * 100),
            'currency' => $currency,
            'description_template' => $template,
            'product_title' => is_string($title) ? $title : '',
        ];
    }

    /**
     * Every acf/payment-gateway block in the post, in document order.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function paymentBlocks(string $content): array
    {
        $blocks = parse_blocks($content);

        // Pages point at a pattern file rather than holding expanded markup, so the block tree
        // has to be resolved through the pattern registry before anything can be found in it.
        if (function_exists('resolve_pattern_blocks')) {
            $blocks = resolve_pattern_blocks($blocks);
        }

        $found = [];
        $this->walk($blocks, $found);

        return $found;
    }

    /**
     * @param  array<int, array<string, mixed>>  $blocks
     * @param  array<int, array<string, mixed>>  $found
     */
    protected function walk(array $blocks, array &$found): void
    {
        foreach ($blocks as $block) {
            if (! is_array($block)) {
                continue;
            }

            if (($block['blockName'] ?? null) === self::BLOCK_NAME) {
                $found[] = $block;
            }

            if (! empty($block['innerBlocks']) && is_array($block['innerBlocks'])) {
                $this->walk($block['innerBlocks'], $found);
            }
        }
    }
}
