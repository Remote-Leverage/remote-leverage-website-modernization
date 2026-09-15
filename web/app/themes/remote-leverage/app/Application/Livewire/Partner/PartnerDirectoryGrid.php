<?php

declare(strict_types=1);

namespace App\Application\Livewire\Partner;

use App\Domains\PartnerHub\Actions\QueryPartnersAction;
use App\Domains\PartnerHub\Services\PartnerHubGlobalData;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class PartnerDirectoryGrid extends Component
{
    public string $search = '';

    public string $selectedCategory = 'All';

    public bool $featuredOnly = false;

    /**
     * Filter pills. Kept in step with the CPT's category select via
     * PartnerHubGlobalData so a partner can't be filed under a category the
     * grid has no pill for. "All" is the grid's own pseudo-category.
     *
     * @var array<int, string>
     */
    public array $categories = [];

    public function mount(): void
    {
        $this->categories = ['All', ...PartnerHubGlobalData::getDirectoryCategories()];
    }

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
