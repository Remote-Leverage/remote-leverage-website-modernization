<?php

namespace App\View;

use Walker_Nav_Menu;

/**
 * Mobile drawer nav. Renders both a real menu and `PrimaryNavigation::fallback()`.
 *
 * Mirrors production's mobile structure: one row per top-level item, hairline separated, with a
 * circled chevron on the rows that open. A top-level item with children becomes an accordion
 * rather than being flattened, which is what the fourteen role pages made necessary: flat, they
 * buried Pricing under fourteen rows.
 *
 * `<details>`/`<summary>` rather than a JS disclosure: it is keyboard and screen reader
 * accessible for free, and the drawer keeps its collapsed height until someone opens a section.
 * The shared `name` makes the sections an exclusive accordion with no JavaScript; browsers
 * without it simply allow several open.
 *
 * No `<li>`s: the header passes `items_wrap => '%3$s'`, so this emits the rows directly.
 */
class MobileNavWalker extends Walker_Nav_Menu
{
    private const ROW = 'flex w-full cursor-pointer items-center justify-between gap-3 border-b border-slate-200 py-4 text-base font-semibold text-slate-800 [&::-webkit-details-marker]:hidden';

    private const CHEVRON = '<span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full border border-slate-300 text-slate-700 transition-colors group-open:border-brand-purple group-open:text-brand-purple">'
        .'<svg class="h-4 w-4 transition-transform duration-200 group-open:rotate-90" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">'
        .'<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" /></svg></span>';

    /** Whether the top-level item being walked opened a `<details>` that end_el must close. */
    private bool $openSection = false;

    /**
     * Starts the list before the elements are added.
     */
    public function start_lvl(&$output, $depth = 0, $args = null): void
    {
        $output .= $depth === 0
            ? '<div class="flex flex-col border-b border-slate-200 pb-2 pl-3">'
            : '<div class="flex flex-col pl-3">';
    }

    /**
     * Ends the list of after the elements are added.
     */
    public function end_lvl(&$output, $depth = 0, $args = null): void
    {
        $output .= '</div>';
    }

    /**
     * Starts the element output.
     */
    public function start_el(&$output, $data_object, $depth = 0, $args = null, $current_object_id = 0): void
    {
        $label = ($args->link_before ?? '').apply_filters('the_title', $data_object->title, $data_object->ID).($args->link_after ?? '');

        if ($depth === 0 && $this->has_children) {
            $this->openSection = true;
            $output .= '<details name="rl-mobile-nav" class="group">'
                .'<summary class="'.self::ROW.' group-open:text-brand-purple"><span>'.$label.'</span>'.self::CHEVRON.'</summary>';

            return;
        }

        $atts = [
            'href' => esc_url(! empty($data_object->url) ? $data_object->url : '#'),
            'class' => $depth === 0
                ? 'block border-b border-slate-200 py-4 text-base font-semibold text-slate-800 hover:text-brand-purple'
                : 'block py-2.5 text-[15px] font-medium text-slate-700 hover:text-brand-purple',
            'title' => esc_attr($data_object->attr_title ?? ''),
            'target' => esc_attr($data_object->target ?? ''),
            'rel' => esc_attr($data_object->xfn ?? ''),
        ];

        $attributes = '';
        foreach ($atts as $attr => $value) {
            if ($value !== '') {
                $attributes .= " {$attr}=\"{$value}\"";
            }
        }

        $item_output = ($args->before ?? '')."<a{$attributes}>{$label}</a>".($args->after ?? '');

        $output .= apply_filters('walker_nav_menu_start_el', $item_output, $data_object, $depth, $args);
    }

    /**
     * Ends the element output.
     */
    public function end_el(&$output, $data_object, $depth = 0, $args = null): void
    {
        if ($depth === 0 && $this->openSection) {
            $this->openSection = false;
            $output .= '</details>';
        }
    }
}
