@php($groups = $this->groups)
<div>
    <h1 class="text-2xl font-bold text-main">{{ __('dashboard.communities.heading') }}</h1>

    @if ($groups['admin']->isEmpty() && $groups['member']->isEmpty())
        <div class="mt-4 rounded-lg border border-dashed border-border-muted p-10 text-center">
            <p class="text-sm text-muted">{{ __('dashboard.communities.none') }}</p>
        </div>
    @else
        {{-- Admin group first, then member; a group with no rows is omitted. --}}
        @foreach (['admin' => 'group_admin', 'member' => 'group_member'] as $key => $labelKey)
            @if ($groups[$key]->isNotEmpty())
                <section class="mt-6">
                    <h2 class="text-xs font-semibold uppercase tracking-wide text-muted">{{ __('dashboard.communities.'.$labelKey) }}</h2>
                    <ul class="mt-2 divide-y divide-border-muted rounded-lg border border-border-muted bg-surface">
                        @foreach ($groups[$key] as $row)
                            <li class="p-4" wire:key="mc-{{ $row['circle']->id }}">
                                {{-- Line 1: geographic breadcrumb trail --}}
                                <x-circle-breadcrumb :ancestors="$row['ancestors']" />
                                {{-- Line 2: circle name + role badge --}}
                                <div class="mt-1 flex items-center gap-2">
                                    <a href="{{ route('communities.show', $row['circle']) }}" wire:navigate
                                       class="font-medium text-indigo-600 hover:underline">{{ $row['circle']->name }}</a>
                                    @if ($row['isAdmin'])
                                        <span class="rounded-full border border-indigo-400 px-2 py-0.5 text-xs font-medium text-indigo-600">{{ __('dashboard.communities.badge_admin') }}</span>
                                    @else
                                        <span class="rounded-full border border-border-muted px-2 py-0.5 text-xs text-muted">{{ __('dashboard.communities.badge_member') }}</span>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        @endforeach
    @endif

    {{-- Recently Visited: distinct communities the viewer browsed but hasn't
         joined (members already appear above), most-recent-first, capped at 8. --}}
    @php($recent = $this->recentlyVisited)
    <section class="mt-8">
        <h2 class="text-xs font-semibold uppercase tracking-wide text-muted">{{ __('dashboard.communities.recent_heading') }}</h2>
        @if ($recent->isEmpty())
            <div class="mt-2 rounded-lg border border-dashed border-border-muted p-6 text-center">
                <p class="text-sm text-muted">{{ __('dashboard.communities.recent_none') }}</p>
            </div>
        @else
            <ul class="mt-2 divide-y divide-border-muted rounded-lg border border-border-muted bg-surface">
                @foreach ($recent as $row)
                    <li class="p-4" wire:key="rv-{{ $row['circle']->id }}">
                        <x-circle-breadcrumb :ancestors="$row['ancestors']" />
                        <div class="mt-1">
                            <a href="{{ route('communities.show', $row['circle']) }}" wire:navigate
                               class="font-medium text-indigo-600 hover:underline">{{ $row['circle']->name }}</a>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>
