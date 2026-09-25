@extends('emails.layout')

@section('content')
    @if ($category)
        <p style="margin:0 0 8px;font-size:12px;text-transform:uppercase;letter-spacing:.05em;color:#45a113;">{{ $category }}</p>
    @endif
    <h1 style="margin:0 0 16px;font-size:22px;color:#45a113;">{{ $title }}</h1>

    @if ($coverImage)
        <img src="{{ $coverImage }}" alt="{{ $title }}" style="width:100%;height:auto;border:0;border-radius:8px;margin-bottom:16px;">
    @endif

    @if ($excerpt)
        <p style="margin:0 0 24px;">{{ $excerpt }}</p>
    @endif

    <a href="{{ $articleUrl }}" style="display:inline-block;background-color:#45a113;color:#ffffff;text-decoration:none;padding:12px 24px;border-radius:8px;font-weight:600;">Read the article</a>
@endsection