<?php

namespace App\View;

use Walker_Nav_Menu;

/**
 * Desktop primary nav. Renders both a real menu and `PrimaryNavigation::fallback()`, so the
 * markup here is the nav's markup whichever one is showing.
 */
class NavWalker extends Walker_Nav_Menu
{
    /**
     * A dropdown with more items than this scrolls and is widened, so a long list such as
     * Roles stays on screen however many pages are added to it in Appearance > Menus.
     */
    private const LONG_LIST = 6;

    /** Whether the top-level item being walked opens a long dropdown. */
    private bool $longList = false;

    public function display_element($element, &$children_elements, $max_depth, $depth, $args, &$output): void
    {
        if ($element && $depth === 0) {
            $id = $element->{$this->db_fields['id']};
            $this->longList = count($children_elements[$id] ?? []) > self::LONG_LIST;
        }

        parent::display_element($element, $children_elements, $max_depth, $depth, $args, $output);
    }

    /**
     * Starts the list before the elements are added.
     */
    public function start_lvl(&$output, $depth = 0, $args = null): void
    {
        $indent = str_repeat("\t", $depth);
        $classes = $this->longList
            ? 'sub-menu absolute left-0 top-full mt-2 min-w-[320px] bg-white rounded-2xl p-2.5 shadow-xl border border-slate-100 z-50 flex-col gap-1 max-h-[min(72vh,560px)] overflow-y-auto'
            : 'sub-menu absolute left-0 top-full mt-2 min-w-[280px] bg-white rounded-2xl p-2.5 shadow-xl border border-slate-100 z-50 flex-col gap-1';

        if ($depth > 0) {
            $classes = 'sub-menu absolute left-full top-0 ml-1 min-w-[260px] bg-white rounded-2xl p-2.5 shadow-xl border border-slate-100 z-50 flex-col gap-1';
        }

        $output .= "\n{$indent}<ul class=\"{$classes}\">\n";
    }

    /**
     * Ends the list of after the elements are added.
     */
    public function end_lvl(&$output, $depth = 0, $args = null): void
    {
        $indent = str_repeat("\t", $depth);
        $output .= "{$indent}</ul>\n";
    }

    /**
     * Starts the element output.
     */
    public function start_el(&$output, $data_object, $depth = 0, $args = null, $current_object_id = 0): void
    {
        $indent = ($depth) ? str_repeat("\t", $depth) : '';
        $classes = empty($data_object->classes) ? [] : (array) $data_object->classes;
        $has_children = $this->has_children;

        // `group` is what app.css opens the dropdown on, so only an item with one carries it.
        $li_classes = $has_children ? ['menu-item', 'relative', 'group'] : [];

        $class_names = implode(' ', array_filter(array_unique(array_merge($classes, $li_classes))));
        $output .= $class_names === '' ? "{$indent}<li>\n" : "{$indent}<li class=\"".esc_attr($class_names)."\">\n";

        $atts = [];
        $atts['title'] = ! empty($data_object->attr_title) ? $data_object->attr_title : '';
        $atts['target'] = ! empty($data_object->target) ? $data_object->target : '';
        $atts['rel'] = ! empty($data_object->xfn) ? $data_object->xfn : '';
        $atts['href'] = ! empty($data_object->url) ? $data_object->url : '#';

        if ($depth === 0 && $has_children) {
            $atts['class'] = 'flex items-center gap-1.5 py-2 hover:text-brand-purple transition-colors cursor-pointer focus:outline-none';
        } elseif ($depth === 0) {
            $atts['class'] = 'py-2 hover:text-brand-purple transition-colors';
        } else {
            $atts['class'] = $this->longList
                ? 'block px-4 py-2 text-sm font-medium text-slate-800 hover:text-brand-purple hover:bg-slate-50 rounded-xl transition-colors'
                : 'block px-4 py-2.5 text-sm font-medium text-slate-800 hover:text-brand-purple hover:bg-slate-50 rounded-xl transition-colors';
        }

        $attributes = '';
        foreach ($atts as $attr => $value) {
            if (! empty($value)) {
                $value = ($attr === 'href') ? esc_url($value) : esc_attr($value);
                $attributes .= " {$attr}=\"{$value}\"";
            }
        }

        $title = apply_filters('the_title', $data_object->title, $data_object->ID);

        $item_output = $args->before ?? '';
        $item_output .= "<a{$attributes}>";

        if ($depth === 0 && $has_children) {
            $item_output .= '<span>'.($args->link_before ?? '').$title.($args->link_after ?? '').'</span>';
            $item_output .= '<svg class="w-4 h-4 text-slate-700 group-hover:text-brand-purple transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" /></svg>';
        } else {
            $item_output .= ($args->link_before ?? '').$title.($args->link_after ?? '');
        }

        $item_output .= '</a>';
        $item_output .= $args->after ?? '';

        $output .= apply_filters('walker_nav_menu_start_el', $item_output, $data_object, $depth, $args);
    }

    /**
     * Ends the element output.
     */
    public function end_el(&$output, $data_object, $depth = 0, $args = null): void
    {
        $output .= "</li>\n";
    }
}
