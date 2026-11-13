<?php

namespace App\Http\Responses;

use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderUtils;

/**
 * The Content-Disposition a stored file is served with, shared by every route
 * that serves one, so each names a file the same way.
 */
final class FileDisposition
{
    /**
     * Shown in the browser, or saved when `$download`, under the uploader's
     * file name. Header-safe whatever the name holds: no slashes, and an ASCII
     * fallback for browsers that do not read the UTF-8 one.
     */
    public static function for(string $originalName, bool $download): string
    {
        $name = str_replace(['/', '\\'], '-', $originalName);
        $fallback = str_replace('%', '', Str::ascii($name));

        return HeaderUtils::makeDisposition(
            $download ? HeaderUtils::DISPOSITION_ATTACHMENT : HeaderUtils::DISPOSITION_INLINE,
            $name,
            $fallback === '' ? 'attachment' : $fallback,
        );
    }
}
