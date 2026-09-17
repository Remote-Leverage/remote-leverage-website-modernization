{{-- min-h ties the header to --rl-header-h, which .rl-screen subtracts from 100dvh for the
     homepage's full-viewport bands. The value is the height this header already had; pinning it
     just stops the two drifting apart. --}}
<header
    class="sticky top-0 z-40 w-full min-h-[var(--rl-header-h)] bg-bg-light border-b border-slate-200/60 transition-all duration-200">
    <div class="w-full px-4 sm:px-6 lg:px-8">
        <div class="rl-container">
        <div class="relative flex items-center justify-end lg:justify-between gap-3 h-20">

            {{-- Brand Logo.
                 Below `lg` (the hamburger breakpoint) the logo is taken out of flow and centred on
                 the row — production serves a viewport-centred 200px logo with no inline CTA beside
                 it, and centring absolutely is what lets it stay 200px down to 320px instead of
                 competing with the hamburger for flex space. At `lg` it returns to the flow as the
                 left-hand item of the `justify-between` row, unchanged. --}}
            <div class="absolute left-1/2 -translate-x-1/2 lg:static lg:left-auto lg:translate-x-0 min-w-0">
                <a href="{{ home_url('/') }}"
                    class="flex min-w-0 items-center gap-3 group focus:outline-none focus:ring-2 focus:ring-brand-purple focus:ring-offset-2 rounded-lg"
                    aria-label="{{ $siteName ?? 'Remote Leverage' }}">
                    <img src="{{ Vite::asset('resources/images/logo.svg') }}" alt="{{ $siteName ?? 'Remote Leverage' }}"
                        width="154" height="18"
                        class="w-[200px] h-auto max-w-none lg:h-7 lg:w-auto lg:max-w-full object-contain object-left transition-transform group-hover:scale-[1.02]"
                        onerror="this.style.display='none'; this.nextElementSibling.style.display='block';" />
                    <span class="hidden text-2xl font-display font-bold text-brand-navy tracking-tight">
                        Remote Leverage
                    </span>
                </a>
            </div>

            <!-- Right Navigation & Consultation CTA (Desktop) -->
            <div class="hidden lg:flex items-center gap-8 xl:gap-10">
                <!-- Native WordPress Nav Menu -->
                <nav class="rl-desktop-nav" aria-label="{{ __('Primary Navigation', 'remote-leverage') }}">
                    {!! wp_nav_menu([
                        'theme_location' => 'primary_navigation',
                        'menu_class' => 'flex items-center gap-7 xl:gap-9 text-[17px] font-display font-medium text-slate-900',
                        'container' => false,
                        'echo' => false,
                        'walker' => new \App\View\NavWalker(),
                        'fallback_cb' => function () {
                            return '
                                  <ul class="flex items-center gap-7 xl:gap-9 text-[17px] font-display font-medium text-slate-900">
                                    <!-- Reviews Dropdown --><li class="menu-item relative group">
                                      <a href="'.home_url('/reviews').'" class="flex items-center gap-1.5 py-2 hover:text-brand-purple transition-colors cursor-pointer focus:outline-none">
                                        <span>Reviews</span>
                                        <svg class="w-4 h-4 text-slate-700 group-hover:text-brand-purple transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                                        </svg>
                                      </a>
                                      <ul class="sub-menu absolute left-0 top-full mt-2 min-w-[280px] bg-white rounded-2xl p-2.5 shadow-xl border border-slate-100 z-50 flex-col gap-1">
                                        <li><a href="' .
                                home_url('/reviews') .
                                '" class="block px-4 py-2.5 text-sm font-medium text-slate-800 hover:text-brand-purple hover:bg-slate-50 rounded-xl transition-colors">Testimonial Reviews</a></li>
                                        <li><a href="' .
                                home_url('/case-study/') .
                                '" class="block px-4 py-2.5 text-sm font-medium text-slate-800 hover:text-brand-purple hover:bg-slate-50 rounded-xl transition-colors">Case Studies</a></li>
                                        <li><a href="' .
                                home_url('/samples') .
                                '" class="block px-4 py-2.5 text-sm font-medium text-slate-800 hover:text-brand-purple hover:bg-slate-50 rounded-xl transition-colors">Sample Applicant Recordings</a></li>
                                      </ul>
                                    </li>
                    
                                    <!-- Roles Dropdown --><li class="menu-item relative group">
                                      <a href="'.home_url('/admin-virtual-assistants/').'" class="flex items-center gap-1.5 py-2 hover:text-brand-purple transition-colors cursor-pointer focus:outline-none">
                                        <span>Roles</span>
                                        <svg class="w-4 h-4 text-slate-700 group-hover:text-brand-purple transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                                        </svg>
                                      </a>
                                      <ul class="sub-menu absolute left-0 top-full mt-2 min-w-[320px] bg-white rounded-2xl p-2.5 shadow-xl border border-slate-100 z-50 flex-col gap-1 max-h-[min(72vh,560px)] overflow-y-auto">
' .
                                /* The fourteen role pages, straight off the shared map, so a role added to
                                   App\Support\RolePages appears here without anyone remembering to edit the nav.
                                   The nine hand-written items this replaced had drifted: Social Media, Marketing
                                   and Bookkeeping pointed at legacy slugs that are now redirects, and Medical,
                                   Legal, Insurance, Real Estate and ECommerce were missing entirely. */
                                implode('', array_map(
                                    fn ($slug) => '<li><a href="' . home_url('/' . $slug . '/') .
                                        '" class="block px-4 py-2 text-sm font-medium text-slate-800 hover:text-brand-purple hover:bg-slate-50 rounded-xl transition-colors">' .
                                        esc_html(\App\Support\RolePages::title($slug)) . '</a></li>',
                                    \App\Support\RolePages::slugs()
                                )) . '
                                      </ul>
                                    </li>
                    
                                    <!-- Pricing Link --><li>
                                      <a href="' .
                                home_url('/vapricing') .
                                '" class="py-2 hover:text-brand-purple transition-colors">
                                        Pricing
                                      </a>
                                    </li>
                                  </ul>';
                        },
                    ]) !!}
                </nav>

                <!-- Consultation Pill Button with Circled Right Arrow -->
                <a href="{{ home_url('/vacalendar') }}"
                    class="inline-flex items-center justify-center gap-2.5 rounded-full border border-black px-6 py-2.5 text-sm font-display font-bold uppercase tracking-wider text-black bg-transparent hover:bg-white hover:shadow-md transition-all duration-200 group">
                    <span>{{ __('Consultation', 'remote-leverage') }}</span>
                    <svg class="w-5 h-5 text-black group-hover:translate-x-0.5 transition-transform" viewBox="0 0 24 24"
                        fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path
                            d="M11.7871 0C18.287 0.000243557 23.573 5.28724 23.5732 11.7871C23.573 18.287 18.287 23.573 11.7871 23.5732C5.28725 23.573 0.000243552 18.287 0 11.7871C0.000233875 5.28724 5.28724 0.00023695 11.7871 0ZM11.7871 1.98535C6.38376 1.98559 1.98559 6.38376 1.98535 11.7871C1.9856 17.1905 6.38377 21.5877 11.7871 21.5879C17.1904 21.5876 21.5876 17.1904 21.5879 11.7871C21.5877 6.38376 17.1905 1.9856 11.7871 1.98535ZM9.71387 5.17578L15.7314 11.4043L16.2773 11.9687L15.7314 12.5342L10.0654 18.3994L9.48242 19.0029L8.89746 18.3994L8.64355 18.1377L8.09668 17.5732L8.64258 17.0078L13.5098 11.9687L8.29102 6.56738L7.74609 6.00195L8.29102 5.4375L8.54492 5.17578L9.12891 4.57031L9.71387 5.17578Z"
                            fill="currentColor" />
                    </svg>
                </a>
            </div>

            {{-- Mobile Menu Button.
                 No inline Consultation pill here: production's mobile header is a centred logo and
                 a hamburger, nothing else. The booking CTA lives inside the drawer below
                 ("Book a Consultation"), which is the only mobile path to /vacalendar — do not
                 remove it from the drawer without replacing it. --}}
            <div class="flex shrink-0 lg:hidden items-center">
                <button type="button" data-rl-nav-toggle aria-expanded="false" aria-controls="rl-mobile-nav"
                    class="p-2 rounded-xl text-slate-700 hover:text-brand-purple hover:bg-slate-100 transition-colors focus:outline-none"
                    aria-label="{{ __('Toggle navigation menu', 'remote-leverage') }}">
                    <svg data-rl-nav-open class="w-6 h-6" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                    <svg data-rl-nav-close class="w-6 h-6" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor" hidden>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

        </div>
        </div>
    </div>

    <!-- Mobile Menu Drawer (Using MobileNavWalker) -->
    {{-- Bounded to whatever the viewport has left under the sticky header, so fourteen role
         links scroll inside the drawer instead of pushing the CTA off the bottom of the screen.
         100dvh, not 100vh: mobile Safari's toolbar makes vh taller than the visible area. --}}
    <div id="rl-mobile-nav" hidden
        class="lg:hidden flex flex-col max-h-[calc(100dvh-var(--rl-header-h))] border-b border-slate-200 bg-[#F4F6FC] shadow-xl">
        <nav class="flex-1 overflow-y-auto overscroll-contain px-4 pt-2 pb-4">
            {!! wp_nav_menu([
                'theme_location' => 'primary_navigation',
                'menu_class' => 'flex flex-col',
                'container' => false,
                'echo' => false,
                'walker' => new \App\View\MobileNavWalker(),
                /*
                 * Mirrors production's mobile structure — one row per top-level item, hairline
                 * separated, with a circled chevron on the rows that open — but in the 2026 type
                 * and colour. The two desktop dropdowns become accordions rather than being
                 * flattened into a long list, which is what the fourteen role pages made
                 * untenable: flat, they buried Pricing under fourteen rows.
                 *
                 * <details>/<summary> rather than a JS disclosure: it is keyboard and screen
                 * reader accessible for free, and the drawer keeps its collapsed height until
                 * someone opens a section.
                 */
                'fallback_cb' => function () {
                    $row = 'flex w-full cursor-pointer items-center justify-between gap-3 border-b border-slate-200 py-4 text-base font-semibold text-slate-800 [&::-webkit-details-marker]:hidden';
                    $chevron = '<span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full border border-slate-300 text-slate-700 transition-colors group-open:border-brand-purple group-open:text-brand-purple">'
                        .'<svg class="h-4 w-4 transition-transform duration-200 group-open:rotate-90" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">'
                        .'<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" /></svg></span>';

                    $child = fn (string $url, string $label) => '<a href="' . $url .
                        '" class="block py-2.5 text-[15px] font-medium text-slate-700 hover:text-brand-purple">' . esc_html($label) . '</a>';

                    // The shared `name` makes these an exclusive accordion: opening one closes
                    // the other, with no JavaScript. Browsers without it simply allow both open,
                    // which is what this did before, so there is nothing to fall back to.
                    $section = fn (string $label, string $children) => '<details name="rl-mobile-nav" class="group">'
                        .'<summary class="' . $row . ' group-open:text-brand-purple"><span>' . esc_html($label) . '</span>' . $chevron . '</summary>'
                        .'<div class="flex flex-col border-b border-slate-200 pb-2 pl-3">' . $children . '</div>'
                        .'</details>';

                    return $section('Reviews',
                        $child(home_url('/reviews'), 'Testimonial Reviews')
                        . $child(home_url('/case-study/'), 'Case Studies')
                        . $child(home_url('/samples'), 'Sample Applicant Recordings')
                    ) . $section('Roles',
                        implode('', array_map(
                            fn ($slug) => $child(home_url('/' . $slug . '/'), \App\Support\RolePages::title($slug)),
                            \App\Support\RolePages::slugs()
                        ))
                    ) . '<a href="' . home_url('/vapricing') .
                        '" class="block border-b border-slate-200 py-4 text-base font-semibold text-slate-800 hover:text-brand-purple">Pricing</a>';
                },
            ]) !!}
        </nav>

        <div class="shrink-0 border-t border-slate-200 bg-[#F4F6FC] px-4 pt-3 pb-[calc(1.5rem+env(safe-area-inset-bottom,0px))]">
            <a href="{{ home_url('/vacalendar') }}"
                class="inline-flex w-full items-center justify-center gap-2 rounded-full border border-black py-3 text-sm font-display font-bold uppercase tracking-wider text-black bg-white shadow-sm">
                <span>{{ __('Book a Consultation', 'remote-leverage') }}</span>
                <svg class="w-4 h-4 text-black" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path
                        d="M11.7871 0C18.287 0.000243557 23.573 5.28724 23.5732 11.7871C23.573 18.287 18.287 23.573 11.7871 23.5732C5.28725 23.573 0.000243552 18.287 0 11.7871C0.000233875 5.28724 5.28724 0.00023695 11.7871 0ZM11.7871 1.98535C6.38376 1.98559 1.98559 6.38376 1.98535 11.7871C1.9856 17.1905 6.38377 21.5877 11.7871 21.5879C17.1904 21.5876 21.5876 17.1904 21.5879 11.7871C21.5877 6.38376 17.1905 1.9856 11.7871 1.98535ZM9.71387 5.17578L15.7314 11.4043L16.2773 11.9687L15.7314 12.5342L10.0654 18.3994L9.48242 19.0029L8.89746 18.3994L8.64355 18.1377L8.09668 17.5732L8.64258 17.0078L13.5098 11.9687L8.29102 6.56738L7.74609 6.00195L8.29102 5.4375L8.54492 5.17578L9.12891 4.57031L9.71387 5.17578Z"
                        fill="currentColor" />
                </svg>
            </a>
        </div>
    </div>
</header>
