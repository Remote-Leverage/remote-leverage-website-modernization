<?php

use App\Support\BlockDefaults;

/**
 * Title: Admin VA Booking Footer - Book a free consultation
 * Slug: remote-leverage/admin-virtual-assistants-booking-footer
 * Categories: remote-leverage
 * Description: The purple booking band that closes the role pages, with the rating pill and hire checkpoints beside the wizard.
 *
 * Identical to remote-leverage/homepage-booking-footer except that the role comps put a Google
 * rating pill and the six hire checkpoints under the description, which the homepage comp has
 * none of. The block's `show_trust` toggle is off by default for exactly that reason, so this
 * page needs its own reference rather than sharing the homepage's.
 */

/*
 * Same six checkpoints as this page's hero, in the same single-column order the comp reads them
 * in. Deliberately not BlockDefaults::homeHeroChecklist(), whose order is interleaved to make
 * the homepage hero's two-column desktop grid agree with its mobile column.
 */
$checklist = array_map(fn ($item) => ['item' => $item], [
    'No Contracts, No Ongoing Fees',
    '12-Month Replacement Guarantee',
    'Hire Direct, No Middleman',
    'Interview Before You Hire',
    '30% Discount on Future Hires',
    'Interview in 48 Hours',
]);

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
