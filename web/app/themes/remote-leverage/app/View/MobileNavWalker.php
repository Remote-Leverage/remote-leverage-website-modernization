<?php

namespace App\View;

use Walker_Nav_Menu;

class MobileNavWalker extends Walker_Nav_Menu
{
    /**
     * Starts the list before the elements are added.
     */
    public function start_lvl(&$output, $depth = 0, $args = null): void
    {
        $indent = str_repeat("\t", $depth);
        $output .= "\n{$indent}<ul class=\"pl-4 py-1 space-y-1 border-l-2 border-slate-100 ml-3 my-1\" x-show=\"mobileSubOpen\" x-collapse style=\"display: none;\">\n";
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
        $has_children = ! empty($args->walker->has_children);

        $li_classes = ['menu-item'];
        $alpine_attr = '';

        if ($depth === 0 && $has_children) {
            $alpine_attr = ' x-data="{ mobileSubOpen: false }"';
        }

        $class_names = implode(' ', array_filter(array_unique(array_merge($classes, $li_classes))));
        $output .= "{$indent}<li class=\"{$class_names}\"{$alpine_attr}>\n";

        $atts = [];
        $atts['title'] = ! empty($data_object->attr_title) ? $data_object->attr_title : '';
        $atts['target'] = ! empty($data_object->target) ? $data_object->target : '';
        $atts['rel'] = ! empty($data_object->xfn) ? $data_object->xfn : '';
        $atts['href'] = ! empty($data_object->url) ? $data_object->url : '#';

        if ($depth === 0) {
            $atts['class'] = 'flex items-center justify-between px-3 py-2.5 rounded-xl text-base font-semibold text-slate-800 hover:bg-slate-100 hover:text-brand-purple transition-colors';
            if ($has_children) {
                $atts['@click'] = 'mobileSubOpen = !mobileSubOpen';
            }
        } else {
            $atts['class'] = 'block px-3 py-2 rounded-lg text-sm font-medium text-slate-600 hover:text-brand-purple hover:bg-slate-50 transition-colors';
        }

        $attributes = '';
        foreach ($atts as $attr => $value) {
            if (! empty($value)) {
                $value = ('href' === $attr) ? esc_url($value) : esc_attr($value);
                $attributes .= " {$attr}=\"{$value}\"";
            }
        }

        $title = apply_filters('the_title', $data_object->title, $data_object->ID);

        $item_output = $args->before ?? '';
        $item_output .= "<a{$attributes}>";
        $item_output .= '<span>' . ($args->link_before ?? '') . $title . ($args->link_after ?? '') . '</span>';

        if ($depth === 0 && $has_children) {
            $item_output .= ' <svg class="w-4 h-4 text-slate-500 transition-transform duration-200" :class="{ \'rotate-180\': mobileSubOpen }" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>';
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
