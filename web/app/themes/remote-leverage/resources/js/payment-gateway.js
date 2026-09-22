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
 *  - The `POST /wp-json/rl/v1/log` leg of the legacy telemetry. That endpoint was registered
 *    with `permission_callback => '__return_true'` — an unauthenticated write endpoint on the
 *    money path — and everything it held is now carried as properties on the analytics events
 *    below (PostHog session id + replay URL, utm_source, utm_campaign, gclid, fbclid, the
 *    time-on-form duration and the severity), or logged server-side by PaymentIntentController
 *    and StripeWebhookController. See app/Domains/Payment/Services/CheckoutTelemetry.php.
 *
 * TELEMETRY (rebuilt 2026-09-15; the port had removed all of it rather than stubbing it).
 * Event names live in FUNNEL below and MUST stay in step with the PHP enum
 * App\Domains\Payment\Data\CheckoutFunnelStep — tests/Unit/PaymentCheckoutTelemetryTest.php
 * reads this file and fails if the two drift. Dispatch is direct to the PostHog and
 * Customer.io CDP (`cioanalytics`) browser SDKs that TrackingHooks already injects; there is
 * no request to our own server, so none of this can add latency to a payment. Both SDKs are
 * absent whenever their credentials are unset (which is the normal local state), and every
 * call site guards for that.
 *
 * The amount is NOT sent from here. The server re-derives it from the block on the page
 * (post_id + block_index); this script only posts who the customer is.
 */

/**
 * Funnel step -> event name. Mirrors CheckoutFunnelStep::eventName() in PHP.
 *
 * `form_started` keeps its lowercase legacy spelling on purpose: it was never
 * payment-specific, the legacy booking widgets fired it too, and production's funnel report
 * aggregates across it. The rest are the legacy Customer.io names, tidied where they were
 * artefacts of PHP's ucfirst() on a snake_case key.
 */
import { loadStylesheet } from './load-stylesheet';
import { retryImport } from './retry-import';

const FUNNEL = {
  gatewayViewed: 'Payment Gateway Viewed',
  checkoutStarted: 'form_started',
  emailCaptured: 'Payment Partial Email Captured',
  paymentSubmitted: 'Payment Attempted',
  paymentSucceeded: 'Payment Succeeded',
  paymentFailed: 'Payment Failed',
};

/** Legacy PostHog-only event, kept verbatim — production insights are keyed to this string. */
const STRIPE_LOAD_FAILED_EVENT = 'payment_stripe_load_failed';

const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

function queryParam(name) {
  try {
    return new URLSearchParams(window.location.search).get(name) || undefined;
  } catch (e) {
    return undefined;
  }
}

/**
 * Build the per-card telemetry dispatcher.
 *
 * Every send is wrapped: an analytics SDK throwing must never take a checkout down, which is
 * the whole reason the legacy version swallowed its own fetch rejection.
 */
