<?php

namespace App\Livewire\Explore;

use LivewireUI\Modal\ModalComponent;

class RequestLocationModal extends ModalComponent
{
    /** Name of the parent LocalMunicipality or City whose MainPlaces are listed. */
    public string $parentLocationName;

    /** Parent circle id — stored for the future save; not acted on yet. */
    public int $parentCircleId;

    /** The unlisted location name the user types (no validation/save yet). */
    public string $locationName = '';

    public function mount(string $parentLocationName, int $parentCircleId): void
    {
        $this->parentLocationName = $parentLocationName;
        $this->parentCircleId = $parentCircleId;
    }

    public function sendRequest(): void
    {
        // TODO: implement save/notification logic
        $this->closeModal();
    }

    public function render()
    {
        return view('livewire.explore.request-location-modal');
    }
}
