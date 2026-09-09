window.intlTelInput = window.intlTelInput || null;

let itiLoadPromise = null;
function loadIntlTelInput() {
  if (window.intlTelInput) return Promise.resolve(window.intlTelInput);
  if (itiLoadPromise) return itiLoadPromise;

  itiLoadPromise = import('intl-tel-input/intlTelInputWithUtils').then((mod) => {
    window.intlTelInput = mod.default;
    return window.intlTelInput;
  });

  return itiLoadPromise;
}

export function phoneInputComponent(config = {}) {
  return {
    iti: null,
    getWire() {
      if (this.$wire) return this.$wire;
      const wireEl = this.$el?.closest('[wire\\:id]');
      if (wireEl && window.Livewire?.find) {
        return window.Livewire.find(wireEl.getAttribute('wire:id'));
      }
      return null;
    },
    initIti() {
      const input = this.$refs.phoneInput;
      if (!input || !window.intlTelInput) return;

      const initialCountry = (config && config.initialCountry) ? config.initialCountry : 'us';

      this.iti = window.intlTelInput(input, {
        initialCountry: initialCountry,
        countryOrder: ['us', 'gb', 'ca', 'co', 'mx', 'au'],
        separateDialCode: false,
        strictMode: true,
        autoPlaceholder: 'aggressive',
        geoIpLookup: (callback) => {
          fetch('https://ipapi.co/json')
            .then((res) => res.json())
            .then((data) => callback(data.country_code))
            .catch(() => callback('us'));
        },
      });

      const sync = () => {
        const wire = this.getWire();
        const country = this.iti.getSelectedCountryData();
        if (country && country.iso2 && wire) {
          wire.phoneCountry = country.iso2.toUpperCase();
        }
        if (wire) {
          wire.phone = this.iti.getNumber() || input.value;
        }
      };

      const adjustPhonePadding = () => {
        const selectedCountry = this.$el.querySelector('.iti__selected-country');
        if (selectedCountry && input) {
          const width = selectedCountry.offsetWidth || 0;
          if (width > 0) {
            input.style.setProperty('padding-left', `${width + 12}px`, 'important');
          }
        }
      };

      adjustPhonePadding();
      setTimeout(adjustPhonePadding, 50);
      setTimeout(adjustPhonePadding, 250);

      // Hide browser tooltip on country flag
      const flag = this.$el.querySelector('.iti__selected-country');
      if (flag) {
        const observer = new MutationObserver(() => {
          if (flag.getAttribute('title')) {
            flag.removeAttribute('title');
          }
          adjustPhonePadding();
        });
        observer.observe(flag, { attributes: true });
        flag.removeAttribute('title');
      }

      input.addEventListener('countrychange', () => {
        sync();
        setTimeout(adjustPhonePadding, 10);
      });
      input.addEventListener('input', sync);
      input.addEventListener('change', sync);
      input.addEventListener('blur', sync);

      const wire = this.getWire();
      if (wire && wire.phone && this.iti) {
        this.iti.setNumber(wire.phone);
        setTimeout(adjustPhonePadding, 10);
      }
    },
    init() {
      loadIntlTelInput();

      if (window.intlTelInput) {
        this.initIti();
      } else {
        const check = setInterval(() => {
          if (window.intlTelInput) {
            clearInterval(check);
            this.initIti();
          }
        }, 30);
        setTimeout(() => clearInterval(check), 3000);
      }
    },
  };
}

window.phoneInputComponent = phoneInputComponent;

