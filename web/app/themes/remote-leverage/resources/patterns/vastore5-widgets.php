<?php

/**
 * Ported verbatim from production /vastore5/ (page 10809): the two self-contained
 * Elementor HTML widgets that page carries.
 *
 *  1. #timerWidget  — a draggable call stopwatch, fixed bottom-right.
 *  2. <job-description-widget> — a shadow-DOM custom element that POSTs to the
 *     REST route /wp-json/jobwidget/v1/chat to draft a job description. That route
 *     is supplied by a production-only plugin and does NOT exist in this install,
 *     so the Generate button will surface an error here. Markup, styling and
 *     behaviour are reproduced 1:1 so the section renders identically; wiring the
 *     endpoint up is a separate decision for the team.
 *
 * Both blobs keep production's own ids/classes and ship their own styles, so they
 * are intentionally not rewritten into Tailwind — restyling them would break the
 * 1:1 requirement and they are scoped tightly enough not to leak.
 *
 * Not a block pattern: it has no pattern header and lives outside patterns/ so
 * WordPress does not try to register it.
 */
?>
<!-- vastore5: call timer widget (production #timerWidget) -->
<div id="timerWidget" class="rl">
    <button id="toggleBtn" aria-label="Hide timer" title="Hide">&times;</button>

    <div id="timerContent" class="card">
        <div id="timeDisplay" class="time">00:00</div>

        <div class="controls">
            <button id="startBtn" class="btn">Start</button>
            <button id="pauseBtn" class="btn" disabled>Pause</button>
            <button id="resetBtn" class="btn">Reset</button>
        </div>
    </div>
</div>

<style>
/* ========= Remote Leverage theme ========= */
  #timerWidget.rl{
    --rl-purple: #6D28D9;
    --rl-purple-600:#5B21B6;
    --rl-bg: #ffffff;
    --rl-text: #111827;
    --rl-muted:#6B7280;
    --rl-border:#E5E7EB;
    --shadow: 0 10px 30px rgba(17,24,39,0.12);
    --radius: 12px;

    position: fixed;             /* draggable container is fixed to viewport */
    bottom: 20px;                /* default position: bottom-right */
    right: 20px;
    z-index: 1000;
    font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
    color: var(--rl-text);
    touch-action: none;          /* smoother drag on touch */
  }

  /* Card */
  #timerWidget .card{
    background: var(--rl-bg);
    border: 1px solid var(--rl-border);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    padding: 16px 18px 14px;
    min-width: 220px;
    text-align: center;
    user-select: none;           /* avoids text selection while dragging */
  }

  /* Time */
  #timerWidget .time{
    font-size: 2.2rem;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
    letter-spacing: .5px;
    margin: 6px 0 12px;
  }

  /* Buttons */
  #timerWidget .controls{
    display: flex;
    gap: 8px;
    justify-content: center;
  }
  #timerWidget .btn{
    appearance: none;
    background: #fff;
    color: var(--rl-purple);
    border: 1px solid var(--rl-purple);
    border-radius: 999px;
    padding: 8px 14px;
    font-weight: 600;
    font-size: 0.95rem;
    cursor: pointer;
    transition: background .15s ease, color .15s ease, box-shadow .15s ease, transform .15s ease;
  }
  #timerWidget .btn:hover{
    background: var(--rl-purple);
    color: #fff;
    box-shadow: 0 6px 16px rgba(109,40,217,0.28);
    transform: translateY(-1px);
  }
  #timerWidget .btn:active{ transform: translateY(0); }
  #timerWidget .btn:disabled{
    opacity: .45;
    cursor: not-allowed;
    background: #fff;
    color: var(--rl-muted);
    border-color: #ddd;
    box-shadow: none;
  }

  /* Hide/Show control */
  #timerWidget #toggleBtn{
    position: absolute;          /* anchored to the container when expanded */
    top: -10px;
    right: -10px;
    width: 28px;
    height: 28px;
    line-height: 26px;
    padding: 0;
    border-radius: 999px;
    border: 1px solid var(--rl-border);
    background: #fff;
    color: var(--rl-purple);
    font-weight: 700;
    font-size: 18px;
    cursor: pointer;
    box-shadow: var(--shadow);
    transition: background .15s ease, color .15s ease, box-shadow .15s ease, transform .15s ease;
  }
  #timerWidget #toggleBtn:hover{
    background: var(--rl-purple);
    color: #fff;
  }
  #timerWidget #toggleBtn:focus{ outline: 3px solid rgba(109,40,217,0.25); }

  /* Collapsed state -> compact, readable pill (never shrinks too small) */
  #timerWidget.collapsed .card{ display: none; }
  #timerWidget.collapsed{
    padding: 0;
    width: auto; height: auto; overflow: visible;
  }
  #timerWidget.collapsed #toggleBtn{
    position: static;            /* becomes a floating pill sized by content */
    display: inline-flex;
    align-items: center;
    justify-content: center;
    white-space: nowrap;         /* keep label intact */
    min-height: 40px;
    min-width: 110px;
    padding: 8px 14px;
    border-radius: 999px;
    border: none;
    background: var(--rl-purple);
    color: #fff;
    font-size: 0.95rem;
    line-height: 1;
    box-shadow: var(--shadow);
  }
  #timerWidget.collapsed #toggleBtn:hover{
    background: var(--rl-purple-600);
  }
