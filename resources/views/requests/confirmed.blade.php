@extends('layouts.public')

@section('title', 'Approved — '.$platformName)

@section('content')
    <div class="rounded-xl border border-border-muted bg-surface p-8 text-center shadow-sm">
        <div class="text-4xl" aria-hidden="true">✅</div>
        <h1 class="mt-3 text-2xl font-bold text-main">Thank you — {{ $organisationName }} is approved</h1>

        <p class="mt-3 text-main">
            {{ $organisationName }} is now active on {{ $platformName }}, and we&rsquo;ve let the
            requester know.
        </p>

        <p class="mt-4 text-sm text-muted">
            No further action is required — you can safely close this page.
        </p>
    </div>
@endsection