export function rlBookingWizardIsolated(config = {}) {
  return {
    isolated: Boolean(config.isolated),
    steps: Array.isArray(config.steps) ? config.steps : [],
    currentSubStep: 0,
    emailVal: config.email || '',
    firstNameVal: config.firstName || '',
    lastNameVal: config.lastName || '',
    phoneVal: config.phone || '',
    monthlyRevenueVal: config.monthlyRevenue || '',
    consentChecked: true,

    isFieldValid(f) {
      if (f === 'email') {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return typeof this.emailVal === 'string' && re.test(this.emailVal.trim());
      }
      if (f === 'name') {
        return typeof this.firstNameVal === 'string' && this.firstNameVal.trim().length > 0 && typeof this.lastNameVal === 'string' && this.lastNameVal.trim().length > 0;
      }
      if (f === 'phone') {
        return true;
      }
      if (f === 'monthly_revenue') {
        return typeof this.monthlyRevenueVal === 'string' && this.monthlyRevenueVal.trim().length > 0;
      }
      if (f === 'consent') {
        return this.consentChecked === true;
      }
      return true;
    },

    isSubStepValid(idx) {
      if (!this.isolated || !this.steps || !this.steps[idx]) return true;
      const fields = this.steps[idx].step_fields || [];
      return fields.every(f => this.isFieldValid(f));
    },

    advanceIfValid(fromIdx) {
      if (!this.isolated) return;
      if (this.isSubStepValid(fromIdx)) {
        if (this.currentSubStep <= fromIdx) {
          this.currentSubStep = fromIdx + 1;
          this.$nextTick(() => {
            const nextContainer = this.$el.querySelector('[data-substep="' + this.currentSubStep + '"]');
            if (nextContainer) {
              const input = nextContainer.querySelector('input:not([type="hidden"]), select, textarea');
              if (input) input.focus();
            }
          });
        }
      }
    },

    onFieldInput(field, stepIdx) {
      if (!this.isolated) return;
      if (this.isSubStepValid(stepIdx)) {
        this.advanceIfValid(stepIdx);
      }
    },

    canShowStep(stepIdx) {
      return !this.isolated || this.currentSubStep >= stepIdx;
    },

    canShowContinue(stepIdx) {
      return this.isolated && this.currentSubStep === stepIdx;
    },

    canShowFinalButton() {
      const lastIdx = Array.isArray(this.steps) && this.steps.length > 0 ? this.steps.length - 1 : 1;
      return !this.isolated || this.currentSubStep >= lastIdx;
    },

    isStepRevealed(stepIdx) {
      return this.currentSubStep >= stepIdx;
    },

    init() {
      if (this.isolated && Array.isArray(this.steps) && this.steps.length > 0) {
        if (this.isSubStepValid(0)) {
          this.currentSubStep = 1;
        }
      }
    },
  };
}

window.rlBookingWizardIsolated = rlBookingWizardIsolated;

const registerAlpine = () => {
  if (window.Alpine) {
    window.Alpine.data('phoneInputComponent', phoneInputComponent);
    window.Alpine.data('rlBookingWizardIsolated', rlBookingWizardIsolated);
  }
};

if (window.Alpine) {
  registerAlpine();
} else {
  document.addEventListener('alpine:init', registerAlpine);
}

// Expose global placeholders so early callers don't throw
window.Sentry = window.Sentry || null;
window.posthog = window.posthog || null;

// Dynamically load Sentry only if DSN is configured
const sentryDsn = window.SENTRY_DSN || import.meta.env.VITE_SENTRY_DSN;
if (sentryDsn) {
  import('@sentry/browser').then((Sentry) => {
    window.Sentry = Sentry;
    Sentry.init({
      dsn: sentryDsn,
      environment: window.APP_ENV || 'production',
      tracesSampleRate: 0.1,
    });
  }).catch((err) => console.error('Sentry initialization failed:', err));
}

// Dynamically load PostHog only if API key is configured
const posthogKey = window.POSTHOG_API_KEY || import.meta.env.VITE_POSTHOG_API_KEY;
const posthogHost = window.POSTHOG_HOST || import.meta.env.VITE_POSTHOG_HOST || 'https://us.i.posthog.com';

if (posthogKey) {
  import('posthog-js').then(({ default: posthog }) => {
    window.posthog = posthog;
    posthog.init(posthogKey, {
      api_host: posthogHost,
      person_profiles: 'identified_only',
      capture_pageview: true,
    });
  }).catch((err) => console.error('PostHog initialization failed:', err));
}
