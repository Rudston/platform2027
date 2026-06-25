<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Theme extends Model
{
    protected $table = 'themes';
    protected $fillable = ['name', 'parent_id', 'slug'];

    protected static function booted(): void
    {
        static::creating(function (Theme $theme): void {
            if (empty($theme->slug) && ! empty($theme->name)) {
                $theme->slug = Str::slug($theme->name);
            }
        });
    }

    // Get the immediate parent category
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    // Get immediate child subcategories
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    // Recursive relationship to eager-load the entire multi-level nested tree
    public function childrenRecursive(): HasMany
    {
        return $this->children()->with('childrenRecursive');
    }
}
