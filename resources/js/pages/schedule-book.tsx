import { zodResolver } from '@hookform/resolvers/zod';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useMemo, useRef, useState, useEffect } from 'react';
import { FormProvider, useForm } from 'react-hook-form';
import BookingCalendar from '@/components/schedule/booking-calendar';
import Countdown from '@/components/countdown';
import PageHeading from '@/components/page-heading';
import FormField from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { ResultBadge } from '@/components/result-badge';
import { permissions } from '@/layouts/workspace-layout';
import { index as scheduleIndex } from '@/routes/schedule';
import { store as bookingStore } from '@/routes/schedule/book';
import { schedule as profileSchedule } from '@/routes/profile';
import { usePermissions } from '@/hooks/use-permissions';
import { formatFullDate, dayKey } from '@/lib/date';
import { bookingSchema } from '@/lib/booking-schema';
import { openSlotsOn, slotLabel } from '@/lib/booking-slots';
import { rateLimitFrom, retryLabel } from '@/lib/rate-limit';
import type { RateLimit } from '@/lib/rate-limit';
import type { StudioNow } from '@/lib/booking-slots';
import type { z } from 'zod';

type BookingFormValues = z.infer<typeof bookingSchema>;

type BookingResult =
    | { status: 'success'; date: Date; arrivalTime: string }
    | { status: 'error'; message?: string; retryAt?: number | null }
    | null;

const BOOKING_FIELDS: Array<keyof BookingFormValues> = [
    'date',
    'arrivalTime',
    'purpose',
    'notes',
];

/**
 * How a send ended: the backend's own verdict (`flashed`), the form request
 * refusing the answers (`invalid`), or anything else, where the backend never
 * got to say anything (`failed`). A failure carries a message only when the
 * server said why, as a 429 does.
 */
type SendOutcome =
    | { kind: 'flashed'; succeeded: boolean }
    | { kind: 'invalid'; errors: Record<string, string> }
    | { kind: 'failed'; rateLimit?: RateLimit };

/** Whether the flash after a send says the booking was saved. */
const bookingSucceeded = (flash: unknown): boolean =>
    (flash as { booking?: { status?: unknown } } | null | undefined)?.booking
        ?.status === 'success';

/**
 * The send, as pages/inquire.tsx does it. `onHttpException` returns false so
 * a server error opens this page's error dialog, which keeps the answers and
 * offers a retry, rather than Inertia's dialog of the raw response. A 429 says
 * how long to wait instead of blaming the server.
 */
const sendBooking = (
    values: BookingFormValues & { date: Date },
): Promise<SendOutcome> =>
    new Promise((resolve) => {
        let rateLimit: RateLimit | undefined;

        router.post(
            bookingStore.url(),
            {
                date: dayKey(values.date),
                arrivalTime: values.arrivalTime,
                purpose: values.purpose,
                notes: values.notes ?? '',
            },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: (page) =>
                    resolve({
                        kind: 'flashed',
                        succeeded: bookingSucceeded(page.flash),
                    }),
                onError: (errors) =>
                    resolve({
                        kind: 'invalid',
                        errors: errors as Record<string, string>,
                    }),
                onHttpException: (response) => {
                    rateLimit = rateLimitFrom(response) ?? undefined;

                    return false;
                },
                onFinish: () => resolve({ kind: 'failed', rateLimit }),
            },
        );
    });

/** A `YYYY-MM-DD` day as a local midnight, the shape the calendar works in. */
const fromDayKey = (day: string): Date => {
    const [year, month, date] = day.split('-').map(Number);

    return new Date(year, month - 1, date);
};

/**
 * `studioNow` is Manila's date and time when the page was served, so which
 * days and slots are already past is decided by the studio's clock, not the
 * browser's. `earliestBookableDate` is the first day the signed-in user may
 * book: tomorrow for a client, today for staff.
 *
 * TODO(staff-booking): staff will pick the client the appointment is for, and
 * the form will send that client's id. Until then the form books for the
 * signed-in client only.
 */
