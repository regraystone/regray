import { z } from 'zod';
import { formatFileSize } from '@/lib/message-attachments';

/**
 * The image fields of the admin's Brands and Projects forms: what a picked
 * file may be, checked before it is sent (the server reads the type from the
 * bytes again), and how an uploaded image's own name and size are shown.
 */

/** An image type a form takes: its MIME type, the extensions to offer, and its name in words. */
export type ImageType = { mime: string; extensions: string[]; name: string };

export const PNG: ImageType = {
    mime: 'image/png',
    extensions: ['.png'],
    name: 'PNG',
};
export const JPG: ImageType = {
    mime: 'image/jpeg',
    extensions: ['.jpg', '.jpeg'],
    name: 'JPG',
};
export const WEBP: ImageType = {
    mime: 'image/webp',
    extensions: ['.webp'],
    name: 'WebP',
};
export const SVG: ImageType = {
    mime: 'image/svg+xml',
    extensions: ['.svg'],
    name: 'SVG',
};

/** "PNG, JPG, WebP or SVG". */
export const imageTypeNames = (types: ImageType[]): string => {
    const names = types.map((type) => type.name);

    return names.length < 2
        ? names.join('')
        : `${names.slice(0, -1).join(', ')} or ${names.at(-1)}`;
};

/** The file input's `accept`: the extensions, then the MIME types. */
export const imageAccept = (types: ImageType[]): string =>
    [
        ...types.flatMap((type) => type.extensions),
        ...types.map((type) => type.mime),
    ].join(',');

/** "PNG, JPG or WebP, up to 5 MB." and anything more to say. */
export const imageHint = (
    types: ImageType[],
    maxKb: number,
    extra?: string,
): string =>
    `${imageTypeNames(types)}, up to ${formatFileSize(maxKb * 1024)}.${extra ? ` ${extra}` : ''}`;

/** A picked image, or none: one of `types`, `maxKb` at most. `what` names it in the message ("logo"). */
export const imageFileSchema = (
    types: ImageType[],
    maxKb: number,
    what: string,
) =>
    z
        .custom<File | null>((value) => value === null || value instanceof File)
        .superRefine((file, ctx) => {
            if (file === null) {
                return;
            }

            if (!types.some((type) => type.mime === file.type)) {
                ctx.addIssue({
                    code: 'custom',
                    message: `The ${what} has to be a ${imageTypeNames(types)}.`,
                });
            } else if (file.size > maxKb * 1024) {
                ctx.addIssue({
                    code: 'custom',
                    message: `The ${what} can be ${formatFileSize(maxKb * 1024)} at most.`,
                });
            }
        });

/** An uploaded image's own name and size, or a dash for one that was committed. */
export const fileLine = (
    file: { originalName: string; sizeBytes: number | null } | null,
): string =>
    file === null
        ? '—'
        : `${file.originalName}${file.sizeBytes ? ` · ${formatFileSize(file.sizeBytes)}` : ''}`;
