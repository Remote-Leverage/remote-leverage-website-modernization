<?php
/**
 * Title: Funnel - Consultation Split Schedule
 * Slug: remote-leverage/consultation-schedule-split
 * Categories: remote-leverage, remote-leverage-funnels, remote-leverage-sections
 * Description: 2-column split consultation layout with hiring value props on left and embedded booking wizard on right.
 */
?>
<!-- wp:group {"className":"rl-split-consultation my-12 rounded-3xl bg-[#13132F] text-white p-8 md:p-12 shadow-2xl border border-white/10","layout":{"type":"constrained"}} -->
<div class="wp-block-group rl-split-consultation my-12 rounded-3xl bg-[#13132F] text-white p-8 md:p-12 shadow-2xl border border-white/10">
  <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 items-center">
    
    <div class="lg:col-span-6 space-y-6">
      <div class="inline-flex items-center gap-2 rounded-full bg-brand-purple/25 border border-brand-purple/40 px-3.5 py-1 text-xs font-semibold text-purple-200">
        <span class="h-2 w-2 rounded-full bg-emerald-400"></span>
        <span>Free 15-Minute Strategy Consultation</span>
      </div>

      <h2 class="font-display text-3xl sm:text-4xl font-extrabold tracking-tight text-white leading-tight">
        Talk to an Executive Talent Strategist Today
      </h2>

      <p class="text-zinc-300 text-base leading-relaxed">
        Tell us what bottleneck is slowing down your business. We will analyze your workflows, identify high-leverage roles to delegate, and send you 3 curated candidate profiles within 48 hours.
      </p>

      <div class="space-y-3 pt-2">
        <div class="flex items-center gap-3 text-sm text-zinc-200">
          <div class="flex h-6 w-6 items-center justify-center rounded-full bg-emerald-500/20 text-emerald-400">
            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
            </svg>
          </div>
          <span>Zero upfront cost &amp; zero recruitment placement fees</span>
        </div>

        <div class="flex items-center gap-3 text-sm text-zinc-200">
          <div class="flex h-6 w-6 items-center justify-center rounded-full bg-emerald-500/20 text-emerald-400">
            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
            </svg>
          </div>
          <span>Fully bilingual (English C1/C2) with verified references</span>
        </div>

        <div class="flex items-center gap-3 text-sm text-zinc-200">
          <div class="flex h-6 w-6 items-center justify-center rounded-full bg-emerald-500/20 text-emerald-400">
            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
            </svg>
          </div>
          <span>Seamless US payroll, billing, and tax compliance handled</span>
        </div>
      </div>
    </div>

    <div class="lg:col-span-6 bg-white/5 p-4 sm:p-6 rounded-2xl border border-white/10 backdrop-blur-xl">
      <!-- wp:acf/booking {"name":"acf/booking","data":{"skin":"glass"},"mode":"preview"} /-->
    </div>

  </div>
</div>
<!-- /wp:group -->