const Page = ({
    studioNow,
    earliestBookableDate,
}: {
    studioNow: StudioNow;
    earliestBookableDate: string;
}) => {
    const { url } = usePage();
    const { can } = usePermissions();
    const isProfile = url.startsWith('/profile');
    const scheduleHref = isProfile
        ? profileSchedule.url()
        : scheduleIndex.url();
    const studioToday = useMemo(
        () => fromDayKey(studioNow.date),
        [studioNow.date],
    );

    const [month, setMonth] = useState(studioToday);
    const [result, setResult] = useState<BookingResult>(null);
    const [isSending, setIsSending] = useState(false);
    const isSendingRef = useRef(false);

    const methods = useForm<BookingFormValues>({
        resolver: zodResolver(bookingSchema) as any,
        mode: 'onTouched',
        reValidateMode: 'onChange',
        defaultValues: {
            date: null as any,
            arrivalTime: '',
            purpose: '',
            notes: '',
        },
    });

    const { handleSubmit, setValue, setError, watch, reset, formState } =
        methods;
    const date = watch('date');
    const arrivalTime = watch('arrivalTime');

    const isDisabled = (day: Date): boolean =>
        openSlotsOn(dayKey(day), studioNow, earliestBookableDate).length === 0;

    const availableSlots = useMemo(
        () =>
            date
                ? openSlotsOn(dayKey(date), studioNow, earliestBookableDate)
                : [],
        [date, studioNow, earliestBookableDate],
    );

    useEffect(() => {
        if (!date || !arrivalTime) {
            return;
        }

        if (!availableSlots.includes(arrivalTime)) {
            setValue('arrivalTime', '', {
                shouldDirty: true,
                shouldValidate: true,
            });
        }
    }, [availableSlots, arrivalTime, date, setValue]);

    const onSubmit = async (values: BookingFormValues) => {
        if (isSendingRef.current || !values.date) {
            return;
        }
        isSendingRef.current = true;
        setIsSending(true);

        const outcome = await sendBooking({ ...values, date: values.date });

        isSendingRef.current = false;
        setIsSending(false);

        if (outcome.kind === 'invalid') {
            setResult(null);

            for (const field of BOOKING_FIELDS) {
                const message = outcome.errors[field];

                if (message) {
                    setError(field, { type: 'server', message });
                }
            }

            return;
        }

        if (outcome.kind === 'failed' || !outcome.succeeded) {
            const rateLimit =
                outcome.kind === 'failed' ? outcome.rateLimit : undefined;

            setResult({
                status: 'error',
                message: rateLimit?.message,
                retryAt: rateLimit?.retryAt,
            });

            return;
        }

        setResult({
            status: 'success',
            date: values.date,
            arrivalTime: values.arrivalTime,
        });
    };

    const handleSuccessClose = () => {
        setResult(null);
        reset();
        setMonth(studioToday);
    };

    const handleRetry = async () => {
        await handleSubmit(onSubmit)();
    };

    const dateError = formState.errors.date?.message;

    if (!can(permissions.scheduleCreate)) {
        return null;
    }

    return (
        <div className="w-full">
            <Head title="Book an appointment" />
            <PageHeading
                title="Book an appointment"
                description="Pick a day and tell us when to expect you"
            />

            <div className="mt-6 grid gap-6 lg:grid-cols-2 lg:gap-8">
                <div className="order-2 space-y-6 lg:order-1">
                    <FormProvider {...methods}>
                        <form
                            onSubmit={handleSubmit(onSubmit)}
                            className="space-y-6"
                        >
                            <div>
                                <p className="text-sm font-medium text-foreground">
                                    {date
                                        ? formatFullDate.format(date)
                                        : 'No day selected yet'}
                                </p>
                                {dateError ? (
                                    <p className="mt-1 text-sm text-destructive">
                                        {dateError}
                                    </p>
                                ) : null}
                            </div>

                            <FormField
                                id="arrivalTime"
                                label="Expected arrival time"
                                hint="Open 10:00 AM – 6:00 PM"
                                error={formState.errors.arrivalTime?.message}
                            >
                                {({ describedBy, invalid }) => (
                                    <Select
                                        onValueChange={(v) =>
                                            setValue('arrivalTime', v, {
                                                shouldValidate: true,
                                            })
                                        }
                                        value={arrivalTime}
                                        disabled={
                                            !date || availableSlots.length === 0
                                        }
                                    >
                                        <SelectTrigger
                                            id="arrivalTime"
                                            className="w-full rounded-none"
                                            aria-invalid={invalid}
                                            aria-describedby={describedBy}
                                        >
                                            <SelectValue placeholder="Choose a time" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {availableSlots.map((slot) => (
                                                <SelectItem
                                                    key={slot}
                                                    value={slot}
                                                    className="rounded-none"
                                                >
                                                    {slotLabel(slot)}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                )}
                            </FormField>

                            <FormField
                                id="purpose"
                                label="Purpose"
                                error={formState.errors.purpose?.message}
                            >
                                {({ describedBy, invalid }) => (
                                    <Input
                                        id="purpose"
                                        {...methods.register('purpose')}
                                        className="rounded-none"
                                        placeholder="e.g. Meet about a countertop project"
                                        aria-invalid={invalid}
                                        aria-describedby={describedBy}
                                    />
                                )}
                            </FormField>

                            <FormField
                                id="notes"
                                label="Notes (optional)"
                                error={formState.errors.notes?.message}
                            >
                                {({ describedBy, invalid }) => (
                                    <Textarea
                                        id="notes"
                                        {...methods.register('notes')}
                                        rows={4}
                                        className="rounded-none"
                                        placeholder="Any details we should know"
                                        aria-invalid={invalid}
                                        aria-describedby={describedBy}
                                    />
                                )}
                            </FormField>

                            <div className="flex flex-col gap-3 sm:flex-row sm:justify-end">
                                <Button
                                    asChild
                                    variant="outline"
                                    type="button"
                                    className="w-full sm:w-auto"
                                >
                                    <Link href={scheduleHref}>
                                        Back to schedule
                                    </Link>
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={isSending}
                                    className="w-full sm:w-auto"
                                >
                                    {isSending ? (
                                        <>
                                            <Spinner />
                                            Sending...
                                        </>
                                    ) : (
                                        'Request appointment'
                                    )}
                                </Button>
                            </div>
                        </form>
                    </FormProvider>
                </div>

                <div className="order-1 lg:order-2">
                    <BookingCalendar
                        month={month}
                        onChangeMonth={setMonth}
                        value={date}
                        onChange={(day) =>
                            setValue('date', day, { shouldValidate: true })
                        }
                        isDisabled={isDisabled}
                        error={dateError}
                        describedBy="date-error"
                    />
                </div>
            </div>

            <Dialog
                open={result !== null}
                onOpenChange={(open) => {
                    if (!open && result?.status === 'success') {
                        handleSuccessClose();
                    }
                    if (!open && result?.status === 'error' && !isSending) {
                        setResult(null);
                    }
                }}
            >
                <DialogContent
                    className="sm:max-w-md"
                    onInteractOutside={(e) => {
                        if (isSending) {
                            e.preventDefault();
                        }
                    }}
                    onEscapeKeyDown={(e) => {
                        if (isSending) {
                            e.preventDefault();
                        }
                    }}
                >
                    {result?.status === 'success' ? (
                        <>
                            <div className="flex flex-col items-center text-center">
                                <ResultBadge status="success" />
                                <DialogHeader className="mt-6 sm:text-center">
                                    <DialogTitle
                                        tabIndex={-1}
                                        className="outline-none"
                                    >
                                        Appointment requested
                                    </DialogTitle>
                                    <DialogDescription className="mt-2">
                                        We&apos;ll expect you on{' '}
                                        {formatFullDate.format(result.date)} at{' '}
                                        {slotLabel(result.arrivalTime)}.
                                    </DialogDescription>
                                </DialogHeader>
                            </div>
                            <DialogFooter className="mt-8 flex-col gap-3 sm:flex-row sm:justify-center">
                                <Button asChild>
                                    <Link href={scheduleHref}>
                                        Back to schedule
                                    </Link>
                                </Button>
                                <Button
                                    variant="outline"
                                    onClick={handleSuccessClose}
                                >
                                    Book another
                                </Button>
                            </DialogFooter>
                        </>
                    ) : result?.status === 'error' ? (
                        <>
                            <div className="flex flex-col items-center text-center">
                                <ResultBadge status="error" />
                                <DialogHeader className="mt-6 sm:text-center">
                                    <DialogTitle
                                        tabIndex={-1}
                                        className="outline-none"
                                    >
                                        We couldn&apos;t book your appointment
                                    </DialogTitle>
                                    <DialogDescription className="mt-2">
                                        {result.message ??
                                            'Something went wrong on our side. Your answers are still here, so you can try again.'}
                                    </DialogDescription>
                                </DialogHeader>
                            </div>
                            <DialogFooter className="mt-8 flex-col gap-3 sm:flex-row sm:justify-center">
                                {/* Held until a 429's wait is over, so a retry cannot extend it. */}
                                <Countdown
                                    key={result.retryAt ?? 'now'}
                                    until={result.retryAt ?? null}
                                >
                                    {(retryIn) => (
                                        <Button
                                            onClick={handleRetry}
                                            disabled={isSending || retryIn > 0}
                                        >
                                            {isSending ? (
                                                <>
                                                    <Spinner />
                                                    Sending...
                                                </>
                                            ) : (
                                                retryLabel(retryIn)
                                            )}
                                        </Button>
                                    )}
                                </Countdown>
                                <Button
                                    variant="outline"
                                    onClick={() => setResult(null)}
                                    disabled={isSending}
                                >
                                    Close
                                </Button>
                            </DialogFooter>
                        </>
                    ) : null}
                </DialogContent>
            </Dialog>
        </div>
    );
};

export default Page;
