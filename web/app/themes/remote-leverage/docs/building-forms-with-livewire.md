# Building Forms with Native Livewire (ADR-0008 Standard)

## 1. Architectural Overview & Context

In legacy versions of Remote Leverage, forms were managed through Gravity Forms plugins (`GF_HubSpot`, `gform_after_submission` hooks, shortcodes). 

Under **ADR-0008**, Gravity Forms has been **completely retired** in favor of native **Livewire 3** components directly integrated with the **Lead Bounded Context** (`app/Domains/Lead`).

### Key Benefits
- **Zero Plugin Overhead**: Eliminates third-party form builders, asset bloat, and database fragmentation (`wp_gf_*` tables).
- **Domain-Driven Architecture**: Every form submission routes through a single domain orchestrator (`CaptureLeadAction`).
- **Standardized Attribution**: Automatic attribution resolution (`AttributionEngine::resolveLeadSource()`) assigns `sourceType` (`ad`, `organic`, `referral_hub`, `partnership`) and `sourceID`.
- **Dual-Logging Contract**: Every submission records both **Stage 1: Dispatch** and **Stage 2: Consumption** in `rl_lead_activity_logs`.
- **Design System Consistency**: Forms utilize Tailwind v4 `@theme` design tokens (`--color-brand-purple`, `--color-surface-white`, `--color-text-body`, `--radius-card`, etc.).

---

## 2. The Form Submission Lifecycle

```
[ User Submits Form in Blade ]
               │
               ▼  wire:submit="submit"
[ Livewire Component Class ]
   │
   ├─► $this->validate()               (Standard Laravel Validation)
   ├─► PhoneValidationService          (International E.164 Parsing)
   │
   ▼
[ CaptureLeadAction ]                  (Lead Bounded Context)
   │
   ├─► AttributionEngine::resolveLeadSource()
   ├─► Lead::create()                  (rl_leads table)
   ├─► LeadActivityLogger::logDispatch() (Stage 1 Log)
   ├─► event(new LeadCreated($lead))
   │
   ▼
┌──────────────────────────────────────────────────────────────┐
│                  Asynchronous Domain Listeners               │
├───────────────────────────────┬──────────────────────────────┤
│ HandleLeadCreatedForTracking  │ Customer.io & PostHog sync   │
│ HandleLeadCreatedForBooking   │ Calendly & Meeting routing   │
│ HubSpotGateway                │ Direct CRM contact syncing   │
└───────────────────────────────┴──────────────────────────────┘
```

---

## 3. Step-by-Step Implementation Guide

### Step 1: Create the Livewire Component Class

Place your component class in `app/Application/Livewire/` (e.g. `app/Application/Livewire/Forms/ContactInquiryForm.php`):

```php
<?php

declare(strict_types=1);

namespace App\Application\Livewire\Forms;

use App\Domains\Lead\Actions\CaptureLeadAction;
use App\Domains\Lead\Data\LeadCaptureData;
use App\Domains\Lead\Services\PhoneValidationService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class ContactInquiryForm extends Component
{
    // Form Input Properties
    public string $name = '';
    public string $email = '';
    public string $phone = '';
    public string $company = '';
    public string $roleNeeded = 'Executive Assistant';
    public string $notes = '';

    // UI Feedback States
    public bool $isSubmitted = false;
    public ?string $errorMessage = null;

    // Validation Rules
    protected array $rules = [
        'name' => 'required|string|min:2|max:100',
        'email' => 'required|email|max:150',
        'phone' => 'nullable|string|max:30',
        'company' => 'nullable|string|max:100',
        'roleNeeded' => 'required|string|max:100',
        'notes' => 'nullable|string|max:1000',
    ];

    public function submit(CaptureLeadAction $captureLead, PhoneValidationService $phoneValidator): void
    {
        $this->errorMessage = null;
        $this->validate();

        // 1. Validate International Phone (if provided)
        if (! empty($this->phone) && ! $phoneValidator->isValid($this->phone)) {
            $this->addError('phone', 'Please enter a valid phone number including country code.');
            return;
        }

        try {
            // 2. Delegate to Lead Domain Action
            $lead = $captureLead->execute(new LeadCaptureData(
                name: $this->name,
                email: $this->email,
                phone: $this->phone ? $phoneValidator->formatE164($this->phone) : null,
                company: $this->company ?: null,
                roleNeeded: $this->roleNeeded,
                notes: $this->notes ?: null,
                extraData: [
                    'source_form' => 'contact_inquiry',
                ]
            ));

            $this->isSubmitted = true;
        } catch (\Throwable $e) {
            $this->errorMessage = 'An error occurred while submitting your request. Please try again.';
        }
    }

    public function render(): View
    {
        return view('livewire.forms.contact-inquiry-form');
    }
}
```

---

### Step 2: Register the Component in LivewireServiceProvider

