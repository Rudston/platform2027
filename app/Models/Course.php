<?php

namespace App\Models;

use App\Models\Communities\CourseCommunity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Plain course entity. NOT a Circleable/Locatable community —
 * it is a standalone record that may be attached to many
 * CourseCommunity records (many-to-many).
 */
class Course extends Model
{
    protected $fillable = [
        'name',
        'description',
        'website',
        'contact_person',
        'contact_email',
    ];

    public function communities(): BelongsToMany
    {
        return $this->belongsToMany(
            CourseCommunity::class,
            'course_course_community'
        )->withTimestamps();
    }
}
