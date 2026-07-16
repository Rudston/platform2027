<?php

namespace App\Http\Controllers;

use App\Enums\CircleStatus;
use App\Enums\CommunityType;
use App\Models\Circles\Circle;
use App\Models\Communication\Request as RequestModel;
use App\Services\Communication\EmailServiceHandler;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

class RequestController extends Controller
{
    public function __construct(private readonly EmailServiceHandler $emails)
    {
    }

    /**
     * Public approval landing page for an external respondent.
     *
     * States: valid + pending → confirm view; anything else (already actioned,
     * expired, or unknown token) → the "no longer valid" expired view. View
     * data never includes sequential ids — only the opaque token.
     */
    public function show(string $token): Response
    {
        $request = RequestModel::where('token', $token)->first();

        if (! $this->isActionable($request)) {
            return response()->view('requests.expired', [
                'platformName' => config('app.name'),
            ]);
        }

        return response()->view('requests.confirm', [
            'token' => $request->token,
            'organisationName' => (string) ($request->requestable?->name ?? ''),
            'requesterName' => (string) ($request->requester?->name ?? ''),
            'platformName' => config('app.name'),
            'expiresAt' => $request->token_expires_at?->format('j M Y, H:i') ?? '',
        ]);
    }

    /**
     * Approve the request: activate the circle, grant the requester the
     * circle_admin role, then notify both parties.
     */
    public function approve(string $token): Response
    {
        $request = RequestModel::where('token', $token)->first();

        if (! $this->isActionable($request)) {
            return response()->view('requests.expired', [
                'platformName' => config('app.name'),
            ]);
        }

        DB::transaction(function () use ($request): void {
            $request->update([
                'status' => 'approved',
                'responded_at' => now(),
            ]);

            $request->circle?->update(['status' => CircleStatus::Active]);

            // Grant the requester the circle-scoped admin role. Spatie teams:
            // set the team (circle) id on the registrar before assigning.
            if ($request->requester && $request->circle_id) {
                app(PermissionRegistrar::class)->setPermissionsTeamId($request->circle_id);
                $request->requester->assignRole('circle_admin');

                // Also give the community's creator an active membership. Direct
                // grant (skipChecks), not a rate-limited join. Organisation
                // creators are labelled organisation_member (matches the
                // circles:backfill-admin-memberships convention).
                $creatorRole = $request->circle?->circleable_type === CommunityType::Organisation->value
                    ? 'organisation_member'
                    : null;
                $request->circle?->joinAsMember($request->requester, internalRole: $creatorRole, skipChecks: true);
            }
        });

        // Notifications happen outside the transaction so a mail failure never
        // rolls back the approval. Each send is logged (success or failure).
        $organisationName = (string) ($request->requestable?->name ?? '');
        // Link to the community page, with a ?from= back-link to the Explore
        // view where the organisation lives (mirrors the "View" button).
        $communityUrl = $request->circle
            ? route('communities.show', [
                'circle' => $request->circle,
                'from' => $this->exploreBackUrl($request->circle),
            ])
            : url('/');

        if ($requester = $request->requester) {
            $this->attemptEmail($request, 'email.organisation_approval_confirmed', $requester->email, [
                'requester_name' => $requester->name,
                'organisation_name' => $organisationName,
                'community_url' => $communityUrl,
            ]);
        }

        if ($request->respondent_email) {
            $this->attemptEmail($request, 'email.organisation_approval_confirmed', $request->respondent_email, [
                'requester_name' => (string) ($request->requester?->name ?? ''),
                'organisation_name' => $organisationName,
                'community_url' => $communityUrl,
            ]);
        }

        return response()->view('requests.confirmed', [
            'organisationName' => $organisationName,
            'platformName' => config('app.name'),
        ]);
    }

    /**
     * Deny the request: record the denial (and optional note). The circle and
     * organisation stay pending. The requester is notified.
     */
    public function deny(Request $httpRequest, string $token): Response
    {
        $request = RequestModel::where('token', $token)->first();

        if (! $this->isActionable($request)) {
            return response()->view('requests.expired', [
                'platformName' => config('app.name'),
            ]);
        }

        $validated = $httpRequest->validate([
            'response_note' => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($request, $validated): void {
            $request->update([
                'status' => 'denied',
                'responded_at' => now(),
                'response_note' => $validated['response_note'] ?? null,
            ]);
            // Circle and Organisation are intentionally left pending.
        });

        if ($requester = $request->requester) {
            $this->attemptEmail($request, 'email.organisation_approval_denied', $requester->email, [
                'requester_name' => $requester->name,
                'organisation_name' => (string) ($request->requestable?->name ?? ''),
            ]);
        }

        return response()->view('requests.denied', [
            'organisationName' => (string) ($request->requestable?->name ?? ''),
            'platformName' => config('app.name'),
        ]);
    }

    /**
     * Relative Explore URL for the organisation's location (its parent circle,
     * with the Organisation bottom-filter) — used as the community page's
     * ?from= back link so it matches the "View" button's behaviour. Must be a
     * /explore path (CommunityPage only honours those).
     */
    private function exploreBackUrl(Circle $circle): string
    {
        return route('explore', array_filter([
            'circle' => $circle->parent_id,
            'community' => CommunityType::Organisation->name,
        ], static fn ($v) => $v !== null && $v !== ''), false);
    }

    /** A request can be acted on only while it exists, is pending and unexpired. */
    private function isActionable(?RequestModel $request): bool
    {
        return $request !== null
            && $request->status === 'pending'
            && ! $request->isExpired();
    }

    /**
     * Send one template email, recording the outcome on the request's email log.
     * Never throws — a mail failure must not break the approval/denial flow.
     *
     * @param  array<string, string|int|float>  $variables
     */
    private function attemptEmail(RequestModel $request, string $template, string $recipient, array $variables): void
    {
        try {
            $this->emails->sendTemplate($template, $recipient, $variables);
            $request->logEmail($template, $recipient, 'sent');
        } catch (Throwable $e) {
            $request->logEmail($template, $recipient, 'failed', $e->getMessage());
        }
    }
}
