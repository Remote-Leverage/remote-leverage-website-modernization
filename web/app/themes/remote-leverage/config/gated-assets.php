<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Gated Download Registry
    |--------------------------------------------------------------------------
    |
    | Every file a visitor can earn by handing over their details, keyed by the
    | slug the form posts as `asset`. The browser never sends a URL — it sends a
    | slug, and GatedAssetResolver turns that slug into a URL from this list.
    | That is the whole security model of the endpoint: an unknown slug gets a
    | 404 and there is deliberately no path that serves a client-supplied path.
    |
    | `filename` is resolved against the media library by name rather than by a
    | hardcoded URL or attachment ID, because both are environment-specific —
    | the uploads year/month folder and the attachment ID differ between
    | production and every local install (production has this PDF under
    | 2026/07, a fresh local import lands it under whatever month it ran).
    | See App\Support\MediaLibrary.
    |
    | `url` is an optional hard override. Leave it empty in normal operation;
    | it exists so a file served from somewhere other than the media library
    | (a CDN, say) can still be gated, and so tests can pin a URL without a
    | database.
    |
    */

    'impact-report-2026' => [
        'title' => 'The Remote Leverage 2026 Impact Report',
        'filename' => 'Remote_Leverage-Impact_Report-2026-1.pdf',
        'url' => '',
    ],

];
