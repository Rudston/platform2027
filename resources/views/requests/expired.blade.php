@extends('layouts.public')

@section('title', 'Link no longer valid — '.$platformName)

@section('content')
    <div class="rounded-xl border border-border-muted bg-surface p-8 text-center shadow-sm">
        <div class="text-4xl" aria-hidden="true">⏳</div>
        <h1 class="mt-3 text-2xl font-bold text-main">This link is no longer valid</h1>

        <p class="mt-3 text-main">
            This approval link has expired or has already been used, so it can no longer be actioned.
        </p>

        <p class="mt-4 text-sm text-muted">
            If you still need to respond, please contact the person who sent you this request and ask
            them to send a new link.
        </p>
    </div>
@endsection
