<?php

declare(strict_types=1);

namespace App\Application\Livewire\Partner;

use App\Domains\PartnerHub\Actions\QueryPartnersAction;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class PartnerDirectoryGrid extends Component
{
    public string $search = '';

    public string $selectedCategory = 'All';

    public bool $featuredOnly = false;

    public array $categories = [
        'All',
        'Staffing & HR',
        'Marketing & Media',
        'Software & Tech',
        'Finance & Legal',
        'Real Estate',
    ];

    public function selectCategory(string $category): void
    {
        $this->selectedCategory = $category;
    }

    public function toggleFeatured(): void
    {
        $this->featuredOnly = ! $this->featuredOnly;
    }

    public function render(): View
    {
        $action = app(QueryPartnersAction::class);
        $partners = $action->execute(
            search: $this->search,
            category: $this->selectedCategory === 'All' ? null : $this->selectedCategory,
            featuredOnly: $this->featuredOnly
        );

        return view('livewire.partner.partner-directory-grid', [
            'partners' => $partners,
        ]);
    }
}
