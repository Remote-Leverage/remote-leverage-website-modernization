window.intlTelInput = window.intlTelInput || null;

let itiLoadPromise = null;
function loadIntlTelInput() {
  if (window.intlTelInput) return Promise.resolve(window.intlTelInput);
  if (itiLoadPromise) return itiLoadPromise;

  itiLoadPromise = Promise.all([
    import('intl-tel-input/intlTelInputWithUtils'),
    import('intl-tel-input/build/css/intlTelInput.css'),
  ]).then(([mod]) => {
    window.intlTelInput = mod.default;
    return window.intlTelInput;
  });

  return itiLoadPromise;
}

function whenVisible(el, callback, rootMargin = '200px') {
  if (!el) {
    callback();
    return;
  }

  const rect = el.getBoundingClientRect();
  if (rect.top < window.innerHeight + 200 && rect.bottom > -200) {
    callback();
    return;
  }

  const io = new IntersectionObserver((entries) => {
    if (entries.some((entry) => entry.isIntersecting)) {
      io.disconnect();
      callback();
    }
  }, { rootMargin });

  io.observe(el);
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
      whenVisible(this.$el, () => {
        loadIntlTelInput().then(() => this.initIti());
      });
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

/**
 * Horizontal card carousel: auto-advance, arrow buttons, and pointer drag.
 *
 * Deliberately plain JS rather than an Alpine component — Alpine only loads lazily
 * (see the rl-livewire-scripts island loader below), so a carousel built on it is
 * dead until the reader happens to scroll near it. Native scroll-snap still does the
 * actual scrolling, so touch and trackpad work even before this runs.
 */
function initCarousels() {
  document.querySelectorAll('[data-rl-carousel]').forEach((root) => {
    const track = root.querySelector('[data-rl-carousel-track]');

    if (!track || track.dataset.rlCarouselReady) {
      return;
    }

    track.dataset.rlCarouselReady = '1';

    const prev = root.querySelector('[data-rl-carousel-prev]');
    const next = root.querySelector('[data-rl-carousel-next]');
    const GAP = 10;
    const DELAY = 4000;

    const maxScroll = () => track.scrollWidth - track.clientWidth;

    const step = () => {
      const card = track.querySelector('[data-rl-carousel-card]');

      return card ? card.offsetWidth + GAP : track.clientWidth * 0.8;
    };

    const sync = () => {
      markCentre();

      const max = maxScroll();

      if (prev) {
        prev.disabled = track.scrollLeft <= 1;
      }

      if (next) {
        next.disabled = max <= 1 || track.scrollLeft >= max - 1;
      }
    };

    const advance = (direction) => {
      track.scrollBy({ left: step() * direction, behavior: 'smooth' });
    };

    // --- auto-advance -------------------------------------------------------
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    let timer = null;
    let paused = false;
    let resumeTimer = null;

    const tick = () => {
      if (paused || document.hidden || maxScroll() <= 1) {
        return;
      }

      if (isCentre) {
        const next = centreIndex() + 1;
        goToIndex(next >= cards().length ? 0 : next);
      } else if (track.scrollLeft >= maxScroll() - 1) {
        track.scrollTo({ left: 0, behavior: 'smooth' });
      } else {
        advance(1);
      }
    };

    const start = () => {
      if (!reduceMotion && !timer) {
        timer = setInterval(tick, DELAY);
      }
    };

    const stop = () => {
      clearInterval(timer);
      timer = null;
    };

    // A manual interaction holds autoplay off for a while so it doesn't yank the
    // track back out from under the reader.
    const hold = () => {
      paused = true;
      clearTimeout(resumeTimer);
      resumeTimer = setTimeout(() => { paused = false; }, DELAY * 2);
    };

    root.addEventListener('pointerenter', () => { paused = true; });
    root.addEventListener('pointerleave', () => { paused = false; });
    root.addEventListener('focusin', () => { paused = true; });
    root.addEventListener('focusout', () => { paused = false; });

    prev?.addEventListener('click', () => { hold(); advance(-1); });
    next?.addEventListener('click', () => { hold(); advance(1); });

    // --- pointer drag -------------------------------------------------------
    let dragging = false;
    let moved = false;
    let originX = 0;
    let originLeft = 0;

    track.addEventListener('pointerdown', (event) => {
      if (event.pointerType === 'mouse' && event.button !== 0) {
        return;
      }

      dragging = true;
      moved = false;
      originX = event.clientX;
      originLeft = track.scrollLeft;
      hold();
    });

    track.addEventListener('pointermove', (event) => {
      if (!dragging) {
        return;
      }

      const delta = event.clientX - originX;

      if (!moved && Math.abs(delta) > 3) {
        moved = true;
        track.style.scrollSnapType = 'none';
        track.classList.add('cursor-grabbing');
        track.setPointerCapture?.(event.pointerId);
      }

      if (moved) {
        track.scrollLeft = originLeft - delta;
      }
    });

    const endDrag = () => {
      if (!dragging) {
        return;
      }

      dragging = false;
      track.style.scrollSnapType = '';
      track.classList.remove('cursor-grabbing');
    };

    track.addEventListener('pointerup', endDrag);
    track.addEventListener('pointercancel', endDrag);

    // Swallow the click that ends a drag so cards don't activate mid-swipe.
    track.addEventListener('click', (event) => {
      if (moved) {
        event.preventDefault();
        event.stopPropagation();
        moved = false;
      }
    }, true);

    // --- centre mode ---------------------------------------------------------
    // Ported from the legacy rl-elementor-blocks guarantee carousel (slick
    // centerMode) without pulling in jQuery + slick: the card nearest the track's
    // centre gets [data-rl-center], and CSS does the scale/elevation from there.
    const isCentre = root.hasAttribute('data-rl-carousel-center');
    const dots = [...root.querySelectorAll('[data-rl-carousel-dot]')];

    const cards = () => [...track.querySelectorAll('[data-rl-carousel-card]')];

    const centreIndex = () => {
      const mid = track.scrollLeft + track.clientWidth / 2;
      let best = 0;
      let bestGap = Infinity;

      cards().forEach((card, i) => {
        const gap = Math.abs(card.offsetLeft + card.offsetWidth / 2 - mid);

        if (gap < bestGap) {
          bestGap = gap;
          best = i;
        }
      });

      return best;
    };

    const markCentre = () => {
      if (!isCentre) {
        return;
      }

      const active = centreIndex();

      cards().forEach((card, i) => {
        card.toggleAttribute('data-rl-center', i === active);
      });

      dots.forEach((dot, i) => {
        dot.setAttribute('aria-current', i === active ? 'true' : 'false');
      });
    };

    const goToIndex = (i) => {
      const card = cards()[i];

      if (!card) {
        return;
      }

      track.scrollTo({
        left: card.offsetLeft - (track.clientWidth - card.offsetWidth) / 2,
        behavior: 'smooth',
      });
    };

    dots.forEach((dot, i) => {
      dot.addEventListener('click', () => { hold(); goToIndex(i); });
    });

    track.addEventListener('scroll', sync, { passive: true });
    window.addEventListener('resize', sync, { passive: true });
    document.addEventListener('visibilitychange', () => { document.hidden ? stop() : start(); });

    sync();

    // Only run autoplay while the carousel is actually on screen.
    if ('IntersectionObserver' in window) {
      new IntersectionObserver((entries) => {
        entries.forEach((entry) => (entry.isIntersecting ? start() : stop()));
      }, { threshold: 0.2 }).observe(root);
    } else {
      start();
    }
  });
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initCarousels);
} else {
  initCarousels();
}

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

