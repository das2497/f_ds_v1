<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

#[Fillable(['key', 'value'])]
class AppSetting extends Model
{
    protected function casts(): array
    {
        return [
            'value' => 'encrypted',
        ];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::rememberForever("app_setting:{$key}", function () use ($key, $default) {
            return self::where('key', $key)->first()?->value ?? $default;
        });
    }

    public static function set(string $key, mixed $value): void
    {
        self::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget("app_setting:{$key}");
    }

    /**
     * Like get(), but coerced to an int — the encrypted cast round-trips
     * every value through a string, so numeric settings come back as "260"
     * rather than 260.
     */
    public static function getInt(string $key): ?int
    {
        $value = self::get($key);

        return $value !== null ? (int) $value : null;
    }
}
