<?php

declare(strict_types=1);

namespace App\Domains\ContentAudit\Commands;

use App\Domains\ContentAudit\Actions\ApplyElementorConversionAction;
use Illuminate\Console\Command;

class ConvertElementorCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'content:convert-elementor {--dry-run : Preview the conversion without writing changes} {--post_id= : Specific post ID to convert}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Batch-convert posts carrying _elementor_data into native Gutenberg markup, queued for mandatory human editorial review (ADR-0005 Amendment)';

    public function handle(ApplyElementorConversionAction $action): int
    {
        $postId = $this->option('post_id');
        $dryRun = (bool) $this->option('dry-run');

        $this->info($dryRun ? 'Starting Elementor conversion (DRY RUN — no changes will be written)...' : 'Starting Elementor conversion...');

        global $wpdb;
        if (! $wpdb) {
            $this->warn('WordPress database not connected in this environment. Running in mock mode.');

            return self::SUCCESS;
        }

        $metaTable = $wpdb->postmeta ?? 'wp_postmeta';
        $query = "SELECT post_id FROM {$metaTable} WHERE meta_key = '_elementor_data'";
        if ($postId) {
            $query .= $wpdb->prepare(' AND post_id = %d', (int) $postId);
        }

        $postIds = $wpdb->get_col($query);

        $convertedCount = 0;
        $cleanCount = 0;
        $unmappedTotal = 0;

        foreach ($postIds as $id) {
            $result = $action->execute((int) $id, $dryRun);

            if ($result['status'] === 'needs_review') {
                $convertedCount++;
                if (! empty($result['unmapped_widgets'])) {
                    $unmappedTotal += count($result['unmapped_widgets']);
                    $this->warn("Post #{$id} converted with unmapped widgets: ".implode(', ', $result['unmapped_widgets']));
                }
            } elseif ($result['status'] === 'clean_no_conversion_needed') {
                $cleanCount++;
            }
        }

        $this->table(
            ['Metric', 'Count'],
            [
                ['Posts Scanned', count($postIds)],
                ['Converted / Queued for Review', $convertedCount],
                ['Clean / No Conversion Needed', $cleanCount],
                ['Unmapped Widget Instances', $unmappedTotal],
            ]
        );

        if ($dryRun) {
            $this->info('Dry run complete — no post content or meta was modified.');
        } else {
            $this->info("Conversion complete. {$convertedCount} post(s) queued for mandatory human editorial review — see Posts \u{2192} Needs Editorial Review.");
        }

        return self::SUCCESS;
    }
}
