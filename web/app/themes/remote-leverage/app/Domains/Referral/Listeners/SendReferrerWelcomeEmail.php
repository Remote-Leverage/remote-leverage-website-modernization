<?php

declare(strict_types=1);

namespace App\Domains\Referral\Listeners;

use App\Domains\Referral\Events\ReferrerRegistered;
use Illuminate\Support\Facades\Log;

class SendReferrerWelcomeEmail
{
    public function handle(ReferrerRegistered $event): void
    {
        $referrer = $event->referrer;

        Log::info('Referrer welcome email triggered', [
            'referrer_id' => $referrer->id,
            'email' => $referrer->email,
            'referral_code' => $referrer->referral_code,
        ]);

        // WordPress wp_mail or Acorn Mailer
        $subject = 'Welcome to the Remote Leverage Referral Program!';
        $message = "Hi {$referrer->name},\n\nWelcome aboard! Your unique referral link is:\n"
            .home_url('/?ref='.$referrer->referral_code)."\n\nBest regards,\nRemote Leverage Team";

        wp_mail($referrer->email, $subject, $message);
    }
}
