<?php

use App\Support\BlockDefaults;

/**
 * Title: Homepage Roles - The Roles That Buy Back Your Time
 * Slug: remote-leverage/homepage-roles
 * Categories: remote-leverage
 * Description: The eight-role mosaic, with Sales (SDR) on the tall photo card.
 */

/*
 * Slots 0 and 2 are swapped against BlockDefaults::rolesGridCards(): the comp puts Sales (SDR)
 * on the tall purple photo card and moves Administrative down to the blue horizontal card in
 * column two. Every other slot is the preset.
 *
 * The desktop and mobile comps disagree here — Page_v1.2.png keeps Administrative on the photo
 * card and labels the other card "Sales (BDR)". Desktop wins, per direction on 2026-09-15 and
 * the CLAUDE.md default for exactly this situation; SDR is the wording that ships.
 *
 * The photo stays with the slot, not with the role: the woman's cutout is the tall card's
 * artwork in both comps. Administrative's new card takes a checklist illustration, which the
 * theme did not have — resources/images/pages/home/roles/admin-checklist.svg is drawn to the
 * comp rather than borrowed from Frame-1092.png, which the Lead Generation card directly above
 * it already uses.
 */
$cards = BlockDefaults::rolesGridCards();

[$cards[0], $cards[2]] = [
    [
        'title' => 'Sales (SDR)',
        'desc' => $cards[2]['desc'],
        'img' => $cards[0]['img'],
    ],
    [
        'title' => 'Administrative',
        'desc' => $cards[0]['desc'],
        'img' => BlockDefaults::pageImg('home', 'roles/admin-checklist.svg'),
    ],
];
?>
<?= BlockDefaults::renderRolesGrid([
    'cta_text' => 'BOOK A CONSULTATION',
    '_cta_text' => 'field_roles_grid_block_cta_text',
    'cta_style' => 'pill',
    '_cta_style' => 'field_roles_grid_block_cta_style',
], $cards) ?>
