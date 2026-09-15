{{-- Production's refundable-deposit checkout: the Stripe Elements card form on
     /virtual-assistant-hiring-manager-refundable-deposit/ (page 38897). Ported from the
     rl-elementor-blocks `rl_payment_gateway` Elementor widget.

     The `rl-` class names are the JS contract — resources/js/payment-gateway.js selects
     .rl-payment-card, .rl-payment-form, .rl-payment-form-columns, .rl-payment-success,
     .rl-pay-submit-btn, .rl-btn-text, .rl-btn-loader and .rl-payment-message by name. Rename
     one and the form silently stops working. Everything else is Tailwind on theme tokens.

     Colours are production's own (#0D1B3E ink, #006BFF action), not the brand purple pill:
     this reproduces a live checkout, and CLAUDE.md puts visual parity with production first.

     Initially-hidden elements carry inline `display:none` and the script toggles
     `style.display`, as the legacy jQuery did — a `hidden` attribute loses to Tailwind's
     display utilities on the same element. --}}

@if ($publishableKey === '')
    @if ($isPreview)
        <div class="p-6 text-[14px] text-[#B45309] bg-[#FFFBEB] border border-[#FDE68A] rounded-card">
            Stripe publishable key is not configured. Set <code>STRIPE_KEY</code>
            (or <code>STRIPE_TEST_KEY</code> with <code>STRIPE_TEST_MODE=true</code>).
            See <code>docs/stripe-payments.md</code>.
        </div>
    @endif
@else

