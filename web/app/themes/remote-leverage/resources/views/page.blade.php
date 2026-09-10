@extends('layouts.app')

@section('content')
  @while(have_posts()) @php(the_post())
    @if(! has_blocks())
      @include('partials.page-header')
    @endif
    @includeFirst(['partials.content-page', 'partials.content'])
  @endwhile
@endsection
