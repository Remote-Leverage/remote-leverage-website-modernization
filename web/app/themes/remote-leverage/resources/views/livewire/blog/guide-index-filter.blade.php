<div class="w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">

  {{-- Search & Filter Controls --}}
  <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
    {{-- Search Input --}}
    <div class="relative w-full md:w-96">
      <svg class="w-4 h-4 text-text-muted absolute left-3.5 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
      </svg>
      <input
        type="text"
        wire:model.live.debounce.300ms="search"
        placeholder="Search guides, SOPs, workflows..."
        class="w-full text-xs sm:text-sm pl-10 pr-4 py-2.5 rounded-pill border-slate-200 focus:border-brand-purple focus:ring-brand-purple text-text-body bg-white shadow-sm"
      />
    </div>

    {{-- Sort By Dropdown --}}
    <div class="flex items-center gap-2 self-end md:self-auto">
      <span class="text-xs text-text-slate font-semibold">Sort:</span>
      <select
        wire:model.live="sortBy"
        class="text-xs rounded-pill border-slate-200 py-1.5 px-3 focus:border-brand-purple focus:ring-brand-purple text-text-body bg-white shadow-sm"
      >
        <option value="latest">Newest First</option>
        <option value="title">Alphabetical</option>
      </select>
    </div>
  </div>

  {{-- Role Filter Tabs --}}
  <div class="flex items-center gap-2 overflow-x-auto pb-2 scrollbar-none">
    @foreach ($roles as $roleKey => $roleLabel)
      <button
        type="button"
        wire:click="filterByRole('{{ $roleKey }}')"
        class="px-4 py-1.5 rounded-pill text-xs font-bold whitespace-nowrap transition cursor-pointer {{ $selectedRole === $roleKey ? 'bg-brand-purple text-white shadow-sm' : 'bg-white text-text-muted hover:text-text-body border border-slate-200/80 hover:border-slate-300' }}"
      >
        {{ $roleLabel }}
      </button>
    @endforeach
  </div>

  {{-- Guides Grid --}}
  <div wire:loading.class="opacity-60" class="transition-opacity">
    @if (empty($articles))
      <div class="text-center py-16 bg-surface-white rounded-card-lg border border-slate-200/80 p-8">
        <div class="w-12 h-12 mx-auto rounded-full bg-slate-100 flex items-center justify-center text-text-muted mb-3">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
        </div>
        <h4 class="text-base font-bold text-text-body">No guides matching your filter</h4>
        <p class="text-xs text-text-muted mt-1 max-w-sm mx-auto">
          Try clearing your search query or selecting "All Roles" to view all playbooks and SOPs.
        </p>
      </div>
    @else
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach ($articles as $article)
          <article class="group relative bg-surface-white rounded-card-lg border border-slate-200/80 hover:border-brand-purple/40 shadow-card hover:shadow-xl transition-all duration-300 flex flex-col justify-between overflow-hidden">
            
            <div class="p-6 space-y-3">
              {{-- Category & Read Time --}}
              <div class="flex items-center justify-between text-2xs">
                <span class="px-2.5 py-0.5 rounded-pill bg-brand-purple/10 text-brand-purple font-bold uppercase tracking-wider">
                  {{ $article['category'] }}
                </span>
                <span class="text-text-slate font-medium">
                  {{ $article['read_time'] }}
                </span>
              </div>

              {{-- Article Title --}}
              <h3 class="text-base sm:text-lg font-bold font-display text-brand-hero group-hover:text-brand-purple transition leading-snug">
                <a href="{{ $article['url'] }}" class="focus:outline-none">
                  {{ $article['title'] }}
                </a>
              </h3>

              {{-- Excerpt --}}
              <p class="text-xs text-text-muted line-clamp-3 leading-relaxed">
                {{ $article['excerpt'] }}
              </p>
            </div>

            {{-- Card Footer --}}
            <div class="p-4 bg-slate-50/70 border-t border-slate-100 flex items-center justify-between text-xs">
              <span class="text-2xs text-text-slate">{{ $article['date'] }}</span>
              <a
                href="{{ $article['url'] }}"
                class="font-bold text-brand-purple hover:text-brand-purple-deep inline-flex items-center gap-1 group-hover:translate-x-0.5 transition-transform"
              >
                <span>Read Playbook</span>
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
              </a>
            </div>

          </article>
        @endforeach
      </div>
    @endif
  </div>

</div>
