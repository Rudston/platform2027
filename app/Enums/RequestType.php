<?php

namespace App\Enums;

/**
 * The type of a Communication\Request. Backed by the string stored in
 * requests.type. Only OrganisationApproval and OrganisationMemberClaim have
 * live flows today; CircleJoin/LocationRequest/CircleAssociation are reserved
 * (referenced by the Governance Requests filter, never created yet).
 */
enum RequestType: string
{
    case OrganisationApproval    = 'organisation_approval';
    case CircleJoin              = 'circle_join';
    case LocationRequest         = 'location_request';
    case CircleAssociation       = 'circle_association';
    case OrganisationMemberClaim = 'organisation_member_claim';
}