function createTelemetry({ blockId, postId }) {
  const storageKey = `rl_session_start_${blockId}`;
  let startedAt = Date.now();

  try {
    const stored = window.sessionStorage.getItem(storageKey);
    if (stored) {
      startedAt = parseInt(stored, 10) || Date.now();
    } else {
      window.sessionStorage.setItem(storageKey, String(startedAt));
    }
  } catch (e) {
    // Private mode / blocked storage. Duration is then per-pageview rather than per-session.
  }

  // Captured once and refreshed on every send, as the legacy script did: PostHog assigns the
  // session id asynchronously, so the first few events would otherwise carry nothing.
  function posthogContext() {
    const posthog = window.posthog;
    const context = {};

    if (!posthog) {
      return context;
    }

    try {
      if (typeof posthog.get_session_id === 'function') {
        context.posthog_session_id = posthog.get_session_id() || undefined;
      }
      if (typeof posthog.get_session_replay_url === 'function') {
        context.posthog_replay_url = posthog.get_session_replay_url() || undefined;
      }
    } catch (e) {
      // Stub not yet replaced by the real library.
    }

    return context;
  }

  function baseProperties() {
    return {
      // `widget_id` is the legacy key for this and the Stripe metadata still uses it.
      widget_id: blockId,
      post_id: postId,
      form_type: 'payment_gateway',
      source: 'client',
      page_url: window.location.href,
      duration: Math.round((Date.now() - startedAt) / 1000),
      utm_source: queryParam('utm_source'),
      utm_campaign: queryParam('utm_campaign'),
      gclid: queryParam('gclid'),
      fbclid: queryParam('fbclid'),
      ...posthogContext(),
    };
  }

  function clean(properties) {
    return Object.fromEntries(
      Object.entries(properties).filter(([, v]) => v !== undefined && v !== null && v !== ''),
    );
  }

  function capture(eventName, properties) {
    const payload = clean(properties);

    try {
      if (window.posthog && typeof window.posthog.capture === 'function') {
        window.posthog.capture(eventName, payload);
      }
    } catch (e) {
      // Analytics is never allowed to break the form.
    }

    try {
      // `cioanalytics` is the CDP snippet TrackingHooks injects and the one production
      // serves, so it is the expected path everywhere. The `_cio` branch is now purely
      // defensive — nothing in v2 defines it — and is kept because the classic tracker can
      // still arrive via the GTM container, and because a staged cutover may serve some
      // pages from the legacy stack. Note the two SDKs disagree on identify()'s signature.
      if (window.cioanalytics && typeof window.cioanalytics.track === 'function') {
        window.cioanalytics.track(eventName, payload);
      } else if (window._cio && typeof window._cio.track === 'function') {
        window._cio.track(eventName, payload);
      }
    } catch (e) {
      // As above.
    }
  }

  return {
    track(eventName, properties = {}) {
      capture(eventName, { ...baseProperties(), ...properties });
    },

    /**
     * Legacy parity: the widget identified the visitor in PostHog by email the moment a valid
     * one was typed, well before any payment, so an abandoned checkout is still attributable.
     */
    identify(email) {
      try {
        if (window.posthog && typeof window.posthog.identify === 'function') {
          window.posthog.identify(email, { email });
        }
      } catch (e) {
        // As above.
      }

      try {
        // Same preference and the same defensive `_cio` branch as capture() above; note
        // the classic tracker takes one object, the CDP snippet takes (userId, traits).
        if (window.cioanalytics && typeof window.cioanalytics.identify === 'function') {
          window.cioanalytics.identify(email, { email });
        } else if (window._cio && typeof window._cio.identify === 'function') {
          window._cio.identify({ id: email, email });
        }
      } catch (e) {
        // As above.
      }
    },
  };
}

