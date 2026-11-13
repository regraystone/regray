import { Download, Eye, FileText, Image as ImageIcon } from 'lucide-react';
import { useState } from 'react';
import { formatSize } from '@/components/file-upload';
import { Button } from '@/components/ui/button';
import { formatDate } from '@/lib/format-date';
import {
    downloadUrl,
    formatLabel,
    OPENING,
    openPreview,
} from '@/lib/inquiry-file-preview';
import type { StoredInquiryFile } from '@/types/quote';

type RowState =
    | { kind: 'idle' }
    | { kind: 'opening' }
    | { kind: 'error'; message: string };

/**
 * One stored file on the inquiry details page, the client's attachments and
 * the team's documents alike: its name, format, size and upload date, with
 * Preview (in a new tab, for the types the server marks previewable) and
 * Download.
 *
 * Preview is a button rather than a link so it can never carry `download`
 * and start a save; Download is a plain link the server answers with
 * `attachment`. A problem opening the file is said in the row, beside it.
 */
export const InquiryFileRow = ({
    file,
    heading,
    className = '',
}: {
    file: StoredInquiryFile;
    /** What the file is, such as "Quotation", above its name. */
    heading?: string;
    className?: string;
}) => {
    const [state, setState] = useState<RowState>({ kind: 'idle' });
    const isPdf = file.mimeType === 'application/pdf';
    const isOpening = state.kind === 'opening';

    const preview = () => {
        setState({ kind: 'opening' });

        void openPreview(
            file.url,
            () => window.open('about:blank', '_blank'),
            (url) => fetch(url, { method: 'HEAD', credentials: 'same-origin' }),
        ).then((message) =>
            setState(
                message === null
                    ? { kind: 'idle' }
                    : { kind: 'error', message },
            ),
        );
    };

    return (
        <li className={className} aria-busy={isOpening}>
            <div className="flex items-start gap-3">
                <span className="flex size-10 shrink-0 items-center justify-center rounded-md border border-sidebar-border">
                    {isPdf ? (
                        <FileText className="size-4" aria-hidden />
                    ) : (
                        <ImageIcon className="size-4" aria-hidden />
                    )}
                </span>
                <div className="min-w-0 flex-1">
                    {heading ? (
                        <p className="text-sm font-bold">{heading}</p>
                    ) : null}
                    {/*
                     * Truncated rather than wrapped: a file name broken across
                     * lines stops looking like a name. The full one is kept in
                     * `title` for the pointer.
                     */}
                    <p
                        className={`truncate text-sm ${heading ? 'text-secondary-foreground' : 'font-bold'}`}
                        title={file.name}
                    >
                        {file.name}
                    </p>
                    <p className="text-xs text-secondary-foreground">
                        {formatLabel(file.format)} ·{' '}
                        {formatSize(file.sizeBytes)} · Uploaded{' '}
                        {formatDate(file.uploadedAt)}
                    </p>

                    <div className="mt-2 flex flex-wrap gap-2">
                        {file.previewable ? (
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={preview}
                                disabled={isOpening}
                                aria-label={`Preview ${file.name}`}
                            >
                                <Eye aria-hidden />
                                {isOpening ? OPENING : 'Preview'}
                            </Button>
                        ) : null}
                        <Button variant="outline" size="sm" asChild>
                            <a
                                href={downloadUrl(file.url)}
                                download
                                aria-label={`Download ${file.name}`}
                            >
                                <Download aria-hidden />
                                Download
                            </a>
                        </Button>
                    </div>

                    {state.kind === 'error' ? (
                        <p
                            role="alert"
                            className="mt-2 text-xs text-destructive"
                        >
                            {state.message}
                        </p>
                    ) : null}
                </div>
            </div>
        </li>
    );
};
