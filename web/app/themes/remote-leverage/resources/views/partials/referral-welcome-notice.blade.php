{{--
  Welcome offer for a visitor who has just arrived on a referral link.

  Rendered from layouts/app.blade.php on every front-end request; App\Domains\Referral\Support\
  ReferralWelcomeNotice decides whether there is anything to show, so this file renders nothing
  at all for ordinary traffic.

  Shown once, on the arrival request only — not for the life of the 60-day attribution cookie.
  It does not auto-dismiss: the message makes a specific monetary promise and a $500 offer that
  vanishes after five seconds is worse than not making it.

  Positioning: `top` has to clear whichever header this page uses. sections/header is sticky and
  --rl-header-h tall (81px); sections/header-cta is FIXED and 90px, and is used by exactly the
  landing pages referral links point at. PageChrome answers which, the same way the layout does.
--}}
@php
  $notice = app(\App\Domains\Referral\Support\ReferralWelcomeNotice::class);
@endphp

@if ($notice->shouldShow())
  @php
    $topOffset = \App\Support\PageChrome::usesCtaOnlyHeader() ? '106px' : 'calc(var(--rl-header-h) + 16px)';
  @endphp

  <div
    id="rl-referral-welcome"
    role="status"
    aria-live="polite"
    class="fixed inset-x-0 z-50 flex justify-center px-4 pointer-events-none"
    style="top: {{ $topOffset }}"
    data-rl-referral-notice
  >
    <div
      class="pointer-events-auto flex items-center gap-3 max-w-[min(92vw,40rem)] rounded-pill bg-white/95 backdrop-blur-sm pl-4 pr-2 py-2.5 shadow-[0_8px_30px_rgba(15,23,42,0.18)] ring-1 ring-slate-900/5 animate-[rl-notice-in_320ms_cubic-bezier(0.16,1,0.3,1)_both]"
    >
      <span class="grid place-items-center w-7 h-7 shrink-0 rounded-full bg-brand-purple/10 text-brand-purple">
        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" d="M20 12v9H4v-9M2 7h20v5H2zM12 22V7M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7zM12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"/>
        </svg>
      </span>

      {{-- Unescaped by necessity: the referrer's name and the amount are wrapped in <strong>.
           ReferralWelcomeNotice::messageHtml() escapes the admin-authored template and both
           substituted values before wrapping them, so nothing reaching here is unescaped. --}}
      <p class="flex-1 min-w-0 text-sm leading-snug text-slate-900">
        {!! $notice->messageHtml() !!}
      </p>

      <button
        type="button"
        class="grid place-items-center w-7 h-7 shrink-0 rounded-full text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition cursor-pointer"
        aria-label="{{ __('Dismiss', 'remote-leverage') }}"
        onclick="this.closest('[data-rl-referral-notice]').remove(); try { sessionStorage.setItem('rl_referral_notice_dismissed', '1'); } catch (e) {}"
      >
        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
          <path stroke-linecap="round" d="M18 6 6 18M6 6l12 12"/>
        </svg>
      </button>
    </div>
  </div>

  <script>
    /* A refresh re-sends ?via=, so the server would render this again after it was dismissed.
       sessionStorage keeps a dismissal honoured for the tab. Wrapped because it throws outright
       in a private window rather than returning null, and an exception here would stop every
       later inline script on the page. */
    (function () {
      try {
        if (sessionStorage.getItem('rl_referral_notice_dismissed') === '1') {
          document.getElementById('rl-referral-welcome')?.remove();
        }
      } catch (e) {}
    })();
  </script>
@endif
