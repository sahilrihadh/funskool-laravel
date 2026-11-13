@extends('layouts.storefront')

@section('title', $page->meta_title ?: $page->title)

@section('content')
    <article class="max-w-3xl mx-auto bg-white border border-black/5 rounded-2xl p-6 sm:p-10">
        <h1 class="font-display text-3xl font-bold text-ink-900 mb-6">{{ $page->title }}</h1>
        {{-- Content comes from the admin RichEditor, which only emits sanitised HTML. --}}
        <div class="prose max-w-none text-ink-900/80 leading-relaxed [&_h2]:font-display [&_h2]:text-xl [&_h2]:font-bold [&_h2]:mt-6 [&_h2]:mb-2 [&_p]:mb-3 [&_ul]:list-disc [&_ul]:pl-5 [&_a]:text-brand-600">
            {!! str($page->content)->sanitizeHtml() !!}
        </div>
        <p class="mt-8 text-xs text-ink-900/40">Last updated {{ $page->updated_at->format('M j, Y') }}</p>
    </article>
@endsection
