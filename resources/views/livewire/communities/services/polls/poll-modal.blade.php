@php
    use App\Enums\Polls\PollEligibility;
    use App\Enums\Polls\PollResponseShape;
@endphp
<div class="p-10">
    <h2 class="text-lg font-semibold text-main">
        {{ $pollId === null ? __('polls.poll.create_title') : __('polls.poll.edit_title') }}
    </h2>

    @if ($pollId !== null)
        {{-- A poll stays editable until its FIRST response, published or not. --}}
        <p class="mt-1 text-xs text-muted">{{ __('polls.poll.amendable_note') }}</p>
    @endif

    <form wire:submit="save" class="mt-6 space-y-6">
        {{-- Basics --}}
        <div class="space-y-4">
            <div>
                <label for="poll-title" class="block text-sm font-medium text-main">{{ __('polls.poll.title') }}</label>
                <input id="poll-title" type="text" wire:model="title" placeholder="{{ __('polls.poll.title_placeholder') }}"
                       class="mt-1 w-full rounded-lg border border-border-muted bg-surface px-3 py-2 text-sm text-main placeholder:text-muted">
                @error('title') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="poll-description" class="block text-sm font-medium text-main">{{ __('polls.poll.description') }}</label>
                <textarea id="poll-description" wire:model="description" rows="2" placeholder="{{ __('polls.poll.description_placeholder') }}"
                          class="mt-1 w-full rounded-lg border border-border-muted bg-surface px-3 py-2 text-sm text-main placeholder:text-muted"></textarea>
            </div>

            <div>
                <label for="poll-prompt" class="block text-sm font-medium text-main">{{ __('polls.poll.prompt') }}</label>
                <input id="poll-prompt" type="text" wire:model="prompt" placeholder="{{ __('polls.poll.prompt_placeholder') }}"
                       class="mt-1 w-full rounded-lg border border-border-muted bg-surface px-3 py-2 text-sm text-main placeholder:text-muted">
                <p class="mt-1 text-xs text-muted">{{ __('polls.poll.prompt_help') }}</p>
                @error('prompt') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        {{-- Shape drives the legal tally methods; the rule lives on the enum. --}}
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="poll-shape" class="block text-sm font-medium text-main">{{ __('polls.poll.shape') }}</label>
                <select id="poll-shape" wire:model.live="shape"
                        class="mt-1 w-full rounded-lg border border-border-muted bg-surface px-3 py-2 text-sm text-main">
                    @foreach (PollResponseShape::cases() as $case)
                        <option value="{{ $case->value }}">{{ __('polls.shape.'.$case->value) }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="poll-tally" class="block text-sm font-medium text-main">{{ __('polls.poll.tally_method') }}</label>
                <select id="poll-tally" wire:model="tallyMethod"
                        class="mt-1 w-full rounded-lg border border-border-muted bg-surface px-3 py-2 text-sm text-main">
                    @foreach ($this->allowedTallyMethods as $method)
                        <option value="{{ $method->value }}">{{ __('polls.method.'.$method->value) }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-muted">{{ __('polls.poll.tally_help') }}</p>
            </div>
        </div>

        @if ($shape === PollResponseShape::RankedChoice->value)
            <label class="flex items-start gap-2 text-sm text-main">
                <input type="checkbox" wire:model="requireFullRanking" class="mt-0.5 rounded border-border-muted">
                <span>{{ __('polls.poll.require_full_ranking') }}</span>
            </label>
        @endif

        @if ($shape === PollResponseShape::Rating->value)
            <div>
                <label for="poll-scale" class="block text-sm font-medium text-main">{{ __('polls.poll.rating_scale') }}</label>
                @if ($this->ratingScales->isEmpty())
                    <p class="mt-1 text-sm text-amber-700">{{ __('polls.poll.no_rating_scales') }}</p>
                @else
                    <select id="poll-scale" wire:model="ratingScaleId"
                            class="mt-1 w-full rounded-lg border border-border-muted bg-surface px-3 py-2 text-sm text-main">
                        <option value="">—</option>
                        @foreach ($this->ratingScales as $scale)
                            <option value="{{ $scale->id }}">{{ $scale->name }}</option>
                        @endforeach
                    </select>
                @endif
            </div>
        @endif

        {{-- Options --}}
        <div>
            <span class="block text-sm font-medium text-main">{{ __('polls.poll.options') }}</span>
            <div class="mt-2 space-y-2">
                @foreach ($options as $index => $option)
                    <div class="flex items-center gap-2" wire:key="option-{{ $index }}">
                        <input type="text" wire:model="options.{{ $index }}" placeholder="{{ __('polls.poll.option_placeholder') }}"
                               class="flex-1 rounded-lg border border-border-muted bg-surface px-3 py-2 text-sm text-main placeholder:text-muted">
                        @if (count($options) > 2)
                            <button type="button" wire:click="removeOption({{ $index }})"
                                    class="rounded px-2 py-1 text-sm text-muted hover:text-red-600">{{ __('polls.poll.remove_option') }}</button>
                        @endif
                    </div>
                @endforeach
            </div>
            <button type="button" wire:click="addOption"
                    class="mt-2 text-sm text-indigo-600 hover:underline">{{ __('polls.poll.add_option') }}</button>
            @error('options') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        {{-- Who, and when --}}
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="poll-eligibility" class="block text-sm font-medium text-main">{{ __('polls.poll.eligibility') }}</label>
                <select id="poll-eligibility" wire:model="eligibility"
                        class="mt-1 w-full rounded-lg border border-border-muted bg-surface px-3 py-2 text-sm text-main">
                    @foreach (PollEligibility::cases() as $case)
                        <option value="{{ $case->value }}">{{ __('polls.eligibility_option.'.$case->value) }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="poll-qualifying" class="block text-sm font-medium text-main">{{ __('polls.poll.qualifying_date') }}</label>
                <input id="poll-qualifying" type="datetime-local" wire:model="qualifyingDate"
                       class="mt-1 w-full rounded-lg border border-border-muted bg-surface px-3 py-2 text-sm text-main">
                <p class="mt-1 text-xs text-muted">{{ __('polls.poll.qualifying_help') }}</p>
            </div>

            <div>
                <label for="poll-opens" class="block text-sm font-medium text-main">{{ __('polls.poll.opens_at') }}</label>
                <input id="poll-opens" type="datetime-local" wire:model="opensAt"
                       class="mt-1 w-full rounded-lg border border-border-muted bg-surface px-3 py-2 text-sm text-main">
            </div>

            <div>
                <label for="poll-closes" class="block text-sm font-medium text-main">{{ __('polls.poll.closes_at') }}</label>
                <input id="poll-closes" type="datetime-local" wire:model="closesAt"
                       class="mt-1 w-full rounded-lg border border-border-muted bg-surface px-3 py-2 text-sm text-main">
            </div>
        </div>

        {{-- Flags --}}
        <div class="space-y-2">
            {{-- No attribution control by design: who chose what is withheld
                 from everyone, unconditionally (docs/adr/0004). --}}
            <label class="flex items-start gap-2 text-sm text-main">
                <input type="checkbox" wire:model="allowResponseUpdate" class="mt-0.5 rounded border-border-muted">
                <span>{{ __('polls.poll.allow_response_update') }}</span>
            </label>
            <label class="flex items-start gap-2 text-sm text-main">
                <input type="checkbox" wire:model="publishResults" class="mt-0.5 rounded border-border-muted">
                <span>{{ __('polls.poll.publish_results') }}</span>
            </label>
        </div>

        <div class="flex justify-end gap-2 pt-2">
            {{-- wire:click, NOT Alpine's $dispatch: wire-elements listens for the
                     component method, and an Alpine DOM event never reaches it. --}}
                <button type="button" wire:click="closeModal"
                    class="rounded-lg border border-border-muted px-4 py-2 text-sm text-main hover:bg-border-muted">
                {{ __('ui.cancel') }}
            </button>
            <button type="submit"
                    class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-indigo-700">
                {{ __('polls.poll.save') }}
            </button>
        </div>
    </form>
</div>