@once
    {{-- intl-tel-input ships its own CSS (loaded by the JS module). These few rules are what
         the legacy stylesheet added on top so the country picker sits inside the field
         instead of beside it; they cannot be expressed as utilities on our own markup
         because the plugin injects its wrapper at runtime. --}}
    <style>
        .rl-payment-card .iti { width: 100%; display: block; position: relative; }
        .rl-payment-card .iti__selected-country { background-color: #F9FAFB; border-right: 1px solid #D1D5DB; border-top-left-radius: 8px; border-bottom-left-radius: 8px; padding: 0 8px 0 10px; }
        .rl-payment-card .iti__country-list { border-radius: 8px; border: 1px solid #E5E7EB; margin-top: 5px; z-index: 100; box-shadow: 0 10px 15px -3px rgba(0,0,0,.1); }
    </style>
@endonce

<section class="w-full py-10 sm:py-14">
    <div class="w-full max-w-[1380px] mx-auto px-4 sm:px-6 lg:px-8">
        <div
            class="rl-payment-card font-sans text-[#0D1B3E] mx-auto {{ $layout === 'two_columns' ? 'max-w-[1000px]' : 'max-w-[550px]' }}"
            data-block-id="{{ $blockId }}"
            data-block-index="{{ $blockIndex }}"
            data-post-id="{{ $postId }}"
            data-publishable-key="{{ $publishableKey }}"
            data-intent-url="{{ $intentUrl }}"
            data-success-url="{{ $successUrl }}"
            {{-- Read by resources/js/payment-gateway.js for the `Payment Gateway Viewed`
                 funnel event, so a one- vs two-column checkout can be compared. --}}
            data-layout="{{ $layout }}"
        >
            <h2 class="rl-payment-card__title font-display font-bold text-[24px] leading-[32px] mb-6">
                {{ $productTitle }}
            </h2>

            <form id="rl-payment-form-{{ $blockId }}" class="rl-payment-form">
                <div @class([
                    'rl-payment-form-columns flex flex-col gap-10',
                    'md:grid md:grid-cols-2 md:gap-14 md:items-start' => $layout === 'two_columns',
                ])>
                    <div class="rl-payment-column-left flex flex-col gap-5">
                        <div class="rl-form-row grid grid-cols-2 gap-5">
                            <div class="rl-form-group flex flex-col gap-2">
                                <label for="rl-first-name-{{ $blockId }}" class="text-[14px] font-bold">First Name *</label>
                                <input id="rl-first-name-{{ $blockId }}" type="text" name="first_name" autocomplete="given-name" required
                                    class="w-full rounded-lg border border-[#D1D5DB] bg-white px-4 py-3 text-[16px] text-[#1F2937] outline-none transition focus:border-[#006BFF] focus:ring-2 focus:ring-[#006BFF]/10">
                            </div>
                            <div class="rl-form-group flex flex-col gap-2">
                                <label for="rl-last-name-{{ $blockId }}" class="text-[14px] font-bold">Last Name *</label>
                                <input id="rl-last-name-{{ $blockId }}" type="text" name="last_name" autocomplete="family-name" required
                                    class="w-full rounded-lg border border-[#D1D5DB] bg-white px-4 py-3 text-[16px] text-[#1F2937] outline-none transition focus:border-[#006BFF] focus:ring-2 focus:ring-[#006BFF]/10">
                            </div>
                        </div>

                        <div class="rl-form-group flex flex-col gap-2">
                            <label for="rl-email-{{ $blockId }}" class="text-[14px] font-bold">Email *</label>
                            <input id="rl-email-{{ $blockId }}" type="email" name="email" autocomplete="email" required
                                class="w-full rounded-lg border border-[#D1D5DB] bg-white px-4 py-3 text-[16px] text-[#1F2937] outline-none transition focus:border-[#006BFF] focus:ring-2 focus:ring-[#006BFF]/10">
                        </div>

                        <div class="rl-form-group flex flex-col gap-2">
                            <label for="rl-phone-{{ $blockId }}" class="text-[14px] font-bold">Phone Number</label>
                            <div class="relative w-full">
                                <input id="rl-phone-{{ $blockId }}" type="tel" name="phone" autocomplete="tel"
                                    class="w-full rounded-lg border border-[#D1D5DB] bg-white px-4 py-3 text-[16px] text-[#1F2937] outline-none transition focus:border-[#006BFF] focus:ring-2 focus:ring-[#006BFF]/10">
                            </div>
                        </div>

                        <div class="rl-payment-info-section my-2.5 rounded-card border border-[#E5E7EB] bg-[#F9FAFB] p-6">
                            <label class="rl-section-label block text-[14px] font-semibold mb-4">Payment information *</label>

                            <div class="rl-price-display mb-5">
                                <span class="rl-label block text-[13px] font-bold uppercase tracking-[0.05em] text-[#6B7280] mb-2">Price</span>
                                <div class="rl-amount-row flex items-baseline">
                                    <span class="rl-amount font-display font-bold text-[32px] leading-none text-[#0D1B3E]">${{ $productPrice }}</span>
                                    <span class="rl-currency ml-1 text-[16px] font-semibold text-[#9CA3AF]">{{ $productCurrency }}</span>
                                </div>
                            </div>

                            <div class="rl-terms-display">
                                <span class="rl-label block text-[13px] font-bold uppercase tracking-[0.05em] text-[#6B7280] mb-2">{{ $termsTitle }}</span>
                                <p class="rl-terms-text mt-2 text-[14px] leading-[1.5] text-[#4B5563]">{{ $termsText }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="rl-payment-column-right flex flex-col gap-5">
                        {{-- Stripe mounts the Payment Element into this node. --}}
                        <div class="rl-stripe-container mb-4 rounded-lg border border-[#E5E7EB] bg-white p-6">
                            <div id="payment-element-{{ $blockId }}"></div>
                        </div>

                        <p class="rl-secure-notice m-0 text-center text-[13px] text-[#6B7280]">
                            Your payments are securely processed by Stripe.
                        </p>

                        <div class="rl-agreement-footer text-center text-[13px] leading-[1.5] text-[#4B5563] [&_a]:font-semibold [&_a]:text-[#006BFF] [&_p]:m-0">
                            {!! $agreementText !!}
                        </div>

                        <button type="submit" disabled
                            class="rl-pay-submit-btn mt-5 inline-flex items-center justify-center gap-3 self-start rounded-pill bg-[#006BFF] px-10 py-3.5 text-[16px] font-bold text-white transition hover:bg-[#0056CC] disabled:cursor-not-allowed disabled:bg-[#D1D5DB] disabled:opacity-70 disabled:hover:bg-[#D1D5DB]">
                            <span class="rl-btn-text">Complete Payment</span>
                            <span class="rl-btn-loader" style="display:none;" aria-hidden="true">
                                <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" opacity="0.25" />
                                    <path d="M22 12a10 10 0 0 1-10 10" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
                                </svg>
                            </span>
                        </button>
                    </div>
                </div>

                <div class="rl-payment-success rounded-card bg-white px-5 py-15 text-center" style="display:none;" role="status" aria-live="polite">
                    <div class="rl-success-content mx-auto max-w-[450px]">
                        <div class="rl-success-icon mx-auto mb-6 flex h-20 w-20 items-center justify-center rounded-circle bg-[#F0FDF4] text-[#16A34A]">
                            <svg class="h-10 w-10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"
                                stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <polyline points="20 6 9 17 4 12"></polyline>
                            </svg>
                        </div>
                        <h3 class="rl-success-title font-display font-bold text-[28px] leading-[34px] text-[#0D1B3E] mb-4">Payment Successful!</h3>
                        <p class="rl-success-message m-0 mb-2 text-[16px] leading-[1.6] text-[#4B5563]">
                            Thank you for your purchase. Your transaction has been completed successfully.
                        </p>
                        <p class="rl-success-submessage m-0 mb-8 text-[14px] text-[#9CA3AF]">
                            A confirmation email will be sent to you shortly.
                        </p>
                        <a href="{{ home_url('/') }}"
                            class="rl-success-btn inline-block rounded-pill bg-[#006BFF] px-8 py-3.5 font-bold text-white no-underline transition hover:bg-[#0056CC]">
                            Return to Home
                        </a>
                    </div>
                </div>

                {{-- Production carries `id="payment-message"`; kept for parity, but the script
                     scopes its lookup to `.rl-payment-message` inside this form so two cards on
                     one page cannot fight over the duplicated id. --}}
                <div id="payment-message" class="rl-payment-message mt-4 rounded-lg p-3 text-center text-[14px]" style="display:none;" role="alert"></div>
            </form>
        </div>
    </div>
</section>

@endif
