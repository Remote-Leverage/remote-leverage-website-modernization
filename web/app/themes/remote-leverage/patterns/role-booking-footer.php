<?php

use App\Support\BlockDefaults;
use App\Support\RolePages;

/**
 * Title: Role Pages Booking Footer - Book a free consultation
 * Slug: remote-leverage/role-booking-footer
 * Categories: remote-leverage
 * Description: The purple booking band that closes the role pages, with the rating pill and hire checkpoints beside the wizard. Shared by all fourteen.
 *
 * Identical to remote-leverage/homepage-booking-footer except that the role comps put a Google
 * rating pill and the six hire checkpoints under the description, which the homepage comp has
 * none of. The block's `show_trust` toggle is off by default for exactly that reason, so this
 * page needs its own reference rather than sharing the homepage's.
 */

/*
 * The same six checkpoints the role hero carries, from the same helper, so the two can never
 * disagree. Deliberately not BlockDefaults::homeHeroChecklist(), whose order is interleaved to
 * make the homepage hero's two-column desktop grid agree with its mobile column.
 */
$checklist = array_map(fn ($item) => ['item' => $item], RolePages::checklist());

$data = BlockDefaults::withFieldKeys('booking_footer', [
    'background' => 'gradient',
    'skin' => 'light',
    'show_trust' => 1,
    'rating_score' => '4.8',
    'form_title' => 'Book a Free 15-Minute Consultation',
    'form_button_text' => 'BOOK FREE CALL',
    'headline' => 'Book a free consultation',
    'description' => "During this meeting we will go over the role you're planning to hire for, what the process looks like, answer any questions you have, and proceed to next steps.",
]);
?>
<?= BlockDefaults::renderBlockWithRepeater('booking-footer', 'checklist', 'field_booking_footer_checklist', $checklist, $data, ['align' => 'full']) ?>
