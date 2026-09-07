<section class="py-16 sm:py-24 bg-surface-white">
  {{-- Schema.org JSON-LD structured data --}}
  @if (! empty($schemaJson))
    <script type="application/ld+json">
      {!! $schemaJson !!}
    </script>
  @endif

  <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
    {{-- Header --}}
    <div class="text-center max-w-2xl mx-auto mb-12">
      <span class="px-3.5 py-1 rounded-pill bg-brand-purple/10 text-brand-purple text-xs font-bold uppercase tracking-wider">
        Answers & Clarifications
      </span>
      <h2 class="text-3xl sm:text-4xl font-extrabold font-display text-brand-hero tracking-tight mt-3">
        {{ $headline }}
      </h2>
    </div>

    {{-- Accordion List --}}
    <div class="space-y-4">
      @foreach ($faqs as $faq)
        <details class="group bg-bg-light rounded-card-lg border border-slate-200/80 p-6 transition-all duration-200 [&_summary::-webkit-details-marker]:hidden open:border-brand-purple/40 open:shadow-card">
          <summary class="flex items-center justify-between cursor-pointer list-none focus:outline-none">
            <h3 class="text-base sm:text-lg font-bold font-display text-brand-hero group-hover:text-brand-purple transition pr-4">
              {{ $faq['question'] }}
            </h3>
            <span class="w-7 h-7 rounded-full bg-white border border-slate-200 flex items-center justify-center shrink-0 text-text-slate group-open:rotate-180 transition-transform duration-300">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </span>
          </summary>

          <div class="mt-4 pt-4 border-t border-slate-200/60 text-text-muted text-xs sm:text-sm leading-relaxed">
            {!! nl2br(e($faq['answer'])) !!}
          </div>
        </details>
      @endforeach
    </div>

    {{-- Still have questions footer CTA --}}
    <div class="mt-12 text-center p-6 rounded-card bg-brand-purple/5 border border-brand-purple/20">
      <p class="text-xs sm:text-sm text-text-body font-medium">
        Have a specific operational question not listed above?
      </p>
      <div class="mt-3">
        <a
          href="#booking-wizard"
          class="inline-flex items-center gap-1.5 text-xs font-bold text-brand-purple hover:text-brand-purple-deep hover:underline"
        >
          <span>Ask our Staffing Director on a Live Call</span>
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
        </a>
      </div>
    </div>

  </div>
</section>
