@extends('emails.layout')

@section('content')
    <h1 style="margin:0 0 16px;font-size:22px;color:#0b3d2e;">New contact form message</h1>
    <p style="margin:0 0 24px;">A visitor to directimpactnetwork.org has submitted the contact form.</p>

    <div style="margin-bottom:16px;">
        <div style="font-size:12px;text-transform:uppercase;letter-spacing:.05em;color:#71717a;">Full name</div>
        <div>{{ $fullName }}</div>
    </div>
    <div style="margin-bottom:16px;">
        <div style="font-size:12px;text-transform:uppercase;letter-spacing:.05em;color:#71717a;">Phone number</div>
        <div>{{ $phoneNumber }}</div>
    </div>
    <div style="margin-bottom:16px;">
        <div style="font-size:12px;text-transform:uppercase;letter-spacing:.05em;color:#71717a;">Email address</div>
        <div>{{ $email }}</div>
    </div>
    <div style="margin-bottom:16px;">
        <div style="font-size:12px;text-transform:uppercase;letter-spacing:.05em;color:#71717a;">Message</div>
        <div style="white-space:pre-wrap;">{{ $message }}</div>
    </div>
@endsection