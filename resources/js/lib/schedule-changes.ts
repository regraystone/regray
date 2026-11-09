import { router } from '@inertiajs/react';

/**
 * Send one change to an appointment (update, cancel, approve, served, did not
 * attend) and say how it went: saved, refused with the server's field errors
 * (a `schedule` error is a refusal of the change itself, such as an
 * appointment already approved meanwhile), or failed without an answer. On
 * success the page's schedules come back with the redirect.
 */
export type ScheduleChangeOutcome =
    | { kind: 'saved' }
    | { kind: 'invalid'; errors: Record<string, string> }
    | { kind: 'failed' };

export const sendScheduleChange = (
    method: 'post' | 'patch',
    url: string,
    data: Record<string, string> = {},
): Promise<ScheduleChangeOutcome> =>
    new Promise((resolve) => {
        let settled = false;
        const settle = (outcome: ScheduleChangeOutcome) => {
            if (!settled) {
                settled = true;
                resolve(outcome);
            }
        };

        router.visit(url, {
            method,
            data,
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => settle({ kind: 'saved' }),
            onError: (errors) =>
                settle({
                    kind: 'invalid',
                    errors: errors as Record<string, string>,
                }),
            onHttpException: () => {
                settle({ kind: 'failed' });

                return false;
            },
            onFinish: () => settle({ kind: 'failed' }),
        });
    });
