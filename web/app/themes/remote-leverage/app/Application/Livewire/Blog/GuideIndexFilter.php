<?php

declare(strict_types=1);

namespace App\Application\Livewire\Blog;

use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class GuideIndexFilter extends Component
{
    use WithPagination;

    public string $search = '';

    public string $selectedCategory = 'all';

    public string $selectedRole = 'all';

    public string $sortBy = 'latest';

    public int $perPage = 9;

    public array $roles = [
        'all' => 'All Roles',
        'executive-assistant' => 'Executive Assistant',
        'real-estate' => 'Real Estate',
        'ecommerce' => 'E-commerce',
        'marketing' => 'Marketing & Content',
        'operations' => 'Operations & Finance',
    ];

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedSelectedCategory(): void
    {
        $this->resetPage();
    }

    public function updatedSelectedRole(): void
    {
        $this->resetPage();
    }

    public function filterByRole(string $role): void
    {
        $this->selectedRole = $role;
        $this->resetPage();
    }

    public function filterByCategory(string $cat): void
    {
        $this->selectedCategory = $cat;
        $this->resetPage();
    }

    public function render(): View
    {
        $articles = $this->queryArticles();

        return view('livewire.blog.guide-index-filter', [
            'articles' => $articles,
        ]);
    }

    protected function queryArticles(): array
    {
        // 1. Attempt WordPress Native WP_Query
        if (function_exists('get_posts')) {
            $args = [
                'post_type' => 'post',
                'post_status' => 'publish',
                'posts_per_page' => $this->perPage,
                'paged' => $this->getPage(),
                's' => $this->search ?: '',
            ];

            if ($this->selectedCategory !== 'all') {
                $args['category_name'] = $this->selectedCategory;
            }

            if ($this->sortBy === 'title') {
                $args['orderby'] = 'title';
                $args['order'] = 'ASC';
            } else {
                $args['orderby'] = 'date';
                $args['order'] = 'DESC';
            }

            if (class_exists(\WP_Query::class)) {
                $wpQuery = new \WP_Query($args);

                if ($wpQuery->have_posts()) {
                    $results = [];
                    while ($wpQuery->have_posts()) {
                        $wpQuery->the_post();
                        $postId = \get_the_ID();
                        $results[] = [
                            'id' => $postId,
                            'title' => \get_the_title(),
                            'excerpt' => \get_the_excerpt() ?: \wp_trim_words(\get_the_content(), 20),
                            'url' => \get_permalink(),
                            'date' => \get_the_date('M j, Y'),
                            'read_time' => '5 min read',
                            'category' => \get_the_category()[0]->name ?? 'VA Guide',
                            'thumbnail_url' => \get_the_post_thumbnail_url($postId, 'medium_large') ?: null,
                        ];
                    }
                    \wp_reset_postdata();

                    return $results;
                }
            }
        }

        // 2. Structured fallback for development & preview
        return $this->getMockArticles();
    }

    protected function getMockArticles(): array
    {
        $mock = [
            [
                'id' => 1,
                'title' => 'The Complete Playbook: Hiring an Executive Assistant in Latin America',
                'excerpt' => 'How US founders leverage nearshore executive assistants to reclaim 20+ hours every single week.',
                'url' => '#',
                'date' => 'Sep 2, 2026',
                'read_time' => '7 min read',
                'role' => 'executive-assistant',
                'category' => 'Hiring Guide',
                'thumbnail_url' => null,
            ],
            [
                'id' => 2,
                'title' => 'Real Estate Transaction Coordinators: Standard Operating Procedures & Tool Stacks',
                'excerpt' => 'The exact SOP checklist our real estate clients use to manage 50+ monthly escrows seamlessly.',
                'url' => '#',
                'date' => 'Aug 28, 2026',
                'read_time' => '6 min read',
                'role' => 'real-estate',
                'category' => 'SOPs & Workflows',
                'thumbnail_url' => null,
            ],
            [
                'id' => 3,
                'title' => 'Nearshore vs. Offshore: Why Timezone Alignment Trumps Cheap Hourly Rates',
                'excerpt' => 'A financial and operational breakdown comparing Philippines BPOs with Latin American talent.',
                'url' => '#',
                'date' => 'Aug 22, 2026',
                'read_time' => '8 min read',
                'role' => 'operations',
                'category' => 'Market Insights',
                'thumbnail_url' => null,
            ],
            [
                'id' => 4,
                'title' => 'Shopify Customer Support Playbook: Escalations, Gorgias & Fast SLAs',
                'excerpt' => 'How DTC e-commerce brands scale support volume during peak holiday seasons with bilingual specialists.',
                'url' => '#',
                'date' => 'Aug 14, 2026',
                'read_time' => '5 min read',
                'role' => 'ecommerce',
                'category' => 'E-commerce Ops',
                'thumbnail_url' => null,
            ],
            [
                'id' => 5,
                'title' => 'Short-Form Content Repurposing Workflow for Solo Founders and Agency CEOs',
                'excerpt' => 'Transform 1 podcast episode into 15 high-performing LinkedIn posts and TikTok clips using remote editors.',
                'url' => '#',
                'date' => 'Aug 08, 2026',
                'read_time' => '4 min read',
                'role' => 'marketing',
                'category' => 'Content Growth',
                'thumbnail_url' => null,
            ],
            [
                'id' => 6,
                'title' => 'Remote Team Legal Compliance: Independent Contractor Agreements & Payouts',
                'excerpt' => 'Everything you need to know about US W-8BEN compliance, intellectual property assignment, and Stripe Connect.',
                'url' => '#',
                'date' => 'Jul 30, 2026',
                'read_time' => '9 min read',
                'role' => 'operations',
                'category' => 'Compliance & Legal',
                'thumbnail_url' => null,
            ],
        ];

        return array_values(array_filter($mock, function ($item) {
            if ($this->selectedRole !== 'all' && $item['role'] !== $this->selectedRole) {
                return false;
            }

            if ($this->search && ! str_contains(strtolower($item['title']), strtolower($this->search))) {
                return false;
            }

            return true;
        }));
    }
}
