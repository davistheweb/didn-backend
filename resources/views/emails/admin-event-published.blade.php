@extends('emails.layout')

@section('content')
    <p style="margin:0 0 8px;font-size:12px;text-transform:uppercase;letter-spacing:.05em;color:#45a113;">{{ $eventType }}</p>
    <h1 style="margin:0 0 16px;font-size:22px;color:#45a113;">{{ $title }}</h1>

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

    <p style="margin:0 0 8px;">This event has been published to the website. Newsletter subscribers have been notified.</p>

    <a href="{{ $eventUrl }}" style="display:inline-block;background-color:#45a113;color:#ffffff;text-decoration:none;padding:12px 24px;border-radius:8px;font-weight:600;">View the event</a>
@endsection