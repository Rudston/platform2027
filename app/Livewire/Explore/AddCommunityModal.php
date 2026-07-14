<?php

namespace App\Livewire\Explore;

use App\Enums\CircleStatus;
use App\Enums\CommunityType;
use App\Filament\Resources\Requests\RequestResource;
use App\Models\Circles\Circle;
use App\Models\Organisation;
use App\Models\Communication\Request;
use App\Models\User;
use App\Services\Circles\CircleCreationService;
use App\Services\Communication\EmailServiceHandler;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LivewireUI\Modal\ModalComponent;
use Throwable;

class AddCommunityModal extends ModalComponent
{
    /** Community type FQCN (kept for the future add form; not used for display). */
    public string $type;

    /** Natural-language phrase incl. article, e.g. "an Organisation Community". */
    public string $label;

    /** Parent circle (current geographic selection) the new community nests under. */
    public ?int $circleId = null;

    /*
    |--------------------------------------------------------------------------
    | Organisation form
    |--------------------------------------------------------------------------
    */

    public string $organisationName = '';

    public string $organisationWebsite = '';

    public string $organisationDescription = '';

    public string $contactName = '';

    public string $contactEmail = '';

    public string $contactJobTitle = '';

    /** Set when an organisation community for this name already exists. */
    public bool $duplicateWarning = false;

    public function mount(string $type, string $label, ?int $circleId = null): void
    {
        $this->type = $type;
        $this->label = $label;
        $this->circleId = $circleId;
    }

    /**
     * @return array<string, array<int, string>>
     */
    protected function rules(): array
    {
        return [
            'organisationName' => ['required', 'string', 'max:150'],
            'organisationWebsite' => ['nullable', 'url', 'max:255'],
            'organisationDescription' => ['nullable', 'string', 'max:800'],
            'contactName' => ['required', 'string', 'max:150'],
            'contactEmail' => ['required', 'email', 'max:150'],
            'contactJobTitle' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * Create the organisation, its (pending) circle, and an external approval
     * request, then email the contact to approve or deny.
     */
    public function submitOrganisation(): void
    {
        /** @var User|null $user */
        $user = auth()->user();

        if (! $user) {
            return;
        }

        $this->validate();

        // Duplicate: an organisation of this name that already owns a community.
        $isDuplicate = Organisation::whereRaw('LOWER(name) = ?', [Str::lower(trim($this->organisationName))])
            ->whereHas('community')
            ->exists();

        if ($isDuplicate) {
            $this->duplicateWarning = true;

            return;
        }

        $request = DB::transaction(function () use ($user): Request {
            $organisation = Organisation::create([
                'name' => $this->organisationName,
                'description' => $this->organisationDescription ?: null,
                'website' => $this->organisationWebsite ?: null,
                'contact_person' => $this->contactName,
                'contact_email' => $this->contactEmail,
                'contact_job_title' => $this->contactJobTitle ?: null,
            ]);

            $parent = $this->circleId ? Circle::find($this->circleId) : null;

            $circle = app(CircleCreationService::class)->create(
                type: CommunityType::Organisation,
                data: [
                    'name' => $this->organisationName,
                    'description' => $this->organisationDescription ?: null,
                ],
                parentCircle: $parent,
                organisation: $organisation,
            );

            // The community stays pending until the contact approves it.
            $circle->update(['status' => CircleStatus::Pending]);

            return Request::createForOrganisation(
                requester: $user,
                circle: $circle,
                organisation: $organisation,
                respondentEmail: $this->contactEmail,
                metadata: [
                    'contact_name' => $this->contactName,
                    'contact_job_title' => $this->contactJobTitle,
                    'organisation_website' => $this->organisationWebsite,
                ],
            );
        });

        // Notify the contact outside the transaction so a mail failure never
        // rolls back the request. Log the outcome either way.
        try {
            app(EmailServiceHandler::class)->sendTemplate(
                'email.organisation_approval_request',
                $this->contactEmail,
                [
                    'contact_name' => $this->contactName,
                    'organisation_name' => $this->organisationName,
                    'requester_name' => $user->name,
                    // Link to the GET landing page where the contact clicks the
                    // real (POST) Approve/Decline actions — email clicks are GET.
                    'review_url' => route('requests.confirm', $request->token),
                    'expires_at' => $request->token_expires_at->format('d M Y'),
                ],
            );
            $request->logEmail('email.organisation_approval_request', $this->contactEmail, 'sent');
        } catch (Throwable $e) {
            $request->logEmail('email.organisation_approval_request', $this->contactEmail, 'failed', $e->getMessage());
        }

        // Heads-up to the internal admin responsible for this request's area, if
        // one was resolved. Any admin/superadmin can act in Filament — this is
        // only a notification. Sent and logged separately so it never affects
        // the contact-facing flow.
        $responsibleAdmin = $request->responsibleAdmin;

        if ($responsibleAdmin !== null) {
            try {
                app(EmailServiceHandler::class)->sendTemplate(
                    'email.organisation_approval_admin_notice',
                    $responsibleAdmin->email,
                    [
                        'admin_name' => $responsibleAdmin->name,
                        'organisation_name' => $this->organisationName,
                        'requester_name' => $user->name,
                        // Filament request view — where admins actually act.
                        // Panel named explicitly: this runs outside a panel
                        // request, so we can't rely on the "current panel".
                        'review_url' => RequestResource::getUrl('view', ['record' => $request], panel: 'admin'),
                    ],
                );
                $request->logEmail('email.organisation_approval_admin_notice', $responsibleAdmin->email, 'sent');
            } catch (Throwable $e) {
                $request->logEmail('email.organisation_approval_admin_notice', $responsibleAdmin->email, 'failed', $e->getMessage());
            }
        }

        $this->closeModal();
        $this->dispatch('community-submitted');
    }

    /** Reset every organisation-form field back to its default. */
    public function resetOrganisationForm(): void
    {
        $this->reset([
            'organisationName',
            'organisationWebsite',
            'organisationDescription',
            'contactName',
            'contactEmail',
            'contactJobTitle',
            'duplicateWarning',
        ]);
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
