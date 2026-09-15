<?php

/**
 * The scoped stylesheet shared by the internal sales playbooks — /vastore5/ (recruitment)
 * and /store/ (Contractor of Record). Both pages are the same artefact for two products:
 * a rep works down discovery questions, a presentation script, a pricing breakdown, deposit
 * routes and bundle packages, live on a call.
 *
 * Extracted out of patterns/vastore5.php on 2026-09-15 when /store/ was migrated on the
 * direction "same kind of purpose as vastore5 — follow vastore5 styling decisions". One copy
 * rather than two means a fix to a band, card or tab reaches both pages.
 *
 * Built from the theme's own @theme custom properties (Tailwind emits them to :root under
 * `theme(static)`), so the playbooks share the sitewide palette, radii, shadows and type face.
 * Fallback literals are the token values, so a page still renders correctly if it is ever
 * viewed without app.css. Scoped CSS rather than utilities because these pages must re-add the
 * list markers Tailwind preflight strips, and because the tab/accordion/dropdown state
 * selectors have no utility equivalent.
 *
 * Config (all keys optional):
 *   scope — the root class the rules hang off. Defaults to 'vastore5'.
 *
 * Element class names keep their v5- prefix on both pages: they name this shared skin, not
 * the page that first carried it.
 *
 * Not a block pattern: it has no pattern header and lives outside patterns/ so WordPress
 * does not try to register it.
 */
$scope = $skin['scope'] ?? 'vastore5';
?>
<style>
/* ------------------------------------------------------------------------------
   /vastore5/ — internal sales playbook.
   Built from the theme's own @theme custom properties (Tailwind emits them to
   :root under `theme(static)`), so this page shares the sitewide palette, radii,
   shadows and type face. Fallback literals are the token values, so the page
   still renders correctly if it is ever viewed without app.css.
   ------------------------------------------------------------------------------ */
