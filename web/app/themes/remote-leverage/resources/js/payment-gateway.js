/**
 * Stripe Elements checkout for the acf/payment-gateway block.
 *
 * Ported from rl-elementor-blocks `assets/js/payment-gateway.js`. Behaviour kept: Stripe
 * Elements mount with the same appearance config, redirect-return handling via
 * retrievePaymentIntent(), confirmPayment() with a return_url, the submit button gated on
 * both the text fields and the Payment Element reporting complete, the success-state reveal,
 * and the intl-tel-input phone field.
 *
 * Dropped in the port:
 *  - Elementor coupling (elementorFrontend.hooks.addAction) — these are Gutenberg blocks now,
 *    so each .rl-payment-card initialises itself on DOMContentLoaded.
 *  - jQuery — the theme does not ship it on the front end.
 *  - TELEMETRY. The legacy script fired PostHog capture/identify, Customer.io `cioanalytics`
 *    track calls, and POSTed every step to `/wp-json/rl/v1/log` (the plugin's LoggingProvider
 *    table). v2 has no LoggingProvider and no such endpoint, and inventing one here would
 *    create an unauthenticated write endpoint on the money path. Payment events are instead
 *    recorded server-side: PaymentIntentController logs intent creation and
 *    StripeWebhookController logs and forwards payment_intent.succeeded, which is the
 *    authoritative record anyway. If client-side attribution is wanted back, wire it to the
 *    already-loaded window.posthog in app.js rather than to a new endpoint.
 *
 * The amount is NOT sent from here. The server re-derives it from the block on the page
 * (post_id + block_index); this script only posts who the customer is.
 */

const STRIPE_JS_SRC = 'https://js.stripe.com/v3/';

let stripeJsPromise = null;

/**
 * Stripe.js must be served from Stripe's own domain — bundling a copy voids PCI SAQ-A
 * eligibility and Stripe rejects it. Loaded once per page, on demand.
 */
function loadStripeJs() {
  if (typeof window.Stripe !== 'undefined') {
    return Promise.resolve(window.Stripe);
  }

  if (stripeJsPromise) {
    return stripeJsPromise;
  }

  stripeJsPromise = new Promise((resolve, reject) => {
    const existing = document.querySelector(`script[src="${STRIPE_JS_SRC}"]`);

    if (existing) {
      existing.addEventListener('load', () => resolve(window.Stripe));
      existing.addEventListener('error', () => reject(new Error('Stripe.js failed to load')));
      return;
    }

    const script = document.createElement('script');
    script.src = STRIPE_JS_SRC;
    script.async = true;
    script.addEventListener('load', () => resolve(window.Stripe));
    script.addEventListener('error', () => reject(new Error('Stripe.js failed to load')));
    document.head.appendChild(script);
  });

  return stripeJsPromise;
}

let itiPromise = null;

/**
 * intl-tel-input comes from npm here rather than the CDN build + `utilsScript` URL the legacy
 * plugin used; the bundled `WithUtils` entry is the same library with the formatting utils
 * already inside, so there is no second network fetch to configure.
 */
function loadIntlTelInput() {
  if (window.intlTelInput) {
    return Promise.resolve(window.intlTelInput);
  }

  if (itiPromise) {
    return itiPromise;
  }

  itiPromise = Promise.all([
    import('intl-tel-input/intlTelInputWithUtils'),
    import('intl-tel-input/build/css/intlTelInput.css'),
  ]).then(([mod]) => {
    window.intlTelInput = mod.default;
    return window.intlTelInput;
  });

  return itiPromise;
}

const MESSAGE_STYLES = {
  error: ['bg-[#FEF2F2]', 'text-[#DC2626]', 'border', 'border-[#FEE2E2]'],
  success: ['bg-[#F0FDF4]', 'text-[#16A34A]', 'border', 'border-[#DCFCE7]', 'font-semibold'],
  info: ['bg-[#F3F4F6]', 'text-[#4B5563]', 'border', 'border-[#E5E7EB]'],
};

