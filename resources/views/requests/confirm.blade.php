@extends('layouts.public')

@php
    $isClaim = ($requestType ?? 'organisation_approval') === 'organisation_member_claim';
    $approveLabel = $isClaim ? 'Confirm' : 'Approve';
    $declineLabel = $isClaim ? 'Reject' : 'Decline';
@endphp

@section('title', ($isClaim ? 'Confirm membership' : 'Approve '.$organisationName).' — '.$platformName)

@section('content')
    <div class="rounded-xl border border-border-muted bg-surface p-8 shadow-sm">
        <p class="text-sm font-medium text-muted">{{ $platformName }}</p>

        @if ($isClaim)
            <h1 class="mt-2 text-2xl font-bold text-main">Confirm a membership role</h1>
            <p class="mt-4 text-main">
                <span class="font-semibold">{{ $personName }}</span> says they are a member of staff
                or the board of <span class="font-semibold">{{ $organisationName }}</span>.
                As the organisation&rsquo;s contact, please confirm or reject this.
            </p>
        @else
            <h1 class="mt-2 text-2xl font-bold text-main">Approve {{ $organisationName }}</h1>
            <p class="mt-4 text-main">
                <span class="font-semibold">{{ $personName }}</span> has requested to add
                <span class="font-semibold">{{ $organisationName }}</span> to {{ $platformName }}.
                As the organisation&rsquo;s contact, please approve or decline this request.
            </p>
        @endif

        {{-- Approve / Confirm --}}
        <form method="POST" action="{{ route('requests.confirm.approve', $token) }}" class="mt-6">
            @csrf
            <button type="submit"
                    class="w-full rounded-lg bg-green-600 px-4 py-2.5 font-semibold text-white transition hover:bg-green-700">
                {{ $approveLabel }}
            </button>
        </form>

        {{-- Decline / Reject (with an optional note for the requester) --}}
        <form method="POST" action="{{ route('requests.confirm.deny', $token) }}" class="mt-4">
            @csrf
            <label for="response_note" class="block text-sm font-medium text-muted">
                Reason (optional)
            </label>
            <textarea id="response_note" name="response_note" rows="3" maxlength="500"
                      class="mt-1 w-full rounded-lg border border-border-muted bg-surface px-3 py-2 text-main placeholder:text-muted"
                      placeholder="Add an optional note…"></textarea>
            <button type="submit"
                    class="mt-3 w-full rounded-lg border border-border-muted px-4 py-2.5 font-semibold text-main transition hover:bg-border-muted">
                {{ $declineLabel }}
            </button>
        </form>

        <p class="mt-6 text-center text-xs text-muted">
            This link expires at {{ $expiresAt }}.
        </p>
    </div>
@endsection
