<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * The name an upload is stored under, for every upload in Regray (inquiry
 * files, message attachments, brand images): a fresh UUID with the extension
 * of the type read from the file's bytes, never the uploader's own name. A PDF
 * sent as `x.html` is stored as `.pdf`. The uploader's name is kept on the row
 * as its `original_name`, to show to people only, never in a path or a URL.
 */
final class StoredFileName
{
    public static function for(UploadedFile $file): string
    {
        return Str::uuid()->toString().'.'.(strtolower((string) $file->guessExtension()) ?: 'bin');
    }
}
