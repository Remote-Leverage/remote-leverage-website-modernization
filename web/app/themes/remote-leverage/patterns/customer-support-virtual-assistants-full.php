<?php

use App\Support\RolePages;

/**
 * Title: Full Page - Customer Support Virtual Assistants
 * Slug: remote-leverage/customer-support-virtual-assistants-full
 * Categories: remote-leverage
 * Description: The /customer-support-virtual-assistants/ role page. Hero and task cards come from the shared role map; everything below is the site-wide set.
 *
 * One of fourteen role pages that are the same page with different words. The hero and the
 * "World's Best ... Talent" band are rendered from App\Support\RolePages, so the layout exists
 * once; the "Why Hire" banner and the booking footer are shared patterns; and the comparison
 * table through the FAQ are the homepage's own, because the comps draw them identically.
 *
 * To change this page's copy, edit its entry in RolePages::all() — not this file.
 */
?>
<?= RolePages::renderHero('customer-support-virtual-assistants') ?>
<?= RolePages::renderTalent('customer-support-virtual-assistants') ?>
<!-- wp:pattern {"slug":"remote-leverage/role-why-hire"} /-->
<!-- wp:pattern {"slug":"remote-leverage/homepage-comparison-table"} /-->
<!-- wp:pattern {"slug":"remote-leverage/homepage-process"} /-->
<!-- wp:pattern {"slug":"remote-leverage/homepage-headache"} /-->
<!-- wp:pattern {"slug":"remote-leverage/homepage-guarantee"} /-->
<!-- wp:pattern {"slug":"remote-leverage/homepage-reviews"} /-->
<!-- wp:pattern {"slug":"remote-leverage/homepage-faq"} /-->
<!-- wp:pattern {"slug":"remote-leverage/role-booking-footer"} /-->