<?php

namespace App\Models;

use App\Models\Communities\OrganisationCommunity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Plain organisation entity. NOT a Circleable/Locatable community —
 * it is a standalone record that an OrganisationCommunity may belong to.
 */
class Organisation extends Model
{
    protected $fillable = [
        'name',
        'description',
        'website',
        'contact_person',
        'contact_email',
        'contact_job_title',
    ];

    public function community(): HasOne
    {
        return $this->hasOne(OrganisationCommunity::class);
    }

    public function hasCommunity(): bool
    {
        return $this->community()->exists();
    }
}
