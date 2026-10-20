<?php

namespace SergeyBruhin\NovaSupportRays\Support;

/** The tool's configuration, read in one place. */
final class Settings
{
    public static function configured(): bool
    {
        return self::baseUrl() !== '' && self::projectUuid() !== '';
    }

    public static function baseUrl(): string
    {
        return (string) config('nova-supportrays.base_url');
    }

    public static function projectUuid(): string
    {
        return (string) config('nova-supportrays.project_uuid');
    }

    public static function portalUrl(): string
    {
        return rtrim((string) config('nova-supportrays.portal_url'), '/');
    }
}
