<?php

namespace App\Models\Communication;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Spatie\Translatable\HasTranslations;

class EmailTemplate extends Model
{
    use HasTranslations;

    /** Translatable JSON columns (resolved to the current locale transparently). */
    public array $translatable = ['subject', 'body'];

    protected $guarded = [];

    protected $casts = [
        'is_html' => 'boolean',
        'is_active' => 'boolean',
        'available_variables' => 'array',
    ];

    /**
     * Retrieve a template by its key for the current locale, cached for 1 hour.
     *
     * Translatable fields (subject/body) resolve to the current locale
     * transparently on access. Returns null when no template exists for $key.
     */
    public static function getByKey(string $key): ?self
    {
        $locale = app()->getLocale();

        return Cache::remember(
            "email_template.{$key}.{$locale}",
            now()->addHour(),
            fn (): ?self => static::query()->where('key', $key)->first(),
        );
    }

    /**
     * Forget the cached template for this key across every supported locale.
     */
    public function flushCache(): void
    {
        foreach ((array) config('app.supported_locales', []) as $locale) {
            Cache::forget("email_template.{$this->key}.{$locale}");
        }
    }

    protected static function booted(): void
    {
        // Invalidate the cache whenever a template is created/updated or deleted.
        static::saved(static fn (self $template) => $template->flushCache());
        static::deleted(static fn (self $template) => $template->flushCache());
    }
}
