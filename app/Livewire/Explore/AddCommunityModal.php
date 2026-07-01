<?php

namespace App\Livewire\Explore;

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

    public function render()
    {
        return view('livewire.explore.add-community-modal');
    }
}
