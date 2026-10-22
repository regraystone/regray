/*
 * The files the composer will send with a message. Pure, with no imports, for
 * tests/js.
 *
 * Mirrors `attachments` in config/messaging.php, so a change there has to be
 * made here too. The server checks the same limits again; these only save a
 * trip that would be refused.
 */

export const ATTACHMENT_LIMITS = {
    files: 5,
    fileBytes: 10 * 1024 * 1024,
    totalBytes: 19 * 1024 * 1024,
    extensions: [
        'jpg',
        'jpeg',
        'png',
        'webp',
        'pdf',
        'docx',
    ] as readonly string[],
};

/** The file picker's `accept`: the same types, by extension and by MIME type. */
export const ATTACHMENT_ACCEPT = [
    '.jpg,.jpeg,.png,.webp,.pdf,.docx',
    'image/jpeg,image/png,image/webp,application/pdf',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
].join(',');

export const ATTACHMENT_ERRORS = {
    type: 'Send photos (JPG, PNG, WebP), PDF or Word (DOCX) files.',
    fileSize: 'Each file can be up to 10 MB.',
    count: `Send up to ${ATTACHMENT_LIMITS.files} files at a time.`,
    totalSize: 'Send up to 19 MB of files at a time.',
};

/** What a reply or a preview says about a message of files alone. */
export const ATTACHMENT_SNIPPET = 'Attachment';

type FileLike = { name: string; size: number };

const extensionOf = (name: string): string => {
    const dot = name.lastIndexOf('.');

    return dot === -1 ? '' : name.slice(dot + 1).toLowerCase();
};

/**
 * Add the files just picked to the ones already chosen. A file that cannot go
 * is left out, and the first reason is returned for the composer to show.
 */
export const addAttachments = <T extends FileLike>(
    current: readonly T[],
    picked: readonly T[],
): { files: T[]; error: string | null } => {
    const files = [...current];
    let total = files.reduce((sum, file) => sum + file.size, 0);
    let error: string | null = null;

    for (const file of picked) {
        let reason: string | null = null;

        if (!ATTACHMENT_LIMITS.extensions.includes(extensionOf(file.name))) {
            reason = ATTACHMENT_ERRORS.type;
        } else if (file.size > ATTACHMENT_LIMITS.fileBytes) {
            reason = ATTACHMENT_ERRORS.fileSize;
        } else if (files.length >= ATTACHMENT_LIMITS.files) {
            reason = ATTACHMENT_ERRORS.count;
        } else if (total + file.size > ATTACHMENT_LIMITS.totalBytes) {
            reason = ATTACHMENT_ERRORS.totalSize;
        }

        if (reason !== null) {
            error ??= reason;

            continue;
        }

        files.push(file);
        total += file.size;
    }

    return { files, error };
};

/** "850 B", "120 KB", "2.4 MB". */
export const formatFileSize = (bytes: number): string => {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${Math.round(bytes / 1024)} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
};
