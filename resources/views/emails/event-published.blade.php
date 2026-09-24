@extends('emails.layout')

@section('content')
    <p style="margin:0 0 8px;font-size:12px;text-transform:uppercase;letter-spacing:.05em;color:#0b3d2e;">{{ $eventType }}</p>
    <h1 style="margin:0 0 16px;font-size:22px;color:#0b3d2e;">{{ $title }}</h1>

    @if ($description)
        <p style="margin:0 0 24px;">{{ $description }}</p>
    @endif

    <div style="margin-bottom:8px;">
        <strong>{{ $date }}</strong>
        @if ($time)
            &middot; {{ $time }}
        @endif
    </div>
    @if ($location)
        <p style="margin:0 0 24px;">{{ $location }}</p>
    @endif

    <a href="{{ $eventUrl }}" style="display:inline-block;background-color:#0b3d2e;color:#ffffff;text-decoration:none;padding:12px 24px;border-radius:8px;font-weight:600;">View event</a>
@endsection