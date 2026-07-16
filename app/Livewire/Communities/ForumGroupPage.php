<?php

namespace App\Livewire\Communities;

use App\Models\Circles\Circle;
use App\Models\Forums\ForumGroup;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * A forum group's Discussions page. Placeholder body for this pass — the route,
 * (circle-scoped) model binding and stateless ?from= back-link are real so the
 * navigation works end-to-end; discussion content lands in a follow-up task.
 */
#[Layout('layouts.main')]
class ForumGroupPage extends Component
{
    public Circle $circle;

    public ForumGroup $group;

    /** Where the "back" link returns to — the Forums tab we came from. */
    public string $backUrl;

    public function mount(Circle $circle, ForumGroup $forumGroup): void
    {
        $this->circle = $circle;
        $this->group = $forumGroup;

        // Restore where we came from (?from=…); only accept an internal
        // /communities path (no open redirects). Fall back to the circle page.
        $this->backUrl = $this->resolveBackUrl(request()->query('from'));
    }

    private function resolveBackUrl(mixed $from): string
    {
        if (is_string($from) && str_starts_with($from, '/communities')) {
            return $from;
        }

        return route('communities.show', $this->circle);
    }

    public function render()
    {
        return view('livewire.communities.forum-group-page')->title($this->group->name);
    }
}