</style>

<script>
(function(){
  const widget     = document.getElementById('timerWidget');
  const card       = document.getElementById('timerContent');
  const timeEl     = document.getElementById('timeDisplay');
  const startBtn   = document.getElementById('startBtn');
  const pauseBtn   = document.getElementById('pauseBtn');
  const resetBtn   = document.getElementById('resetBtn');
  const toggleBtn  = document.getElementById('toggleBtn');

  // ---- TIMER: track total seconds, render MM:SS or H:MM:SS ----
  let totalSeconds = 0;
  let timerInterval = null;
  let isPaused = false;

  function formatTime(ts){
    const h = Math.floor(ts / 3600);
    const m = Math.floor((ts % 3600) / 60);
    const s = ts % 60;
    if (h > 0) {
      return `${h}:${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`; // H:MM:SS
    }
    return `${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;        // MM:SS
  }
  function updateDisplay(){ timeEl.textContent = formatTime(totalSeconds); }

  function tick(){
    totalSeconds++;
    updateDisplay();
  }

  function startTimer(){
    if (timerInterval) return;    // guard against double starts
    startBtn.textContent = 'Started';
    startBtn.disabled = true;
    pauseBtn.disabled = false;
    isPaused = false;
    timerInterval = setInterval(tick, 1000);
  }

  function togglePause(){
    if (!isPaused) {
      clearInterval(timerInterval);
      timerInterval = null;
      isPaused = true;
      pauseBtn.textContent = 'Resume';
    } else {
      isPaused = false;
      pauseBtn.textContent = 'Pause';
      timerInterval = setInterval(tick, 1000);
    }
  }

  function resetTimer(){
    clearInterval(timerInterval);
    timerInterval = null;
    totalSeconds = 0;
    updateDisplay();
    startBtn.textContent = 'Start';
    startBtn.disabled = false;
    pauseBtn.textContent = 'Pause';
    pauseBtn.disabled = true;
    isPaused = false;
  }

  // ---- COLLAPSE / EXPAND (pill is always readable) ----
  function toggleTimerContent(){
    const collapsed = widget.classList.contains('collapsed');
    if (collapsed) {
      widget.classList.remove('collapsed');
      toggleBtn.textContent = '×';
      toggleBtn.setAttribute('aria-label','Hide timer');
      toggleBtn.title = 'Hide';
    } else {
      widget.classList.add('collapsed');
      toggleBtn.textContent = '⤢ Expand';
      toggleBtn.setAttribute('aria-label','Show timer');
      toggleBtn.title = 'Show';
    }
  }

  // ---- DRAGGABLE + SNAP TO SIDES ----
  let dragging = false;
  let offsetX = 0, offsetY = 0;

  function onPointerDown(e){
    // avoid starting drag on control buttons (but allow drag on pill or card)
    if (e.target.closest('.btn')) return;
    dragging = true;

    // convert current fixed position to explicit left/top for smooth drag
    const rect = widget.getBoundingClientRect();
    widget.style.left = `${rect.left}px`;
    widget.style.top  = `${rect.top}px`;
    widget.style.right = 'auto';
    widget.style.bottom = 'auto';

    offsetX = e.clientX - rect.left;
    offsetY = e.clientY - rect.top;

    document.addEventListener('pointermove', onPointerMove);
    document.addEventListener('pointerup', onPointerUp);
  }

  function onPointerMove(e){
    if (!dragging) return;
    const rect = widget.getBoundingClientRect();
    const w = rect.width;
    const h = rect.height;
    const vw = window.innerWidth;
    const vh = window.innerHeight;

    let x = e.clientX - offsetX;
    let y = e.clientY - offsetY;

    // clamp within viewport
    const margin = 20;
    x = Math.max(margin, Math.min(x, vw - w - margin));
    y = Math.max(margin, Math.min(y, vh - h - margin));

    widget.style.left = `${x}px`;
    widget.style.top  = `${y}px`;
  }

  function onPointerUp(){
    if (!dragging) return;
    dragging = false;

    // snap horizontally to nearest side
    const rect = widget.getBoundingClientRect();
    const vw = window.innerWidth;
    const margin = 20;
    const snapLeft = rect.left + rect.width/2 < vw/2;

    widget.style.top = `${Math.max(margin, Math.min(rect.top, window.innerHeight - rect.height - margin))}px`;
    if (snapLeft) {
      widget.style.left  = `${margin}px`;
      widget.style.right = 'auto';
    } else {
      widget.style.right = `${margin}px`;
      widget.style.left  = 'auto';
    }

    document.removeEventListener('pointermove', onPointerMove);
    document.removeEventListener('pointerup', onPointerUp);
  }

  // drag on card or pill (both states)
  widget.addEventListener('pointerdown', onPointerDown);

  // Events
  startBtn.addEventListener('click', startTimer);
  pauseBtn.addEventListener('click', togglePause);
  resetBtn.addEventListener('click', resetTimer);
  toggleBtn.addEventListener('click', toggleTimerContent);

  // init
  updateDisplay();
})();
</script>

<!-- vastore5: job description + traits generator (production <job-description-widget>) -->
<script>
class JobDescriptionWidget extends HTMLElement {
      constructor() {
        super();
        this.attachShadow({ mode: 'open' });
        this.shadowRoot.innerHTML = `
          <style>
            :host{
              --rl-blue:#007BFF;
              --rl-purple:#6D28D9;
              --rl-purple-dark:#5B21B6;
              --rl-lavender:#F5F3FF;
              --rl-text:#111827;
              --rl-muted:#6B7280;
              --rl-border:#E5E7EB;
              --rl-shadow:0 6px 18px rgba(0,0,0,.08);
              --rl-radius:10px;
            }
            .widget-container{ width:100%; font-family:"Helvetica Neue", Helvetica, Arial, sans-serif; color:var(--rl-text); margin:0 auto; }
            .widget-header{ background:var(--rl-blue); color:#fff; padding:16px 20px; border-radius:var(--rl-radius) var(--rl-radius) 0 0; display:flex; align-items:center; justify-content:space-between; box-shadow:var(--rl-shadow); }
            .widget-content{ background:#fff; border:1px solid var(--rl-border); border-top:none; border-radius:0 0 var(--rl-radius) var(--rl-radius); padding:24px; box-shadow:var(--rl-shadow); margin-bottom:20px; }
            .custom-container{ background:#fff; border-radius:var(--rl-radius); padding:30px 20px; max-width:640px; width:100%; margin:10px auto 0; }
            label{ display:block; margin-top:14px; font-weight:600; color:var(--rl-text); }
            input,textarea{ width:100%; padding:12px; margin-top:8px; border:1px solid var(--rl-border); border-radius:8px; font-size:16px; box-sizing:border-box; background:#fff; }
            input:focus,textarea:focus{ border-color:var(--rl-purple); outline:none; box-shadow:0 0 0 3px rgba(109,40,217,.2); }
            button{ background:var(--rl-purple); color:#fff; border:none; padding:13px 20px; margin-top:22px; border-radius:10px; font-size:16px; font-weight:600; cursor:pointer; width:100%; transition:transform .15s, box-shadow .15s, background-color .15s; box-shadow:0 6px 14px rgba(109,40,217,.28); }
            button:hover{ background:var(--rl-purple-dark); transform:translateY(-1px); box-shadow:0 8px 18px rgba(91,33,182,.34); }
            button:active{ transform:translateY(0); box-shadow:0 6px 14px rgba(91,33,182,.28); }
            button[disabled]{ opacity:.7; cursor:not-allowed; }

            /* Combined Result Box */
            #result{ display:none; margin-top:22px; padding:0; border:1px solid var(--rl-purple); border-radius:10px; background:var(--rl-lavender); color:var(--rl-text); box-shadow:var(--rl-shadow); }
            .result-inner{ padding:16px 16px 8px 16px; }
            .result-title{ font-weight:800; margin-bottom:8px; }
            .subhead{ font-weight:700; margin:8px 0 6px; color:#2d2d2d; }
            .result-list{ margin:0; padding-left:20px; }
            .result-list li{ margin:6px 0; line-height:1.45; }

            /* Traits: two columns */
            .traits-list{ margin:0; padding-left:20px; column-count:2; column-gap:28px; }
            .traits-list li{ break-inside:avoid; -webkit-column-break-inside:avoid; page-break-inside:avoid; margin:6px 0; line-height:1.45; }
            @media (max-width:640px){ .traits-list{ column-count:1; } }

            .result-footer{ display:flex; justify-content:flex-end; align-items:center; gap:8px; padding:8px 12px 12px 12px; }
            .copy-btn{ appearance:none; border:1px solid rgba(109,40,217,.35); background:#fff; color:var(--rl-purple); font-weight:600; font-size:12px; padding:6px 10px; border-radius:999px; cursor:pointer; }
            .copy-btn:hover{ background:rgba(109,40,217,.08); }
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
            const jdResp = await fetch("/wp-json/jobwidget/v1/chat", {
              method: "POST",
              headers: { "Content-Type": "application/json" },
              body: JSON.stringify({
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
              })
            });
            const jdData = await jdResp.json();

            if (!(jdData.choices && jdData.choices.length)) {
              resultInner.innerHTML = `<div class="result-title"><strong>(No output received)</strong></div>`;
              resultBox.style.display = "block";
              lastCombinedPlain = "";
              return;
            }

            const jdRaw = jdData.choices[0].message.content.trim();
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

            const traitsResp = await fetch("/wp-json/jobwidget/v1/chat", {
              method: "POST",
              headers: { "Content-Type": "application/json" },
              body: JSON.stringify({
                model: "gpt-3.5-turbo",
                messages: [
                  { role:"system", content:"You are a recruiting assistant. Output ONLY the bullet list requested — one trait per line." },
                  { role:"user", content: traitsPrompt }
                ],
                max_tokens: 220,
                temperature: 0.35
              })
            });
            const traitsData = await traitsResp.json();
            const traitsRaw = (traitsData.choices && traitsData.choices[0]?.message?.content || "").trim();
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
