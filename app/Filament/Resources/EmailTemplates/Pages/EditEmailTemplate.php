<?php

namespace App\Filament\Resources\EmailTemplates\Pages;

use App\Filament\Resources\EmailTemplates\EmailTemplateResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditEmailTemplate extends EditRecord
{
    protected static string $resource = EmailTemplateResource::class;

    /**
     * Load the full per-locale translations arrays into the form so each
     * locale tab is populated (the model's accessors otherwise return only
     * the current locale's string for subject/body).
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['subject'] = $this->record->getTranslations('subject');
        $data['body'] = $this->record->getTranslations('body');

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
