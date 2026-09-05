<?php

declare(strict_types=1);

namespace App\Infrastructure\WordPress\Hooks;

use App\Domains\Tracking\Subscribers\GravityFormsSubmissionSubscriber;

class GravityFormsHooks
{
    public function __construct(
        protected GravityFormsSubmissionSubscriber $subscriber
    ) {}

    /**
     * Register Gravity Forms integration hooks.
     * Ported from RLCustomerIO\Integrations\GravityForms.
     */
    public function register(): void
    {
        add_action('gform_after_submission', [$this, 'onFormSubmission'], 10, 2);
        add_filter('gform_confirmation', [$this, 'handleRedirectIdentification'], 10, 4);
    }

    /**
     * Intercept form submission and delegate to domain subscriber.
     */
    public function onFormSubmission(array $entry, array $form): void
    {
        $this->subscriber->handleSubmission($entry, $form);
    }

    /**
     * Append customer.io attribution query params or inline tracking script to confirmation.
     * Ported verbatim from RLCustomerIO\Integrations\GravityForms::handle_redirect_identification().
     */
    public function handleRedirectIdentification($confirmation, array $form, array $entry, bool $ajax)
    {
        $email = null;
        foreach ($form['fields'] as $field) {
            if ($field->type === 'email') {
                $email = $entry[$field->id] ?? null;
                break;
            }
        }

        if (! $email) {
            return $confirmation;
        }

        // Case 1: The confirmation is a Redirect URL
        if (is_array($confirmation) && isset($confirmation['redirect'])) {
            if (function_exists('add_query_arg')) {
                $confirmation['redirect'] = add_query_arg([
                    'cio_id' => urlencode((string) $email),
                    'cio_form' => urlencode((string) ($form['title'] ?? '')),
                    'cio_fid' => $form['id'] ?? '',
                ], $confirmation['redirect']);
            }
        }
        // Case 2: The confirmation is a Page / Text message
        elseif (is_string($confirmation)) {
            $escapedEmail = function_exists('esc_js') ? esc_js($email) : addslashes($email);
            $escapedFid = function_exists('esc_js') ? esc_js((string) $form['id']) : addslashes((string) $form['id']);
            $escapedTitle = function_exists('esc_js') ? esc_js((string) $form['title']) : addslashes((string) $form['title']);

            $script = <<<HTML
<script>
(function() {
    if (window.cioanalytics) {
        cioanalytics.identify('{$escapedEmail}', { email: '{$escapedEmail}' });
        cioanalytics.track('Form Submitted', { form_id: '{$escapedFid}', form_title: '{$escapedTitle}' });
    }
})();
</script>
HTML;
            $confirmation .= $script;
        }

        return $confirmation;
    }
}
