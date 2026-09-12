@extends('layouts.app')

@section('content')
  @php
    $term = is_category() ? get_queried_object() : null;
  @endphp

  @include('partials.blog-index', [
    'indexTitle' => $term?->name ?? wp_strip_all_tags(get_the_archive_title()),
    'indexDescription' => $term?->description
      ?: __('Practical insights on building high-performing remote teams. We share lessons from working with founders and operators who hire globally.', 'remote-leverage'),
    'activeCategory' => $term,
  ])
@endsection
