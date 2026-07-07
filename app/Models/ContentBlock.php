<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Spatie\Translatable\HasTranslations;

class ContentBlock extends Model
{
    use HasTranslations;

    /** Translatable JSON columns (resolved to the current locale transparently). */
    public array $translatable = ['content', 'title'];

    protected $guarded = [];

    protected $casts = [
        'is_html' => 'boolean',
        'collapsible' => 'boolean',
        'default_collapsed' => 'boolean',
    ];

    /**
     * Resolve a content block's value for the current locale, cached for 1 hour.
     *
     * Resolution order: current locale → English (app.fallback_locale) → $fallback.
     * A missing block always yields $fallback.
     */
    public static function get(string $key, string $fallback = ''): string
    {
        $locale = app()->getLocale();

        return Cache::remember(
            "content_block.{$key}.{$locale}",
            now()->addHour(),
            function () use ($key, $locale, $fallback): string {
                $block = static::query()->where('key', $key)->first();

                if (! $block) {
                    return $fallback;
                }

                $value = (string) $block->getTranslation('content', $locale, false);

                // Fall back to English when the current locale has no meaningful
                // content. Markup/whitespace-only (e.g. an empty RichEditor's
                // "<p></p>") counts as empty.
                if (static::isBlank($value)) {
                    $value = (string) $block->getTranslation('content', config('app.fallback_locale', 'en'), false);
                }

                return static::isBlank($value) ? $fallback : $value;
            },
        );
    }

    /** Treat markup/whitespace-only content (e.g. "<p></p>") as empty. */
    protected static function isBlank(string $value): bool
    {
        return trim(strip_tags($value)) === '';
    }

    /**
     * Forget the cached value for this block's key across every supported locale.
     */
    public function flushCache(): void
    {
        foreach ((array) config('app.supported_locales', []) as $locale) {
            Cache::forget("content_block.{$this->key}.{$locale}");
        }
    }

    protected static function booted(): void
    {
        // Invalidate the cache whenever a block is created/updated or deleted.
        static::saved(static fn (self $block) => $block->flushCache());
        static::deleted(static fn (self $block) => $block->flushCache());
    }
}
