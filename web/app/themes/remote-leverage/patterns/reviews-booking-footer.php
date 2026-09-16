<?php

/**
 * Title: Reviews - Book a free consultation
 * Slug: remote-leverage/reviews-booking-footer
 * Categories: remote-leverage
 * Description: Closing consultation CTA for /reviews/, matching production's "Book a free consultation" heading rather than the shared "Ready to scale your global team?" band.
 */

/*
 * Production's /reviews/ closes with "Book a free consultation", not the
 * "Ready to scale your global team?" heading the shared booking-footer pattern
 * hardcodes (verified against production 2026-09-16). This reuses the existing
 * acf/booking-footer block with the production copy rather than forking markup;
 * the shared pattern is left alone because /about-us/ and /vapricing/ also
 * include it and production shows neither string on those pages.
 */
?>
<!-- wp:acf/booking-footer {"name":"acf/booking-footer","data":{"skin":"light","_skin":"field_booking_footer_skin","headline":"Book a free consultation","_headline":"field_booking_footer_headline","description":"During this meeting we will go over the role you're planning to hire for, what the process looks like, answer any questions you have, and proceed to next steps.","_description":"field_booking_footer_description"},"align":"full","mode":"preview"} /-->
