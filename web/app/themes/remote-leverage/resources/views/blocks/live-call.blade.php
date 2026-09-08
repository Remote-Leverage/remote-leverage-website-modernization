@if ($buttonOnly ?? false)
  <div class="{{ ($alignment ?? 'center') === 'left' ? 'text-left' : (($alignment ?? 'center') === 'right' ? 'text-right' : 'text-center') }}">
    <livewire:scheduling.instant-live-call-button :buttonSize="$buttonSize" />
  </div>
@else
<div class="py-12 px-4 sm:px-6 lg:px-8">
  @if ($showCardWrapper)
    <div class="max-w-4xl mx-auto rounded-card-lg bg-linear-to-r from-brand-midnight to-brand-hero text-white p-8 sm:p-12 shadow-glow-purple border border-purple-900/40 relative overflow-hidden">
      {{-- Ambient lights --}}
      <div class="absolute -right-20 -top-20 w-80 h-80 bg-brand-purple/20 rounded-full blur-3xl pointer-events-none"></div>
      <div class="absolute -left-20 -bottom-20 w-80 h-80 bg-brand-magenta/20 rounded-full blur-3xl pointer-events-none"></div>

      <div class="relative z-10 flex flex-col md:flex-row md:items-center md:justify-between gap-8">
        <div class="space-y-3 max-w-xl">
          <div class="inline-flex items-center gap-2 px-3 py-1 rounded-pill bg-white/10 text-purple-200 text-xs font-bold uppercase tracking-wider border border-white/10">
            <span class="w-2 h-2 rounded-full bg-status-success animate-ping"></span>
            <span>Real-Time Video Matching</span>
          </div>
          <h3 class="text-2xl sm:text-3xl font-bold font-display tracking-tight text-white">
            {{ $headline }}
          </h3>
          @if ($description)
            <p class="text-slate-300 text-sm sm:text-base leading-relaxed">
              {{ $description }}
            </p>
          @endif
        </div>

        <div class="shrink-0 flex items-center justify-start md:justify-end">
          <livewire:scheduling.instant-live-call-button :buttonSize="$buttonSize" />
        </div>
      </div>
    </div>
  @else
    <div class="text-center space-y-4">
      <h3 class="text-2xl font-bold font-display text-brand-hero">{{ $headline }}</h3>
      @if ($description)
        <p class="text-text-muted text-sm max-w-lg mx-auto">{{ $description }}</p>
      @endif
      <div class="pt-2">
        <livewire:scheduling.instant-live-call-button :buttonSize="$buttonSize" />
      </div>
    </div>
  @endif
</div>
@endif
