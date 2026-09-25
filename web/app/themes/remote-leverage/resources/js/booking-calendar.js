/**
 * The booking wizard's date and time steps, rendered in the visitor's own zone.
 *
 * The server hands over one list: the tier's open slots as UTC instants (`$wire.openSlots`, see
 * MultistepBookingWizard::loadAvailability()). Everything that depends on a zone — which day a
 * slot falls on, how it is labelled, which month is "today" — is worked out here, with Intl.
 *
 * It used to be the other way round. The server fetched and cached availability per zone, per
 * month and per day, so every new zone, every date click and every zone change was a request,
 * and usually a Calendly call. Calendly answers in UTC regardless, so none of that work changed
 * the data; it only relabelled it. The 2026-09-23 campaign send filled all ten PHP workers with
 * exactly those calls, and every visitor whose call failed was left on New York times.
 *
 * Date, slot and step changes still reach the server — selectDate(), selectSlot() and
 * goToStep() carry the funnel events — but in the background: the screen changes first.
 *
 * The helpers are pure and exported so tests/Unit/BookingCalendarJsTest.php can run them under
 * node. Keep them free of `window` and Alpine.
 */

const formatters = new Map();

function formatter(timeZone, options) {
  const key = timeZone + JSON.stringify(options);

  if (!formatters.has(key)) {
    formatters.set(key, new Intl.DateTimeFormat('en-US', { timeZone, ...options }));
  }

  return formatters.get(key);
}

function parts(timeZone, options, value) {
  const out = {};

  for (const part of formatter(timeZone, options).formatToParts(new Date(value))) {
    out[part.type] = part.value;
  }

  return out;
}

/** `YYYY-MM-DD` of an instant, as a clock in `timeZone` reads it. */
export function dateKey(value, timeZone) {
  const p = parts(timeZone, { year: 'numeric', month: '2-digit', day: '2-digit' }, value);

  return `${p.year}-${p.month}-${p.day}`;
}

/**
 * A slot's label, `10:45am` — the same shape as PHP's `g:ia`, which this replaced.
 *
 * The instant is formatted *into* the zone. The 2026-09-21 bug was the reverse: a UTC clock time
 * printed under a banner naming the visitor's zone, so "3:45pm" booked 10:45 Central.
 */
export function timeLabel(value, timeZone) {
  const p = parts(timeZone, { hour: 'numeric', minute: '2-digit', hour12: true }, value);

  return `${p.hour}:${p.minute}${(p.dayPeriod || '').toLowerCase()}`;
}

/** The current time in a zone, `14:05` — PHP's `H:i`. h23 so midnight is 00, never 24. */
export function clockLabel(timeZone, now = Date.now()) {
  const p = parts(timeZone, { hour: '2-digit', minute: '2-digit', hourCycle: 'h23' }, now);

  return `${p.hour}:${p.minute}`;
}

/**
 * Open slots grouped by the day they fall on in `timeZone`, dropping any already started.
 *
 * Days come out soonest first — `nextOpen` reads the first one — so the input is sorted here
 * rather than trusted to arrive sorted. Zulu ISO strings sort chronologically as text.
 */
export function groupByDate(slots, timeZone, now = Date.now()) {
  const byDate = new Map();

  for (const iso of [...slots].sort()) {
    if (Date.parse(iso) <= now) {
      continue;
    }

    const key = dateKey(iso, timeZone);

    if (!byDate.has(key)) {
      byDate.set(key, []);
    }

    byDate.get(key).push(iso);
  }

  return byDate;
}

function calendarDate(key) {
  const [y, m, d] = key.split('-').map(Number);

  return Date.UTC(y, m - 1, d);
}

/**
 * Format a calendar day (`YYYY-MM-DD`, already in the visitor's zone) for display.
 *
 * Formatted in UTC because the key is a date, not an instant: midnight UTC of that date, read
 * back in UTC, is the same date everywhere.
 */
