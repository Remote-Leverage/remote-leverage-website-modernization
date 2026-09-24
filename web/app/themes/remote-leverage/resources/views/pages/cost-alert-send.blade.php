{{--
  Where the cost alert card's "Send new alert" button lands. See CostAlertSendController.

  Standalone rather than extending layouts.app: this is an internal status line, and the site
  layout would load the header, the footer and every tracking pixel to show it — a pageview on
  a page no visitor ever reaches.

  The GET renders this and posts nothing. The script below POSTs the signed values back, which
  is what sends the card; a link preview or a scanner fetching the URL does not run it.
--}}
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <meta name="referrer" content="no-referrer">
  <title>Send cost alert</title>
  @verbatim
  <style>
    :root { --bg: #f6f7f9; --card: #ffffff; --ink: #111827; --muted: #4b5563; --line: #e5e7eb; --ok: #15803d; --bad: #b91c1c; --accent: #2271b1; }
    @media (prefers-color-scheme: dark) {
      :root { --bg: #0f1115; --card: #181b21; --ink: #f3f4f6; --muted: #9ca3af; --line: #2a2f38; --ok: #4ade80; --bad: #f87171; --accent: #60a5fa; }
    }
    * { box-sizing: border-box; }
    body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 16px; background: var(--bg); color: var(--ink); font: 16px/1.5 system-ui, -apple-system, "Segoe UI", sans-serif; }
    main { width: 100%; max-width: 480px; background: var(--card); border: 1px solid var(--line); border-radius: 12px; padding: 28px; }
    h1 { margin: 0 0 8px; font-size: 20px; line-height: 1.3; }
    p { margin: 0 0 12px; color: var(--muted); }
    .status { color: var(--ink); font-weight: 600; }
    .status.ok { color: var(--ok); }
    .status.bad { color: var(--bad); }
    .bar { height: 6px; border-radius: 3px; background: var(--line); overflow: hidden; margin: 16px 0; }
    .bar span { display: block; height: 100%; width: 35%; border-radius: 3px; background: var(--accent); animation: slide 1.2s ease-in-out infinite; }
    .bar[hidden] { display: none; }
    a { color: var(--accent); }
    @keyframes slide { from { transform: translateX(-100%); } to { transform: translateX(300%); } }
  </style>
  @endverbatim
</head>
<body>
  <main
    id="rl-cost-alert-send"
    data-endpoint="{{ $endpoint }}"
    data-expires="{{ $expires }}"
    data-sig="{{ $sig }}"
    data-ready="{{ $problem === null ? '1' : '0' }}"
  >
    <h1>Marketing cost alert</h1>

    @if ($problem === null)
      <p class="status" id="rl-status">Posting a fresh card to Slack.</p>
      <div class="bar" id="rl-bar"><span></span></div>
      <p id="rl-note">This usually takes 10 to 20 seconds. Keep this tab open until it finishes. The card carries the figures as of now, whichever card the button was on.</p>
      <noscript>
        <p class="status bad">This page needs JavaScript to post the card. Use Send to Slack now on the dashboard instead.</p>
      </noscript>
    @else
      <p class="status bad">{{ $message }}</p>
    @endif

    @if ($dashboardUrl !== '')
      <p><a href="{{ $dashboardUrl }}">Open the dashboard</a></p>
    @endif
  </main>

  @verbatim
  <script>
    (function () {
      var root = document.getElementById('rl-cost-alert-send');

      if (!root || root.dataset.ready !== '1') {
        return;
      }

      var status = document.getElementById('rl-status');
      var bar = document.getElementById('rl-bar');
      var note = document.getElementById('rl-note');

      function finish(message, ok) {
        status.textContent = message;
        status.className = 'status ' + (ok ? 'ok' : 'bad');
        bar.hidden = true;
        note.textContent = ok ? 'You can close this tab.' : 'Nothing new is in the channel from this press.';
      }

      fetch(root.dataset.endpoint, {
        method: 'POST',
        credentials: 'omit',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ expires: root.dataset.expires, sig: root.dataset.sig })
      }).then(function (response) {
        return response.json().catch(function () {
          return { sent: false, message: 'The site answered with something unexpected (HTTP ' + response.status + ').' };
        });
      }).then(function (result) {
        finish(result.message || (result.sent ? 'Posted.' : 'Nothing was sent.'), result.sent === true);
      }).catch(function () {
        finish('Could not reach the site. Check your connection and press the button again.', false);
      });
    })();
  </script>
  @endverbatim
</body>
</html>