Add your component registration to [app/Infrastructure/Providers/LivewireServiceProvider.php](file:///Users/adriansalvatori/Documents/projects-rl/remoteleverage-v2/web/app/themes/remote-leverage/app/Infrastructure/Providers/LivewireServiceProvider.php):

```php
Livewire::component('forms.contact-inquiry-form', \App\Application\Livewire\Forms\ContactInquiryForm::class);
```

---

### Step 3: Build the Blade View with Tailwind Design Tokens

Create `resources/views/livewire/forms/contact-inquiry-form.blade.php`:

```blade
<div class="w-full max-w-xl mx-auto p-6 sm:p-8 bg-surface-white rounded-card-lg border border-slate-200/80 shadow-card">
  
  @if ($isSubmitted)
    <div class="text-center py-8 space-y-4">
      <div class="w-14 h-14 rounded-full bg-status-success/10 text-status-success mx-auto flex items-center justify-center">
        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
        </svg>
      </div>
      <h3 class="text-2xl font-bold font-display text-brand-hero">Request Received!</h3>
      <p class="text-xs sm:text-sm text-text-muted max-w-sm mx-auto">
        Thank you, {{ $name }}. One of our senior staffing directors will reach out within 1 business day.
      </p>
    </div>

  @else
    <div class="mb-6">
      <h3 class="text-xl font-bold font-display text-brand-hero">Request Talent Consultation</h3>
      <p class="text-xs text-text-muted mt-1">Fill out the form below to begin scaling your team.</p>
    </div>

    @if ($errorMessage)
      <div class="mb-4 p-3 rounded-card bg-red-50 border border-red-200 text-status-alert text-xs">
        {{ $errorMessage }}
      </div>
    @endif

    <form wire:submit.prevent="submit" class="space-y-4">
      <div>
        <label class="block text-xs font-bold uppercase tracking-wider text-text-body mb-1">Full Name *</label>
        <input 
          type="text" 
          wire:model="name"
          placeholder="Sarah Jenkins"
          class="w-full px-4 py-2.5 rounded-card border border-slate-200 focus:border-brand-purple focus:ring-2 focus:ring-brand-purple/20 text-sm text-text-body bg-white transition"
        />
        @error('name') <span class="text-status-alert text-xs block mt-1">{{ $message }}</span> @enderror
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <label class="block text-xs font-bold uppercase tracking-wider text-text-body mb-1">Work Email *</label>
          <input 
            type="email" 
            wire:model="email"
            placeholder="sarah@company.com"
            class="w-full px-4 py-2.5 rounded-card border border-slate-200 focus:border-brand-purple focus:ring-2 focus:ring-brand-purple/20 text-sm text-text-body bg-white transition"
          />
          @error('email') <span class="text-status-alert text-xs block mt-1">{{ $message }}</span> @enderror
        </div>

        <div>
          <label class="block text-xs font-bold uppercase tracking-wider text-text-body mb-1">Phone Number</label>
          <input 
            type="tel" 
            wire:model="phone"
            placeholder="+1 (555) 000-0000"
            class="w-full px-4 py-2.5 rounded-card border border-slate-200 focus:border-brand-purple focus:ring-2 focus:ring-brand-purple/20 text-sm text-text-body bg-white transition"
          />
          @error('phone') <span class="text-status-alert text-xs block mt-1">{{ $message }}</span> @enderror
        </div>
      </div>

      <div>
        <label class="block text-xs font-bold uppercase tracking-wider text-text-body mb-1">Primary Role Needed</label>
        <select 
          wire:model="roleNeeded"
          class="w-full px-4 py-2.5 rounded-card border border-slate-200 focus:border-brand-purple focus:ring-2 focus:ring-brand-purple/20 text-sm text-text-body bg-white cursor-pointer transition"
        >
          <option value="Executive Assistant">Executive Assistant</option>
          <option value="Real Estate Assistant">Real Estate Assistant</option>
          <option value="Marketing Specialist">Marketing Specialist</option>
          <option value="Operations Manager">Operations Manager</option>
          <option value="Custom Specialty">Other Specialty</option>
        </select>
      </div>

      <button 
        type="submit" 
        wire:loading.attr="disabled"
        class="w-full btn-primary cursor-pointer mt-2 flex items-center justify-center gap-2"
      >
        <span wire:loading.remove wire:target="submit">Submit Request</span>
        <span wire:loading wire:target="submit" class="inline-flex items-center gap-2">
          <svg class="animate-spin w-4 h-4 text-white" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          Submitting...
        </span>
      </button>
    </form>
  @endif

</div>
```

---

### Step 4: Embedding in Pages & Gutenberg Blocks

To embed this form in a Blade template or Gutenberg block render template:
```blade
<livewire:forms.contact-inquiry-form />
```

---

## 4. Testing Your Form

Forms should be verified via Pest tests in `tests/Feature/`:

```php
use App\Application\Livewire\Forms\ContactInquiryForm;
use App\Domains\Lead\Models\Lead;
use Livewire\Livewire;

test('ContactInquiryForm captures lead into rl_leads and logs activity', function () {
    Livewire::test(ContactInquiryForm::class)
        ->set('name', 'Alex Mercer')
        ->set('email', 'alex@mercer.com')
        ->set('phone', '+13055550199')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('isSubmitted', true);

    $lead = Lead::where('email', 'alex@mercer.com')->first();
    expect($lead)->not->toBeNull()
        ->and($lead->name)->toBe('Alex Mercer')
        ->and($lead->phone)->toBe('+13055550199');
});
```
