<?php

/**
 * Ported verbatim from production /vastore5/ (page 10809): the two self-contained
 * Elementor HTML widgets that page carries.
 *
 *  1. #timerWidget  — a draggable call stopwatch, fixed bottom-right. Shared with
 *     /store/ since 2026-09-15 and now lives in resources/patterns/call-timer-widget.php;
 *     it is included below so both playbooks run one copy.
 *  2. <job-description-widget> — a shadow-DOM custom element that POSTs to the
 *     REST route /wp-json/jobwidget/v1/chat to draft a job description.
 *
 *     That route was never a plugin: it lived in the legacy hello-theme-child's
 *     functions.php, which cutover deleted along with the theme, so from the v2
 *     launch until 2026-09-21 every call 404'd. The widget never showed an error
 *     for it — `rest_no_route` answers with a *JSON* body, which parses fine, so
 *     the catch block never ran and the page reported "(No output received)"
 *     instead. A misleading symptom, not a second bug.
 *
 *     The route now exists in this theme (App\Domains\Tools, config/job-widget.php),
 *     behind a nonce, an origin check, a model allowlist and a per-IP rate limit —
 *     the legacy one had `permission_callback => '__return_true'` and a hardcoded
 *     key. It is inert on any environment without OPENAI_API_KEY, which registers
 *     no route and 404s exactly as before.
 *
 * 2026-09-15 redesign: visual parity with production was dropped by client direction, so
 * both blobs are now skinned with the theme's own @theme custom properties (Tailwind emits
 * them to :root under `theme(static)`, and custom properties inherit through a shadow
 * boundary, so the generator picks them up inside its shadow root). Every element id, class,
 * event listener, fetch call and parsing rule is untouched — this is presentation only.
 *
 * They keep their own <style> blocks rather than becoming Tailwind utilities because the
 * generator's CSS lives inside a shadow root, which Tailwind cannot reach at all, and the
 * timer is a fixed-position widget with drag/collapse state selectors.
 *
 * Not a block pattern: it has no pattern header and lives outside patterns/ so
 * WordPress does not try to register it.
 */
?>
<?php include get_theme_file_path('resources/patterns/call-timer-widget.php'); ?>

<!-- vastore5: job description + traits generator (production <job-description-widget>) -->
<?php
/*
 * The `wp_rest` nonce the generator sends as X-WP-Nonce. Minted per render rather than
 * enqueued, because this widget is inline markup inside a pattern and has no script handle to
 * localise onto.
 *
 * Safe under the 60s nginx HTML cache: a nonce is valid for 12h, and logged-in requests bypass
 * that cache ($skip_cache_cookie), so a cached page cannot hand a logged-in user a nonce minted
 * for uid 0. The widget still works without it if that ever stops being true — the route
 * answers 403 with a message the widget now renders, rather than failing silently.
 */
