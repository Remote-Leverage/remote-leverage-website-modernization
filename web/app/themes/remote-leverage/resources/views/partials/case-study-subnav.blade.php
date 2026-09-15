{{--
  Shared "CASE STUDIES / TALENT PROFILES / REVIEWS" sub-navigation bar.

  ONE copy, rendered from layouts.app directly above @yield('content') so every surface
  gets the identical bar without any template holding its own markup. Which pages show it,
  which tab is active, and where each tab points all come from App\Support\CaseStudySubnav
  — see that class for the production measurements these classes encode and for the note
  on where production actually shows the bar.

  Type/colour/spacing read off https://remoteleverage.com/case-study/chick-fil-a/ with
  getComputedStyle at 1440px and 400px (2026-09-15):
    band   #330034, min-height 60px
    inner  flex row, gap 40px, align-items:center  (stacks to column, gap 10px, below 768px)
    label  16px / 24px, Inter, already-uppercase copy (no text-transform on production)
    idle   #FFFFFF weight 300 · active #3DC53D weight 500 underlined
--}}
@php($subnavActiveTab = \App\Support\CaseStudySubnav::activeTab())

{{-- No active tab means this request is not one of the bar's surfaces, and the bar must
     not render at all — `tabs()` always returns all three, so gating on it would put the
     bar on every page on the site. --}}
@if ($subnavActiveTab !== null)
    @php($subnavTabs = \App\Support\CaseStudySubnav::tabs($subnavActiveTab))
    <nav class="w-full bg-[#330034] min-h-[60px]" aria-label="{{ __('Case studies, talent and reviews', 'remote-leverage') }}">
        {{-- Container comes from the surface, so the bar's left edge lines up with the
             content directly beneath it — production does the same, it just has only one
             container to match. See CaseStudySubnav::CONTAINERS. --}}
        <div class="{{ \App\Support\CaseStudySubnav::containerClass($subnavActiveTab) }} flex flex-col items-center gap-2.5 py-5 md:flex-row md:items-center md:gap-10 md:py-0 md:min-h-[60px]">
            @foreach ($subnavTabs as $tab)
                <a href="{{ $tab['url'] }}"
                    @if ($tab['active']) aria-current="page" @endif
                    class="font-sans text-[16px] leading-6 transition-colors
                        {{ $tab['active']
                            ? 'font-medium text-[#3DC53D] underline decoration-[#3DC53D]'
                            : 'font-light text-white hover:text-white/80' }}">
                    {{ $tab['label'] }}
                </a>
            @endforeach
        </div>
    </nav>
@endif
