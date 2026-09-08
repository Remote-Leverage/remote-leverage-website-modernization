import intlTelInput from 'intl-tel-input/intlTelInputWithUtils';
window.intlTelInput = intlTelInput;

// Expose global placeholders so early callers don't throw
window.Sentry = window.Sentry || null;
window.posthog = window.posthog || null;

// Dynamically load Sentry only if DSN is configured
const sentryDsn = window.SENTRY_DSN || import.meta.env.VITE_SENTRY_DSN;
if (sentryDsn) {
  import('@sentry/browser').then((Sentry) => {
    window.Sentry = Sentry;
    Sentry.init({
      dsn: sentryDsn,
      environment: window.APP_ENV || 'production',
      tracesSampleRate: 0.1,
    });
  }).catch((err) => console.error('Sentry initialization failed:', err));
}

// Dynamically load PostHog only if API key is configured
const posthogKey = window.POSTHOG_API_KEY || import.meta.env.VITE_POSTHOG_API_KEY;
const posthogHost = window.POSTHOG_HOST || import.meta.env.VITE_POSTHOG_HOST || 'https://us.i.posthog.com';

if (posthogKey) {
  import('posthog-js').then(({ default: posthog }) => {
    window.posthog = posthog;
    posthog.init(posthogKey, {
      api_host: posthogHost,
      person_profiles: 'identified_only',
      capture_pageview: true,
    });
  }).catch((err) => console.error('PostHog initialization failed:', err));
}
