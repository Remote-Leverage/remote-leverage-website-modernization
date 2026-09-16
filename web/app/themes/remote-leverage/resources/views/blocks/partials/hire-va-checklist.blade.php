{{-- Reassurance checklist (2 columns, simple white checkmark). Extracted so the hero can
     render it either inside the left column (default) or as its own grid child, without
     the markup existing twice. --}}
<div class="grid grid-cols-1 sm:grid-cols-2 gap-y-3.5 gap-x-6">
    @foreach ($checklist as $item)
        <div class="flex items-center gap-3 text-white text-sm sm:text-base font-medium">
            <svg class="w-4 h-4 text-white shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                <polyline points="20 6 9 17 4 12" />
            </svg>
            <span>{{ $item }}</span>
        </div>
    @endforeach
</div>
