{{-- Production's "Bundle Finance Calculator" on /services/ — an Elementor HTML widget holding
     its own markup, CSS and JS. Ported verbatim (2026-09-15) apart from the button rule at the
     end of the stylesheet: production's own `.bfc-btn` CSS is overridden on the live page by
     the Elementor theme's global button style, so both buttons actually render as chunky
     purple pills. The override below reproduces what a visitor sees rather than what the
     widget author wrote.

     The markup uses ids, so this partial is single-use per page. The whole body is wrapped in
     a verbatim block to keep Blade away from the stylesheet's media query and the script's
     template literals. (Do not name that directive in this comment — Blade scans for it
     before it strips comments, and would open the block here.) --}}
@verbatim
<div id="bfc-card" style="width:100%;max-width:1100px;margin:24px auto;background:#fff;border:1px solid #eee;border-radius:16px;box-shadow:0 10px 30px rgba(17,24,39,.07);overflow:hidden;font-family:var(--font-sans);">
  <div style="background:linear-gradient(135deg,#6D28D9,#7C3AED);color:#fff;padding:18px 22px;font-weight:700;font-size:18px;">
    Bundle Finance Calculator
  </div>

  <div style="padding:20px 22px 24px;">
    <div class="bfc-section">
      <div class="bfc-section-title">Payment Details</div>
      <div class="bfc-grid">
        <div>
          <label for="bfc-paid" class="bfc-label">Amount Paid</label>
          <div class="bfc-field bfc-lg">
            <span class="bfc-prefix">$</span>
            <input type="number" id="bfc-paid" min="0" step="1" placeholder="e.g. 8000" inputmode="numeric" />
          </div>
          <label class="bfc-inline-option" for="bfc-paid-cc-used" title="If the first payment(s) were by card and included a 3% fee, we remove that fee before applying the credit.">
            <input type="checkbox" id="bfc-paid-cc-used" />
            <span>CC Used Previous Payment? (exclude 3% fee from credit)</span>
          </label>
        </div>

        <div id="bfc-pay-wrap">
          <label class="bfc-label">Payment Method</label>
          <div class="bfc-paymethod bfc-lg">
            <label class="bfc-radio">
              <input type="radio" name="bfc-pay" id="bfc-pay-ach" value="ach" checked />
              <span>ACH (no fee)</span>
            </label>
            <label class="bfc-radio">
              <input type="radio" name="bfc-pay" id="bfc-pay-cc" value="cc" />
              <span>Credit Card (+3% fee)</span>
            </label>
          </div>
        </div>
      </div>
    </div>

    <div class="bfc-section">
      <div class="bfc-section-title">Bundle Details</div>
      <div class="bfc-grid">
        <div id="bfc-bundle-wrap">
          <label for="bfc-bundle" class="bfc-label">Select Bundle</label>
          <div class="bfc-field bfc-lg">
            <select id="bfc-bundle"></select>
          </div>
        </div>

        <div>
          <label for="bfc-hired-count" class="bfc-label">Number of VAs Hired</label>
          <div class="bfc-field bfc-lg">
            <select id="bfc-hired-count">
              <option value="1" selected>1</option><option value="2">2</option><option value="3">3</option>
              <option value="4">4</option><option value="5">5</option><option value="6">6</option>
              <option value="7">7</option><option value="8">8</option><option value="9">9</option><option value="10">10</option>
            </select>
          </div>
        </div>
      </div>
    </div>

    <div class="bfc-actions">
      <button type="button" id="bfc-calc" class="bfc-btn bfc-primary">Calculate</button>
      <button type="button" id="bfc-clear" class="bfc-btn bfc-ghost">Clear</button>
    </div>

    <div id="bfc-results" class="bfc-results" style="display:none;">
      <div class="bfc-box-title">Paid in Full</div>

      <div class="bfc-row">
        <div class="bfc-label-sm">Bundle package price (Total)</div>
        <div class="bfc-value" id="bfc-total">$0</div>
      </div>

      <div class="bfc-row">
        <div class="bfc-label-sm">Credit (already paid)</div>
        <div class="bfc-value" id="bfc-credit">$0</div>
      </div>

      <div class="bfc-row" id="bfc-credit-feeremoved-row" style="display:none;">
        <div class="bfc-label-sm">CC fee removed from credit</div>
        <div class="bfc-value" id="bfc-credit-feeremoved">$0</div>
      </div>

      <div class="bfc-row">
        <div class="bfc-label-sm">Remaining balance (Total &minus; Credit)</div>
        <div class="bfc-value" id="bfc-remaining">$0</div>
      </div>

      <div class="bfc-row" id="bfc-fee-row" style="display:none;">
        <div class="bfc-label-sm">Card fee (3% of amount due)</div>
        <div class="bfc-value" id="bfc-fee">$0</div>
      </div>

      <div class="bfc-row" id="bfc-remaininghires-row" style="display:none;">
        <div class="bfc-label-sm">Remaining hires</div>
        <div class="bfc-value" id="bfc-remaininghires">0</div>
      </div>

      <div class="bfc-row" id="bfc-costper-row" style="display:none;">
        <div class="bfc-label-sm">Cost per hire (remaining)</div>
        <div class="bfc-value" id="bfc-costper">$0</div>
      </div>

      <div class="bfc-note" id="bfc-note" aria-live="polite" style="display:none;"></div>
    </div>
  </div>
