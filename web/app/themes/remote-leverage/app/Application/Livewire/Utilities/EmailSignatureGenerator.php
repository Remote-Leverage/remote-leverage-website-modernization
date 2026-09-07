<?php

declare(strict_types=1);

namespace App\Application\Livewire\Utilities;

use App\Domains\ContentAudit\Actions\GenerateSignatureHtmlAction;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class EmailSignatureGenerator extends Component
{
    public string $name = 'Adrian Salvatori';

    public string $title = 'Founder & Managing Director';

    public string $email = 'adrian@remoteleverage.com';

    public string $phone = '+1 (800) 518-9128';

    public string $mobile = '';

    public string $website = 'https://remoteleverage.com';

    public string $websiteDisplay = 'remoteleverage.com';

    public string $addressLine1 = '1309 Coffeen Avenue STE 1200';

    public string $addressLine2 = 'Sheridan, WY 82801';

    public string $logoIconUrl = 'https://remoteleverage.com/wp-content/uploads/logo-icon-white.svg';

    public string $logoUrl = 'https://remoteleverage.com/wp-content/uploads/rl-logo.png';

    public string $bookingUrl = 'https://remoteleverage.com/#booking';

    public string $generatedHtml = '';

    public function mount(): void
    {
        $this->regenerateSignature();
    }

    public function updated(): void
    {
        $this->regenerateSignature();
    }

    public function regenerateSignature(): void
    {
        $action = app(GenerateSignatureHtmlAction::class);
        $this->generatedHtml = $action->execute([
            'name' => $this->name,
            'title' => $this->title,
            'email' => $this->email,
            'phone' => $this->phone,
            'mobile' => $this->mobile,
            'website' => $this->website,
            'website_display' => $this->websiteDisplay,
            'address_line1' => $this->addressLine1,
            'address_line2' => $this->addressLine2,
            'logo_icon_url' => $this->logoIconUrl,
            'logo_url' => $this->logoUrl,
            'booking_url' => $this->bookingUrl,
        ]);
    }

    public function render(): View
    {
        return view('livewire.utilities.email-signature-generator');
    }
}
