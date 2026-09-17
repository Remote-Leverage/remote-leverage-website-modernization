{{--
  Shared "Log in / Apply" switcher for the referrer program.

  Rendered from inside the two Livewire components (not from the page views) so it tracks
  their state: it disappears the moment someone authenticates or submits an application,
  without waiting for a full page load.

  The legacy "/referral-dashboard/" URL serves both halves as tabs, and before this existed
  its bare form rendered signup with the login form reachable only via "?tab=login" — a URL
  nobody types. See the route in routes/web.php.

  Both tabs point at the canonical named routes rather than reflecting the current URL:
  during a Livewire update the request is /livewire/update, so request()-derived hrefs would
  flip between renders.

  @param string $active  'login' or 'sign-up'
--}}
@php($referrerAuthTabs = [
    ['key' => 'login', 'label' => __('Log in', 'remote-leverage'), 'url' => route('referrer.portal')],
    ['key' => 'sign-up', 'label' => __('Apply', 'remote-leverage'), 'url' => route('referrer.register')],
])

<nav
    class="mx-auto mb-6 flex w-full max-w-xs items-center gap-1 rounded-pill bg-slate-100 p-1"
    aria-label="{{ __('Referrer login and application', 'remote-leverage') }}"
>
    @foreach ($referrerAuthTabs as $tab)
        <a
            href="{{ $tab['url'] }}"
            @if ($tab['key'] === $active) aria-current="page" @endif
            class="flex-1 rounded-pill px-4 py-2 text-center text-xs font-bold transition
                {{ $tab['key'] === $active
                    ? 'bg-surface-white text-brand-purple shadow-card'
                    : 'text-slate-500 hover:text-slate-700' }}"
        >
            {{ $tab['label'] }}
        </a>
    @endforeach
</nav>