/** `pi_123_secret_abc` -> `pi_123`, so a client-side success carries the same id as the webhook. */
function intentIdFromClientSecret(clientSecret) {
  if (typeof clientSecret !== 'string') {
    return undefined;
  }

  const [id] = clientSecret.split('_secret_');

  return id || undefined;
}

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
    retryImport(() => import('intl-tel-input/intlTelInputWithUtils')),
    import('intl-tel-input/build/css/intlTelInput.css?url').then(({ default: href }) => loadStylesheet(href)),
  ]).then(([mod]) => {
    window.intlTelInput = mod.default;
    return window.intlTelInput;
  }).catch((err) => {
    itiPromise = null;
    throw err;
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
  let clientSecret = null;

  const telemetry = createTelemetry({ blockId, postId });

  // --- Funnel step 1: gateway viewed -------------------------------------------------------
  // Tied to visibility rather than to render. The block sits well below the fold on the
  // deposit page, so counting every page load as a view would make every later step look like
  // a catastrophic drop-off. No legacy equivalent existed — this name is new.
  let viewedFired = false;

  function fireGatewayViewed() {
    if (viewedFired) {
      return;
    }
    viewedFired = true;
    telemetry.track(FUNNEL.gatewayViewed, { layout: card.dataset.layout || undefined });
  }

  if (typeof IntersectionObserver === 'function') {
    const observer = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          fireGatewayViewed();
          observer.disconnect();
        }
      });
    }, { threshold: 0.25 });
    observer.observe(card);
  } else {
    fireGatewayViewed();
  }

  // --- Funnel step 2: checkout started -----------------------------------------------------
  let checkoutStartedFired = false;

  form.addEventListener('input', () => {
    if (checkoutStartedFired) {
      return;
    }
    checkoutStartedFired = true;
    fireGatewayViewed();
    // `form_id` is the legacy property name on this event and is shared with the booking
    // widgets' version of it; keep it.
    telemetry.track(FUNNEL.checkoutStarted, { form_id: blockId });
  }, true);

  // --- Funnel step 2b: partial email capture -----------------------------------------------
  let lastIdentifiedEmail = '';

  function handleEmailCapture(raw) {
    const email = String(raw || '').trim().toLowerCase();

    if (!EMAIL_PATTERN.test(email) || email === lastIdentifiedEmail) {
      return;
    }

    lastIdentifiedEmail = email;
    telemetry.identify(email);
    telemetry.track(FUNNEL.emailCaptured, { email });
  }

  const emailField = field('email');

  if (emailField) {
    emailField.addEventListener('blur', () => handleEmailCapture(emailField.value));
    emailField.addEventListener('change', () => handleEmailCapture(emailField.value));
    handleEmailCapture(emailField.value);
  }

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
      // Legacy parity: this exact event name, with these exact properties, is what the widget
      // captured when the <script src="https://js.stripe.com/v3/"> tag never arrived.
      telemetry.track(STRIPE_LOAD_FAILED_EVENT, {
        correlation_id: `stripe_load_failed_${blockId}_${Date.now()}_${Math.random().toString(36).slice(2, 10)}`,
      });
      telemetry.track(FUNNEL.paymentFailed, {
        failure_stage: 'stripe_js',
        error: e && e.message ? e.message : 'Stripe.js failed to load',
        severity: 'critical',
      });
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
          // The browser came back from a redirect-based method (Link, 3D Secure). The webhook
          // records the same success independently; `confirmation_source` is what lets these
          // be deduplicated by payment_intent_id downstream.
          telemetry.track(FUNNEL.paymentSucceeded, {
            payment_intent_id: paymentIntent.id,
            confirmation_source: 'client_redirect',
            email: value('email') || undefined,
          });
          showSuccessState();
          window.history.replaceState({}, document.title, window.location.pathname);
          setLoading(false);
          return;
        case 'processing':
          showMessage('Your payment is processing.');
          break;
        case 'requires_payment_method':
          telemetry.track(FUNNEL.paymentFailed, {
            payment_intent_id: paymentIntent.id,
            failure_stage: 'confirmation',
            confirmation_source: 'client_redirect',
            error: 'requires_payment_method',
            severity: 'error',
            email: value('email') || undefined,
          });
          showMessage('Your payment was not successful, please try again.', 'error');
          break;
        default:
          showMessage('Something went wrong.');
          break;
      }
    }

    try {
      clientSecret = await createIntent();
    } catch (e) {
      // PaymentIntentController records its own side of this (source: 'server'). This one is
      // the only record when the request never reached the server at all.
      telemetry.track(FUNNEL.paymentFailed, {
        failure_stage: 'intent',
        error: e && e.message ? e.message : 'Failed to initialize payment.',
        severity: 'error',
        email: value('email') || undefined,
      });
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

    const paymentIntentId = intentIdFromClientSecret(clientSecret);

    telemetry.track(FUNNEL.paymentSubmitted, {
      payment_intent_id: paymentIntentId,
      email: value('email') || undefined,
      phone: phoneNumber() || undefined,
    });

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
      telemetry.track(FUNNEL.paymentFailed, {
        payment_intent_id: paymentIntentId,
        failure_stage: 'confirmation',
        confirmation_source: 'client_inline',
        error: error.message,
        error_type: error.type,
        error_code: error.code,
        decline_code: error.decline_code,
        severity: 'error',
        email: value('email') || undefined,
      });

      if (error.type === 'card_error' || error.type === 'validation_error') {
        showMessage(error.message, 'error');
      } else {
        showMessage('An unexpected error occurred.', 'error');
      }
    } else {
      telemetry.track(FUNNEL.paymentSucceeded, {
        payment_intent_id: paymentIntentId,
        confirmation_source: 'client_inline',
        email: value('email') || undefined,
      });

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
