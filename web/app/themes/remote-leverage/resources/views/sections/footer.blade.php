<footer class="content-info bg-black text-slate-300 border-t border-neutral-900">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 lg:py-20">
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-10 lg:gap-8">
      
      <!-- Brand & Mission Column (Col span 2) -->
      <div class="lg:col-span-2 space-y-6">
        <a href="{{ home_url('/') }}" class="inline-block focus:outline-none focus:ring-2 focus:ring-brand-purple rounded-lg">
          <div class="flex items-center gap-3">
            <img 
              src="{{ Vite::asset('resources/images/logo.svg') }}" 
              alt="{{ $siteName ?? 'Remote Leverage' }}" 
              width="140"
              height="24"
              loading="lazy"
              decoding="async"
              class="h-6 w-auto brightness-0 invert" 
              onerror="this.style.display='none'; this.nextElementSibling.style.display='block';"
            />
            <span class="hidden text-xl font-display font-bold text-white tracking-tight">
              Remote Leverage
            </span>
          </div>
        </a>

        <p class="text-sm text-slate-400 max-w-sm leading-relaxed">
          {{ __('We connect fast-growing companies with the top 1% of vetted bilingual virtual assistants and remote professionals from Latin America.', 'remote-leverage') }}
        </p>

        <!-- Direct Contact & Trust Badges -->
        <div class="space-y-2 pt-2">
          <div class="flex items-center gap-3 text-sm text-slate-300">
            <svg class="w-4 h-4 text-brand-purple shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
            </svg>
            <a href="mailto:sales@remoteleverage.com" class="hover:text-white transition-colors">
              sales@remoteleverage.com
            </a>
          </div>

          <div class="flex items-center gap-2 text-xs text-amber-400 pt-2">
            <div class="flex gap-0.5">
              @for ($i = 0; $i < 5; $i++)
                <svg class="w-4 h-4 fill-current" viewBox="0 0 20 20">
                  <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                </svg>
              @endfor
            </div>
            <span class="text-slate-300 font-medium">4.9/5 from 2,000+ businesses</span>
          </div>
        </div>
      </div>

      <!-- Column 2: Role Specialties -->
      <div>
        <h3 class="text-xs font-bold text-white uppercase tracking-wider mb-4 font-display">
          {{ __('VA Specialties', 'remote-leverage') }}
        </h3>
        <ul class="space-y-2.5 text-sm">
          <li>
            <a href="{{ home_url('/admin-virtual-assistants/') }}" class="text-slate-400 hover:text-white transition-colors">
              {{ __('Administrative Assistants', 'remote-leverage') }}
            </a>
          </li>
          <li>
            <a href="{{ home_url('/executive-virtual-assistants/') }}" class="text-slate-400 hover:text-white transition-colors">
              {{ __('Executive Assistants', 'remote-leverage') }}
            </a>
          </li>
          <li>
            <a href="{{ home_url('/customer-support-virtual-assistants/') }}" class="text-slate-400 hover:text-white transition-colors">
              {{ __('Customer Support VAs', 'remote-leverage') }}
            </a>
          </li>
          <li>
            <a href="{{ home_url('/sales-virtual-assistants/') }}" class="text-slate-400 hover:text-white transition-colors">
              {{ __('Sales & Outbound SDRs', 'remote-leverage') }}
            </a>
          </li>
          <li>
            <a href="{{ home_url('/bookkeeping-accounting-virtual-assistants/') }}" class="text-slate-400 hover:text-white transition-colors">
              {{ __('Bookkeeping & Accounting', 'remote-leverage') }}
            </a>
          </li>
          <li>
            <a href="{{ home_url('/marketing-assistants-legacy/') }}" class="text-slate-400 hover:text-white transition-colors">
              {{ __('Marketing & Social Media', 'remote-leverage') }}
            </a>
          </li>
        </ul>
      </div>

      <!-- Column 3: Platform & Resources -->
      <div>
        <h3 class="text-xs font-bold text-white uppercase tracking-wider mb-4 font-display">
          {{ __('Resources', 'remote-leverage') }}
        </h3>
        <ul class="space-y-2.5 text-sm">
          <li>
            <a href="{{ home_url('/reviews') }}" class="text-slate-400 hover:text-white transition-colors">
              {{ __('Client Testimonials', 'remote-leverage') }}
            </a>
          </li>
          <li>
            <a href="{{ home_url('/case-study/') }}" class="text-slate-400 hover:text-white transition-colors">
              {{ __('Case Studies', 'remote-leverage') }}
            </a>
          </li>
          <li>
            <a href="{{ home_url('/vapricing') }}" class="text-slate-400 hover:text-white transition-colors">
              {{ __('Pricing Calculator', 'remote-leverage') }}
            </a>
          </li>
          <li>
            <a href="{{ home_url('/samples') }}" class="text-slate-400 hover:text-white transition-colors">
              {{ __('Audio & Video Samples', 'remote-leverage') }}
            </a>
          </li>
          <li>
            <a href="{{ home_url('/partners') }}" class="text-slate-400 hover:text-white transition-colors">
              {{ __('Partner Network', 'remote-leverage') }}
            </a>
          </li>
        </ul>
      </div>

      <!-- Column 4: Consultation CTA -->
      <div>
        <h3 class="text-xs font-bold text-white uppercase tracking-wider mb-4 font-display">
          {{ __('Ready to Scale?', 'remote-leverage') }}
        </h3>
        <p class="text-xs text-slate-400 mb-4 leading-relaxed">
          {{ __('Book a free 15-minute consultation to define your requirements and review sample candidates.', 'remote-leverage') }}
        </p>
        <a href="{{ home_url('/vacalendar') }}" class="btn-primary w-full! py-2.5! px-4! text-xs! justify-center!">
          <span>{{ __('Schedule Consultation', 'remote-leverage') }}</span>
        </a>
      </div>

    </div>

    <!-- Optional WordPress Widget Area -->
    @if (is_active_sidebar('sidebar-footer'))
      <div class="mt-12 pt-8 border-t border-neutral-900">
        @php(dynamic_sidebar('sidebar-footer'))
      </div>
    @endif

    <!-- Bottom Legal Bar -->
    <div class="mt-16 pt-8 border-t border-neutral-900 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-slate-400">
      <p>
        &copy; {{ date('Y') }} {{ $siteName ?? 'Remote Leverage' }}. {{ __('All rights reserved.', 'remote-leverage') }}
      </p>

      <div class="flex items-center gap-6">
        <a href="{{ home_url('/terms-of-use/') }}" class="hover:text-slate-300 transition-colors">
          {{ __('Terms of Service', 'remote-leverage') }}
        </a>
        <a href="{{ home_url('/privacy-policy') }}" class="hover:text-slate-300 transition-colors">
          {{ __('Privacy Policy', 'remote-leverage') }}
        </a>
        <a href="{{ home_url('/referrer-portal') }}" class="hover:text-slate-300 transition-colors">
          {{ __('Referrer Portal', 'remote-leverage') }}
        </a>
      </div>
    </div>

  </div>
</footer>
