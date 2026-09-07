<section class="py-16 sm:py-24 bg-bg-light">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    
    {{-- Header --}}
    <div class="text-center max-w-3xl mx-auto mb-14">
      <span class="px-3.5 py-1 rounded-pill bg-brand-purple/10 text-brand-purple text-xs font-bold uppercase tracking-wider">
        Comparative Economics
      </span>
      <h2 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold font-display text-brand-hero tracking-tight mt-3">
        {{ $tableTitle }}
      </h2>
    </div>

    {{-- Comparison Table Container --}}
    <div class="bg-surface-white rounded-card-lg border border-slate-200/80 shadow-card overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse min-w-[650px]">
          {{-- Table Headers --}}
          <thead>
            <tr class="border-b border-slate-200/80 text-xs uppercase tracking-wider">
              <th scope="col" class="py-5 px-6 font-bold text-text-slate bg-slate-50/50 w-1/4">
                {{ $col1Header }}
              </th>
              <th scope="col" class="py-5 px-6 font-extrabold text-brand-purple bg-brand-purple/10 border-x-2 border-brand-purple/30 w-1/3 relative">
                <div class="flex items-center justify-between">
                  <span>{{ $col2Header }}</span>
                  <span class="px-2 py-0.5 rounded-pill bg-brand-purple text-white text-2xs font-bold">Recommended</span>
                </div>
              </th>
              <th scope="col" class="py-5 px-6 font-bold text-text-muted bg-slate-50/50 w-1/5">
                {{ $col3Header }}
              </th>
              <th scope="col" class="py-5 px-6 font-bold text-text-muted bg-slate-50/50 w-1/5">
                {{ $col4Header }}
              </th>
            </tr>
          </thead>

          {{-- Table Body --}}
          <tbody class="divide-y divide-slate-100 text-xs sm:text-sm">
            @foreach ($rows as $row)
              <tr class="{{ ! empty($row['highlight']) ? 'bg-purple-50/20 font-medium' : 'hover:bg-slate-50/60' }} transition">
                {{-- Metric Name --}}
                <td class="py-4 px-6 font-bold text-text-body">
                  {{ $row['feature_name'] }}
                </td>

                {{-- Remote Leverage (Highlighted Column) --}}
                <td class="py-4 px-6 font-bold text-brand-purple bg-brand-purple/5 border-x-2 border-brand-purple/30">
                  <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-emerald-500 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                    <span>{{ $row['col_2_val'] }}</span>
                  </div>
                </td>

                {{-- Competitor 1 --}}
                <td class="py-4 px-6 text-text-muted">
                  {{ $row['col_3_val'] }}
                </td>

                {{-- Competitor 2 --}}
                <td class="py-4 px-6 text-text-muted">
                  {{ $row['col_4_val'] }}
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>

    {{-- Bottom CTA button --}}
    <div class="text-center mt-10">
      <a
        href="#booking-wizard"
        class="inline-flex items-center gap-2 px-8 py-3.5 rounded-cta bg-brand-magenta hover:bg-brand-magenta-hover text-white font-bold text-sm shadow-md hover:shadow-lg transition transform hover:-translate-y-0.5"
      >
        <span>Experience the Remote Leverage Advantage</span>
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
      </a>
    </div>

  </div>
</section>
