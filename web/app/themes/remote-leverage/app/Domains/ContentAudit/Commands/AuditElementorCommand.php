<?php

declare(strict_types=1);

namespace App\Domains\ContentAudit\Commands;

use App\Domains\ContentAudit\Services\ElementorAuditService;
use Illuminate\Console\Command;

class AuditElementorCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'content:audit-elementor {--post_id= : Specific post ID to audit}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Audit WordPress posts for Elementor dependencies and report conversion readiness (ADR-0005 Amendment)';

    public function handle(ElementorAuditService $auditService): int
    {
        $postId = $this->option('post_id');

        $this->info('Starting Elementor Dependency Audit...');

        global $wpdb;
        if (! $wpdb) {
            $this->warn('WordPress database not connected in this environment. Running in mock mode.');

            return self::SUCCESS;
        }

        $metaTable = $wpdb->postmeta ?? 'wp_postmeta';
        $query = "SELECT post_id, meta_value FROM {$metaTable} WHERE meta_key = '_elementor_data'";
        if ($postId) {
            $query .= $wpdb->prepare(' AND post_id = %d', (int) $postId);
        }

        $results = $wpdb->get_results($query);
        $totalFound = count($results);

        $this->info("Found {$totalFound} posts carrying _elementor_data.");

        $cleanCount = 0;
        $dependentCount = 0;
        $unmappedTotal = 0;

        foreach ($results as $row) {
            $audit = $auditService->auditElementorData($row->meta_value);

            if ($audit['has_elementor_dependency']) {
                $dependentCount++;
                if (! $audit['all_mapped']) {
                    $unmappedTotal += count($audit['unmapped_widgets']);
                    $this->warn("Post #{$row->post_id} has unmapped widgets: ".implode(', ', array_keys($audit['unmapped_widgets'])));
                }
            } else {
                $cleanCount++;
            }
        }

        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Posts with Elementor Meta', $totalFound],
                ['Active Elementor Dependencies', $dependentCount],
                ['Clean / No Active Widgets', $cleanCount],
                ['Unmapped Widget Instances', $unmappedTotal],
            ]
        );

        if ($dependentCount === 0) {
            $this->info('Zero Elementor dependencies detected! Completion gate passed.');
        } else {
            $this->warn("Completion gate status: Incomplete. {$dependentCount} posts require conversion and mandatory human editorial sign-off.");
        }

        return self::SUCCESS;
    }
}