?>
<script>window.rlJobWidgetNonce = <?php echo wp_json_encode(wp_create_nonce('wp_rest')); ?>;</script>
<script>
class JobDescriptionWidget extends HTMLElement {
      constructor() {
        super();
        this.attachShadow({ mode: 'open' });
        this.shadowRoot.innerHTML = `
          <style>
            /* Skinned from the theme's :root tokens — custom properties inherit through the
               shadow boundary, so --color-brand-* / --radius-card / --font-sans resolve here.
               Literals are the token values, used only if app.css is ever absent. */
            :host{
              --rl-blue: var(--color-brand-purple, #8A2BE2);
              --rl-purple: var(--color-brand-purple, #8A2BE2);
              --rl-purple-dark: var(--color-brand-purple-deep, #6200A4);
              --rl-lavender: var(--color-lavender-surface, #FBF6FF);
              --rl-text: var(--color-brand-midnight, #18112C);
              --rl-muted:#6F6B85;
              --rl-border: rgba(24,17,44,.10);
              --rl-shadow: 0 6px 24px rgba(24,17,44,.06);
              --rl-radius: var(--radius-card, 16px);
              --rl-pill: var(--radius-pill, 100px);
            }
            .widget-container{ width:100%; font-family:var(--font-sans, "Inter Variable", "Inter", system-ui, -apple-system, sans-serif); color:var(--rl-text); margin:0 auto; }
            .widget-header{ background:linear-gradient(135deg, var(--rl-purple) 0%, var(--rl-purple-dark) 100%); color:#fff; padding:18px 24px; border-radius:var(--rl-radius) var(--rl-radius) 0 0; display:flex; align-items:center; justify-content:space-between; letter-spacing:-.01em; }
            .widget-content{ background:#fff; border:1px solid var(--rl-border); border-top:none; border-radius:0 0 var(--rl-radius) var(--rl-radius); padding:8px 24px 24px; box-shadow:var(--rl-shadow); margin-bottom:20px; }
            .custom-container{ background:#fff; border-radius:var(--rl-radius); padding:16px 0 4px; max-width:640px; width:100%; margin:0 auto; }
            label{ display:block; margin-top:18px; font-size:13.5px; font-weight:600; color:var(--rl-text); }
            input,textarea{ width:100%; padding:13px 14px; margin-top:8px; border:1px solid var(--rl-border); border-radius:12px; font-family:inherit; font-size:15px; color:var(--rl-text); box-sizing:border-box; background:#fff; transition:border-color .15s ease, box-shadow .15s ease; }
            textarea{ resize:vertical; line-height:1.55; }
            input::placeholder,textarea::placeholder{ color:var(--rl-muted); opacity:.7; }
            input:focus,textarea:focus{ border-color:var(--rl-purple); outline:none; box-shadow:0 0 0 3px rgba(138,43,226,.18); }
            button{ background:var(--rl-purple); color:#fff; border:none; padding:14px 22px; margin-top:24px; border-radius:var(--rl-pill); font-family:inherit; font-size:16px; font-weight:700; cursor:pointer; width:100%; transition:transform .15s, box-shadow .15s, background-color .15s; box-shadow:0 8px 20px rgba(138,43,226,.3); }
            button:hover{ background:var(--rl-purple-dark); transform:translateY(-1px); box-shadow:0 12px 26px rgba(98,0,164,.34); }
            button:active{ transform:translateY(0); box-shadow:0 8px 20px rgba(98,0,164,.28); }
            button[disabled]{ opacity:.7; cursor:not-allowed; }

            /* Combined Result Box */
            #result{ display:none; margin-top:24px; padding:0; border:1px solid rgba(138,43,226,.28); border-radius:var(--rl-radius); background:var(--rl-lavender); color:var(--rl-text); box-shadow:var(--rl-shadow); }
            .result-inner{ padding:20px 20px 10px 20px; }
            .result-title{ font-size:18px; font-weight:700; letter-spacing:-.02em; margin-bottom:10px; }
            .subhead{ font-size:13.5px; font-weight:700; margin:16px 0 8px; color:var(--rl-purple-dark); }
            .result-list{ margin:0; padding-left:20px; }
            .result-list li{ margin:6px 0; line-height:1.55; }

            /* Traits: two columns */
            .traits-list{ margin:0; padding-left:20px; column-count:2; column-gap:28px; }
            .traits-list li{ break-inside:avoid; -webkit-column-break-inside:avoid; page-break-inside:avoid; margin:6px 0; line-height:1.45; }
            @media (max-width:640px){ .traits-list{ column-count:1; } }

            .result-footer{ display:flex; justify-content:flex-end; align-items:center; gap:8px; padding:8px 14px 14px 14px; }
            .copy-btn{ appearance:none; border:1px solid rgba(138,43,226,.35); background:#fff; color:var(--rl-purple); font-family:inherit; font-weight:600; font-size:12px; padding:7px 14px; border-radius:var(--rl-pill); cursor:pointer; width:auto; margin-top:0; box-shadow:none; }
            .copy-btn:hover{ background:rgba(138,43,226,.08); color:var(--rl-purple); transform:none; box-shadow:none; }
            .copied{ color:var(--rl-muted); font-size:12px; }
          </style>

          <div class="widget-container">
            <div class="widget-header">
              <span style="font-size:1rem; font-weight:700;">Job Description & Traits Generator</span>
            </div>

            <div class="widget-content" id="widget-content">
              <div class="custom-container">
                <label for="industry">Client Industry:</label>
                <input type="text" id="industry" placeholder="Real Estate, SaaS, E-commerce" />

                <label for="jobTitle">Job Title:</label>
                <input type="text" id="jobTitle" placeholder="Virtual Receptionist, Automation Specialist" />

                <label for="tasks">Tasks:</label>
                <textarea id="tasks" rows="4"></textarea>

                <button id="generateBtn">Generate Description & Traits</button>

                <!-- COMBINED RESULT -->
                <div id="result">
                  <div class="result-inner" id="result-inner">
                    <!-- Filled by JS:
                      <div class="result-title"><strong>Programmer</strong></div>
                      <div class="subhead">Description</div>
                      <ul class="result-list" id="desc-list"></ul>
                      <div class="subhead">Traits</div>
                      <ul class="traits-list" id="traits-list"></ul>
                    -->
                  </div>
                  <div class="result-footer">
                    <button id="copyBtn" class="copy-btn" type="button">Copy</button>
                    <span id="copiedNote" class="copied" style="display:none;">Copied</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        `;
      }