.<?= $scope ?> {
    --v5-ink: var(--color-brand-midnight, #18112C);
    --v5-body: #4A4560;
    --v5-muted: #6F6B85;
    --v5-line: rgba(24, 17, 44, .10);
    --v5-purple: var(--color-brand-purple, #8A2BE2);
    --v5-deep: var(--color-brand-purple-deep, #6200A4);
    --v5-violet: var(--color-brand-dark-violet, #25104A);
    --v5-orange: var(--color-brand-orange, #FB7501);
    --v5-alert: var(--color-status-alert, #D94900);
    --v5-ground: var(--color-bg-light, #F4F6FC);
    --v5-r: var(--radius-card, 16px);
    --v5-r-sm: 12px;
    --v5-pill: var(--radius-pill, 100px);
    --v5-shadow: 0 6px 24px rgba(24, 17, 44, .06);
    --v5-shadow-lg: 0 24px 60px rgba(24, 17, 44, .13);

    font-family: var(--font-sans, "Inter Variable", "Inter", system-ui, -apple-system, sans-serif);
    color: var(--v5-body);
    font-size: 16px;
    line-height: 1.65;
    -webkit-font-smoothing: antialiased;
}
.<?= $scope ?> *, .<?= $scope ?> *::before, .<?= $scope ?> *::after { box-sizing: border-box; }
.<?= $scope ?> h2, .<?= $scope ?> h3, .<?= $scope ?> h4 { margin: 0; font-weight: 700; color: var(--v5-ink); letter-spacing: -.02em; }
.<?= $scope ?> p { margin: 0 0 14px; }
.<?= $scope ?> p:last-child { margin-bottom: 0; }
.<?= $scope ?> a { color: var(--v5-purple); }
.<?= $scope ?> strong, .<?= $scope ?> b { font-weight: 700; }
.<?= $scope ?> em { font-style: italic; }

.<?= $scope ?> .v5-inner { width: 100%; max-width: var(--width-container, 1380px); margin-inline: auto;
    padding-inline: clamp(16px, 3vw, 32px); }
.<?= $scope ?> .v5-section { padding-block: clamp(32px, 3.4vw, 56px); }
.<?= $scope ?> .v5-section--tight { padding-block: clamp(20px, 2.4vw, 32px); }
.<?= $scope ?> [id^="v5-s-"] { scroll-margin-top: calc(var(--v5-navtop, 80px) + 72px); }

/* bands */
.<?= $scope ?>.v5-band { background: transparent; }
.<?= $scope ?>.v5-band--white { background: #fff; }
.<?= $scope ?>.v5-band--deep {
    background:
        radial-gradient(1100px 520px at 12% -10%, rgba(138, 43, 226, .55) 0%, rgba(138, 43, 226, 0) 62%),
        linear-gradient(158deg, var(--v5-violet) 0%, var(--v5-deep) 100%);
    color: rgba(255, 255, 255, .84);
}
/* Only the band's own furniture turns white — anything inside a white card on the band
   (the recruitment price card, the deposit tab card) keeps the light-surface ink. */
.<?= $scope ?>.v5-band--deep > .v5-inner > .v5-head h2,
.<?= $scope ?>.v5-band--deep > .v5-inner > .v5-bundle-intro { color: #fff; }

/* section headings */
.<?= $scope ?> .v5-head { margin: 0 0 clamp(20px, 2vw, 30px); }
.<?= $scope ?> .v5-head h2 { font-size: clamp(25px, 2.5vw, 34px); line-height: 1.14; }
.<?= $scope ?> .v5-head--center { text-align: center; max-width: 860px; margin-inline: auto; }

/* generic card */
.<?= $scope ?> .v5-card { background: #fff; border: 1px solid var(--v5-line); border-radius: var(--v5-r);
    box-shadow: var(--v5-shadow); color: var(--v5-body); }
.<?= $scope ?> .v5-card--pad { padding: clamp(22px, 2.4vw, 36px); }

/* ---------------------------------------------------------------- jump nav -- */
.<?= $scope ?>.v5-nav { position: sticky; top: var(--v5-navtop, 80px); z-index: 30;
    background: color-mix(in srgb, var(--v5-ground) 88%, transparent);
    -webkit-backdrop-filter: blur(12px) saturate(150%); backdrop-filter: blur(12px) saturate(150%);
    border-bottom: 1px solid var(--v5-line); }
@supports not (background: color-mix(in srgb, red 50%, blue)) {
    .<?= $scope ?>.v5-nav { background: rgba(244, 246, 252, .92); }
}
.<?= $scope ?> .v5-nav__inner { display: flex; align-items: center; gap: 4px; overflow-x: auto;
    scrollbar-width: none; padding-block: 10px; }
.<?= $scope ?> .v5-nav__inner::-webkit-scrollbar { display: none; }
.<?= $scope ?> .v5-nav__inner a { flex: none; font-size: 13.5px; font-weight: 600; line-height: 1;
    color: var(--v5-muted); text-decoration: none; padding: 9px 14px; border-radius: var(--v5-pill);
    white-space: nowrap; transition: background-color .15s ease, color .15s ease; }
.<?= $scope ?> .v5-nav__inner a:hover { background: rgba(138, 43, 226, .09); color: var(--v5-deep); }
.<?= $scope ?> .v5-nav__inner a.is-current { background: var(--v5-purple); color: #fff; }

/* ------------------------------------------------------------------- tools -- */
.<?= $scope ?> .v5-tools { max-width: 760px; margin-inline: auto; }

/* --------------------------------------------------- tabbed question cards -- */
.<?= $scope ?> .v5-tabcard { overflow: hidden; max-width: 1100px; margin-inline: auto; }
.<?= $scope ?> .v5-tabcard__head { padding: clamp(20px, 2vw, 26px) clamp(20px, 2.2vw, 30px) 0; }
.<?= $scope ?> .v5-tabcard__head h2 { font-size: 21px; line-height: 1.2; }
.<?= $scope ?> .v5-tabcard__tabs { display: flex; flex-wrap: wrap; gap: 5px; padding: 5px;
    margin: 16px clamp(20px, 2.2vw, 30px) 0; background: var(--v5-ground); border-radius: var(--v5-pill); }
.<?= $scope ?> .v5-tabcard__tabs button { flex: 1 1 140px; min-width: 0; appearance: none; border: 0;
    background: transparent; color: var(--v5-ink); font: inherit; font-size: 13.5px; font-weight: 600;
    line-height: 1.25; padding: 11px 12px; border-radius: var(--v5-pill); cursor: pointer;
    transition: background-color .15s ease, color .15s ease, box-shadow .15s ease; }
.<?= $scope ?> .v5-tabcard__tabs button:hover { background: rgba(24, 17, 44, .05); }
.<?= $scope ?> .v5-tabcard__tabs button.is-active { background: var(--v5-purple); color: #fff;
    box-shadow: 0 4px 14px rgba(138, 43, 226, .3); }
.<?= $scope ?> .v5-subtabs { display: inline-flex; gap: 4px; padding: 4px; margin: 10px clamp(20px, 2.2vw, 30px) 0;
    background: var(--v5-ground); border-radius: var(--v5-pill); }
.<?= $scope ?> .v5-subtabs button { appearance: none; border: 0; background: transparent; color: var(--v5-muted);
    font: inherit; font-size: 12.5px; font-weight: 600; line-height: 1; padding: 9px 20px;
    border-radius: var(--v5-pill); cursor: pointer; transition: background-color .15s ease, color .15s ease; }
.<?= $scope ?> .v5-subtabs button:hover { color: var(--v5-ink); }
.<?= $scope ?> .v5-subtabs button.is-active { background: var(--v5-violet); color: #fff; }
.<?= $scope ?> .v5-tabcard__body { padding: 18px clamp(20px, 2.2vw, 30px) clamp(22px, 2.2vw, 30px); }
/* Production copy — styled as a label, but never case-transformed. */
.<?= $scope ?> .v5-tabcard__body h4 { font-size: 13.5px; font-weight: 700; letter-spacing: .01em;
    color: var(--v5-deep); margin: 24px 0 10px; padding-bottom: 8px;
    border-bottom: 1px solid rgba(138, 43, 226, .16); }
.<?= $scope ?> .v5-tabcard__body h4:first-child { margin-top: 4px; }
.<?= $scope ?> .v5-tabcard__body ul { list-style: none; margin: 0; padding: 0; display: grid; gap: 9px; }
.<?= $scope ?> .v5-tabcard__body li { position: relative; padding-left: 20px; font-size: 15.5px;
    line-height: 1.55; color: var(--v5-body); }
.<?= $scope ?> .v5-tabcard__body li::before { content: ""; position: absolute; left: 3px; top: 10px;
    width: 6px; height: 6px; border-radius: 50%; background: var(--v5-purple); opacity: .5; }
.<?= $scope ?> .v5-tabcard__body a { color: var(--v5-deep); font-weight: 600; text-decoration: underline;
    text-underline-offset: 2px; word-break: break-word; }

/* ----------------------------------------------------------- discovery grid -- */
.<?= $scope ?> .v5-qgrid { list-style: none; margin: 0; padding: 0; counter-reset: v5q;
    display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
.<?= $scope ?> .v5-qgrid li { counter-increment: v5q; position: relative; background: #fff;
    border: 1px solid var(--v5-line); border-radius: var(--v5-r-sm); box-shadow: var(--v5-shadow);
    padding: 18px 22px 18px 60px; font-size: 16px; line-height: 1.45; color: var(--v5-ink); }
.<?= $scope ?> .v5-qgrid li::before { content: counter(v5q); position: absolute; left: 18px; top: 16px;
    width: 28px; height: 28px; border-radius: 50%; background: rgba(138, 43, 226, .1);
    color: var(--v5-purple); font-size: 12.5px; font-weight: 700; line-height: 28px; text-align: center; }
.<?= $scope ?> .v5-qgrid li strong { font-weight: 600; }

/* ------------------------------------------------------------ long-form prose -- */
.<?= $scope ?> .v5-prose { font-size: 16px; line-height: 1.7; color: var(--v5-body); }
.<?= $scope ?> .v5-prose > ul { list-style: none; margin: 0; padding: 0; display: grid; gap: 13px; }
.<?= $scope ?> .v5-prose > ul > li { position: relative; padding-left: 26px; }
.<?= $scope ?> .v5-prose > ul > li::before { content: ""; position: absolute; left: 6px; top: 11px;
    width: 7px; height: 7px; border-radius: 50%; background: var(--v5-purple); }
.<?= $scope ?> .v5-prose ul ul { list-style: none; margin: 12px 0 0; padding: 0 0 0 20px; display: grid; gap: 9px;
    border-left: 2px solid rgba(138, 43, 226, .2); }
.<?= $scope ?> .v5-prose ul ul li { position: relative; padding-left: 18px; font-size: 15px; color: var(--v5-muted); }
.<?= $scope ?> .v5-prose ul ul li::before { content: ""; position: absolute; left: 0; top: 10px;
    width: 5px; height: 5px; border-radius: 50%; background: var(--v5-purple); opacity: .45; }
.<?= $scope ?> .v5-prose > ol { list-style: decimal; margin: 14px 0 0; padding-left: 26px;
    display: grid; gap: 9px; }
.<?= $scope ?> .v5-prose > ol > li { padding-left: 4px; }
.<?= $scope ?> .v5-prose > ol > li::marker { color: var(--v5-purple); font-weight: 700; }
.<?= $scope ?> .v5-callout { margin-top: 26px; border-left: 4px solid var(--v5-purple); border-radius: var(--v5-r-sm);
    background: linear-gradient(90deg, rgba(138, 43, 226, .11), rgba(138, 43, 226, 0));
    padding: 16px 20px; font-size: 17px; font-weight: 700; color: var(--v5-ink); }

/* ------------------------------------------------------- cannot-hire-for list -- */
.<?= $scope ?> .v5-warn { border-left: 5px solid var(--v5-alert); }
.<?= $scope ?> .v5-warn__title { display: flex; align-items: flex-start; gap: 11px; margin: 0 0 20px;
    font-size: 17px; font-weight: 700; font-style: italic; color: var(--v5-alert); }
.<?= $scope ?> .v5-warn__title svg { width: 21px; height: 21px; flex: none; margin-top: 2px; fill: var(--v5-alert); }
.<?= $scope ?> .v5-warn ul { list-style: none; margin: 0; padding: 0; display: grid; gap: 10px;
    grid-template-columns: repeat(2, minmax(0, 1fr)); }
.<?= $scope ?> .v5-warn li { position: relative; padding: 14px 16px 14px 42px; background: #FFF8F4;
    border: 1px solid rgba(217, 73, 0, .16); border-radius: var(--v5-r-sm); font-size: 15px;
    line-height: 1.55; color: var(--v5-body); }
.<?= $scope ?> .v5-warn li::before { content: "\2715"; position: absolute; left: 17px; top: 13px;
    font-size: 12px; font-weight: 700; color: var(--v5-alert); }
.<?= $scope ?> .v5-warn .v5-small { font-size: 14px; color: var(--v5-muted); }

/* --------------------------------------------------------------- rate table -- */
.<?= $scope ?> .v5-glass { background: rgba(255, 255, 255, .07); border: 1px solid rgba(255, 255, 255, .17);
    border-radius: var(--v5-r); }
.<?= $scope ?> .v5-rates { max-width: 840px; margin-inline: auto; padding: 8px clamp(18px, 2.5vw, 34px); }
.<?= $scope ?> .v5-rate { display: grid; grid-template-columns: 130px minmax(0, 1fr) 160px; align-items: center;
    gap: 18px; padding: 18px 0; border-bottom: 1px solid rgba(255, 255, 255, .13); }
.<?= $scope ?> .v5-rate:last-child { border-bottom: 0; }
.<?= $scope ?> .v5-rate__label { font-size: 23px; font-weight: 600; letter-spacing: -.01em;
    color: rgba(255, 255, 255, .78); font-variant-numeric: tabular-nums; }
.<?= $scope ?> .v5-rate__lead { overflow: hidden; white-space: nowrap; text-align: center; font-size: 13px;
    letter-spacing: 4px; color: rgba(255, 255, 255, .22); }
.<?= $scope ?> .v5-rate__value { text-align: right; font-size: 32px; line-height: 1.1; font-weight: 700;
    letter-spacing: -.025em; color: #fff; font-variant-numeric: tabular-nums; }
.<?= $scope ?> .v5-note-pill { display: block; width: fit-content; margin: 22px auto 0; padding: 11px 24px;
    border-radius: var(--v5-pill); background: rgba(255, 255, 255, .12);
    border: 1px solid rgba(255, 255, 255, .2); font-size: 15px; font-weight: 600; color: #fff; }

/* --------------------------------------------------------- bundle price grid -- */
.<?= $scope ?> .v5-bundle-intro { max-width: 900px; margin: 0 auto clamp(24px, 2.6vw, 36px); text-align: center;
    font-size: 16px; line-height: 1.65; font-weight: 400; color: rgba(255, 255, 255, .8); }
.<?= $scope ?> .v5-bundles { max-width: 1180px; margin-inline: auto; display: grid; gap: 14px;
    grid-template-columns: repeat(4, minmax(0, 1fr)); }
.<?= $scope ?> .v5-bundle { padding: 24px 22px; }
.<?= $scope ?> .v5-bundle__label { font-size: 14px; font-weight: 600; color: rgba(255, 255, 255, .62); }
.<?= $scope ?> .v5-bundle__value { margin-top: 8px; font-size: 14px; color: rgba(255, 255, 255, .62); }
.<?= $scope ?> .v5-bundle__value strong { font-size: 31px; font-weight: 700; letter-spacing: -.025em;
    color: #fff; font-variant-numeric: tabular-nums; }

/* ------------------------------------------------------ recruitment price card -- */
.<?= $scope ?> .v5-pricecard { max-width: 1000px; margin-inline: auto; background: #fff;
    border-radius: var(--v5-r); box-shadow: var(--v5-shadow-lg); overflow: hidden; color: var(--v5-body); }
.<?= $scope ?> .v5-pricecard__head { padding: clamp(24px, 2.6vw, 34px) clamp(24px, 2.6vw, 36px) 0; }
.<?= $scope ?> .v5-pricecard__title { font-size: clamp(22px, 2.1vw, 28px); line-height: 1.2; }
.<?= $scope ?> .v5-pricecard__grid { display: grid; grid-template-columns: 380px minmax(0, 1fr);
    gap: clamp(20px, 2.6vw, 34px); padding: clamp(20px, 2.2vw, 28px) clamp(24px, 2.6vw, 36px) clamp(24px, 2.6vw, 36px);
    align-items: start; }
.<?= $scope ?> .v5-pricecard__media img { display: block; width: 100%; height: 300px; object-fit: cover;
    border-radius: var(--v5-r-sm); }
.<?= $scope ?> .v5-pricecard__sub { font-size: clamp(18px, 1.6vw, 21px); line-height: 1.3; color: var(--v5-deep);
    background: linear-gradient(90deg, rgba(138, 43, 226, .12), rgba(138, 43, 226, .02));
    border-left: 4px solid var(--v5-purple); border-radius: var(--v5-r-sm); padding: 15px 18px; margin-bottom: 20px; }
.<?= $scope ?> .v5-facts { list-style: none; margin: 0 0 20px; padding: 0; display: grid; gap: 9px; }
.<?= $scope ?> .v5-facts li { position: relative; padding-left: 26px; font-size: 15px; line-height: 1.55; }
.<?= $scope ?> .v5-facts li::before { content: ""; position: absolute; left: 0; top: 4px; width: 16px; height: 16px;
    border-radius: 50%; background: rgba(138, 43, 226, .12); }
.<?= $scope ?> .v5-facts li::after { content: ""; position: absolute; left: 5px; top: 8px; width: 6px; height: 3px;
    border-left: 2px solid var(--v5-purple); border-bottom: 2px solid var(--v5-purple); transform: rotate(-45deg); }
.<?= $scope ?> .v5-pricecard__note { border-top: 1px dashed var(--v5-line); padding-top: 16px; margin-bottom: 22px;
    font-size: 14px; line-height: 1.6; color: var(--v5-muted); }
.<?= $scope ?> .v5-btn { display: inline-flex; align-items: center; justify-content: center; gap: 10px;
    background: var(--v5-orange); color: #fff; font-size: 17px; font-weight: 700; line-height: 1.2;
    padding: 16px 34px; border-radius: var(--v5-pill); text-decoration: none;
    box-shadow: 0 10px 24px rgba(251, 117, 1, .3);
    transition: transform .15s ease, box-shadow .15s ease, background-color .15s ease; }
.<?= $scope ?> .v5-btn:hover { background: #E56A00; transform: translateY(-1px);
    box-shadow: 0 14px 30px rgba(251, 117, 1, .38); }
.<?= $scope ?> .v5-btn--block { display: flex; width: 100%; }
.<?= $scope ?> .v5-btn-row { display: flex; justify-content: center; margin-top: clamp(24px, 2.6vw, 34px); }

/* ------------------------------------------------------- calendly + failsafe -- */
.<?= $scope ?> .v5-calwrap { max-width: 1180px; margin-inline: auto; background: #fff; border-radius: var(--v5-r);
    overflow: hidden; box-shadow: var(--v5-shadow-lg); }
.<?= $scope ?> .v5-calwrap .calendly-inline-widget { min-width: 320px; height: 700px; }
.<?= $scope ?> .v5-failsafe { max-width: 760px; margin: 20px auto 0; text-align: center; }
.<?= $scope ?> .v5-failsafe__toggle { cursor: pointer; background: rgba(255, 255, 255, .12);
    border: 1px solid rgba(255, 255, 255, .28); color: #fff; border-radius: var(--v5-pill);
    padding: 12px 26px; font: inherit; font-size: 15px; font-weight: 600;
    transition: background-color .15s ease; }
.<?= $scope ?> .v5-failsafe__toggle:hover { background: rgba(255, 255, 255, .2); }
.<?= $scope ?> .v5-failsafe__panel { display: none; margin-top: 14px; }
.<?= $scope ?> .v5-failsafe__panel.is-open { display: flex; flex-wrap: wrap; gap: 10px; justify-content: center; }
.<?= $scope ?> .v5-failsafe__btn { display: inline-block; padding: 12px 24px; background: #fff;
    color: var(--v5-deep); border-radius: var(--v5-pill); font-size: 15px; font-weight: 700;
    text-decoration: none; transition: transform .15s ease; }
.<?= $scope ?> .v5-failsafe__btn:hover { transform: translateY(-1px); }

/* -------------------------------------------------------------- objections -- */
.<?= $scope ?> .v5-obj { max-width: 1040px; margin-inline: auto; }
.<?= $scope ?> .v5-obj__intro { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px; margin-bottom: clamp(24px, 2.6vw, 34px); align-items: start; }
.<?= $scope ?> .v5-obj__panel { padding: clamp(20px, 2.2vw, 28px); }
.<?= $scope ?> .v5-obj__lead { font-size: 15px; font-weight: 700; color: var(--v5-ink); margin: 0 0 14px; }
.<?= $scope ?> .v5-obj__steps { display: grid; gap: 10px; }
.<?= $scope ?> .v5-obj__steps p { margin: 0; padding: 12px 15px; background: var(--v5-ground);
    border-radius: 10px; font-size: 14.5px; line-height: 1.55; }
.<?= $scope ?> .v5-obj__steps p b { color: var(--v5-deep); }
.<?= $scope ?> .v5-obj__script { display: grid; gap: 8px; }
.<?= $scope ?> .v5-obj__script p { margin: 0; font-size: 14.5px; line-height: 1.6; }
.<?= $scope ?> .v5-obj__script p.is-client { padding: 10px 14px; border-radius: 10px;
    background: rgba(217, 73, 0, .07); color: #8A4A21; font-weight: 600; }
.<?= $scope ?> .v5-obj__script p.is-rep { padding: 10px 14px; border-radius: 10px;
    background: rgba(138, 43, 226, .07); color: var(--v5-ink); }
.<?= $scope ?> .v5-sep { margin: 4px 0; font-size: 11px; line-height: 1; letter-spacing: -1px;
    color: rgba(24, 17, 44, .16); overflow: hidden; white-space: nowrap; }
.<?= $scope ?> .v5-acc { display: grid; gap: 9px; }
.<?= $scope ?> .v5-acc details { background: #fff; border: 1px solid var(--v5-line); border-radius: var(--v5-r-sm);
    overflow: hidden; transition: box-shadow .15s ease, border-color .15s ease; }
.<?= $scope ?> .v5-acc details:hover { border-color: rgba(138, 43, 226, .3); }
.<?= $scope ?> .v5-acc details[open] { box-shadow: var(--v5-shadow); border-color: rgba(138, 43, 226, .3); }
.<?= $scope ?> .v5-acc summary { display: flex; align-items: center; justify-content: space-between; gap: 14px;
    padding: 17px 20px; font-size: 16px; font-weight: 600; color: var(--v5-ink); cursor: pointer;
    list-style: none; }
.<?= $scope ?> .v5-acc summary::-webkit-details-marker { display: none; }
.<?= $scope ?> .v5-acc__ico { display: flex; align-items: center; justify-content: center; flex: none;
    width: 30px; height: 30px; border-radius: 50%; background: rgba(138, 43, 226, .1);
    transition: background-color .15s ease; }
.<?= $scope ?> .v5-acc details[open] .v5-acc__ico { background: var(--v5-purple); }
.<?= $scope ?> .v5-acc__icon { width: 12px; height: 12px; fill: var(--v5-purple); }
.<?= $scope ?> .v5-acc details[open] .v5-acc__icon { fill: #fff; }
.<?= $scope ?> .v5-acc summary .v5-acc__icon--minus { display: none; }
.<?= $scope ?> .v5-acc details[open] summary .v5-acc__icon--minus { display: block; }
.<?= $scope ?> .v5-acc details[open] summary .v5-acc__icon--plus { display: none; }
.<?= $scope ?> .v5-acc__body { padding: 18px 20px 22px; border-top: 1px solid var(--v5-line);
    font-size: 15.5px; line-height: 1.7; }
.<?= $scope ?> .v5-acc__body p { margin: 0 0 12px; }
.<?= $scope ?> .v5-acc__body ul { list-style: disc; margin: 0 0 14px; padding-left: 22px; }
.<?= $scope ?> .v5-acc__body ol { list-style: decimal; margin: 0 0 14px; padding-left: 22px; }
.<?= $scope ?> .v5-acc__body li { margin: 0 0 7px; }
.<?= $scope ?> .v5-acc__body strong { font-weight: 700; color: var(--v5-ink); }

/* ---------------------------------------------------------- role catalogue -- */
.<?= $scope ?> .v5-roles { max-width: 1180px; margin-inline: auto; display: grid; gap: 9px; }
.<?= $scope ?> .v5-dd { background: #fff; border: 1px solid var(--v5-line); border-radius: var(--v5-r-sm);
    overflow: hidden; transition: border-color .15s ease; }
.<?= $scope ?> .v5-dd:hover { border-color: rgba(138, 43, 226, .3); }
.<?= $scope ?> .v5-dd > input[type="checkbox"] { position: absolute; width: 1px; height: 1px; opacity: 0;
    pointer-events: none; }
.<?= $scope ?> .v5-dd__btn { display: flex; align-items: center; justify-content: space-between; gap: 14px;
    padding: 17px 22px; font-size: 16.5px; font-weight: 700; color: var(--v5-ink); cursor: pointer;
    transition: background-color .15s ease, color .15s ease; }
.<?= $scope ?> .v5-dd__btn:hover { background: var(--v5-ground); }
.<?= $scope ?> .v5-dd__btn::after { content: ""; flex: none; width: 9px; height: 9px; margin-right: 4px;
    border-right: 2px solid var(--v5-purple); border-bottom: 2px solid var(--v5-purple);
    transform: rotate(45deg) translate(-2px, -2px); transition: transform .2s ease; }
.<?= $scope ?> .v5-dd > input[type="checkbox"]:focus-visible + .v5-dd__btn { outline: 2px solid var(--v5-purple);
    outline-offset: -2px; }
.<?= $scope ?> .v5-dd > input[type="checkbox"]:checked + .v5-dd__btn { color: var(--v5-deep);
    background: linear-gradient(90deg, rgba(138, 43, 226, .1), rgba(138, 43, 226, .02)); }
.<?= $scope ?> .v5-dd > input[type="checkbox"]:checked + .v5-dd__btn::after {
    transform: rotate(-135deg) translate(-2px, -2px); }
.<?= $scope ?> .v5-dd__content { display: none; }
.<?= $scope ?> .v5-dd > input[type="checkbox"]:checked ~ .v5-dd__content { display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; padding: 18px 22px 22px;
    border-top: 1px solid var(--v5-line); }
.<?= $scope ?> .v5-role { padding: 14px 16px; background: var(--v5-ground); border-radius: 10px; }
.<?= $scope ?> .v5-role__title { font-size: 15px; font-weight: 700; color: var(--v5-ink); margin-bottom: 3px; }
.<?= $scope ?> .v5-role__desc { font-size: 14px; line-height: 1.55; color: var(--v5-muted); }

/* ------------------------------------------------------------- breakpoints -- */
@media (max-width: 980px) {
    .<?= $scope ?> .v5-qgrid,
    .<?= $scope ?> .v5-warn ul,
    .<?= $scope ?> .v5-obj__intro,
    .<?= $scope ?> .v5-dd > input[type="checkbox"]:checked ~ .v5-dd__content { grid-template-columns: 1fr; }
    .<?= $scope ?> .v5-bundles { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .<?= $scope ?> .v5-pricecard__grid { grid-template-columns: 1fr; }
    .<?= $scope ?> .v5-pricecard__media img { height: 260px; }
}
@media (max-width: 640px) {
    .<?= $scope ?> .v5-bundles { grid-template-columns: 1fr; }
    .<?= $scope ?> .v5-rate { grid-template-columns: 1fr auto; gap: 10px; }
    .<?= $scope ?> .v5-rate__lead { display: none; }
    .<?= $scope ?> .v5-rate__value { font-size: 26px; }
    .<?= $scope ?> .v5-tabcard__tabs button { flex: 1 1 100%; }
}
</style>
