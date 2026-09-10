{{-- Case studies render their own hero/title inside the CaseStudyBlock itself, so this
     partial (unlike the generic content-single) skips the blog-post h1/byline/comments furniture. --}}
@php(the_content())