      connectedCallback() {
        const $ = (sel) => this.shadowRoot.querySelector(sel);

        const generateBtn   = $('#generateBtn');
        const resultBox     = $('#result');
        const resultInner   = $('#result-inner');
        const copyBtn       = $('#copyBtn');
        const copiedNote    = $('#copiedNote');

        const escapeHTML = (str) =>
          str.replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;")
             .replace(/"/g,"&quot;").replace(/'/g,"&#39;");

        const parseJDOutput = (text) => {
          const stripped = text.replace(/<[^>]*>/g,"").trim();
          const lines = stripped.split(/\r?\n/).map(l=>l.trim()).filter(Boolean);
          const title = lines[0] || "";
          const bullets = lines.slice(1).map(l=>{
            let t = l.replace(/^[-•]\s*/,"").trim();
            return t;
          }).filter(Boolean);
          return { title, bullets };
        };

        const buildCombinedHTML = ({ title, descBullets, traits }) => {
          return `
            <div class="result-title"><strong>${escapeHTML(title)}</strong></div>
            <div class="subhead">Description</div>
            <ul class="result-list">
              ${descBullets.map(b=>`<li>${escapeHTML(b)}</li>`).join("")}
            </ul>
            <div class="subhead" style="margin-top:12px;">Traits</div>
            <ul class="traits-list">
              ${traits.map(t=>`<li>${escapeHTML(t)}</li>`).join("")}
            </ul>
          `;
        };

        const buildCombinedPlain = ({ title, descBullets, traits }) =>
          `${title}\n\nDescription:\n${descBullets.map(b=>"- "+b).join("\n")}\n\nTraits:\n${traits.map(t=>"- "+t).join("\n")}`;

        const parseTraitsText = (text) => {
          const stripped = text.replace(/<[^>]*>/g,"").trim();
          const lines = stripped.split(/\r?\n/).map(l=>l.trim()).filter(Boolean);
          const items = lines.map(l => l.replace(/^[-•]\s*/,"").replace(/[.;,]\s*$/,"").trim())
                             .filter(Boolean);
          // De-dupe + keep concise (≤ 6 words)
          return Array.from(new Set(items)).filter(t => t.split(" ").length <= 6);
        };

        /*
         * One place that knows how to talk to the proxy, so the two calls below cannot drift.
         *
         * Every failure throws, including a non-2xx with a readable body: the endpoint answers
         * 403 (stale nonce), 429 (rate limited) and 502 (upstream down) with a `message` the
         * visitor can act on, and the catch block below renders it. The old code inspected only
         * `choices` and collapsed every one of those into "(No output received)".
         */
        const callChat = async (payload) => {
          const headers = { "Content-Type": "application/json" };
          if (window.rlJobWidgetNonce) headers["X-WP-Nonce"] = window.rlJobWidgetNonce;

          const resp = await fetch("/wp-json/jobwidget/v1/chat", {
            method: "POST",
            headers,
            body: JSON.stringify(payload)
          });

          let data = null;
          try { data = await resp.json(); } catch { data = null; }

          if (!resp.ok) {
            throw new Error((data && data.message) || `Request failed (${resp.status}).`);
          }
          if (!(data && data.choices && data.choices.length)) {
            throw new Error("The generator returned nothing. Please try again.");
          }
          return data.choices[0].message.content.trim();
        };

        let lastCombinedPlain = "";

        copyBtn.addEventListener('click', async () => {
          const textToCopy = lastCombinedPlain || (resultInner.innerText || "").trim();
          if (!textToCopy) return;
          try {
            await navigator.clipboard.writeText(textToCopy);
            copiedNote.style.display = "inline";
            setTimeout(()=>copiedNote.style.display="none", 1200);
          } catch {
            const ta = document.createElement('textarea');
            ta.value = textToCopy;
            document.body.appendChild(ta);
            ta.select(); document.execCommand('copy');
            document.body.removeChild(ta);
            copiedNote.style.display = "inline";
            setTimeout(()=>copiedNote.style.display="none", 1200);
          }
        });

        generateBtn.addEventListener('click', async () => {
          const industry = $('#industry').value.trim();
          const jobTitle = $('#jobTitle').value.trim();
          const tasks    = $('#tasks').value.trim();

          const lines = [];
          if (industry) lines.push(`Industry: ${industry}`);
          if (jobTitle) lines.push(`Job Title: ${jobTitle}`);
          if (tasks)    lines.push(`Tasks: ${tasks}`);
          if (lines.length === 0) { alert("Please fill in at least one field."); return; }

          const prompt = `Context:\n${lines.join('\n')}`;

          const oldLabel = generateBtn.textContent;
          generateBtn.textContent = "Generating…";
          generateBtn.disabled = true;

          try {
            /* ----- 1) JD call ----- */
            const jdRaw = await callChat({
              model: "gpt-3.5-turbo",
              messages: [
                { role:"system", content:`You are a job-description assistant. Output EXACTLY this format and nothing else:

First line:
<strong><final title></strong>
- No labels. Do NOT write "Job Title:" or "Likely Job Title:". Do NOT append "(Remote)".

Next lines:
- 3–5 bullets, each on its own line starting with "- ".
- Each bullet starts with a strong verb, uses plain English, and is ≤20 words.
- No preamble, no extra headings, no closing text.

Remote-only rules:
- Treat the role as fully remote.
- NEVER include in-person or physical tasks (e.g., greet visitors, front desk, reception area, stocking, filing cabinets, photocopying, cash handling, errands, walk-ins).
- If such items appear in the inputs, REWRITE them as remote equivalents (phone/email/live chat support, online scheduling, digital filing, coordinating shipping with vendors, updating CRMs, managing shared docs).

Tool inclusion:
- If any tools/technologies/platforms are explicitly mentioned in the context (e.g., n8n, Zapier, Make.com, GoHighLevel), include them verbatim in at least one bullet, preserving exact spelling/casing.
- Do not introduce tools that were not provided.

Additional:
- Convert typically on-site titles to remote equivalents when needed (e.g., Receptionist → Virtual Receptionist).
- Avoid duplicate bullets.` },
                { role:"user", content: prompt }
              ],
              max_tokens: 120,
              temperature: 0.7
            });

            const { title, bullets: descBullets } = parseJDOutput(jdRaw);

            /* ----- 2) Traits call (9–12 lines, highly relevant only) ----- */
            const traitsPrompt = `Role: ${title}
Industry: ${industry || "(unspecified)"}
Tasks (if any): ${tasks || "(unspecified)"}

Return ONLY the most relevant traits that predict success in this role.
FORMAT RULES (strict):
- 9–12 lines total (not more than 12)
- Each line starts with "- " followed by a concise trait (2–5 words)
- No numbering, no extra text, no headings, no closing text
- Avoid generic fluff; keep only role-relevant traits
- No discriminatory characteristics (age, gender, etc.)
- No duplicates or near-duplicates`;

            const traitsRaw = await callChat({
              model: "gpt-3.5-turbo",
              messages: [
                { role:"system", content:"You are a recruiting assistant. Output ONLY the bullet list requested — one trait per line." },
                { role:"user", content: traitsPrompt }
              ],
              max_tokens: 220,
              temperature: 0.35
            });
            const traits = parseTraitsText(traitsRaw);

            /* ----- 3) Render combined ----- */
            resultInner.innerHTML = buildCombinedHTML({
              title,
              descBullets: descBullets,
              traits: traits
            });
            lastCombinedPlain = buildCombinedPlain({
              title,
              descBullets,
              traits
            });
            resultBox.style.display = "block";

          } catch (err) {
            console.error(err);
            resultInner.innerHTML = `<div class="result-title"><strong>Error</strong></div><p>${escapeHTML(err.message || String(err))}</p>`;
            resultBox.style.display = "block";
            lastCombinedPlain = "";
          } finally {
            generateBtn.textContent = oldLabel;
            generateBtn.disabled = false;
          }
        });
      }
    }
    customElements.define('job-description-widget', JobDescriptionWidget);
</script>
<job-description-widget></job-description-widget>
