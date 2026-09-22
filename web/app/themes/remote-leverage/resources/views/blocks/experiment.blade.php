{{-- One arm of a PostHog A/B test: wraps ordinary blocks and paints them only for visitors assigned that variant. Two of these with the same flag key make a test, on one URL, with no page per variant. --}}

{{--
  See app/Blocks/ExperimentBlock.php and docs/ab-testing.md.

  The default arm renders visible; every other arm renders `hidden`. That ordering is the whole
  fallback strategy: JavaScript off, PostHog blocked, /flags/ down or an unknown variant name all
  land on a correct page with no timeout and no layout shift. Hiding every arm and revealing the
  winner would turn all four into a blank section.
--}}
@php
    $flag = $flag ?? '';
    $variant = $variant ?? '';
    $incomplete = $flag === '' || $variant === '';
@endphp

@if ($incomplete)
    @if ($isEditor)
        <div class="border-2 border-dashed border-red-400 bg-red-50 p-4 text-sm text-red-700">
            <strong>Experiment variant not configured.</strong>
            Set both a feature flag key and a variant name in the block sidebar, or this arm will
            not render on the front end.
            <div class="mt-3 border-t border-red-200 pt-3">
                <InnerBlocks />
            </div>
        </div>
    @endif
@elseif ($isEditor)
    {{-- Every arm stays visible and labelled in the editor. --}}
    <div class="my-2 border-2 border-dashed border-violet-400 bg-violet-50/40">
        <div class="flex flex-wrap items-center gap-2 border-b border-violet-200 px-3 py-2 text-xs font-semibold tracking-wide text-violet-800 uppercase">
            <span>Experiment</span>
            <code class="rounded bg-violet-200/70 px-1.5 py-0.5 normal-case">{{ $flag }}</code>
            <span aria-hidden="true">&rarr;</span>
            <code class="rounded bg-violet-200/70 px-1.5 py-0.5 normal-case">{{ $variant }}</code>
            @if ($isDefault)
                <span class="rounded bg-violet-700 px-1.5 py-0.5 text-white">default</span>
            @endif
        </div>
        <div class="p-3">
            <InnerBlocks />
        </div>
    </div>
@else
    <div
        data-rl-exp="{{ $flag }}"
        data-rl-exp-variant="{{ $variant }}"
        @if ($isDefault) data-rl-exp-default @else hidden @endif
    >
        <InnerBlocks />
    </div>
    {{-- Inline and immediately after the markup, so a decision already in localStorage is applied
         while the parser is still in the body — before the first paint rather than after it. --}}
    <script>window.rlExp&&window.rlExp.resolve({!! wp_json_encode($flag) !!});</script>
@endif
