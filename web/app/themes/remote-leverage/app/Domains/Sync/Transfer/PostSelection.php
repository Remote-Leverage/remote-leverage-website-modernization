<?php

declare(strict_types=1);

namespace App\Domains\Sync\Transfer;

use App\Domains\Sync\Datasets\DatasetRegistry;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The wp_posts rows one dataset of a transfer covers.
 *
 * Shared deliberately by the exporting side (what to send) and the cleaning
 * side (what to delete before the incoming rows land), because those two have
 * to describe the same set. A clean that deleted a wider set than the export
 * replaces is rows destroyed with nothing to put them back — so there is one
 * definition of the set and both callers use it.
 *
 * Content and media partition wp_posts between them: media is exactly the
 * attachments, content is everything else. That way selecting both covers each
 * row once, and selecting content alone leaves attachments alone deliberately
 * rather than by accident.
 */
final class PostSelection
{
    /**
     * Never exported, and therefore never cleaned.
     *
     * Both are per-environment churn rather than content: an auto-draft is an
     * empty placeholder WordPress created on its own, and a revision belongs to
     * the post it was taken from rather than travelling in its own right.
     *
     * @var array<int, string>
     */
    public const ALWAYS_EXCLUDED_TYPES = ['revision', 'auto-draft'];

    /**
     * Rows in this dataset, on whichever environment this runs.
     */
    public static function query(TransferManifest $manifest, string $dataset): Builder
    {
        $query = DB::table('posts')->whereNotIn('post_type', self::ALWAYS_EXCLUDED_TYPES);

        // Settings are wp_options rows and own no posts at all. Without this the
        // "everything that is not an attachment" branch below claims them, and a
        // settings-only push silently ships the entire content set instead of
        // seven option keys — the opposite of what the dataset promises.
        if ($dataset === DatasetRegistry::SETTINGS) {
            return $query->whereRaw('1 = 0');
        }

        if ($dataset === DatasetRegistry::MEDIA) {
            $query->where('post_type', '=', 'attachment');
        } else {
            $query->where('post_type', '!=', 'attachment');

            if ($manifest->excludedPostTypes !== []) {
                $query->whereNotIn('post_type', $manifest->excludedPostTypes);
            }
        }

        // Exclusions bind the clean as much as the export. A post type or ID the
        // transfer was told to skip has nothing arriving to replace it, so
        // deleting it here would be a one-way loss rather than a mirror.
        if ($manifest->excludedPostIds !== []) {
            $query->whereNotIn('ID', $manifest->excludedPostIds);
        }

        return $query;
    }
}
