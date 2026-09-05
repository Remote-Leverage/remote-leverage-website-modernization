<?php

declare(strict_types=1);

namespace App\Domains\Referral\Listeners;

use App\Domains\Referral\Events\PartnerRegistered;
use Illuminate\Support\Facades\Log;

class SendPartnerWelcomeEmail
{
    public function handle(PartnerRegistered $event): void
    {
        $partner = $event->partner;

        Log::info('Partner welcome email triggered', [
            'partner_id' => $partner->id,
            'email' => $partner->email,
            'referral_code' => $partner->referral_code,
        ]);

        // WordPress wp_mail or Acorn Mailer
        $subject = 'Welcome to the Remote Leverage Partner Program!';
        $message = "Hi {$partner->name},\n\nWelcome aboard! Your unique referral link is:\n"
            .home_url('/?ref='.$partner->referral_code)."\n\nBest regards,\nRemote Leverage Team";

        wp_mail($partner->email, $subject, $message);
    }
}
