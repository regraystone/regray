<?php

namespace App\Http\Controllers\Inbox;

use App\Http\Controllers\Controller;
use App\Http\Responses\FileDisposition;
use App\Models\MessageAttachment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A file sent in a thread. The files sit on the private disk and have no URL of
 * their own: this is the only way to them, and it asks ConversationPolicy
 * whether the viewer may read the conversation, the same as the thread does.
 *
 * On S3 (production) the answer is a redirect to a link that expires after
 * `messaging.attachments.link_minutes`, so the file does not pass through
 * PHP. On a local disk the file is streamed.
 *
 * A removed message's files stay on the disk, as the message stays as a row
 * (see Message), but are no longer served: 404.
 */
class AttachmentController extends Controller
{
    public function show(Request $request, MessageAttachment $attachment): RedirectResponse|StreamedResponse
    {
        $message = $attachment->message ?? abort(404);
        Gate::authorize('view', $message->conversation);

        $disposition = FileDisposition::for($attachment->original_name, $request->boolean('download'));

        if (config("filesystems.disks.{$attachment->disk}.driver") === 's3') {
            $url = Storage::disk($attachment->disk)->temporaryUrl(
                $attachment->path,
                now()->addMinutes((int) config('messaging.attachments.link_minutes')),
                [
                    'ResponseContentType' => $attachment->mime_type,
                    'ResponseContentDisposition' => $disposition,
                ],
            );

            return redirect()->away($url)->header('Cache-Control', 'private, no-store');
        }

        return Storage::disk($attachment->disk)->response($attachment->path, $attachment->original_name, [
            'Content-Type' => $attachment->mime_type,
            'Content-Disposition' => $disposition,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }
}
