<div 
  x-data="{
    copiedRich: false,
    copiedRaw: false,
    copyRichText() {
      const el = document.getElementById('signature-render-container');
      if (!el) return;
      const htmlBlob = new Blob([el.innerHTML], { type: 'text/html' });
      const textBlob = new Blob([el.innerText], { type: 'text/plain' });
      const item = new ClipboardItem({ 'text/html': htmlBlob, 'text/plain': textBlob });
      navigator.clipboard.write([item]).then(() => {
        this.copiedRich = true;
        setTimeout(() => this.copiedRich = false, 2500);
      });
    },
    copyRawHtml() {
      const el = document.getElementById('signature-render-container');
      if (!el) return;
      navigator.clipboard.writeText(el.innerHTML).then(() => {
        this.copiedRaw = true;
        setTimeout(() => this.copiedRaw = false, 2500);
      });
    }
  }"
  class="w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8"
>

  <div class="mb-8">
    <span class="px-3 py-1 rounded-pill bg-brand-purple/10 text-brand-purple text-xs font-bold uppercase tracking-wider">
      Internal Tools
    </span>
    <h2 class="text-3xl font-bold font-display text-brand-hero tracking-tight mt-2">
      Email Signature Generator
    </h2>
    <p class="text-text-muted text-sm mt-1">
      Create a standardized, brand-compliant HTML email signature for your team or personal client communications.
    </p>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
    
    {{-- Left Column: Form Controls --}}
    <div class="lg:col-span-5 bg-surface-white rounded-card-lg border border-slate-200/80 shadow-card p-6 space-y-4">
      <h3 class="text-base font-bold font-display text-brand-hero border-b border-slate-100 pb-3">
        Signature Details
      </h3>

      <div>
        <label class="block text-xs font-semibold text-text-slate mb-1">Full Name</label>
        <input
          type="text"
          wire:model.live.debounce.250ms="name"
          class="w-full text-xs sm:text-sm rounded-card border-slate-200 py-2 px-3 focus:border-brand-purple focus:ring-brand-purple text-text-body bg-white"
        />
      </div>

      <div>
        <label class="block text-xs font-semibold text-text-slate mb-1">Job Title / Role</label>
        <input
          type="text"
          wire:model.live.debounce.250ms="title"
          class="w-full text-xs sm:text-sm rounded-card border-slate-200 py-2 px-3 focus:border-brand-purple focus:ring-brand-purple text-text-body bg-white"
        />
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-semibold text-text-slate mb-1">Email</label>
          <input
            type="email"
            wire:model.live.debounce.250ms="email"
            class="w-full text-xs sm:text-sm rounded-card border-slate-200 py-2 px-3 focus:border-brand-purple focus:ring-brand-purple text-text-body bg-white"
          />
        </div>

        <div>
          <label class="block text-xs font-semibold text-text-slate mb-1">Office Phone</label>
          <input
            type="text"
            wire:model.live.debounce.250ms="phone"
            class="w-full text-xs sm:text-sm rounded-card border-slate-200 py-2 px-3 focus:border-brand-purple focus:ring-brand-purple text-text-body bg-white"
          />
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-semibold text-text-slate mb-1">Mobile (Optional)</label>
          <input
            type="text"
            wire:model.live.debounce.250ms="mobile"
            placeholder="+1 (555) 000-0000"
            class="w-full text-xs sm:text-sm rounded-card border-slate-200 py-2 px-3 focus:border-brand-purple focus:ring-brand-purple text-text-body bg-white"
          />
        </div>

        <div>
          <label class="block text-xs font-semibold text-text-slate mb-1">Website URL</label>
          <input
            type="text"
            wire:model.live.debounce.250ms="website"
            class="w-full text-xs sm:text-sm rounded-card border-slate-200 py-2 px-3 focus:border-brand-purple focus:ring-brand-purple text-text-body bg-white"
          />
        </div>
      </div>

      <div>
        <label class="block text-xs font-semibold text-text-slate mb-1">Address Line 1</label>
        <input
          type="text"
          wire:model.live.debounce.250ms="addressLine1"
          class="w-full text-xs sm:text-sm rounded-card border-slate-200 py-2 px-3 focus:border-brand-purple focus:ring-brand-purple text-text-body bg-white"
        />
      </div>

      <div>
        <label class="block text-xs font-semibold text-text-slate mb-1">Address Line 2</label>
        <input
          type="text"
          wire:model.live.debounce.250ms="addressLine2"
          class="w-full text-xs sm:text-sm rounded-card border-slate-200 py-2 px-3 focus:border-brand-purple focus:ring-brand-purple text-text-body bg-white"
        />
      </div>

      <div>
        <label class="block text-xs font-semibold text-text-slate mb-1">Direct Booking URL</label>
        <input
          type="text"
          wire:model.live.debounce.250ms="bookingUrl"
          class="w-full text-xs sm:text-sm rounded-card border-slate-200 py-2 px-3 focus:border-brand-purple focus:ring-brand-purple text-text-body bg-white"
        />
      </div>
    </div>

    {{-- Right Column: Live Render & Clipboard Controls --}}
    <div class="lg:col-span-7 space-y-6 lg:sticky lg:top-8">
      <div class="bg-surface-white rounded-card-lg border border-slate-200/80 shadow-card p-6 sm:p-8">
        <div class="flex items-center justify-between border-b border-slate-100 pb-4 mb-6">
          <div class="flex items-center gap-2">
            <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
            <h3 class="text-base font-bold font-display text-brand-hero">
              Live Signature Preview
            </h3>
          </div>
          <span class="text-xs text-text-muted">600 x 200 Standard Table</span>
        </div>

        {{-- Signature Render Sandbox --}}
        <div class="overflow-x-auto pb-4 pt-2">
          <div id="signature-render-container" class="inline-block shadow-sm rounded-card overflow-hidden">
            {!! $generatedHtml !!}
          </div>
        </div>

        {{-- Copy Action Buttons --}}
        <div class="pt-6 border-t border-slate-100 flex flex-wrap items-center gap-3">
          <button
            type="button"
            @click="copyRichText()"
            class="px-6 py-3 rounded-cta bg-gradient-to-r from-brand-purple to-brand-magenta hover:opacity-95 text-white text-xs sm:text-sm font-bold shadow-md transition cursor-pointer flex items-center gap-2"
          >
            <span x-show="!copiedRich">Copy Formatted (For Gmail/Outlook)</span>
            <span x-show="copiedRich" x-cloak class="flex items-center gap-1.5">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
              Copied to Clipboard!
            </span>
          </button>

          <button
            type="button"
            @click="copyRawHtml()"
            class="px-5 py-3 rounded-cta border border-slate-200 hover:bg-slate-50 text-text-body text-xs sm:text-sm font-semibold transition cursor-pointer flex items-center gap-2"
          >
            <span x-show="!copiedRaw">Copy Raw HTML</span>
            <span x-show="copiedRaw" x-cloak class="flex items-center gap-1.5 text-brand-purple">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
              HTML Copied!
            </span>
          </button>
        </div>

        <div class="mt-4 p-3.5 rounded-card bg-slate-50 border border-slate-200 text-2xs text-text-muted space-y-1">
          <p><span class="font-bold text-text-body">How to install:</span> Click "Copy Formatted", then open your email client settings (Gmail Settings &gt; General &gt; Signature or Outlook Signatures) and press <kbd class="px-1.5 py-0.5 rounded bg-white border border-slate-300 font-mono text-text-body">Cmd+V</kbd> or <kbd class="px-1.5 py-0.5 rounded bg-white border border-slate-300 font-mono text-text-body">Ctrl+V</kbd>.</p>
        </div>
      </div>
    </div>

  </div>

</div>
