<?php

namespace App\Filament\Resources\ContentBlocks\Pages;

use App\Filament\Resources\ContentBlocks\ContentBlockResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditContentBlock extends EditRecord
{
    protected static string $resource = ContentBlockResource::class;

    /**
     * Load the full per-locale translations array into the form so each
     * locale tab is populated (the model's `content` accessor otherwise
     * returns only the current locale's string).
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['content'] = $this->record->getTranslations('content');

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