</div>

<style>
  .bfc-section{border:1px solid #f0eaff;background:#fbf9ff;border-radius:12px;padding:14px 14px 6px;margin-bottom:14px}
  .bfc-section-title{font-size:14px;font-weight:800;color:#4b5563;margin-bottom:10px}

  .bfc-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:16px}
  @media(max-width:980px){.bfc-grid{grid-template-columns:1fr}}

  #bfc-bundle-wrap{grid-column:span 1}
  #bfc-pay-wrap{grid-column:span 1}

  .bfc-label{display:block;font-size:13px;font-weight:700;color:#111827;margin:2px 0 8px}
  .bfc-field{display:flex;align-items:center;background:#fafafa;border:1px solid #eaeaea;border-radius:12px;padding:10px 12px}
  .bfc-lg{padding:12px 14px}
  .bfc-field:focus-within{border-color:#A78BFA;box-shadow:0 0 0 3px rgba(124,58,237,.16);background:#fff}
  .bfc-prefix{font-size:14px;color:#6b7280;margin-right:8px;min-width:16px;text-align:center}
  .bfc-field input,.bfc-field select{border:none;outline:none;background:transparent;width:100%;font-size:16px;color:#111827;appearance:none}
  .bfc-field select{white-space:nowrap}

  .bfc-paymethod{display:flex;gap:16px;align-items:center;background:#fafafa;border:1px solid #eaeaea;border-radius:12px;padding:10px 12px}
  .bfc-radio{display:flex;align-items:center;gap:8px;font-size:14px;color:#1f2937}
  .bfc-radio input{accent-color:#7C3AED;transform:scale(1.05)}

  .bfc-inline-option{display:flex;align-items:center;gap:8px;margin-top:8px;font-size:12px;color:#374151;cursor:pointer}
  .bfc-inline-option input{accent-color:#7C3AED;transform:scale(1.05)}
  .bfc-help{margin-top:6px;font-size:12px;color:#6b7280}

  .bfc-actions{display:flex;gap:12px;margin:16px 0 10px;flex-wrap:wrap}
  .bfc-btn{cursor:pointer;border-radius:10px;padding:11px 16px;font-weight:800;border:1px solid transparent;line-height:1}
  .bfc-primary{background:#7C3AED;color:#fff;box-shadow:0 6px 18px rgba(124,58,237,.25)}
  .bfc-primary:hover{background:#6931e7}
  .bfc-ghost{background:#fff;color:#7C3AED;border-color:#eae1ff}

  .bfc-results{margin-top:16px;padding:16px;border:1px solid #efe9ff;background:#faf8ff;border-radius:12px}
  .bfc-box-title{font-size:18px;font-weight:900;color:#111827;margin:2px 0 10px}
  .bfc-row{display:flex;justify-content:space-between;align-items:center;gap:10px;padding:10px 0;border-bottom:1px dashed #eadfff}
  .bfc-row:last-child{border-bottom:none}
  .bfc-label-sm{font-size:14px;color:#6b7280}
  .bfc-label-xs{font-size:12px;color:#6b7280}
  .bfc-value{font-size:18px;font-weight:900;color:#111827;display:flex;align-items:center;gap:8px}
  .bfc-credit{color:#065f46}
  .bfc-tag{margin-left:6px;font-size:11px;font-weight:800;background:#ecfdf5;color:#065f46;border:1px solid #d1fae5;padding:4px 8px;border-radius:999px}
  .bfc-capnote{font-size:11px;color:#6b7280;background:#f3f4f6;border:1px solid #e5e7eb;padding:4px 8px;border-radius:999px;margin-left:6px}
  .bfc-note{margin-top:10px;font-size:12px;color:#6b7280}

  .bfc-costper-good{
    color:#065f46 !important;
    background:#ecfdf5;
    border:1px solid #d1fae5;
    padding:6px 10px;
    border-radius:999px;
    font-weight:900;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-width:84px;
    box-shadow:0 2px 6px rgba(6,95,70,0.06);
  }

  /* What production actually paints: the site's global button style wins over the two
     rules above, so both buttons are solid purple-deep at 6px radius, 17px/700. */
  #bfc-card .bfc-btn,
  #bfc-card .bfc-btn.bfc-primary,
  #bfc-card .bfc-btn.bfc-ghost{
    background:var(--color-brand-purple-deep);
    color:#fff;
    border-color:transparent;
    border-radius:6px;
    padding:22px 35px;
    font-size:17px;
    line-height:17px;
    font-weight:700;
  }
  #bfc-card .bfc-btn:hover{background:#520089}
</style>

<script>
(function(){
  const BUNDLES = [
    { id:'3va',  label:'3 Virtual Assistants Bundle - $12,000 ($4k Per VA) (~$7k savings)',        price:12000, size:3 },
    { id:'5va',  label:'5 Virtual Assistants Bundle - $17,500 ($3.5k Per VA) (~$10k in savings)',  price:17500, size:5 },
    { id:'7va',  label:'7 Virtual Assistants Bundle - $23,000 ($3.28k Per VA) (~$13k in savings)', price:23000, size:7 },
    { id:'10va', label:'10 Virtual Assistants Bundle - $30,000 ($3k Per VA) (~$19k in savings)',   price:30000, size:10 }
  ];

  const $ = id => document.getElementById(id);

  const paid       = $('bfc-paid');
  const paidCCUsed = $('bfc-paid-cc-used');
  const bundleSel  = $('bfc-bundle');
  const hiredSel   = $('bfc-hired-count');

  if (!paid || !bundleSel || !hiredSel) return;

  const payACH     = $('bfc-pay-ach');
  const payCC      = $('bfc-pay-cc');

  const calcBtn    = $('bfc-calc');
  const clearBtn   = $('bfc-clear');

  const boxRes     = $('bfc-results');
  const totalEl    = $('bfc-total');
  const creditEl   = $('bfc-credit');
  const creditFeeRemovedRow = $('bfc-credit-feeremoved-row');
  const creditFeeRemovedEl  = $('bfc-credit-feeremoved');
  const remEl      = $('bfc-remaining');
  const feeRow     = $('bfc-fee-row');
  const feeEl      = $('bfc-fee');
  const remHiresRow= $('bfc-remaininghires-row');
  const remHiresEl = $('bfc-remaininghires');
  const costPerRow = $('bfc-costper-row');
  const costPerEl  = $('bfc-costper');
  const noteEl     = $('bfc-note');

  BUNDLES.forEach(b=>{
    const opt=document.createElement('option');
    opt.value=b.id;
    opt.textContent=b.label;
    bundleSel.appendChild(opt);
  });
  bundleSel.value='3va';
  hiredSel.value='1';

  const fmt = n => "$" + Math.round(n).toLocaleString("en-US");
  const selectedBundle = () => BUNDLES.find(b=>b.id===bundleSel.value);

  function compute(){
    const sel = selectedBundle();
    const total = sel.price;

    const paidGross = parseFloat(paid.value || "0");
    const hiredCnt  = parseInt(hiredSel.value,10);

    if (Number.isNaN(paidGross) || paidGross < 0 || Number.isNaN(hiredCnt) || hiredCnt <= 0){
      boxRes.style.display='none';
      alert('Please enter valid numbers for Amount Paid and Number of VAs Hired (>=1).');
      return;
    }

    const FIRST_CC_RATE = 0.03;
    const paidNet = paidCCUsed.checked ? (paidGross / (1 + FIRST_CC_RATE)) : paidGross;
    const feeRemoved = Math.max(paidGross - paidNet, 0);

    const CREDIT_CAP = 8000;
    const creditApplied = Math.min(paidNet, CREDIT_CAP);
    const isCapped = paidNet > CREDIT_CAP;

    const remainingBeforeFee = Math.max(total - creditApplied, 0);

    const payMethod = document.querySelector('input[name="bfc-pay"]:checked')?.value || 'ach';
    const feeRate   = (payMethod === 'cc') ? 0.03 : 0;
    const fee       = remainingBeforeFee * feeRate;
    const amountDue = remainingBeforeFee + fee;

    totalEl.textContent = fmt(total);

    creditEl.innerHTML = `${fmt(creditApplied)}${isCapped ? ' <span class="bfc-capnote">(max ' + fmt(CREDIT_CAP) + ' credit)</span>' : ''}`;

    if (paidCCUsed.checked && feeRemoved > 0.0001){
      creditFeeRemovedRow.style.display = '';
      creditFeeRemovedEl.textContent = fmt(feeRemoved);
    } else {
      creditFeeRemovedRow.style.display = 'none';
    }

    remEl.textContent = fmt(remainingBeforeFee);

    if (feeRate){
      feeRow.style.display = '';
      feeEl.textContent = fmt(fee);
    } else {
      feeRow.style.display = 'none';
      feeEl.textContent = fmt(0);
    }

    if (sel.size){
      const remainingHires = Math.max(sel.size - hiredCnt, 0);
      if (remainingHires > 0){
        remHiresRow.style.display = '';
        costPerRow.style.display  = '';
        remHiresEl.textContent    = String(remainingHires);
        costPerEl.textContent     = fmt(amountDue / remainingHires);
        costPerEl.classList.add('bfc-costper-good');
      } else {
        remHiresRow.style.display = 'none';
        costPerRow.style.display  = 'none';
        costPerEl.classList.remove('bfc-costper-good');
      }
    } else {
      remHiresRow.style.display = 'none';
      costPerRow.style.display  = 'none';
      costPerEl.classList.remove('bfc-costper-good');
    }

    let note = '';
    if (isCapped){
      note += 'Credit capped at ' + fmt(CREDIT_CAP) + '. ';
    }
    if (paidCCUsed.checked){
      note += 'First payment credit excludes 3% CC fee. ';
    }
    note += feeRate
      ? 'Paying by credit card adds a 3% fee to the remaining balance.'
      : 'No additional fee when paying by ACH.';
    noteEl.textContent = note;
    noteEl.style.display = 'block';

    boxRes.style.display = 'block';
  }

  function clearAll(){
    paid.value='';
    paidCCUsed.checked=false;
    bundleSel.value='3va';
    hiredSel.value='1';
    payACH.checked=true;
    payCC.checked=false;

    boxRes.style.display='none';
    costPerEl.classList.remove('bfc-costper-good');
    paid.focus();
  }

  calcBtn.addEventListener('click', compute);
  clearBtn.addEventListener('click', clearAll);
})();
</script>
@endverbatim
