<?php

namespace App\View;

use Walker_Nav_Menu;

class NavWalker extends Walker_Nav_Menu
{
    /**
     * Starts the list before the elements are added.
     */
    public function start_lvl(&$output, $depth = 0, $args = null): void
    {
        $indent = str_repeat("\t", $depth);
        $classes = 'sub-menu absolute left-0 top-full mt-2 min-w-[280px] bg-white rounded-2xl p-2.5 shadow-xl border border-slate-100 z-50 flex flex-col gap-1 transition-all duration-150';

        if ($depth > 0) {
            $classes = 'sub-menu absolute left-full top-0 ml-1 min-w-[260px] bg-white rounded-2xl p-2.5 shadow-xl border border-slate-100 z-50 flex flex-col gap-1';
        }

        $output .= "\n{$indent}<ul class=\"{$classes}\" x-show=\"open\" x-transition:enter=\"transition ease-out duration-150\" x-transition:enter-start=\"opacity-0 translate-y-1\" x-transition:enter-end=\"opacity-100 translate-y-0\" x-transition:leave=\"transition ease-in duration-100\" x-transition:leave-start=\"opacity-100 translate-y-0\" x-transition:leave-end=\"opacity-0 translate-y-1\" style=\"display: none;\">\n";
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

        if ($depth === 0) {
            $li_classes[] = 'relative';
            $li_classes[] = 'group';
            $alpine_attr = $has_children ? ' x-data="{ open: false }" @mouseenter="open = true" @mouseleave="open = false" @click.outside="open = false"' : '';
        } else {
            $li_classes[] = 'relative';
            $alpine_attr = '';
        }

        $class_names = implode(' ', array_filter(array_unique(array_merge($classes, $li_classes))));
        $output .= "{$indent}<li class=\"{$class_names}\"{$alpine_attr}>\n";

        $atts = [];
        $atts['title'] = ! empty($data_object->attr_title) ? $data_object->attr_title : '';
        $atts['target'] = ! empty($data_object->target) ? $data_object->target : '';
        $atts['rel'] = ! empty($data_object->xfn) ? $data_object->xfn : '';
        $atts['href'] = ! empty($data_object->url) ? $data_object->url : '#';

        if ($depth === 0) {
            $atts['class'] = 'text-[17px] font-display font-medium text-slate-900 hover:text-brand-purple transition-colors flex items-center gap-1.5 py-2 cursor-pointer focus:outline-none';
            if ($has_children) {
                $atts['@click'] = 'open = !open';
            }
        } else {
            $atts['class'] = 'block px-4 py-2.5 text-sm font-medium text-slate-800 hover:text-brand-purple hover:bg-slate-50 rounded-xl transition-colors';
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
        $item_output .= ($args->link_before ?? '') . $title . ($args->link_after ?? '');

        if ($depth === 0 && $has_children) {
            $item_output .= ' <svg class="w-4 h-4 text-slate-700 group-hover:text-brand-purple transition-transform duration-200" :class="{ \'rotate-180\': open }" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" /></svg>';
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
