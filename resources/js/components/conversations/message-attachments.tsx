import { Download, FileText, Image as ImageIcon } from 'lucide-react';
import { useState } from 'react';
import ImageLightbox, {
    LightboxTrigger,
    type LightboxImage,
} from '@/components/image-lightbox';
import { formatFileSize } from '@/lib/message-attachments';
import { cn } from '@/lib/utils';
import type { ChatAttachment } from '@/types/message';

type MessageAttachmentsProps = {
    attachments: ChatAttachment[];
    isOwn: boolean;
};

/**
 * The files in a bubble. A photo shows as a thumbnail that opens full size in
 * the same lightbox as the stone profile's gallery (click anywhere or Escape
 * to close); any other file is a row that downloads it.
 *
 * Every link is the download route, which checks the viewer may read the
 * conversation and, in production, sends them on to a short-lived S3 link.
 */
export const MessageAttachments = ({
    attachments,
    isOwn,
}: MessageAttachmentsProps) => {
    const [lightboxImage, setLightboxImage] = useState<LightboxImage | null>(
        null,
    );
    const photos = attachments.filter((attachment) => attachment.isImage);
    const files = attachments.filter((attachment) => !attachment.isImage);

    return (
        <div
            className="flex flex-col gap-1.5"
            data-testid="message-attachments"
        >
            {photos.length > 0 ? (
                <div
                    className={cn(
                        'grid gap-1',
                        photos.length > 1 ? 'grid-cols-2' : 'grid-cols-1',
                    )}
                >
                    {photos.map((photo) => (
                        <div
                            key={photo.id}
                            className="max-w-60"
                            data-testid="attachment-photo"
                        >
                            <LightboxTrigger
                                className="rounded-sm"
                                onOpen={() =>
                                    setLightboxImage({
                                        src: photo.url,
                                        alt: photo.name,
                                    })
                                }
                            >
                                <img
                                    src={photo.url}
                                    alt={photo.name}
                                    loading="lazy"
                                    draggable={false}
                                    className="aspect-square w-full object-cover"
                                />
                            </LightboxTrigger>
                        </div>
                    ))}
                </div>
            ) : null}

            {files.map((file) => (
                <a
                    key={file.id}
                    href={`${file.url}?download=1`}
                    className={cn(
                        'flex min-w-0 items-center gap-2 rounded-sm px-2 py-1.5 text-xs focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none',
                        isOwn ? 'bg-primary-foreground/15' : 'bg-background/60',
                    )}
                    data-testid="attachment-file"
                >
                    <FileText className="size-4 shrink-0" aria-hidden />
                    <span className="min-w-0 flex-1 truncate font-semibold">
                        {file.name}
                    </span>
                    <span className="shrink-0 opacity-80">
                        {formatFileSize(file.size)}
                    </span>
                    <Download className="size-3.5 shrink-0" aria-hidden />
                    <span className="sr-only">Download</span>
                </a>
            ))}

            <ImageLightbox
                image={lightboxImage}
                onClose={() => setLightboxImage(null)}
            />
        </div>
    );
};

/** The files of a message still on its way: names only, nothing to open yet. */
export const PendingAttachments = ({ files }: { files: File[] }) => (
    <div className="flex flex-col gap-1" data-testid="pending-attachments">
        {files.map((file, index) => (
            <span
                key={`${file.name}-${index}`}
                className="flex min-w-0 items-center gap-2 text-xs"
            >
                {file.type.startsWith('image/') ? (
                    <ImageIcon className="size-4 shrink-0" aria-hidden />
                ) : (
                    <FileText className="size-4 shrink-0" aria-hidden />
                )}
                <span className="min-w-0 truncate">{file.name}</span>
            </span>
        ))}
    </div>
);
