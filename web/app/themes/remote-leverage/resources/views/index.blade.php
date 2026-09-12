@extends('layouts.app')

@section('content')
  @include('partials.blog-index', [
    'indexTitle' => __('Practical insights to scale smarter', 'remote-leverage'),
    'indexDescription' => __('Lessons for founders and operators who hire globally to build high performance remote teams.', 'remote-leverage'),
    'activeCategory' => null,
  ])
@endsection
