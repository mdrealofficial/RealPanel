<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['group', 'key', 'value'];

    public static function get(string $key, $default = null)
    {
        return Cache::remember("setting:{$key}", 3600, function () use ($key, $default) {
            $setting = static::where('key', $key)->first();
            return $setting ? $setting->value : $default;
        });
    }

    public static function set(string $key, $value, string $group = 'general'): void
    {
        static::updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'group' => $group]
        );
        Cache::forget("setting:{$key}");
    }

    public static function getGroup(string $group): array
    {
        return static::where('group', $group)
            ->pluck('value', 'key')
            ->toArray();
    }

    /**
     * Get the default primary nameserver (NS1), dynamically replacing {domain} if provided.
     */
    public static function getNs1(?string $domain = null): string
    {
        $val = trim((string) (static::get('default_ns1') ?: static::get('ns1')));
        if ($val !== '') {
            $val = rtrim($val, '.');
            return $domain ? str_replace('{domain}', $domain, $val) : $val;
        }
        return config('realpanel.ns1', $domain ? 'ns1.' . $domain : 'ns1.realpanel.io');
    }

    /**
     * Get the default secondary nameserver (NS2), dynamically replacing {domain} if provided.
     */
    public static function getNs2(?string $domain = null): string
    {
        $val = trim((string) (static::get('default_ns2') ?: static::get('ns2')));
        if ($val !== '') {
            $val = rtrim($val, '.');
            return $domain ? str_replace('{domain}', $domain, $val) : $val;
        }
        return config('realpanel.ns2', $domain ? 'ns2.' . $domain : 'ns2.realpanel.io');
    }
}