export function formatDay(key, style) {
  const options = {
    weekday: { weekday: 'long' },
    long: { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' },
    monthDayYear: { month: 'long', day: 'numeric', year: 'numeric' },
    weekdayMonthDay: { weekday: 'long', month: 'long', day: 'numeric' },
    monthDay: { month: 'long', day: 'numeric' },
  }[style];

  return formatter('UTC', options).format(calendarDate(key));
}

export function monthTitle(year, month) {
  return formatter('UTC', { month: 'long', year: 'numeric' }).format(Date.UTC(year, month - 1, 1));
}

/**
 * The cells of one month: leading blanks for the weekday offset, then one per day.
 *
 * Same shape the server's getDaysGridProperty() produced, so the two skins' markup maps across
 * one-for-one. Dates compare as strings because `YYYY-MM-DD` sorts chronologically.
 */
export function monthGrid(year, month, { todayKey, open, selected = null }) {
  const cells = [];
  const offset = new Date(Date.UTC(year, month - 1, 1)).getUTCDay();
  const days = new Date(Date.UTC(year, month, 0)).getUTCDate();

  for (let i = 0; i < offset; i++) {
    cells.push({ empty: true, key: `blank-${i}` });
  }

  for (let day = 1; day <= days; day++) {
    const date = `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
    const isPast = date < todayKey;

    cells.push({
      empty: false,
      key: date,
      day,
      date,
      isPast,
      isToday: date === todayKey,
      hasAvailability: open.has(date) && !isPast,
      isSelected: date === selected,
    });
  }

  return cells;
}

/** The offered zones, with the visitor's own first when it is not one of them. */
export function zoneChoices(base, timeZone) {
  return base.includes(timeZone) ? base : [timeZone, ...base];
}

/** `America/New_York` -> `America, New York`. The label for a zone without a friendly name. */
export function zoneFallbackLabel(timeZone) {
  return timeZone.replaceAll('_', ' ').replaceAll('/', ', ');
}

export function zoneLabel(timeZone, labels = {}) {
  return labels[timeZone] ?? zoneFallbackLabel(timeZone);
}

export function rlBookingCalendar(config = {}) {
  return {
    view: 'date',
    year: 0,
    month: 0,
    now: Date.now(),
    submitting: false,
    unreachable: false,
    baseChoices: config.choices || [],
    labels: config.labels || {},

    init() {
      this.view = this.$wire.currentStep === 3 && this.$wire.selectedDate ? 'time' : 'date';
      this.showMonthOf(this.$wire.selectedDate || this.todayKey);

      // Server-driven step changes: a refused booking pins step 3, going back pins step 2.
      this.$watch('$wire.currentStep', (step) => {
        if (step === 2) {
          this.view = 'date';
        } else if (step === 3 && this.$wire.selectedDate) {
          this.view = 'time';
        }
      });

      // Keeps the "(14:05)" beside the zone honest, and lets slots that start drop out.
      this.ticker = setInterval(() => {
        this.now = Date.now();
      }, 30000);
    },

    destroy() {
      clearInterval(this.ticker);
    },

    get tz() {
      return this.$wire.timezone || 'America/New_York';
    },

    get todayKey() {
      return dateKey(this.now, this.tz);
    },

    get byDate() {
      return groupByDate(this.$wire.openSlots || [], this.tz, this.now);
    },

    get grid() {
      return monthGrid(this.year, this.month, {
        todayKey: this.todayKey,
        open: this.byDate,
        selected: this.$wire.selectedDate,
      });
    },

    get title() {
      return monthTitle(this.year, this.month);
    },

    get monthIsEmpty() {
      return !this.grid.some((cell) => cell.hasAvailability);
    },

    /** The soonest open day in any month — the sold-out copy's way out of an empty month. */
    get nextOpen() {
      const first = this.byDate.keys().next();

      return first.done ? null : first.value;
    },

    get canGoBack() {
      const [y, m] = this.todayKey.split('-').map(Number);

      return this.year * 12 + this.month > y * 12 + m;
    },

    get times() {
      return (this.byDate.get(this.$wire.selectedDate) || []).map((iso) => ({
        iso,
        label: timeLabel(iso, this.tz),
      }));
    },

    get choices() {
      return zoneChoices(this.baseChoices, this.tz);
    },

    day(style) {
      return this.$wire.selectedDate ? formatDay(this.$wire.selectedDate, style) : '';
    },

    format(key, style) {
      return formatDay(key, style);
    },

    clock(timeZone) {
      return clockLabel(timeZone, this.now);
    },

    label(timeZone) {
      return zoneLabel(timeZone, this.labels);
    },

    fallbackLabel(timeZone) {
      return zoneFallbackLabel(timeZone);
    },

    showMonthOf(key) {
      const [y, m] = key.split('-').map(Number);
      this.year = y;
      this.month = m;
    },

    prevMonth() {
      if (!this.canGoBack) {
        return;
      }

      this.month === 1 ? ((this.month = 12), this.year--) : this.month--;
      this.replayMonth();
    },

    nextMonth() {
      this.month === 12 ? ((this.month = 1), this.year++) : this.month++;
      this.replayMonth();
    },

    /*
     * The month transition the server re-render used to trigger by swapping the grid. Replayed
     * on the ref rather than by keying the grid in an outer x-for: Alpine does not initialise an
     * x-for nested inside another x-for's clone, so that left the days unrendered.
     */
    replayMonth() {
      this.$nextTick(() => {
        const grid = this.$refs.grid;

        if (!grid) {
          return;
        }

        grid.classList.remove('animate-grid-month');
        void grid.offsetWidth;
        grid.classList.add('animate-grid-month');
      });
    },

    /*
     * Written to `$wire` locally first, so the screen follows the click at once; the server call
     * then carries the same values plus the funnel event. A failed call costs the event, not the
     * booking — submitBooking() sends selectedDate and selectedSlot along with it regardless.
     */
    pickDate(key) {
      if (!this.byDate.has(key)) {
        return;
      }

      this.unreachable = false;
      this.$wire.selectedDate = key;
      this.$wire.selectedSlot = null;
      this.view = 'time';
      this.$wire.selectDate(key).catch(() => {});
    },

    backToDates() {
      this.view = 'date';
      this.$wire.goToStep(2).catch(() => {});
    },

    pickSlot(iso) {
      this.unreachable = false;
      this.$wire.selectedSlot = iso;
      this.$wire.selectSlot(iso).catch(() => {});
    },

    /*
     * Only a request that never got an answer lands in the catch — a refused or unconfirmed
     * booking comes back as a normal response and the server says so in `errorMessage`.
     */
    confirm() {
      if (this.submitting || !this.$wire.selectedSlot) {
        return;
      }

      this.submitting = true;
      this.unreachable = false;

      this.$wire
        .submitBooking()
        .catch(() => {
          this.unreachable = true;
        })
        .finally(() => {
          this.submitting = false;
        });
    },

    /*
     * Local only. The zone rides along with the next request, where updatedTimezone() marks it
     * as the visitor's choice so a late browser detection cannot overwrite it.
     */
    setZone(timeZone) {
      this.$wire.timezone = timeZone;
    },

    jumpToNext() {
      const key = this.nextOpen;

      if (!key) {
        return;
      }

      this.showMonthOf(key);
      this.pickDate(key);
    },
  };
}
