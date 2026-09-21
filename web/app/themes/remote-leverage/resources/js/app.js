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

/**
 * There is deliberately no synchronous getBoundingClientRect() fast path here.
 *
 * Reading a rect during init forces the browser to lay out the whole document on the
 * main thread before it would otherwise have to — on a page the size of the homepage
 * (190 KB of markup, 76 images) that is tens of milliseconds, and it is what Lighthouse
 * reports as "Forced reflow". IntersectionObserver costs nothing here: it delivers its
 * first callback off the next natural layout, so an element that is already on screen
 * still fires immediately, one frame later.
 */
function whenVisible(el, callback, rootMargin = '200px') {
  if (!el || !('IntersectionObserver' in window)) {
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

    /*
     * The input's bound value.
     *
     * The template binds `x-model="phoneVal"`, but this component never declared it — so after
     * a Livewire DOM patch (navigating back to step 1) Alpine resolved the name against
     * whatever was in scope and wrote a non-string into the field, which the browser rendered
     * as "[object Object]". Declaring it, and coercing on every write, makes that impossible
     * regardless of what Alpine finds.
     */
    phoneVal: typeof config.phone === 'string' ? config.phone : '',
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
          const number = this.iti.getNumber();
          const nextPhone = typeof number === 'string' && number !== ''
            ? number
            : String(input.value ?? '');

          this.phoneVal = nextPhone;
          wire.phone = nextPhone;
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

      /*
       * Autofill catch-up. Now that phone is a required field, a browser or password manager
       * that fills the box without emitting `input` would leave `$wire.phone` empty and block
       * a visitor who can plainly see their number on screen. Cheap to re-check; the same
       * staggered timings adjustPhonePadding already uses.
       */
      const syncIfFilledSilently = () => {
        if (String(input.value ?? '').trim() !== '' && String(this.phoneVal ?? '').trim() === '') {
          sync();
        }
      };
      setTimeout(syncIfFilledSilently, 400);
      setTimeout(syncIfFilledSilently, 1200);

      const wire = this.getWire();
      // Livewire hands this back as a reactive proxy, not always a plain string —
      // intl-tel-input calls .indexOf() on it and throws if it isn't one.
      const phone = wire && typeof wire.phone === 'string' ? wire.phone : '';

      this.phoneVal = phone;

      if (phone && this.iti) {
        this.iti.setNumber(phone);
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

/**
 * The progressive "isolated fields" reveal on step 1.
 *
 * Reads field values straight off `$wire` rather than keeping its own copies.
 *
 * It used to mirror every field into a shadow property (`emailVal`, `firstNameVal`, …) seeded
 * from the server through the `x-data` attribute, with the inputs carrying both `x-model` and
 * `wire:model`. Two things then went wrong together: Livewire rewrites that attribute on every
 * re-render, which makes Alpine rebuild this component from the server's last-known values;
 * and the duplicate `x-model` binding pushed those stale values back into the DOM. Typing a
 * name while the revenue field's `.live` round trip was in flight lost the name — Livewire's
 * own state kept it, the shadow copy did not, and the shadow copy owned the input.
 *
 * `$wire` is the single source of truth and is reactive, so these getters re-evaluate in the
 * `x-show` / `:class` effects exactly as the old properties did. Deferred `wire:model` still
 * updates `$wire` locally on every keystroke — it only defers the network request — so the
 * reveal remains instant and works offline.
 */
export function rlBookingWizardIsolated(config = {}) {
  return {
    isolated: Boolean(config.isolated),
    steps: Array.isArray(config.steps) ? config.steps : [],
    currentSubStep: 0,

    /** Livewire hands back reactive proxies; coerce before calling string methods on them. */
    wireString(prop) {
      const value = this.$wire ? this.$wire[prop] : '';
      return typeof value === 'string' ? value : '';
    },

    isFieldValid(f) {
      if (f === 'email') {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(this.wireString('email').trim());
      }
      if (f === 'name') {
        return this.wireString('firstName').trim().length > 0
          && this.wireString('lastName').trim().length > 0;
      }
      if (f === 'phone') {
        /*
         * Read the input, not `$wire.phone`. The field sits inside `wire:ignore` and is owned
         * by phoneInputComponent, which writes `$wire.phone` on input/change/blur/
         * countrychange — so gating on the wire property lags the typing by a round trip, and
         * misses an autofill that never emitted those events entirely. The DOM value is what
         * the visitor can actually see.
         *
         * This used to return true unconditionally while the label carried a red asterisk and
         * the server rule read `nullable`, which is how bookings reached `booked` with no
         * phone number.
         */
        const input = document.getElementById('booking-phone-input');
        if (input) return String(input.value ?? '').trim().length > 0;
        return this.wireString('phone').trim().length > 0;
      }
      if (f === 'monthly_revenue') {
        return this.wireString('monthlyRevenue').trim().length > 0;
      }
      if (f === 'consent') {
        // Recorded, not required: an unticked box must never hold the sub-step
        // shut, or clearing its default would strand the isolated wizard.
        return true;
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
 * Bring the form back into view when the wizard changes step.
 *
 * Each step is a different height — step 1 is ~720px, the calendar ~306px — so advancing
 * shrinks the document under the visitor while the scroll position stays put. On a phone that
 * leaves the form above the viewport and the footer filling the screen: measured on an iPhone
 * 13, stepping to the calendar put its first row 83px off the top with the footer below it.
 * The visitor is simply stranded, with no indication anything happened.
 *
 * Watches `$wire` rather than listening for a dispatched event, so no server change is needed
 * and every route into a new step is covered — the Continue button, `selectDate()` jumping
 * straight to step 3, the Back buttons, and the pricing warning appearing or being dismissed.
 * Same `$watch('$wire.…')` shape already used by instant-live-call-button.
 */
export function rlBookingStepScroll() {
  return {
    scrollToForm() {
      // Wait for the new step to be painted, or we measure the old layout's height.
      requestAnimationFrame(() => {
        const rect = this.$el.getBoundingClientRect();

        // A sticky site header would otherwise cover the top of the card.
        const header = document.querySelector('header.sticky, header.fixed, [data-sticky-header]');
        const offset = (header ? header.getBoundingClientRect().height : 0) + 16;

        // Leave it alone when the top of the form is already comfortably in view —
        // scrolling a desktop visitor who can see the whole card is just a jolt.
        if (rect.top >= offset && rect.top < window.innerHeight * 0.5) return;

        const reduced = window.matchMedia?.('(prefers-reduced-motion: reduce)')?.matches;

        window.scrollTo({
          top: Math.max(0, rect.top + window.scrollY - offset),
          behavior: reduced ? 'auto' : 'smooth',
        });
      });
    },
    init() {
      if (!this.$wire) return;
      this.$watch('$wire.currentStep', () => this.scrollToForm());
      this.$watch('$wire.showWarning', () => this.scrollToForm());
    },
  };
}

window.rlBookingStepScroll = rlBookingStepScroll;

export function rlAudioPlayer(initialDuration = '0:45') {
  return {
    playing: false,
    currentTime: '0:00',
    durationTime: initialDuration,
    progress: 0,
    init() {
      window.addEventListener('paused-externally', (e) => {
        if (this.$refs.audio && this.$refs.audio !== e.target) {
          this.playing = false;
        }
      });
    },
    toggle() {
      const audio = this.$refs.audio;
      if (!audio) return;
      if (this.playing) {
        audio.pause();
        this.playing = false;
      } else {
        document.querySelectorAll('audio').forEach((a) => {
          if (a !== audio) {
            a.pause();
            a.dispatchEvent(new CustomEvent('paused-externally'));
          }
        });
        audio.play().catch(() => {});
        this.playing = true;
      }
    },
    seek(event) {
      const track = this.$refs.track;
      if (!track) return;
      const rect = track.getBoundingClientRect();
      const clickX = event.clientX - rect.left;
      const width = rect.width;
      const percent = Math.max(0, Math.min(1, clickX / width));
      const audio = this.$refs.audio;
      if (audio && audio.duration) {
        audio.currentTime = percent * audio.duration;
        this.progress = percent * 100;
      }
    },
    onLoadedMetadata() {
      const audio = this.$refs.audio;
      if (audio && !isNaN(audio.duration) && audio.duration > 0) {
        this.durationTime = this.formatTime(audio.duration);
      }
    },
    onTimeUpdate() {
      const audio = this.$refs.audio;
      if (audio && !isNaN(audio.duration) && audio.duration > 0) {
        this.progress = (audio.currentTime / audio.duration) * 100;
        this.currentTime = this.formatTime(audio.currentTime);
      }
    },
    onEnded() {
      this.playing = false;
      this.progress = 0;
      this.currentTime = '0:00';
    },
    formatTime(sec) {
      if (isNaN(sec) || sec === null || sec === undefined) return '0:00';
      const m = Math.floor(sec / 60);
      const s = Math.floor(sec % 60);
      return m + ':' + (s < 10 ? '0' : '') + s;
    },
  };
}

window.rlAudioPlayer = rlAudioPlayer;
window.formatTime = function (sec) {
  if (isNaN(sec) || sec === null || sec === undefined) return '0:00';
  const m = Math.floor(sec / 60);
  const s = Math.floor(sec % 60);
  return m + ':' + (s < 10 ? '0' : '') + s;
};

/**
 * Highlights the section currently being read in the legal-document sidebar nav.
 *
 * Uses a scroll listener rather than IntersectionObserver because legal sections
 * vary wildly in length — a long clause can leave no heading inside an observer
 * band at all, which reads to the user as the highlight falling off.
 */
function rlDocumentToc() {
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

window.rlDocumentToc = rlDocumentToc;

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

    // Every geometry read happens before the first write. markCentre() used to run first
    // and toggle [data-rl-center] on the cards, which invalidates layout, so the
    // maxScroll() read that followed forced a synchronous relayout — once at init and then
    // once per scroll event, since sync() is also the scroll handler.
    const sync = () => {
      const max = maxScroll();
      const left = track.scrollLeft;
      const active = isCentre ? centreIndex() : -1;

      paintCentre(active);

      if (prev) {
        prev.disabled = left <= 1;
      }

      if (next) {
        next.disabled = max <= 1 || left >= max - 1;
      }
    };

    // Scroll fires far more often than the screen repaints, and a smooth scroll or a drag
    // emits a burst of them. Coalescing to one pass per frame keeps that from queueing a
    // layout per event.
    let syncQueued = false;

    const queueSync = () => {
      if (syncQueued) {
        return;
      }

      syncQueued = true;

      requestAnimationFrame(() => {
        syncQueued = false;
        sync();
      });
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

    // Measured off bounding rects rather than offsetLeft: the track isn't a
    // positioned ancestor, so offsetLeft reports coordinates from far up the tree.
    const trackCentre = () => {
      const rect = track.getBoundingClientRect();

      return rect.left + rect.width / 2;
    };

    const cardOffset = (card) => {
      const rect = card.getBoundingClientRect();

      return rect.left + rect.width / 2 - trackCentre();
    };

    // Pure read. The track's own rect is taken once rather than once per card — cardOffset()
    // calls trackCentre(), so measuring this way was two rect reads per card.
    const centreIndex = () => {
      const centre = trackCentre();

      let best = 0;
      let bestGap = Infinity;

      cards().forEach((card, i) => {
        const rect = card.getBoundingClientRect();
        const gap = Math.abs(rect.left + rect.width / 2 - centre);

        if (gap < bestGap) {
          bestGap = gap;
          best = i;
        }
      });

      return best;
    };

    // Pure write. Kept separate from centreIndex() so sync() can do all of its reading
    // before any of its writing.
    const paintCentre = (active) => {
      if (!isCentre || active < 0) {
        return;
      }

      cards().forEach((card, i) => {
        card.toggleAttribute('data-rl-center', i === active);
      });

      dots.forEach((dot, i) => {
        dot.setAttribute('aria-current', i === active ? 'true' : 'false');
      });
    };

    const markCentre = () => paintCentre(isCentre ? centreIndex() : -1);

    const goToIndex = (i, behavior = 'smooth') => {
      const card = cards()[i];

      if (!card) {
        return;
      }

      track.scrollBy({ left: cardOffset(card), behavior });
    };

    dots.forEach((dot, i) => {
      dot.addEventListener('click', () => { hold(); goToIndex(i); });
    });

    track.addEventListener('scroll', queueSync, { passive: true });
    window.addEventListener('resize', queueSync, { passive: true });
    document.addEventListener('visibilitychange', () => { document.hidden ? stop() : start(); });

    // Deferred a frame for the same reason as the probes above: this runs inside
    // DOMContentLoaded, and sync() reads geometry. The arrows are enabled for that one
    // frame, which is not long enough to click.
    queueSync();

    // Centre carousels open on the middle card, as the legacy slick config did.
    if (isCentre && cards().length) {
      const middle = Math.floor(cards().length / 2);
      requestAnimationFrame(() => {
        goToIndex(middle, 'auto');
        markCentre();
      });
    }

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
    window.Alpine.data('rlBookingStepScroll', rlBookingStepScroll);
    window.Alpine.data('rlDocumentToc', rlDocumentToc);
    window.Alpine.data('rlAudioPlayer', rlAudioPlayer);
  }
};

if (window.Alpine) {
  registerAlpine();
} else {
  document.addEventListener('alpine:init', registerAlpine);
}


/**
 * Safety net for [x-data] islands Alpine's own walk did not reach.
 *
 * Alpine.start() — run from Livewire.start(), see startLivewire below — walks the
 * whole document and initialises every island itself, so this is normally a no-op.
 * It stays as a fallback for markup injected after that walk.
 *
 * Skips anything Alpine has already initialised (its own marker), which keeps this
 * idempotent and safe to re-run after Livewire morphs. Never call it before
 * Alpine.start(): initialising a tree early runs its directives against a set of
 * plugins that are not registered yet (x-collapse et al) and double-initialises it
 * when the real walk arrives.
 */
function initAlpineIslands() {
  const Alpine = window.Alpine;

  if (!Alpine || typeof Alpine.initTree !== 'function') {
    return false;
  }

  registerAlpine();

  document.querySelectorAll('[x-data]').forEach((el) => {
    if (el._x_dataStack) {
      return;
    }

    try {
      Alpine.initTree(el);
    } catch (error) {
      console.error('Alpine island failed to initialise', el, error);
    }
  });

  return true;
}

window.rlInitAlpineIslands = initAlpineIslands;

let alpineStarted = false;

document.addEventListener('alpine:initialized', () => {
  alpineStarted = true;
  initAlpineIslands();
});

/**
 * Start Livewire (and with it Alpine) by hand.
 *
 * livewire.js only self-starts from a DOMContentLoaded listener it registers when
 * the bundle executes. We inject that bundle lazily (see bootLivewire), always
 * after DOMContentLoaded has already fired, so that listener never runs: without
 * this call Livewire.start() is never reached, every wire: component stays inert
 * and Alpine never registers the plugin directives Livewire bundles — which is
 * what the "[x-collapse] without first installing the Collapse plugin" warnings
 * on the FAQ accordions were.
 */
function startLivewire() {
  if (window.__rlLivewireStarted || alpineStarted) {
    return true;
  }

  const livewire = window.Livewire;

  if (!livewire || typeof livewire.start !== 'function') {
    return false;
  }

  window.__rlLivewireStarted = true;

  // Alpine.data() has to be registered before Alpine.start(), which Livewire.start()
  // calls internally. The alpine:init listener covers this too; doing it here keeps
  // it true regardless of listener ordering.
  registerAlpine();
  livewire.start();

  return true;
}

/**
 * The script's load event is the primary trigger; the poll covers the case where
 * it fired before the handler was attached (cached bundle) or never fires at all.
 */
function watchForLivewire() {
  let attempts = 0;
  const poll = setInterval(() => {
    attempts += 1;

    if (startLivewire() || attempts > 100) {
      clearInterval(poll);
    }
  }, 50);
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
    if (orig.src) {
      script.addEventListener('load', () => startLivewire());
    }
    document.body.appendChild(script);
  });

  watchForLivewire();
}

function scheduleLivewire() {
  const targets = document.querySelectorAll('[wire\\:id], [wire\\:snapshot], [x-data], #booking-footer');
  if (!targets.length) {
    return;
  }

  // Same reasoning as whenVisible(): the rect probe that used to stand here ran inside
  // DOMContentLoaded and forced the document's first full layout synchronously, for an
  // answer the observer below gives for free one frame later. The observer's rootMargin
  // is the same 800px the probe tested against, so what boots eagerly has not changed.
  if (window.location.hash === '#booking-footer' || !('IntersectionObserver' in window)) {
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

/**
 * Stripe Elements checkout (acf/payment-gateway).
 *
 * Imported dynamically and only when a card is on the page: the module pulls in
 * intl-tel-input and injects Stripe.js, none of which belongs on the other ~40 pages.
 */
function initPaymentGateway() {
  if (!document.querySelector('.rl-payment-card')) {
    return;
  }

  import('./payment-gateway.js')
    .then(({ initPaymentGateways }) => initPaymentGateways())
    .catch((err) => console.error('Payment gateway failed to initialise', err));
}

/**
 * Paints the CTA-only landing header (sections/header-cta.blade.php) white once the
 * page has scrolled off the top.
 *
 * The bar is `fixed` and transparent at rest so the hero artwork runs under it; that
 * artwork ends, and a white logo on a white page section is an invisible header. The
 * `data-stuck` attribute is the single switch — every colour change hangs off it in
 * the Blade template, so there is nothing to keep in sync here.
 *
 * rAF-throttled: the scroll listener only records that a frame is due, and the write
 * happens once per frame, so a fast flick does not queue a style recalc per event.
 */
function initCtaHeader() {
  const header = document.querySelector('[data-rl-cta-header]');
  if (!header) {
    return;
  }

  const THRESHOLD = 24; // Clear of the one-pixel jitter a trackpad produces at rest.
  let ticking = false;
  let stuck = null;

  const apply = () => {
    ticking = false;
    const next = window.scrollY > THRESHOLD;
    if (next === stuck) {
      return;
    }
    stuck = next;
    header.toggleAttribute('data-stuck', next);
  };

  const onScroll = () => {
    if (!ticking) {
      ticking = true;
      window.requestAnimationFrame(apply);
    }
  };

  window.addEventListener('scroll', onScroll, { passive: true });
  // A reload part-way down the page restores the scroll position before this runs.
  apply();
}

function initMobileNav() {
  const button = document.querySelector('[data-rl-nav-toggle]');
  const panel = document.getElementById('rl-mobile-nav');
  if (!button || !panel) {
    return;
  }

  const iconOpen = button.querySelector('[data-rl-nav-open]');
  const iconClose = button.querySelector('[data-rl-nav-close]');

  // `hidden` is an HTMLElement IDL property. These two icons are <svg>, i.e.
  // SVGSVGElement, where assigning `.hidden` sets an inert JS property and never
  // reflects to the attribute the UA stylesheet's [hidden] rule keys off — so the
  // hamburger never became an X. Toggle the attribute itself.
  const setHidden = (el, hide) => el && el.toggleAttribute('hidden', hide);

  const setOpen = (open) => {
    button.setAttribute('aria-expanded', open ? 'true' : 'false');
    panel.hidden = !open;
    setHidden(iconOpen, open);
    setHidden(iconClose, !open);
  };

  setOpen(false);
  button.addEventListener('click', () => setOpen(panel.hidden));
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => {
    initMobileNav();
    initCtaHeader();
    initPaymentGateway();
    scheduleLivewire();
  });
} else {
  initMobileNav();
  initCtaHeader();
  initPaymentGateway();
  scheduleLivewire();
}

// Expose global placeholders so early callers don't throw
window.Sentry = window.Sentry || null;
window.posthog = window.posthog || null;

/*
 * Browser error reporting.
 *
 * Unfiltered, this reports almost nothing useful. The overwhelming majority of front-end
 * "errors" on a marketing site come from code we did not write and cannot fix: browser
 * extensions injecting scripts, GTM tags referencing globals that never loaded, ad pixels,
 * and bots running engines from 2016. Production's legacy Sentry is exactly this — a
 * representative alert is `ReferenceError: oaiq is not defined`, a measurement global a GTM tag
 * expects, from a page we do not control the tags on.
 *
 * An alert channel that is mostly noise is worse than none, because it trains people to ignore
 * the one real error when it arrives. The filters below are ordered by how much they remove.
 */

// Errors that are known-benign, or are browser quirks with no action attached to them.
const SENTRY_IGNORE = [
  // Cross-origin script error with no stack. Nothing actionable is ever in it.
  'Script error.',
  'Script error',
  // Fired by browsers when a ResizeObserver callback takes slightly too long. Harmless, and
  // triggered by ordinary layout work.
  /ResizeObserver loop/,
  // A rejected promise whose reason is not an Error. Usually a third-party fetch.
  'Non-Error promise rejection captured',
  // The visitor navigated away, or the network dropped, mid-request.
  /Failed to fetch/,
  /NetworkError when attempting to fetch resource/,
  /Load failed/,
  /AbortError/,
  'TypeError: cancelled',
  'TypeError: Cancelled',
  // Safari private mode and storage-blocked contexts.
  /QuotaExceededError/,
  /The operation is insecure/,
  // Extension and injected-script noise that slips past denyUrls.
  /^ResizeObserver/,
  /Can't find variable: \$/,
  /__firefox__/,
  /webkitExitFullScreen/,
  // Instagram and Facebook in-app browsers inject these.
  /_AutofillCallbackHandler/,
  /instantSearchSDKJSBridgeClearHighlight/,
];

// Scripts we will never be able to fix, so an error inside one is not a bug report.
const SENTRY_DENY = [
  /extensions\//i,
  /^chrome:\/\//i,
  /^chrome-extension:\/\//i,
  /^moz-extension:\/\//i,
  /^safari-(web-)?extension:\/\//i,
  /googletagmanager\.com/i,
  /google-analytics\.com/i,
  /connect\.facebook\.net/i,
  /snap\.licdn\.com/i,
  /static\.hotjar\.com/i,
  /cdp\.customer\.io/i,
  /assets\.calendly\.com/i,
  /js\.stripe\.com/i,
];

// Engines that are not people. A crawler hitting a JS error tells us nothing about a customer's
// experience, and headless browsers generate a disproportionate share of the total.
const SENTRY_BOT_UA = /bot|crawl|spider|slurp|headless|phantom|puppeteer|playwright|lighthouse|pagespeed|gtmetrix|pingdom|uptime|preview|scrape|curl|wget|python-requests|semrush|ahrefs|mj12|dotbot|petal|bytespider/i;

const sentryDsn = window.SENTRY_DSN || import.meta.env.VITE_SENTRY_DSN;

/*
 * Automation reports `navigator.webdriver`, and it is the only reliable signal left.
 *
 * The user-agent list below used to catch Lighthouse because it appended `Chrome-Lighthouse`;
 * it no longer does. Measured 2026-09-18, a Lighthouse run reports a plain
 * `Chrome/153.0.0.0 Mobile Safari/537.36`, so the guard had silently stopped working and every
 * audit and headless bot was pulling 133KB of Sentry it would never report from. The UA list
 * stays for the crawlers that still identify themselves honestly.
 */
const isAutomated = () => {
  try {
    return navigator.webdriver === true || SENTRY_BOT_UA.test(navigator.userAgent || '');
  } catch (e) {
    return false;
  }
};

if (sentryDsn && !isAutomated()) {
  import('@sentry/browser').then((Sentry) => {
    window.Sentry = Sentry;

    Sentry.init({
      dsn: sentryDsn,
      environment: window.APP_ENV || 'production',
      release: window.APP_VERSION || undefined,

      // Performance sampling is separate from error sampling and much cheaper to lose.
      tracesSampleRate: 0.1,

      ignoreErrors: SENTRY_IGNORE,
      denyUrls: SENTRY_DENY,

      /*
       * The single biggest filter: only report errors whose stack points at our own code.
       *
       * Everything a GTM tag, an extension or an embedded widget throws lands outside this and
       * is dropped before it becomes an alert. `allowUrls` matches against the frames in the
       * stack, so an error merely *triggered* on our page but thrown inside a third-party
       * bundle does not qualify.
       */
      allowUrls: [new RegExp(window.location.host.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'))],

      beforeSend(event) {
        /*
         * Drop events with no usable stack.
         *
         * A cross-origin script error arrives with an empty frame list, which Sentry groups
         * into one enormous unactionable issue. If we cannot see where it happened we cannot
         * fix it, so it is not worth waking anyone for.
         */
        const frames = event.exception?.values?.[0]?.stacktrace?.frames;

        if (event.exception && (!frames || frames.length === 0)) {
          return null;
        }

        return event;
      },
    });
  }).catch((err) => console.error('Sentry initialization failed:', err));
}

// PostHog is NOT initialised here. TrackingHooks::injectPostHogSnippet() already
// installs the queueing stub on `wp_head` (priority 2) and defers `array.js`
// until idle/load/interaction, which is earlier than this module and is what
// production does. This block used to import the `posthog-js` package and call
// `init()` a second time against the same key, which loaded two copies of the
// SDK and captured every pageview twice. It was invisible only because
// POSTHOG_API_KEY has never been set.
//
// `window.posthog` is the snippet's queueing stub until array.js lands, so
// callers such as resources/js/payment-gateway.js can call `posthog.capture()`
// immediately either way.
