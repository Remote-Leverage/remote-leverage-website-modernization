<?php

/**
 * The call stopwatch that sits fixed bottom-right on the internal sales playbooks
 * (/vastore5/ and /store/). Ported verbatim from production, where the identical
 * Elementor HTML widget appears on both pages — same #timerWidget markup, same ids,
 * same drag/snap/collapse behaviour.
 *
 * Extracted out of vastore5-widgets.php on 2026-09-15 when /store/ was migrated, so the
 * two pages share one copy rather than forking ~300 lines of behaviour. /vastore5/ pairs
 * it with the job-description generator; /store/ carries the timer alone, which is what
 * production's /store/ does.
 *
 * Every element id, class, event listener and style rule is unchanged from the version
 * that shipped on /vastore5/. Ids are global (#timerWidget, #startBtn, …), so include
 * this at most once per page.
 *
 * 2026-09-15 redesign: skinned with the theme's own @theme custom properties (Tailwind
 * emits them to :root under `theme(static)`). Presentation only — no behaviour changed.
 *
 * Not a block pattern: it has no pattern header and lives outside patterns/ so WordPress
 * does not try to register it.
 */
?>
<!-- sales playbook: call timer widget (production #timerWidget) -->
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
    --rl-purple: var(--color-brand-purple, #8A2BE2);
    --rl-purple-600: var(--color-brand-purple-deep, #6200A4);
    --rl-bg: #ffffff;
    --rl-text: var(--color-brand-midnight, #18112C);
    --rl-muted:#6F6B85;
    --rl-border: rgba(24,17,44,.10);
    --shadow: 0 18px 44px rgba(24,17,44,.18);
    --radius: var(--radius-card, 16px);

    position: fixed;             /* draggable container is fixed to viewport */
    bottom: 20px;                /* default position: bottom-right */
    right: 20px;
    z-index: 1000;
    font-family: var(--font-sans, "Inter Variable", "Inter", system-ui, -apple-system, sans-serif);
    color: var(--rl-text);
    touch-action: none;          /* smoother drag on touch */
  }

  /* Card */
  #timerWidget .card{
    background: var(--rl-bg);
    border: 1px solid var(--rl-border);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    padding: 18px 20px 16px;
    min-width: 224px;
    text-align: center;
    user-select: none;           /* avoids text selection while dragging */
  }

  /* Time */
  #timerWidget .time{
    font-size: 2.35rem;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
    letter-spacing: -.02em;
    line-height: 1.05;
    color: var(--rl-text);
    margin: 4px 0 14px;
  }

  /* Buttons */
  #timerWidget .controls{
    display: flex;
    gap: 8px;
    justify-content: center;
  }
  #timerWidget .btn{
    appearance: none;
    font-family: inherit;
    background: #fff;
    color: var(--rl-purple);
    border: 1px solid color-mix(in srgb, var(--rl-purple) 35%, transparent);
    border-radius: 999px;
    padding: 9px 15px;
    font-weight: 600;
    font-size: 0.9rem;
    cursor: pointer;
    transition: background .15s ease, color .15s ease, box-shadow .15s ease, transform .15s ease;
  }
  #timerWidget .btn:hover{
    background: var(--rl-purple);
    color: #fff;
    box-shadow: 0 6px 16px rgba(138,43,226,0.3);
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
  #timerWidget #toggleBtn:focus{ outline: 3px solid rgba(138,43,226,0.28); }

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