function bootLivewire() {
  if (window.__rlLivewireBooted) {
    return;
  }
  window.__rlLivewireBooted = true;

  const tpl = document.getElementById('rl-livewire-scripts');
  if (!tpl) {
    return;
  }

  tpl.content.querySelectorAll('script').forEach((orig) => {
    const script = document.createElement('script');
    Array.from(orig.attributes).forEach((attr) => {
      script.setAttribute(attr.name, attr.value);
    });
    if (orig.src) {
      script.src = orig.src;
    } else {
      script.textContent = orig.textContent;
    }
    // Dynamically inserted scripts default to async; keep Livewire's file +
    // inline start() in source order.
    script.async = false;
    document.body.appendChild(script);
  });
}

function scheduleLivewire() {
  const targets = document.querySelectorAll('[wire\\:id], [wire\\:snapshot], [x-data], #booking-footer');
  if (!targets.length) {
    return;
  }

  const nearViewport = Array.from(targets).some((el) => {
    const rect = el.getBoundingClientRect();
    return rect.top < window.innerHeight + 800;
  });

  if (nearViewport || window.location.hash === '#booking-footer') {
    bootLivewire();
    return;
  }

  const io = new IntersectionObserver((entries) => {
    if (entries.some((entry) => entry.isIntersecting)) {
      io.disconnect();
      bootLivewire();
    }
  }, { rootMargin: '800px 0px' });

  targets.forEach((el) => io.observe(el));

  document.addEventListener('click', (event) => {
    if (event.target.closest('a[href*="booking-footer"]')) {
      bootLivewire();
    }
  }, true);
}

function initMobileNav() {
  const button = document.querySelector('[data-rl-nav-toggle]');
  const panel = document.getElementById('rl-mobile-nav');
  if (!button || !panel) {
    return;
  }

  const iconOpen = button.querySelector('[data-rl-nav-open]');
  const iconClose = button.querySelector('[data-rl-nav-close]');

  const setOpen = (open) => {
    button.setAttribute('aria-expanded', open ? 'true' : 'false');
    panel.hidden = !open;
    if (iconOpen) {
      iconOpen.hidden = open;
    }
    if (iconClose) {
      iconClose.hidden = !open;
    }
  };

  setOpen(false);
  button.addEventListener('click', () => setOpen(panel.hidden));
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => {
    initMobileNav();
    scheduleLivewire();
  });
} else {
  initMobileNav();
  scheduleLivewire();
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
