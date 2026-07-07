<?php

namespace App\Livewire\Explore;

use App\Enums\CommunityType;
use LivewireUI\Modal\ModalComponent;

class AddCommunityModal extends ModalComponent
{
    /** Community type FQCN (kept for the future add form; not used for display). */
    public string $type;

    /** Natural-language phrase incl. article, e.g. "an Organisation Community". */
    public string $label;

    public function mount(string $type, string $label): void
    {
        $this->type = $type;
        $this->label = $label;
    }

    /**
     * Content block key for the collapsible "how to add" guidance.
     *
     * Derived from the community TYPE (language-independent) — never from the
     * translated $label, which differs per locale. Null when the type has no
     * how-to block.
     */
    public function howToKey(): ?string
    {
        return match (CommunityType::tryFrom($this->type)) {
            CommunityType::Organisation   => 'community.how_to_add.organisation',
            CommunityType::Campaign       => 'community.how_to_add.campaign',
            CommunityType::Course         => 'community.how_to_add.course',
            CommunityType::Event          => 'community.how_to_add.event',
            CommunityType::ThemeCommunity => 'community.how_to_add.theme',
            default                       => null,
        };
    }

    public function render()
    {
        return view('livewire.explore.add-community-modal', [
            'howToKey' => $this->howToKey(),
        ]);
    }
}
