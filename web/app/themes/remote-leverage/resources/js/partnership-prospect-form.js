/**
 * The /become-a-partner/ form's browser half: bringing the card back into view after submit, and
 * listening to the Calendly page embedded in it (App\Application\Livewire\Partner\PartnershipProspectForm).
 *
 * A module rather than inline x-data, and not only for tidiness. The form renders inside post
 * content, and WordPress runs wptexturize over that: an arrow function's `=>` or a `>` comparison
 * reads to it as the end of the tag, so everything after it in the attribute is "text" and its
 * quotes are curled — which is a syntax error by the time Alpine evaluates it. Kept out of the
 * markup, there is nothing for it to touch.
 *
 * `calendlyMessage` is pure and exported so it can be run under node. Keep it free of `window`.
 */

/**
 * What a message from the embedded Calendly page means, or null when it is not one we act on.
 *
 * Only messages from Calendly's own origin count — the iframe is the only thing on the page that
 * speaks this protocol, and anything else posting `calendly.event_scheduled` is not Calendly.
 *
 * @returns {{ type: 'height', height: number } | { type: 'scheduled', event: string, invitee: string } | null}
 */
export function calendlyMessage(event, origin) {
  if (!event || event.origin !== origin || !event.data || typeof event.data.event !== 'string') {
    return null;
  }

  const payload = event.data.payload || {};

  if (event.data.event === 'calendly.page_height') {
    const height = parseInt(payload.height, 10);

    return height > 0 ? { type: 'height', height } : null;
  }

  if (event.data.event === 'calendly.event_scheduled') {
    return {
      type: 'scheduled',
      event: String((payload.event || {}).uri || ''),
      invitee: String((payload.invitee || {}).uri || ''),
    };
  }

  return null;
}

/**
 * The card root. `reveal()` scrolls it back into view once the form has been replaced — the
 * thank-you is a fifth of the form's height, so a visitor who pressed the button at the bottom is
 * otherwise left looking at the section below it.
 *
 * Not the booking wizard's rlBookingStepScroll: that watches `$wire.currentStep`, and on a
 * component without the property Livewire resolves the read as a call to a server method.
 */
export function rlPartnershipForm() {
  return {
    reveal() {
      this.$nextTick(() => {
        // The Livewire root, not `$root`: called from the calendar, `$root` is the calendar's
        // own x-data, and scrolling that to the top tucks the card's heading under the header.
        const card = this.$root.closest('[wire\\:id]') || this.$root;
        const top = card.getBoundingClientRect().top;

        if (top >= 80 && top < window.innerHeight / 2) {
          return;
        }

        const reduced = window.matchMedia?.('(prefers-reduced-motion: reduce)')?.matches;

        card.scrollIntoView({ behavior: reduced ? 'auto' : 'smooth', block: 'start' });
      });
    },
  };
}

/**
 * The embedded Calendly page. Grows the iframe to the height Calendly reports, and tells the
 * server when the call is booked.
 *
 * The starting height is what Calendly's stacked layout needs at the card's width, so a page that
 * never reports its height still shows the whole calendar.
 */
export function rlPartnershipCalendar(origin) {
  return {
    height: 1050,

    init() {
      this.onMessage = (event) => {
        const message = calendlyMessage(event, origin);

        if (message?.type === 'height') {
          this.height = message.height;
        } else if (message?.type === 'scheduled') {
          // A failed call costs our note of the booking, never the booking: it is in Calendly.
          this.$wire.recordBooking(message.event, message.invitee).catch(() => {});
        }
      };

      window.addEventListener('message', this.onMessage);
    },

    destroy() {
      window.removeEventListener('message', this.onMessage);
    },
  };
}