function initCard(card) {
  if (card.dataset.rlPaymentReady) {
    return;
  }
  card.dataset.rlPaymentReady = '1';

  const blockId = card.dataset.blockId || '';
  const blockIndex = card.dataset.blockIndex || '0';
  const postId = card.dataset.postId || '';
  const publishableKey = card.dataset.publishableKey || '';
  const intentUrl = card.dataset.intentUrl || '';
  const successUrl = (card.dataset.successUrl || '').trim();

  const form = card.querySelector('.rl-payment-form');

  if (!form || !publishableKey || !intentUrl) {
    return;
  }

  const submitBtn = form.querySelector('.rl-pay-submit-btn');
  const btnText = form.querySelector('.rl-btn-text');
  const loader = form.querySelector('.rl-btn-loader');
  const messageContainer = form.querySelector('.rl-payment-message');
  const successState = form.querySelector('.rl-payment-success');
  const formColumns = form.querySelector('.rl-payment-form-columns');
  const phoneInput = form.querySelector('input[name="phone"]');

  const field = (name) => form.querySelector(`input[name="${name}"]`);
  const value = (name) => (field(name) ? field(name).value : '');

  let stripe = null;
  let elements = null;
  let paymentElement = null;
  let iti = null;
  let isStripeValid = false;
  let messageTimer = null;

  function showMessage(text, type = 'info') {
    if (!messageContainer) {
      return;
    }

    messageContainer.textContent = text;
    Object.values(MESSAGE_STYLES).forEach((classes) => messageContainer.classList.remove(...classes));
    messageContainer.classList.add(...(MESSAGE_STYLES[type] || MESSAGE_STYLES.info));
    messageContainer.style.display = '';

    // Success messages linger; errors clear so a stale one isn't read as current.
    clearTimeout(messageTimer);
    messageTimer = setTimeout(() => {
      messageContainer.style.display = 'none';
    }, type === 'success' ? 10000 : 5000);
  }

  function checkFormValidity() {
    if (!submitBtn) {
      return;
    }

    const fieldsValid = value('first_name').length > 0
      && value('last_name').length > 0
      && value('email').length > 0;

    submitBtn.disabled = !(fieldsValid && isStripeValid);
  }

  function setLoading(isLoading) {
    if (!submitBtn) {
      return;
    }

    if (isLoading) {
      submitBtn.disabled = true;
      if (loader) loader.style.display = '';
      if (btnText) btnText.style.opacity = '0.5';
      return;
    }

    if (loader) loader.style.display = 'none';
    if (btnText) btnText.style.opacity = '1';
    checkFormValidity();
  }

  function showSuccessState() {
    if (formColumns) formColumns.style.display = 'none';
    if (successState) successState.style.display = '';

    const top = card.getBoundingClientRect().top + window.scrollY - 100;
    window.scrollTo({ top: Math.max(top, 0), behavior: 'smooth' });
  }

  function phoneNumber() {
    if (iti && typeof iti.getNumber === 'function') {
      return iti.getNumber() || value('phone');
    }
    return value('phone');
  }

  function initPhoneField() {
    if (!phoneInput) {
      return;
    }

    loadIntlTelInput().then((intlTelInput) => {
      iti = intlTelInput(phoneInput, {
        initialCountry: 'auto',
        geoIpLookup: (callback) => {
          fetch('https://ipapi.co/json')
            .then((res) => res.json())
            .then((data) => callback(data.country_code))
            .catch(() => callback('us'));
        },
        separateDialCode: true,
        autoPlaceholder: 'aggressive',
        countryOrder: ['us', 'gb', 'ca', 'co', 'mx'],
      });

      // Keep the typed number clear of the dial-code chip, which changes width per country.
      const adjustPadding = () => {
        const selected = card.querySelector('.iti__selected-country');
        const width = selected ? selected.offsetWidth : 0;
        if (width > 0) {
          phoneInput.style.setProperty('padding-left', `${width + 8}px`, 'important');
        }
      };

      adjustPadding();
      setTimeout(adjustPadding, 300);
      phoneInput.addEventListener('countrychange', () => setTimeout(adjustPadding, 10));

      // The flag keeps re-adding a title attribute, which shows as a browser tooltip.
      const flag = card.querySelector('.iti__selected-country');
      if (flag) {
        const observer = new MutationObserver(() => {
          if (flag.getAttribute('title')) {
            flag.removeAttribute('title');
          }
          adjustPadding();
        });
        observer.observe(flag, { attributes: true });
        flag.removeAttribute('title');
      }
    }).catch(() => {
      // A missing phone widget must not take the checkout down — the plain tel input stands.
    });
  }

  async function createIntent() {
    const response = await fetch(intentUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
      },
      body: JSON.stringify({
        post_id: postId,
        block_index: blockIndex,
        block_id: blockId,
        first_name: value('first_name'),
        last_name: value('last_name'),
        email: value('email'),
        phone: phoneNumber(),
      }),
    });

    const data = await response.json().catch(() => ({}));

    if (!response.ok || !data.client_secret) {
      throw new Error(data.error || 'Failed to initialize payment.');
    }

    return data.client_secret;
  }

  async function initialize() {
    setLoading(true);
    initPhoneField();

    try {
      const Stripe = await loadStripeJs();
      stripe = Stripe(publishableKey);
    } catch (e) {
      showMessage('Unable to load the payment form. Please refresh the page or try again later.', 'error');
      setLoading(false);
      return;
    }

    // Stripe sends the browser back here with these params after Link / 3D Secure / any
    // redirect-based method, so the outcome has to be read off the URL before anything else.
    const params = new URL(window.location.href).searchParams;
    const clientSecretFromUrl = params.get('payment_intent_client_secret');

    if (clientSecretFromUrl) {
      const { paymentIntent } = await stripe.retrievePaymentIntent(clientSecretFromUrl);

      switch (paymentIntent && paymentIntent.status) {
        case 'succeeded':
          showSuccessState();
          window.history.replaceState({}, document.title, window.location.pathname);
          setLoading(false);
          return;
        case 'processing':
          showMessage('Your payment is processing.');
          break;
        case 'requires_payment_method':
          showMessage('Your payment was not successful, please try again.', 'error');
          break;
        default:
          showMessage('Something went wrong.');
          break;
      }
    }

    let clientSecret;

    try {
      clientSecret = await createIntent();
    } catch (e) {
      showMessage(e.message || 'Failed to initialize payment.', 'error');
      setLoading(false);
      return;
    }

    const appearance = {
      theme: 'stripe',
      variables: {
        colorPrimary: '#006bff',
        colorBackground: '#ffffff',
        colorText: '#0d1b3e',
        colorDanger: '#dc2626',
        borderRadius: '8px',
        fontFamily: 'Inter, system-ui, sans-serif',
        spacingGridRow: '20px',
      },
      rules: {
        '.Input': {
          border: '1px solid #d1d5db',
          boxShadow: 'none',
          padding: '12px',
        },
        '.Input:focus': {
          border: '1px solid #006bff',
          boxShadow: '0 0 0 2px rgba(0, 107, 255, 0.1)',
        },
        '.Label': {
          fontSize: '14px',
          fontWeight: '700',
          color: '#0d1b3e',
        },
      },
    };

    elements = stripe.elements({
      appearance,
      clientSecret,
      defaultValues: {
        billingDetails: {
          name: `${value('first_name')} ${value('last_name')}`.trim(),
          email: value('email'),
          phone: value('phone'),
        },
      },
    });

    paymentElement = elements.create('payment');
    paymentElement.mount(`#payment-element-${blockId}`);

    paymentElement.on('ready', () => {
      setLoading(false);
      checkFormValidity();
    });

    paymentElement.on('change', (event) => {
      isStripeValid = Boolean(event.complete);
      checkFormValidity();
    });

    form.querySelectorAll('input[required]').forEach((input) => {
      input.addEventListener('input', checkFormValidity);
    });

    checkFormValidity();
  }

  form.addEventListener('submit', async (event) => {
    event.preventDefault();

    if (!stripe || !elements) {
      return;
    }

    setLoading(true);

    const { error } = await stripe.confirmPayment({
      elements,
      confirmParams: {
        return_url: successUrl || window.location.href,
        payment_method_data: {
          billing_details: {
            name: `${value('first_name')} ${value('last_name')}`.trim(),
            email: value('email'),
            phone: phoneNumber(),
          },
        },
      },
    });

    if (error) {
      if (error.type === 'card_error' || error.type === 'validation_error') {
        showMessage(error.message, 'error');
      } else {
        showMessage('An unexpected error occurred.', 'error');
      }
    } else {
      // Card payments normally redirect and never reach here; some methods resolve inline.
      if (successUrl) {
        window.location.href = successUrl;
        return;
      }
      showSuccessState();
    }

    setLoading(false);
  });

  initialize().catch(() => {
    showMessage('An unexpected error occurred.', 'error');
    setLoading(false);
  });
}

export function initPaymentGateways() {
  document.querySelectorAll('.rl-payment-card').forEach(initCard);
}

export default initPaymentGateways;
