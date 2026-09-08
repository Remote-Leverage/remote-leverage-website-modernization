<header x-data="{ mobileMenuOpen: false }"
    class="sticky top-0 z-40 w-full bg-bg-light border-b border-slate-200/60 transition-all duration-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-20">

            <!-- Brand Logo -->
            <div class="shrink-0">
                <a href="{{ home_url('/') }}"
                    class="flex items-center gap-3 group focus:outline-none focus:ring-2 focus:ring-brand-purple focus:ring-offset-2 rounded-lg"
                    aria-label="{{ $siteName ?? 'Remote Leverage' }}">
                    <img src="{{ Vite::asset('resources/images/logo.svg') }}" alt="{{ $siteName ?? 'Remote Leverage' }}"
                        width="168" height="28"
                        class="h-6 sm:h-7 w-auto transition-transform group-hover:scale-[1.02]"
                        onerror="this.style.display='none'; this.nextElementSibling.style.display='block';" />
                    <span class="hidden text-2xl font-display font-extrabold text-brand-navy tracking-tight">
                        Remote Leverage
                    </span>
                </a>
            </div>

            <!-- Right Navigation & Consultation CTA (Desktop) -->
            <div class="hidden lg:flex items-center gap-8 xl:gap-10">
                <!-- Native WordPress Nav Menu -->
                <nav aria-label="{{ __('Primary Navigation', 'remote-leverage') }}">
                    {!! wp_nav_menu([
                        'theme_location' => 'primary_navigation',
                        'menu_class' => 'flex items-center gap-7 xl:gap-9 text-[17px] font-display font-medium text-slate-900',
                        'container' => false,
                        'echo' => false,
                        'walker' => new \App\View\NavWalker(),
                        'fallback_cb' => function () {
                            return '
                                  <ul class="flex items-center gap-7 xl:gap-9 text-[17px] font-display font-medium text-slate-900">
                                    <!-- Reviews Dropdown --><li class="relative group" x-data="{ open: false }" @mouseenter="open = true" @mouseleave="open = false" @click.outside="open = false">
                                      <a href="#" @click.prevent="open = !open" class="flex items-center gap-1.5 py-2 hover:text-brand-purple transition-colors cursor-pointer focus:outline-none">
                                        <span>Reviews</span>
                                        <svg class="w-4 h-4 text-slate-700 group-hover:text-brand-purple transition-transform duration-200" :class="{ \'rotate-180\': open }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                                        </svg>
                                      </a>
                                      <ul x-show="open" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0" x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 translate-y-1" class="absolute left-0 top-full mt-2 min-w-[280px] bg-white rounded-2xl p-2.5 shadow-xl border border-slate-100 z-50 flex flex-col gap-1" style="display: none;">
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
                    
                                    <!-- Roles Dropdown --><li class="relative group" x-data="{ open: false }" @mouseenter="open = true" @mouseleave="open = false" @click.outside="open = false">
                                      <a href="#" @click.prevent="open = !open" class="flex items-center gap-1.5 py-2 hover:text-brand-purple transition-colors cursor-pointer focus:outline-none">
                                        <span>Roles</span>
                                        <svg class="w-4 h-4 text-slate-700 group-hover:text-brand-purple transition-transform duration-200" :class="{ \'rotate-180\': open }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                                        </svg>
                                      </a>
                                      <ul x-show="open" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0" x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 translate-y-1" class="absolute left-0 top-full mt-2 min-w-[320px] bg-white rounded-2xl p-2.5 shadow-xl border border-slate-100 z-50 flex flex-col gap-1 max-h-[480px] overflow-y-auto" style="display: none;">
                                        <li><a href="' .
                                home_url('/admin-virtual-assistants/') .
                                '" class="block px-4 py-2 text-sm font-medium text-slate-800 hover:text-brand-purple hover:bg-slate-50 rounded-xl transition-colors">Admin Virtual Assistants</a></li>
                                        <li><a href="' .
                                home_url('/executive-virtual-assistants/') .
                                '" class="block px-4 py-2 text-sm font-medium text-slate-800 hover:text-brand-purple hover:bg-slate-50 rounded-xl transition-colors">Executive Virtual Assistants</a></li>
                                        <li><a href="' .
                                home_url('/customer-support-virtual-assistants/') .
                                '" class="block px-4 py-2 text-sm font-medium text-slate-800 hover:text-brand-purple hover:bg-slate-50 rounded-xl transition-colors">Customer Support Virtual Assistants</a></li>
                                        <li><a href="' .
                                home_url('/sales-virtual-assistants/') .
                                '" class="block px-4 py-2 text-sm font-medium text-slate-800 hover:text-brand-purple hover:bg-slate-50 rounded-xl transition-colors">Sales Virtual Assistants</a></li>
                                        <li><a href="' .
                                home_url('/lead-generation-virtual-assistants/') .
                                '" class="block px-4 py-2 text-sm font-medium text-slate-800 hover:text-brand-purple hover:bg-slate-50 rounded-xl transition-colors">Lead Generation Virtual Assistants</a></li>
                                        <li><a href="' .
                                home_url('/socialmediavirtualassistants/') .
                                '" class="block px-4 py-2 text-sm font-medium text-slate-800 hover:text-brand-purple hover:bg-slate-50 rounded-xl transition-colors">Social Media Virtual Assistants</a></li>
                                        <li><a href="' .
                                home_url('/marketing-assistants-legacy/') .
                                '" class="block px-4 py-2 text-sm font-medium text-slate-800 hover:text-brand-purple hover:bg-slate-50 rounded-xl transition-colors">Marketing Virtual Assistants</a></li>
                                        <li><a href="' .
                                home_url('/graphic-design-virtual-assistants/') .
                                '" class="block px-4 py-2 text-sm font-medium text-slate-800 hover:text-brand-purple hover:bg-slate-50 rounded-xl transition-colors">Graphic Design Virtual Assistants</a></li>
                                        <li><a href="' .
                                home_url('/bookkeeping-accounting-virtual-assistants/') .
                                '" class="block px-4 py-2 text-sm font-medium text-slate-800 hover:text-brand-purple hover:bg-slate-50 rounded-xl transition-colors">Bookkeeping / Accounting Virtual Assistants</a></li>
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

            <!-- Mobile Menu Button -->
            <div class="flex lg:hidden items-center gap-3">
                <a href="{{ home_url('/vacalendar') }}"
                    class="inline-flex items-center gap-1.5 rounded-full border border-black px-4 py-1.5 text-xs font-display font-bold uppercase tracking-wider text-black bg-transparent">
                    <span>{{ __('Consultation', 'remote-leverage') }}</span>
                </a>

                <button type="button" @click="mobileMenuOpen = !mobileMenuOpen"
                    class="p-2 rounded-xl text-slate-700 hover:text-brand-purple hover:bg-slate-100 transition-colors focus:outline-none"
                    aria-label="{{ __('Toggle navigation menu', 'remote-leverage') }}">
                    <svg x-show="!mobileMenuOpen" class="w-6 h-6" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                    <svg x-show="mobileMenuOpen" class="w-6 h-6" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor" style="display: none;">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

        </div>
    </div>

    <!-- Mobile Menu Drawer (Using MobileNavWalker) -->
    <div x-show="mobileMenuOpen" x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 -translate-y-4" x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 -translate-y-4"
        class="lg:hidden border-b border-slate-200 bg-[#F4F6FC] px-4 pt-2 pb-6 space-y-4 shadow-xl"
        style="display: none;">
        <nav class="flex flex-col space-y-1">
            {!! wp_nav_menu([
                'theme_location' => 'primary_navigation',
                'menu_class' => 'flex flex-col space-y-1',
                'container' => false,
                'echo' => false,
                'walker' => new \App\View\MobileNavWalker(),
                'fallback_cb' => function () {
                    return '
                      <a href="' .
                        home_url('/reviews') .
                        '" class="px-3 py-2 rounded-lg text-base font-semibold text-slate-800 hover:bg-slate-100">Reviews</a>
                      <a href="' .
                        home_url('/case-study/') .
                        '" class="px-3 py-2 rounded-lg text-base font-semibold text-slate-800 hover:bg-slate-100">Case Studies</a>
                      <a href="' .
                        home_url('/admin-virtual-assistants/') .
                        '" class="px-3 py-2 rounded-lg text-base font-semibold text-slate-800 hover:bg-slate-100">Virtual Assistant Roles</a>
                      <a href="' .
                        home_url('/vapricing') .
                        '" class="px-3 py-2 rounded-lg text-base font-semibold text-slate-800 hover:bg-slate-100">Pricing</a>
                      <a href="' .
                        home_url('/samples') .
                        '" class="px-3 py-2 rounded-lg text-base font-semibold text-slate-800 hover:bg-slate-100">Audio Samples</a>';
                },
            ]) !!}
        </nav>

        <div class="pt-3 border-t border-slate-200">
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
