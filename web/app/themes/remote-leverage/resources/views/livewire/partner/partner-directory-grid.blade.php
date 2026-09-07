<div class="w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-8">

  {{-- Filters Header --}}
  <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
    {{-- Search Bar --}}
    <div class="relative w-full md:w-96">
      <svg class="w-4 h-4 text-text-muted absolute left-3.5 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
      </svg>
      <input
        type="text"
        wire:model.live.debounce.300ms="search"
        placeholder="Search partners, services, perks..."
        class="w-full text-xs sm:text-sm pl-10 pr-4 py-2.5 rounded-pill border-slate-200 focus:border-brand-purple focus:ring-brand-purple text-text-body bg-white shadow-sm"
      />
    </div>

    {{-- Featured Toggle --}}
    <div class="flex items-center gap-2">
      <button
        type="button"
        wire:click="toggleFeatured"
        class="inline-flex items-center gap-2 px-4 py-2 rounded-pill text-xs font-semibold border transition cursor-pointer {{ $featuredOnly ? 'border-brand-purple bg-brand-purple text-white shadow-sm' : 'border-slate-200 hover:border-slate-300 bg-white text-text-muted' }}"
      >
        <span class="w-2 h-2 rounded-full {{ $featuredOnly ? 'bg-white' : 'bg-brand-orange' }}"></span>
        <span>Featured Only</span>
      </button>
    </div>
  </div>

  {{-- Category Filter Pills --}}
  <div class="flex items-center gap-2 overflow-x-auto pb-2 scrollbar-none">
    @foreach ($categories as $cat)
      <button
        type="button"
        wire:click="selectCategory('{{ $cat }}')"
        class="px-4 py-1.5 rounded-pill text-xs font-bold whitespace-nowrap transition cursor-pointer {{ $selectedCategory === $cat ? 'bg-brand-purple text-white shadow-sm' : 'bg-white text-text-muted hover:text-text-body border border-slate-200/80 hover:border-slate-300' }}"
      >
        {{ $cat }}
      </button>
    @endforeach
  </div>

  {{-- Partners Grid --}}
  <div wire:loading.class="opacity-60" class="transition-opacity">
    @if (empty($partners))
      <div class="text-center py-16 bg-surface-white rounded-card-lg border border-slate-200/80 p-8">
        <div class="w-12 h-12 mx-auto rounded-full bg-slate-100 flex items-center justify-center text-text-muted mb-3">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </div>
        <h4 class="text-base font-bold text-text-body">No matching partners found</h4>
        <p class="text-xs text-text-muted mt-1 max-w-sm mx-auto">
          Try adjusting your search query or select "All" categories to view our complete strategic ecosystem.
        </p>
      </div>
    @else
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach ($partners as $partner)
          <div class="group relative bg-surface-white rounded-card-lg border border-slate-200/80 hover:border-brand-purple/40 shadow-card hover:shadow-xl transition-all duration-300 flex flex-col justify-between overflow-hidden">
            
            <div class="p-6 space-y-4">
              {{-- Card Header: Logo / Badge --}}
              <div class="flex items-start justify-between gap-3">
                <div class="w-12 h-12 rounded-card bg-brand-midnight/5 border border-slate-100 flex items-center justify-center p-2">
                  @if (! empty($partner['logo_url']))
                    <img src="{{ $partner['logo_url'] }}" alt="{{ $partner['name'] }}" class="max-h-full max-w-full object-contain" />
                  @else
                    <span class="font-display font-bold text-brand-purple text-lg">{{ substr($partner['name'], 0, 2) }}</span>
                  @endif
                </div>

                <div class="flex items-center gap-1.5">
                  @if (! empty($partner['featured']))
                    <span class="px-2 py-0.5 rounded-pill bg-brand-magenta/10 text-brand-magenta text-2xs font-bold uppercase tracking-wider">
                      Featured
                    </span>
                  @endif
                  <span class="px-2 py-0.5 rounded-pill bg-slate-100 text-text-muted text-2xs font-semibold">
                    {{ $partner['category'] ?? 'Partner' }}
                  </span>
                </div>
              </div>

              {{-- Title & Bio --}}
              <div>
                <h4 class="text-lg font-bold font-display text-brand-hero group-hover:text-brand-purple transition">
                  {{ $partner['name'] }}
                </h4>
                <p class="text-xs text-text-muted mt-1.5 line-clamp-3 leading-relaxed">
                  {{ $partner['description'] ?? 'Strategic partner in the Remote Leverage verified provider ecosystem.' }}
                </p>
              </div>

              {{-- Exclusive Perk / Benefit --}}
              @if (! empty($partner['perk_description']))
                <div class="p-3 rounded-card bg-brand-purple/5 border border-brand-purple/20 flex items-center gap-2.5 text-xs text-brand-purple">
                  <svg class="w-4 h-4 shrink-0 text-brand-magenta" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M5 2a2 2 0 00-2 2v14l4-2 4 2 4-2 4 2V4a2 2 0 00-2-2H5zm0 2h10v11.382l-3-1.5-2 1-2-1-3 1.5V4z" clip-rule="evenodd"/>
                  </svg>
                  <span class="font-medium line-clamp-1">{{ $partner['perk_description'] }}</span>
                </div>
              @endif
            </div>

            {{-- Card Footer CTA --}}
            <div class="p-4 bg-slate-50/70 border-t border-slate-100 flex items-center justify-between">
              @php($hubSlug = ! empty($partner['slug']) ? $partner['slug'] : \Illuminate\Support\Str::slug($partner['name']))
              <a
                href="{{ home_url('/partners/' . $hubSlug . '/overview') }}"
                class="text-xs font-bold text-brand-purple hover:text-brand-purple-deep flex items-center gap-1.5"
              >
                <span>View Co-Branded Hub</span>
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
              </a>

              @if (! empty($partner['website']))
                <a
                  href="{{ $partner['website'] }}"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="text-2xs text-text-slate hover:text-text-body"
                >
                  Visit Website &nearr;
                </a>
              @endif
            </div>

          </div>
        @endforeach
      </div>
    @endif
  </div>

</div>
