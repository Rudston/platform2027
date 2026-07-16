@extends('layouts.public')

@php($isClaim = ($requestType ?? 'organisation_approval') === 'organisation_member_claim')

@section('title', 'Response recorded — '.$platformName)

@section('content')
    <div class="rounded-xl border border-border-muted bg-surface p-8 text-center shadow-sm">
        <div class="text-4xl" aria-hidden="true">📝</div>
        <h1 class="mt-3 text-2xl font-bold text-main">Your response has been recorded</h1>

        @if ($isClaim)
            <p class="mt-3 text-main">
                The membership claim by {{ $personName }} for {{ $organisationName }} has been
                rejected, and we&rsquo;ve let them know.
            </p>
        @else
            <p class="mt-3 text-main">
                The request to add {{ $organisationName }} to {{ $platformName }} has been declined,
                and we&rsquo;ve let the requester know.
            </p>
        @endif

        <p class="mt-4 text-sm text-muted">
            No further action is required — you can safely close this page.
        </p>
    </div>
@endsection
