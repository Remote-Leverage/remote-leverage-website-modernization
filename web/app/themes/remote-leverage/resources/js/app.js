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

/**
 * Highlights the section currently being read in the legal-document sidebar nav.
 *
 * Uses a scroll listener rather than IntersectionObserver because legal sections
 * vary wildly in length — a long clause can leave no heading inside an observer
 * band at all, which reads to the user as the highlight falling off.
 */
function rlLegalToc() {
  return {
    active: '',

    observe() {
      const headings = Array.from(document.querySelectorAll('article h2[id]'));

      if (!headings.length) {
        return;
      }

      const OFFSET = 120; // Sticky header (80px) plus breathing room.
      const nav = this.$el.querySelector('nav');

      const update = () => {
        let current = headings[0].id;

        for (const heading of headings) {
          if (heading.getBoundingClientRect().top > OFFSET) {
            break;
          }
          current = heading.id;
        }

        // Bottom of the page: the last section is what's on screen, even though
        // its heading scrolled past the offset line a long way back.
        if (window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 2) {
          current = headings[headings.length - 1].id;
        }

        if (current === this.active) {
          return;
        }

        this.active = current;
        this.revealActiveLink(nav, current);
      };

      let ticking = false;

      const onScroll = () => {
        if (ticking) {
          return;
        }

        ticking = true;

        requestAnimationFrame(() => {
          update();
          ticking = false;
        });
      };

      window.addEventListener('scroll', onScroll, { passive: true });
      window.addEventListener('resize', onScroll, { passive: true });

      update();
    },

    /**
     * Keep the highlighted link visible when the section list is long enough to
     * scroll on its own. Adjusts the nav's own scrollTop rather than calling
     * scrollIntoView, which would also scroll the page and fight the reader.
     */
    revealActiveLink(nav, id) {
      if (!nav || nav.scrollHeight <= nav.clientHeight) {
        return;
      }

      const link = nav.querySelector(`[data-toc-link="${id}"]`);

      if (!link) {
        return;
      }

      const linkTop = link.offsetTop - nav.offsetTop;
      const linkBottom = linkTop + link.offsetHeight;

      if (linkTop < nav.scrollTop) {
        nav.scrollTop = linkTop - 12;
      } else if (linkBottom > nav.scrollTop + nav.clientHeight) {
        nav.scrollTop = linkBottom - nav.clientHeight + 12;
      }
    },
  };
}

window.rlLegalToc = rlLegalToc;

const registerAlpine = () => {
  if (window.Alpine) {
    window.Alpine.data('phoneInputComponent', phoneInputComponent);
    window.Alpine.data('rlBookingWizardIsolated', rlBookingWizardIsolated);
    window.Alpine.data('rlLegalToc', rlLegalToc);
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
