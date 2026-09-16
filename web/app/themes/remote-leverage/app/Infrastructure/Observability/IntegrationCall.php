<?php

declare(strict_types=1);

namespace App\Infrastructure\Observability;

use App\Domains\Lead\Models\Lead;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One outbound call to a third-party integration, request and response.
 *
 * @property string $integration
 * @property string $method
 * @property string $url
 * @property int|null $status_code
 * @property string $outcome
 */
class IntegrationCall extends Model
{
    public $timestamps = false;

    protected $table = 'rl_integration_calls';

    protected $fillable = [
        'lead_id', 'integration', 'operation', 'method', 'url', 'credential_label',
        'request_headers', 'request_body',
        'status_code', 'response_headers', 'response_body',
        'duration_ms', 'outcome', 'error_message', 'created_at',
    ];

    protected $casts = [
        'request_headers' => 'array',
        'response_headers' => 'array',
        'status_code' => 'integer',
        'duration_ms' => 'integer',
        'created_at' => 'datetime',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }

    public function scopeForIntegration(Builder $query, string $integration): Builder
    {
        return $query->where('integration', $integration);
    }

    /**
     * Calls worth looking at first when something is wrong.
     */
    public function scopeProblems(Builder $query): Builder
    {
        return $query->whereIn('outcome', ['failed', 'error']);
    }

    /**
     * Pretty-print a recorded body for display.
     *
     * Bodies are stored exactly as sent or received, because a diagnosis often turns on
     * formatting — a field sent as `"true"` rather than `true` is precisely the kind of thing
     * that makes HubSpot reject a whole contact. Prettifying happens at read time so the stored
     * copy stays faithful.
     */
    public function prettyBody(?string $body): string
    {
        $body = (string) $body;

        if ($body === '') {
            return '';
        }

        $decoded = json_decode($body, true);

        if (json_last_error() === JSON_ERROR_NONE && (is_array($decoded) || is_object($decoded))) {
            return (string) json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        return $body;
    }
}
