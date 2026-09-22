{{--
  The form a rep fills in while the referrer is on the phone.

  Field order follows the order a call actually goes in: whose referral this is first, then the
  person being referred. The required set is identical to the main booking form's step 1 —
  enforced in SubmitReferredLeadAction, which the referrer portal's own modal shares.
--}}
<div>
  <div class="mb-6">
    <h1 class="text-2xl sm:text-3xl font-bold text-text-body">Record a referral</h1>
    <p class="mt-1.5 text-sm text-text-muted">
      For a referrer naming someone on a call. Everything here is required, and the referral is
      logged as pending until the prospect books.
    </p>
  </div>

  @if ($errorMessage)
    <div class="mb-5 p-3 rounded-card bg-rose-50 ring-1 ring-rose-200 text-rose-700 text-sm">
      {{ $errorMessage }}
    </div>
  @endif

  @if ($successMessage)
    <div class="mb-5 p-4 rounded-card bg-emerald-50 ring-1 ring-emerald-200 text-emerald-800 text-sm space-y-1">
      <p class="font-semibold">{{ $successMessage }}</p>
      @if ($lastSubmission)
        {{-- Read back on the call, so the rep can confirm it out loud before hanging up. --}}
        <p class="text-xs text-emerald-700">
          Referral #{{ $lastSubmission['referral_id'] }} &mdash;
          {{ $lastSubmission['lead_name'] }}, credited to
          {{ $lastSubmission['referrer'] }} ({{ $lastSubmission['referral_code'] }}).
        </p>
      @endif
    </div>
  @endif

  <form wire:submit.prevent="submit" class="space-y-5">

    <div class="p-4 rounded-card bg-bg-light ring-1 ring-slate-200">
      {{--
        The code field is hidden while the register panel is open: registering generates the
        code, so asking for one at the same time offers a choice that does not exist. It comes
        back populated the moment registration succeeds, which is also when the panel closes.
      --}}
      @if (! $showRegisterPanel)
        <label for="sr-code" class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1">
          Referrer code *
        </label>
        <input
          id="sr-code"
          type="text"
          wire:model="referrerCode"
          autocomplete="off"
          placeholder="The code the referrer gives you"
          class="w-full px-3 py-2 rounded-card ring-1 ring-slate-200 border-0 text-sm font-mono text-slate-900 focus:ring-2 focus:ring-brand-purple"
        />
        <p class="mt-1 text-[11px] text-slate-500">
          Who gets credit. Kept between submissions, so you can take several from one caller.
        </p>
      @else
        <p class="text-[11px] font-bold uppercase tracking-wider text-slate-600">New referrer</p>
        <p class="mt-1 text-[11px] text-slate-500">
          Their referral code is created for them &mdash; you do not need one to start.
        </p>
      @endif

      @if ($registerSuccess)
        <p class="mt-2 text-[11px] font-semibold text-emerald-700">{{ $registerSuccess }}</p>
      @endif

      <button type="button" wire:click="toggleRegisterPanel" class="mt-2 text-[11px] font-semibold text-brand-purple hover:underline cursor-pointer">
        @if ($showRegisterPanel) Cancel @else Not registered yet? Register them &rarr; @endif
      </button>

      {{-- Opens on its own when a code does not resolve: mid-call that almost always means
           the person has never signed up. --}}
      @if ($showRegisterPanel)
        <div class="mt-3 pt-3 border-t border-slate-200 space-y-3">
          @if ($registerError)
            <p class="text-[11px] text-rose-700">{{ $registerError }}</p>
          @endif

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label for="sr-new-name" class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1">Referrer name *</label>
              <input id="sr-new-name" type="text" wire:model="newReferrerName" autocomplete="off" class="w-full px-3 py-2 rounded-card ring-1 ring-slate-200 border-0 text-sm text-slate-900 focus:ring-2 focus:ring-brand-purple" />
            </div>
            <div>
              <label for="sr-new-email" class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1">Referrer email *</label>
              <input id="sr-new-email" type="email" inputmode="email" wire:model="newReferrerEmail" autocomplete="off" class="w-full px-3 py-2 rounded-card ring-1 ring-slate-200 border-0 text-sm text-slate-900 focus:ring-2 focus:ring-brand-purple" />
            </div>
          </div>

          <div>
            <label for="sr-new-company" class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1">Company (optional)</label>
            <input id="sr-new-company" type="text" wire:model="newReferrerCompany" autocomplete="off" class="w-full px-3 py-2 rounded-card ring-1 ring-slate-200 border-0 text-sm text-slate-900 focus:ring-2 focus:ring-brand-purple" />
          </div>

          <p class="text-[11px] text-slate-500">
            Creates their account and referral code so you can credit this referral now. They set
            their own password later by signing up with this same email &mdash; you do not need to
            give them one.
          </p>

          <button
            type="button"
            wire:click="registerReferrer"
            wire:loading.attr="disabled"
            wire:target="registerReferrer"
            class="px-4 py-2 rounded-cta bg-slate-800 hover:bg-slate-900 text-white text-xs font-bold cursor-pointer transition disabled:opacity-60"
          >
            <span wire:loading.remove wire:target="registerReferrer">Register referrer</span>
            <span wire:loading wire:target="registerReferrer">Registering&hellip;</span>
          </button>
        </div>
      @endif
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
      <div>
        <label for="sr-first" class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1">First name *</label>
        <input id="sr-first" type="text" wire:model="firstName" autocomplete="off" class="w-full px-3 py-2 rounded-card ring-1 ring-slate-200 border-0 text-sm text-slate-900 focus:ring-2 focus:ring-brand-purple" />
      </div>
      <div>
        <label for="sr-last" class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1">Last name *</label>
        <input id="sr-last" type="text" wire:model="lastName" autocomplete="off" class="w-full px-3 py-2 rounded-card ring-1 ring-slate-200 border-0 text-sm text-slate-900 focus:ring-2 focus:ring-brand-purple" />
      </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
      <div>
        <label for="sr-email" class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1">Email *</label>
        <input id="sr-email" type="email" inputmode="email" wire:model="email" autocomplete="off" class="w-full px-3 py-2 rounded-card ring-1 ring-slate-200 border-0 text-sm text-slate-900 focus:ring-2 focus:ring-brand-purple" />
      </div>
      <div>
        <label for="sr-phone" class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1">Phone *</label>
        <input id="sr-phone" type="tel" inputmode="tel" wire:model="phone" autocomplete="off" class="w-full px-3 py-2 rounded-card ring-1 ring-slate-200 border-0 text-sm text-slate-900 focus:ring-2 focus:ring-brand-purple" />
      </div>
    </div>

    <div>
      <label for="sr-revenue" class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1">Monthly revenue *</label>
      <select id="sr-revenue" wire:model="revenue" class="w-full px-3 py-2 rounded-card ring-1 ring-slate-200 border-0 text-sm text-slate-900 bg-white cursor-pointer focus:ring-2 focus:ring-brand-purple">
        <option value="">Select a band&hellip;</option>
        @foreach (\App\Domains\Lead\Services\LeadQualification::REVENUE_BANDS as $band)
          <option value="{{ $band }}">{{ $band }}</option>
        @endforeach
      </select>
      <p class="mt-1 text-[11px] text-slate-500">
        Asked on the booking form too &mdash; a lead without a band cannot be qualified.
      </p>
    </div>

    <div>
      <label for="sr-notes" class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1">Notes (optional)</label>
      <textarea id="sr-notes" wire:model="notes" rows="3" placeholder="Role needed, headcount, timeline, anything said on the call..." class="w-full px-3 py-2 rounded-card ring-1 ring-slate-200 border-0 text-sm text-slate-900 focus:ring-2 focus:ring-brand-purple"></textarea>
    </div>

    <div class="pt-2 flex items-center justify-end gap-3">
      <button
        type="submit"
        wire:loading.attr="disabled"
        wire:target="submit"
        class="px-6 py-2.5 rounded-cta bg-brand-purple hover:bg-brand-purple-deep text-white text-sm font-bold cursor-pointer transition disabled:opacity-60 disabled:cursor-wait"
      >
        <span wire:loading.remove wire:target="submit">Record referral</span>
        <span wire:loading wire:target="submit">Recording&hellip;</span>
      </button>
    </div>
  </form>
</div>
