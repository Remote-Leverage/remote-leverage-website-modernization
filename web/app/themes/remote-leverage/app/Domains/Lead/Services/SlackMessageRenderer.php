<?php

declare(strict_types=1);

namespace App\Domains\Lead\Services;

/**
 * Renders a Block Kit template from `config/slack-notifications.php` against a value map.
 *
 * Exists so the message layout can be designed rather than coded — built in Slack's Block Kit
 * Builder, pasted into the config, and iterated on without touching a listener or its tests.
 *
 * Two rules, both there because the alternative shows up in a channel people watch all day:
 *
 *  - **An unresolved placeholder renders empty, never as `{{ key }}`.** A template referencing a
 *    value a given lead does not have is normal, not a bug, and the literal token appearing in a
 *    sales channel would look broken.
 *  - **`_when` drops a block or field whose values are all empty.** Without it a lead with no
 *    campaign data renders a grid of dashes, and a message that is mostly empty fields trains
 *    people to stop reading it.
 */
class SlackMessageRenderer
{
    /**
     * Block properties that Slack treats as optional, and that are therefore safe to drop when
     * their placeholder resolves to nothing. See renderBlock().
     */
    public const OPTIONAL_TEXT_KEYS = ['subtitle', 'description'];

    /**
     * Render one named template.
     *
     * @param  array<string, string>  $values
     * @return array{text: string, blocks: array<int, array<string, mixed>>, color: ?string}
     */
    public function render(string $template, array $values): array
    {
        $config = config("slack-notifications.{$template}", []);

        if (! is_array($config)) {
            return ['text' => '', 'blocks' => [], 'color' => null];
        }

        $text = $this->substitute((string) ($config['fallback'] ?? ''), $values);

        $blocks = [];

        foreach ((array) ($config['blocks'] ?? []) as $block) {
            $rendered = $this->renderBlock(is_array($block) ? $block : [], $values);

            if ($rendered !== null) {
                $blocks[] = $rendered;
            }
        }

        return [
            // Collapse the blank lines an omitted value leaves behind in the fallback.
            'text' => trim((string) preg_replace("/\n{2,}/", "\n", $text)),
            'blocks' => $blocks,

            // Drives the attachment bar that boxes the message; null leaves it unboxed.
            'color' => ($config['color'] ?? null) ? (string) $config['color'] : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $block
     * @param  array<string, string>  $values
     * @return array<string, mixed>|null
     */
    protected function renderBlock(array $block, array $values): ?array
    {
        if (! $this->passes($block, $values)) {
            return null;
        }

        unset($block['_when']);

        /*
         * An accessory is a single element, not a collection, and it fails differently: a
         * button whose `url` resolved to empty is rejected by Slack outright, and a leftover
         * `_when` key is an unknown property it also rejects. So it is dropped whole, leaving
         * the section it decorated intact.
         */
        if (isset($block['accessory']) && is_array($block['accessory'])) {
            if ($this->passes($block['accessory'], $values)) {
                $accessory = $block['accessory'];
                unset($accessory['_when']);
                $block['accessory'] = $this->substituteDeep($accessory, $values);
            } else {
                unset($block['accessory']);
            }
        }

        /*
         * Optional text objects that resolved to nothing.
         *
         * Slack rejects the ENTIRE message with `invalid_blocks` when a text object carries an
         * empty string — not the one block, the whole post — so a single unbound placeholder
         * silently costs the notification. That is what happened to every referrer registration
         * (`referrer_registered` bound a card subtitle to `company`, which the public form never
         * collects): the row was created, the event fired, the listener ran, Slack answered
         * `ok:false`, and nobody heard anything.
         *
         * `_when` cannot cover this — it prunes whole blocks, the accessory, and items inside
         * `fields`/`elements`, and a card's own title/subtitle/body are none of those. Only
         * genuinely optional keys are pruned here; a section's required `text` is left alone,
         * because dropping that would produce a different invalid block rather than a valid one.
         */
        foreach (self::OPTIONAL_TEXT_KEYS as $key) {
            if (! isset($block[$key]) || ! is_array($block[$key]) || ! isset($block[$key]['text'])) {
                continue;
            }

            if (trim($this->substitute((string) $block[$key]['text'], $values)) === '') {
                unset($block[$key]);
            }
        }

        /*
         * A carousel's cards arrive under `elements`, the same key an actions block uses, so they
         * prune here without anything being added: a carousel whose every card dropped out has
         * nothing to scroll through, and Slack rejects an empty one outright — which costs the
         * entire message, not the one block.
         */
        foreach (['fields', 'elements'] as $collection) {
            if (! isset($block[$collection]) || ! is_array($block[$collection])) {
                continue;
            }

            $kept = [];

            foreach ($block[$collection] as $item) {
                if (! is_array($item) || ! $this->passes($item, $values)) {
                    continue;
                }

                unset($item['_when']);
                $kept[] = $this->substituteDeep($item, $values);
            }

            // A section whose fields all dropped out has nothing left to say; emitting it
            // would render as a stray blank row.
            if ($kept === []) {
                return null;
            }

            // Slack renders at most 10 fields in one section.
            $block[$collection] = $collection === 'fields' ? array_slice($kept, 0, 10) : $kept;
        }

        $block = $this->substituteDeep($block, $values);

        /*
         * Last line of defence: never emit a block still carrying an empty text object.
         *
         * Slack refuses the WHOLE message with `invalid_blocks` over one empty `text`, so the
         * cost of a stray unbound placeholder is the entire notification rather than the block
         * that used it. Pruning the optional keys above is not enough on its own — a card's
         * `title` and `body` are required, and an empty one there is still fatal.
         *
         * Dropping the block is the conservative trade: losing one card is visibly worse than
         * a complete card and enormously better than silence, which is what this cost before —
         * every referrer registration went unannounced for as long as the feature existed.
         */
        if ($this->hasEmptyTextObject($block)) {
            return null;
        }

        return $block;
    }

    /**
     * Does any text object anywhere in this block carry an empty string?
     *
     * @param  array<string, mixed>  $node
     */
    protected function hasEmptyTextObject(array $node): bool
    {
        if (isset($node['type'], $node['text'])
            && in_array($node['type'], ['mrkdwn', 'plain_text'], true)
            && is_string($node['text'])
            && trim($node['text']) === ''
        ) {
            return true;
        }

        foreach ($node as $value) {
            if (is_array($value) && $this->hasEmptyTextObject($value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Does every placeholder named in `_when` resolve to something?
     *
     * @param  array<string, mixed>  $node
     * @param  array<string, string>  $values
     */
    protected function passes(array $node, array $values): bool
    {
        foreach ((array) ($node['_when'] ?? []) as $key) {
            if (trim((string) ($values[$key] ?? '')) === '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string|int, mixed>  $node
     * @param  array<string, string>  $values
     * @return array<string|int, mixed>
     */
    protected function substituteDeep(array $node, array $values): array
    {
        foreach ($node as $key => $value) {
            if (is_array($value)) {
                $node[$key] = $this->substituteDeep($value, $values);
            } elseif (is_string($value)) {
                $node[$key] = $this->substitute($value, $values);
            }
        }

        return $node;
    }

    /**
     * @param  array<string, string>  $values
     */
    protected function substitute(string $subject, array $values): string
    {
        return (string) preg_replace_callback(
            '/\{\{\s*([a-z0-9_]+)\s*\}\}/i',
            static fn (array $m) => (string) ($values[$m[1]] ?? ''),
            $subject,
        );
    }
}
