@extends('layouts.public')

@section('title', 'Approve '.$organisationName.' — '.$platformName)

@section('content')
    <div class="rounded-xl border border-border-muted bg-surface p-8 shadow-sm">
        <p class="text-sm font-medium text-muted">{{ $platformName }}</p>
        <h1 class="mt-2 text-2xl font-bold text-main">Approve {{ $organisationName }}</h1>

        <p class="mt-4 text-main">
            <span class="font-semibold">{{ $requesterName }}</span> has requested to add
            <span class="font-semibold">{{ $organisationName }}</span> to {{ $platformName }}.
            As the organisation&rsquo;s contact, please approve or decline this request.
        </p>

        {{-- Approve --}}
        <form method="POST" action="{{ route('requests.confirm.approve', $token) }}" class="mt-6">
            @csrf
            <button type="submit"
                    class="w-full rounded-lg bg-green-600 px-4 py-2.5 font-semibold text-white transition hover:bg-green-700">
                Approve
            </button>
        </form>

        {{-- Decline (with an optional note for the requester) --}}
        <form method="POST" action="{{ route('requests.confirm.deny', $token) }}" class="mt-4">
            @csrf
            <label for="response_note" class="block text-sm font-medium text-muted">
                Reason for declining (optional)
            </label>
            <textarea id="response_note" name="response_note" rows="3" maxlength="500"
                      class="mt-1 w-full rounded-lg border border-border-muted bg-surface px-3 py-2 text-main placeholder:text-muted"
                      placeholder="Add an optional note for the requester…"></textarea>
            <button type="submit"
                    class="mt-3 w-full rounded-lg border border-border-muted px-4 py-2.5 font-semibold text-main transition hover:bg-border-muted">
                Decline
            </button>
        </form>

        <p class="mt-6 text-center text-xs text-muted">
            This link expires at {{ $expiresAt }}.
        </p>
    </div>
@endsection
