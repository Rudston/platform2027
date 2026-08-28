@php
    use App\Enums\Polls\PollResponseShape;
    use App\Enums\Polls\TallyMethod;
    /** @var \App\Models\Polls\Poll $poll */
    /** @var \App\Models\Polls\PollGroup $group */
    /** @var string $backUrl */
    $state = $poll->stateKey();
    $shape = $this->question?->type;
    $result = $this->result;
    $stateStyles = [
        'draft'     => 'border-border-muted text-muted',
        'scheduled' => 'border-border-muted text-muted',
        'open'      => 'border-emerald-600 text-emerald-700',
        'closed'    => 'border-border-muted text-main',
        'concluded' => 'border-border-muted text-main',
        'cancelled' => 'border-red-600 text-red-700',
    ];
@endphp
<div class="mx-auto max-w-3xl px-4 py-8">
    <a href="{{ $backUrl }}" wire:navigate class="text-sm text-indigo-600 hover:underline">{{ __('polls.back_to_group') }}</a>

    <div class="mt-3 flex items-start justify-between gap-4">
        <div class="min-w-0">
            <h1 class="text-2xl font-semibold text-main">{{ $poll->title }}</h1>
            @if ($poll->description)
                <p class="mt-2 text-sm text-muted">{{ $poll->description }}</p>
            @endif

            {{-- Tags: what this poll is ABOUT, drawn from the platform-wide
                 theme vocabulary so "Water" means the same in every circle.
                 Read-only row; managers get an "Edit tags" toggle. --}}
            <div x-data="{ editingTags: false }" class="mt-3">
                @if ($this->tags->isNotEmpty() || $this->canManageTags)
                    <div class="flex flex-wrap items-center gap-2">
                        <x-tag-list :tags="$this->tags" />
                        @if ($this->canManageTags)
                            <button type="button" x-on:click="editingTags = !editingTags"
                                    class="inline-flex items-center gap-1 text-xs text-indigo-600 hover:underline"><x-icons.edit class="h-3.5 w-3.5" />{{ __('tags.edit') }}</button>
                        @endif
                    </div>
                @endif

                @if ($this->canManageTags)
                    <div x-show="editingTags" x-cloak class="mt-2">
                        <livewire:tags.tag-picker
                            :taggable-type="\App\Models\Polls\Poll::class"
                            :taggable-id="$poll->id"
                            :key="'poll-tags-'.$poll->id" />
                    </div>
                @endif
            </div>
        </div>
        <span class="shrink-0 rounded-full border px-2 py-0.5 text-xs {{ $stateStyles[$state] }}">
            {{ __('polls.state.'.$state) }}
        </span>
    </div>

    {{-- Turnout. The denominator was fixed at publish and cannot move. --}}
    <p class="mt-3 text-sm text-muted">
        @if ($poll->isDraft())
            {{ __('polls.state_hint.draft') }}
        @else
            {{ __('polls.turnout', ['responded' => $this->respondentCount, 'electorate' => $poll->electorateCount()]) }}
        @endif
    </p>

    @error('lifecycle') <p class="mt-3 rounded-lg border border-red-600 bg-surface p-3 text-sm text-red-700">{{ $message }}</p> @enderror

    {{-- Organiser actions. canEnd = the Organiser while still a member, or any
         circle admin. A circle admin can end a poll they cannot read. --}}
    @if ($this->canManage || $this->canEnd)
        <div class="mt-4 flex flex-wrap gap-2">
            {{-- Editable until the FIRST response, published or not. Once the
                 ballot is fixed, say why rather than silently dropping the
                 button. --}}
            @if ($this->canAmend)
                <button type="button"
                        wire:click="$dispatch('openModal', { component: 'communities.services.polls.poll-modal', arguments: { groupId: {{ $group->id }}, pollId: {{ $poll->id }} } })"
                        class="inline-flex items-center gap-1 rounded-lg border border-border-muted px-4 py-2 text-sm text-main hover:bg-border-muted">
                    <x-icons.edit class="h-4 w-4" />{{ __('polls.actions.edit_poll') }}
                </button>
            @elseif ($this->canManage && ! $poll->isCancelled() && ! $poll->isDraft())
                <p class="w-full text-xs text-muted">{{ __('polls.actions.not_amendable') }}</p>
            @endif

            @if ($poll->isDraft() && $this->canManage)
                <button type="button" wire:click="publish" wire:confirm="{{ __('polls.actions.publish_confirm') }}"
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    {{ __('polls.actions.publish') }}
                </button>
            @endif

            @if ($poll->isOpen() && $this->canEnd)
                <button type="button" wire:click="conclude" wire:confirm="{{ __('polls.actions.conclude_confirm') }}"
                        class="rounded-lg border border-border-muted px-4 py-2 text-sm text-main hover:bg-border-muted">
                    {{ __('polls.actions.conclude') }}
                </button>
                <button type="button" wire:click="cancelPoll" wire:confirm="{{ __('polls.actions.cancel_confirm') }}"
                        class="rounded-lg border border-red-600 px-4 py-2 text-sm text-red-700 hover:bg-border-muted">
                    {{ __('polls.actions.cancel_poll') }}
                </button>
            @endif
        </div>
    @endif

    {{-- The Prompt, then the ballot beside the poll's timing. --}}
    <div class="mt-6 rounded-lg border border-border-muted bg-surface p-5 shadow-sm">
        <p class="font-medium text-main">{{ $this->question?->text }}</p>

        @if ($flash !== '')
            <p class="mt-3 rounded-lg border border-emerald-600 bg-surface p-3 text-sm text-emerald-700">{{ $flash }}</p>
        @endif

        @error('response') <p class="mt-3 text-sm text-red-600">{{ $message }}</p> @enderror

        <div class="mt-4 grid gap-6 sm:grid-cols-3">
        <div class="sm:col-span-2">
        @if ($this->canRespond)
            <form wire:submit="submit" class="mt-4 space-y-3">
                @if ($shape === PollResponseShape::SingleChoice)
                    @foreach ($this->options as $option)
                        <label class="flex items-center gap-3 rounded-lg border border-border-muted p-3 text-sm text-main hover:bg-border-muted/40">
                            <input type="radio" wire:model="choice" value="{{ $option->id }}" name="choice">
                            <span>{{ $option->label }}</span>
                        </label>
                    @endforeach

                @elseif ($shape === PollResponseShape::RankedChoice)
                    @foreach ($this->options as $option)
                        <div class="flex items-center gap-3 rounded-lg border border-border-muted p-3 text-sm text-main">
                            <label class="flex items-center gap-2">
                                <span class="text-xs text-muted">{{ __('polls.respond.rank_label') }}</span>
                                <select wire:model="ranks.{{ $option->id }}"
                                        class="rounded-lg border border-border-muted bg-surface px-2 py-1 text-sm">
                                    <option value="">{{ __('polls.respond.rank_none') }}</option>
                                    @for ($i = 1; $i <= $this->options->count(); $i++)
                                        <option value="{{ $i }}">{{ $i }}</option>
                                    @endfor
                                </select>
                            </label>
                            <span>{{ $option->label }}</span>
                        </div>
                    @endforeach

                @elseif ($shape === PollResponseShape::Rating)
                    @foreach ($this->options as $option)
                        <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-border-muted p-3 text-sm text-main">
                            <span>{{ $option->label }}</span>

                            {{-- The scale says how it is drawn; we never infer
                                 it from its name or its number of points. --}}
                            @if ($this->ratingScale?->rendersAsStars())
                                <x-polls.star-rating
                                    :points="$this->scalePoints"
                                    property="scores.{{ $option->id }}"
                                    :selected="$scores[$option->id] ?? null"
                                    :label="$option->label" />
                            @else
                                <select wire:model="scores.{{ $option->id }}"
                                        class="rounded-lg border border-border-muted bg-surface px-2 py-1 text-sm">
                                    <option value="">{{ __('polls.respond.rank_none') }}</option>
                                    @foreach ($this->scalePoints as $point)
                                        <option value="{{ $point->id }}">{{ $point->label }}</option>
                                    @endforeach
                                </select>
                            @endif
                        </div>
                    @endforeach
                @endif

                <button type="submit"
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    {{ $this->hasResponded ? __('polls.respond.update') : __('polls.respond.submit') }}
                </button>
            </form>
        @else
            <ul class="mt-4 space-y-2">
                @foreach ($this->options as $option)
                    <li class="rounded-lg border border-border-muted p-3 text-sm text-main">{{ $option->label }}</li>
                @endforeach
            </ul>

            @if ($this->blockedReason)
                <p class="mt-3 text-sm text-muted">{{ __('polls.respond.'.$this->blockedReason) }}</p>
            @endif
        @endif
        </div>

        {{-- Timing. Every instant is rendered through inDisplayZone(): stored
             UTC, read in the wall clock the interface speaks. The labels switch
             tense on whether the moment has passed, so a scheduled poll reads
             "opens" and a running one reads "opened". --}}
        <aside class="sm:col-span-1">
            <p class="text-xs font-medium uppercase tracking-wide text-muted">{{ __('polls.timing.heading') }}</p>

            <dl class="mt-2 space-y-3 text-sm">
                @if ($poll->isDraft())
                    <p class="text-muted">{{ __('polls.timing.not_published') }}</p>
                @else
                    @if ($poll->opens_at)
                        <div>
                            <dt class="text-xs text-muted">
                                {{ $poll->opens_at->isPast() ? __('polls.timing.opened') : __('polls.timing.opens') }}
                            </dt>
                            <dd class="text-main">{{ $poll->opens_at->inDisplayZone()->format('d M Y, H:i') }}</dd>
                        </div>
                    @endif

                    <div>
                        @if ($poll->closes_at)
                            <dt class="text-xs text-muted">
                                {{ $poll->closes_at->isPast() ? __('polls.timing.closed') : __('polls.timing.closes') }}
                            </dt>
                            <dd class="text-main">{{ $poll->closes_at->inDisplayZone()->format('d M Y, H:i') }}</dd>
                        @else
                            {{-- No closing time: the poll runs until concluded. --}}
                            <dd class="text-muted">{{ __('polls.timing.no_close') }}</dd>
                        @endif
                    </div>

                    {{-- Only when it differs from the opening: a qualifying
                         date defaults to the publish moment, so showing it then
                         would just repeat the line above. When it IS earlier,
                         it is the more consequential of the two — it decides
                         who may respond at all, not merely when. --}}
                    @if ($poll->qualifying_date && ! $poll->qualifying_date->equalTo($poll->opens_at))
                        <div>
                            <dt class="text-xs text-muted">{{ __('polls.timing.qualifying') }}</dt>
                            <dd class="text-main">{{ $poll->qualifying_date->inDisplayZone()->format('d M Y, H:i') }}</dd>
                            <dd class="mt-0.5 text-xs text-muted">{{ __('polls.timing.qualifying_help') }}</dd>
                        </div>
                    @endif

                    @if ($poll->isCancelled())
                        <p class="text-red-700">{{ __('polls.timing.cancelled') }}</p>
                    @endif
                @endif
            </dl>

            {{-- Say which clock these are, having just fixed a bug where they
                 were two hours out with nothing on screen to reveal it. --}}
            <p class="mt-3 text-xs text-muted">
                {{ __('polls.timing.zone_note', ['zone' => now()->inDisplayZone()->format('T')]) }}
            </p>
        </aside>
        </div>
    </div>

    {{-- Result --}}
    <div class="mt-6 rounded-lg border border-border-muted bg-surface p-5 shadow-sm">
        <h2 class="font-medium text-main">
            {{ $result && ! $poll->hasResult() ? __('polls.result.live') : __('polls.result.heading') }}
        </h2>

        @if ($poll->isCancelled())
            <p class="mt-2 text-sm text-muted">{{ __('polls.result.none') }}</p>
        @elseif ($result === null)
            <p class="mt-2 text-sm text-muted">{{ __('polls.result.pending') }}</p>
        @elseif ($result->isEmpty())
            <p class="mt-2 text-sm text-muted">{{ __('polls.result.no_responses') }}</p>
        @else
            @if (! $poll->hasResult())
                <p class="mt-1 text-xs text-amber-700">{{ __('polls.result.live_note') }}</p>
            @endif

            <p class="mt-2 text-sm text-main">
                @if ($result->isTie())
                    <span class="font-semibold">{{ __('polls.result.tie') }}</span>
                @else
                    <span class="text-muted">{{ __('polls.result.winner') }}:</span>
                    <span class="font-semibold">{{ $this->optionLabels[$result->winnerOptionId] ?? '—' }}</span>
                @endif
            </p>

            <p class="mt-1 text-xs text-muted">
                {{ __('polls.result.turnout', ['responded' => $result->turnout, 'electorate' => $poll->electorateCount()]) }}
            </p>

            <p class="mt-4 text-xs font-medium uppercase tracking-wide text-muted">{{ __('polls.result.totals_heading') }}</p>
            <ul class="mt-1 space-y-1 text-sm text-main">
                @foreach ($result->totals as $optionId => $total)
                    <li class="flex justify-between gap-4">
                        <span>{{ $this->optionLabels[$optionId] ?? $optionId }}</span>
                        {{-- Formatted by the Result, which knows whether these
                             are means or counts. --}}
                        <span class="font-mono">{{ $result->formattedTotal($optionId) }}</span>
                    </li>
                @endforeach
            </ul>

            {{-- Instant runoff totals are FIRST PREFERENCES, so the winner is
                 often not the biggest number. Say so, or it reads as a bug. --}}
            @if ($result->method === TallyMethod::InstantRunoff)
                <p class="mt-3 text-xs text-muted">{{ __('polls.result.irv_note', ['rounds' => $result->rounds]) }}</p>
            @elseif ($result->method === TallyMethod::Borda)
                <p class="mt-3 text-xs text-muted">{{ __('polls.result.borda_note') }}</p>
            @elseif ($result->method === TallyMethod::AverageScore)
                <p class="mt-3 text-xs text-muted">{{ __('polls.result.average_note') }}</p>
            @endif

            @if ($poll->hasResult() && $poll->result_frozen_at)
                <p class="mt-3 text-xs text-muted">{{ __('polls.result.frozen', ['date' => $poll->result_frozen_at->diffForHumans()]) }}</p>
            @endif
        @endif
    </div>

    {{-- The Roster: names only once Closed, so a live poll is never a list of
         who has yet to comply. roster() THROWS if called early, so the guard is
         canSeeRoster (rosterIsVisible + standing in the Circle) rather than a
         truthiness check — a visitor reading a published Result sees the
         totals, never who responded. --}}
    @if ($this->canSeeRoster)
        <div class="mt-6 rounded-lg border border-border-muted bg-surface p-5 shadow-sm">
            <h2 class="font-medium text-main">{{ __('polls.result.roster_heading') }}</h2>
            <p class="mt-1 text-xs text-muted">{{ __('polls.result.roster_note') }}</p>
            <p class="mt-2 text-sm text-main">{{ $poll->roster()->pluck('name')->implode(', ') }}</p>
        </div>
    @endif

    {{-- Modal host for the edit dispatch above. --}}
    <livewire:wire-elements-modal />
</div>
