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

    public string $dept = 'Executive';

    public string $email = 'adrian@remoteleverage.com';

    public string $phone = '+1 (800) 518-9128';

    public string $mobile = '';

    public string $website = 'https://remoteleverage.com';

    public string $websiteDisplay = 'remoteleverage.com';

    public string $addressLine1 = '1309 Coffeen Avenue STE 1200';

    public string $addressLine2 = 'Sheridan, WY 82801';

    public string $avatarUrl = '';

    public string $linkedin = 'https://www.linkedin.com/company/remoteleverage';

    public string $twitter = 'https://x.com/remoteleverage';

    public string $facebook = '';

    public string $instagram = '';

    public int $selectedLayout = 1;

    public string $selectedTheme = 'light';

    public string $generatedHtml = '';

    public function mount(): void
    {
        $this->regenerateSignature();
    }

    public function updated(): void
    {
        $this->regenerateSignature();
    }

    public function setLayout(int $layout): void
    {
        if (in_array($layout, [1, 2, 3], true)) {
            $this->selectedLayout = $layout;
            $this->regenerateSignature();
        }
    }

    public function setTheme(string $theme): void
    {
        if (in_array($theme, ['light', 'dark'], true)) {
            $this->selectedTheme = $theme;
            $this->regenerateSignature();
        }
    }

    public function regenerateSignature(): void
    {
        $action = app(GenerateSignatureHtmlAction::class);
        $this->generatedHtml = $action->execute([
            'template' => $this->selectedLayout,
            'theme' => $this->selectedTheme,
            'name' => $this->name,
            'title' => $this->title,
            'dept' => $this->dept,
            'email' => $this->email,
            'phone' => $this->phone,
            'mobile' => $this->mobile,
            'website' => $this->website,
            'website_display' => $this->websiteDisplay,
            'address_line1' => $this->addressLine1,
            'address_line2' => $this->addressLine2,
            'avatar_url' => $this->avatarUrl,
            'linkedin' => $this->linkedin,
            'twitter' => $this->twitter,
            'facebook' => $this->facebook,
            'instagram' => $this->instagram,
        ]);
    }

    public function render(): View
    {
        return view('livewire.utilities.email-signature-generator');
    }
}
