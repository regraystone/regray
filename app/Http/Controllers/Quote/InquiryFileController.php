<?php

namespace App\Http\Controllers\Quote;

use App\Http\Controllers\Controller;
use App\Http\Requests\Quote\ShowInquiryDetailsRequest;
use App\Http\Responses\FileDisposition;
use App\Models\User;
use App\Policies\InquiryFileVisibility;
use App\Services\InquiryDetailsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * One file on an inquiry, for whoever may open the inquiry's details page and
 * see that file there. The files sit on the private disk with no URL of their
 * own: this is the only way to them, by the generated `stored_name`, never by
 * a database id or the uploader's name.
 *
 * An inquiry the reader cannot open, and a file that is missing, removed or
 * not theirs to see, are the same 404, so the answer never says which exist.
 *
 * Shown in the browser's own viewer for the types InquiryFile trusts it with,
 * saved with `?download=1`. Any other type is download-only: previewing it is
 * 415. The stored bytes are sent unchanged. On S3 (production) the answer is a
 * redirect to a link that expires, as for message attachments; HEAD is
 * answered here on every disk, as that link is signed for GET alone.
 */
class InquiryFileController extends Controller
{
    public function __construct(
        public readonly InquiryDetailsService $inquiryDetailsService,
        public readonly InquiryFileVisibility $visibility,
    ) {}

    public function show(ShowInquiryDetailsRequest $request, string $reference, string $storedName): Response|RedirectResponse|StreamedResponse
    {
        /** @var User $viewer */
        $viewer = $request->user();
        $inquiry = $this->inquiryDetailsService->forViewer($viewer, $request->validated('reference'));
        $file = $inquiry->files()->where('stored_name', $storedName)->first();

        if ($file === null || ! $this->visibility->allows($viewer, $file, $inquiry->files)) {
            abort(404);
        }

        $download = $request->boolean('download');

        if (! $download && ! $file->isPreviewable()) {
            abort(415);
        }

        $disk = Storage::disk($file->disk);

        if (! $disk->exists($file->path)) {
            Log::warning('An inquiry file is missing from its disk.', ['inquiry' => $inquiry->reference, 'file' => $file->id]);

            abort(404);
        }

        $headers = [
            'Content-Type' => $file->mime_type,
            'Content-Disposition' => FileDisposition::for($file->original_name, $download),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ];

        if ($request->isMethod('HEAD')) {
            return response('', 200, [...$headers, 'Content-Length' => (string) $file->size_bytes]);
        }

        if (config("filesystems.disks.{$file->disk}.driver") === 's3') {
            $url = $disk->temporaryUrl(
                $file->path,
                now()->addMinutes((int) config('inquiry.file_link_minutes')),
                [
                    'ResponseContentType' => $file->mime_type,
                    'ResponseContentDisposition' => $headers['Content-Disposition'],
                ],
            );

            return redirect()->away($url)->header('Cache-Control', 'private, no-store');
        }

        return $disk->response($file->path, $file->original_name, $headers);
    }
}
