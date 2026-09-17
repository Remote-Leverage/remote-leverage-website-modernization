<?php

declare(strict_types=1);

namespace App\Domains\Lead\Import;

use Generator;
use RuntimeException;

/**
 * Streams a Gravity Forms entry export, one associative row at a time.
 *
 * Gravity's export is not quite ordinary CSV, and each difference has already cost something:
 *
 *  - **A UTF-8 BOM sits before the opening quote of the first header cell.** `fgetcsv()` sees a
 *    field that does not *start* with a quote, so it stops treating it as quoted and hands back
 *    `"Business Email"` with the quote marks still attached. Every lookup of the first column
 *    then misses, silently, and every lead imports with no email. The BOM is stripped from the
 *    raw bytes before the header is parsed, not after.
 *  - **Backslashes appear inside values** — Windows user agents and Google's `wbraid` among
 *    them. PHP's default `$escape` of `\` makes a trailing one swallow the field delimiter and
 *    merge two columns, shifting the rest of the row left. Passing an empty escape turns that
 *    off and matches what RFC 4180 (and Gravity) actually write.
 *  - **Values contain literal newlines** — the consent paragraph does. Line counting is
 *    therefore meaningless here; only a real parser knows where a record ends.
 *  - **Values are HTML-entity encoded.** Gravity escapes on the way out, so every `&` in a URL
 *    arrives as `&amp;` — 77,120 of them in a typical pair of exports. Stored unchanged, a
 *    landing URL is not the URL the visitor loaded: it does not resolve, and `utm_source` read
 *    back out of it reads as `amp;utm_source`. Decoded here rather than at each call site,
 *    because the encoding is a property of the file, not of any one column.
 *
 * Rows whose field count does not match the header are not guessed at. A short row means the
 * parse has already gone wrong somewhere upstream, and combining it against the header would
 * assign values to the wrong columns — writing a phone number into `utm_source` is worse than
 * skipping the row, because it looks like data. They are counted and reported instead.
 */
final class GravityCsvReader
{
    /**
     * @param  string  $path  An existing, readable CSV export.
     */
    public function __construct(private string $path)
    {
        if (! is_file($this->path) || ! is_readable($this->path)) {
            throw new RuntimeException("Gravity export not readable: {$this->path}");
        }
    }

    /**
     * The export's column headers, in file order.
     *
     * @return array<int, string>
     */
    public function headers(): array
    {
        $handle = $this->open();
        $headers = $this->readHeaders($handle);
        fclose($handle);

        return $headers;
    }

    /**
     * Every record in the file, keyed by header.
     *
     * @return Generator<int, array<string, string>>
     */
    public function rows(): Generator
    {
        $handle = $this->open();
        $headers = $this->readHeaders($handle);
        $width = count($headers);

        try {
            while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                // A trailing newline parses as a single empty field, not as end-of-file.
                if ($row === [null] || $row === ['']) {
                    continue;
                }

                if (count($row) !== $width) {
                    $this->malformed++;

                    continue;
                }

                yield array_combine($headers, array_map(
                    static fn ($value): string => html_entity_decode(
                        trim((string) $value),
                        ENT_QUOTES | ENT_HTML5,
                        'UTF-8',
                    ),
                    $row,
                ));
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * How many records were skipped for not matching the header width.
     */
    public function malformedCount(): int
    {
        return $this->malformed;
    }

    /**
     * A slug naming the form this file came from, taken from its filename.
     *
     * Gravity does not put the form name inside the export, and the two files in a typical
     * backfill are different forms whose entry ids interleave. The filename is the only thing
     * that distinguishes them, so it is recorded rather than inferred.
     */
    public function formSlug(): string
    {
        $name = pathinfo($this->path, PATHINFO_FILENAME);

        // Trailing export date carries no information once the entry dates are stored.
        return (string) preg_replace('/-\d{4}-\d{2}-\d{2}$/', '', $name);
    }

    private int $malformed = 0;

    /**
     * @return resource
     */
    private function open()
    {
        $handle = fopen($this->path, 'r');

        if ($handle === false) {
            throw new RuntimeException("Could not open Gravity export: {$this->path}");
        }

        return $handle;
    }

    /**
     * @param  resource  $handle
     * @return array<int, string>
     */
    private function readHeaders($handle): array
    {
        $headers = fgetcsv($handle, 0, ',', '"', '');

        if ($headers === false || $headers === [null]) {
            throw new RuntimeException("Gravity export has no header row: {$this->path}");
        }

        // See the class docblock: the BOM has to go before the quote is interpreted, and by this
        // point fgetcsv has already given up on the first cell. Strip both.
        $headers[0] = trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $headers[0]) ?? '', '"');

        return array_map(static fn ($header): string => trim((string) $header), $headers);
    }
}
