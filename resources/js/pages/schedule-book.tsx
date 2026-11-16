import { zodResolver } from '@hookform/resolvers/zod';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useMemo, useRef, useState, useEffect } from 'react';
import { FormProvider, useForm } from 'react-hook-form';
import BookingCalendar from '@/components/schedule/booking-calendar';
import BookingClientPicker from '@/components/schedule/booking-client-picker';
import SalesPicker from '@/components/schedule/sales-picker';
import Countdown from '@/components/countdown';
import PageHeading from '@/components/page-heading';
import FormField from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import {} from '@/components/ui/dialog';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import ResultDialog from '@/components/result-dialog';
import { permissions } from '@/layouts/workspace-layout';
import { index as scheduleIndex } from '@/routes/schedule';
import { store as bookingStore } from '@/routes/schedule/book';
import {
    schedule as profileSchedule,
    show as showProfile,
} from '@/routes/profile';
import { usePermissions } from '@/hooks/use-permissions';
import { formatFullDate, dayKey } from '@/lib/date';
import type { ClientAccount } from '@/lib/staff-clients-api';
import { staffBookingSchema, updateScheduleSchema } from '@/lib/booking-schema';
import { sendScheduleChange } from '@/lib/schedule-changes';
import { update as updateSchedule } from '@/routes/schedule';
import type { AssignableSales } from '@/lib/schedule-sales-api';
import { openSlotsOn, slotLabel } from '@/lib/booking-slots';
import { rateLimitFrom, retryLabel } from '@/lib/rate-limit';
import type { RateLimit } from '@/lib/rate-limit';
import type { StudioNow } from '@/lib/booking-slots';
import type { z } from 'zod';

/*
 * One form for booking and updating: a client's booking has no client or
 * sales member to choose, so `clientId` and `salesId` are only checked (and
 * sent) when marketing books; updating
 * keeps the client and asks for the reason for the change instead. See
 * `staffBookingSchema` and `updateScheduleSchema`.
 */
type BookingFormValues = Omit<
    z.infer<typeof staffBookingSchema>,
    'date' | 'clientId' | 'salesId'
> & {
    date: Date | null;
    clientId: number | null;
    salesId: number | null;
    reason: string;
};

/** The appointment being updated, as ViewController::scheduleEdit() sends it. */
type UpdatingSchedule = {
    id: number;
    title: string;
    /** `YYYY-MM-DD` in the studio's zone. */
    date: string;
    /** `HH:mm` in the studio's zone. */
    arrivalTime: string;
    notes: string | null;
    status: string;
    /** Whose it is: staff readers only. */
    clientName: string | null;
};

type BookingResult =
    | {
          status: 'success';
          date: Date;
          arrivalTime: string;
          clientName: string | null;
          salesName: string | null;
      }
    | { status: 'error'; message?: string; retryAt?: number | null }
    | null;

const BOOKING_FIELDS: Array<keyof BookingFormValues> = [
    'clientId',
    'salesId',
    'date',
    'arrivalTime',
    'purpose',
    'notes',
    'reason',
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
                ...(values.clientId === null
                    ? {}
                    : { clientId: values.clientId }),
                ...(values.salesId === null ? {} : { salesId: values.salesId }),
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
 * Send the update of an appointment, answered the way a booking is: saved,
 * refused with field errors (or `schedule`, the appointment itself no longer
 * changing), or failed.
 */
const sendUpdate = async (
    scheduleId: number,
    values: BookingFormValues & { date: Date },
): Promise<SendOutcome> => {
    const outcome = await sendScheduleChange(
        'patch',
        updateSchedule.url(scheduleId),
        {
            date: dayKey(values.date),
            arrivalTime: values.arrivalTime,
            purpose: values.purpose,
            notes: values.notes ?? '',
            reason: values.reason,
        },
    );

    return outcome.kind === 'saved'
        ? { kind: 'flashed', succeeded: true }
        : outcome.kind === 'invalid'
          ? outcome
          : { kind: 'failed' };
};

/**
 * `studioNow` is Manila's date and time when the page was served, so which
 * days and slots are already past is decided by the studio's clock, not the
 * browser's. `earliestBookableDate` is the first day the signed-in user may
 * book: tomorrow for a client, today for staff.
 *
 * Staff (marketing, who read the schedule from the sidebar, `schedule.view`)
 * first choose the client the appointment is for and the sales member who
 * confirms it. A client books for themselves, and theirs waits for marketing
 * to assign it.
 *
 * With `schedule`, the page updates that appointment instead: the same fields,
 * filled in with it, the client kept, and the reason for the change asked.
 */
const Page = ({
    studioNow,
    earliestBookableDate,
    schedule = null,
}: {
    studioNow: StudioNow;
    earliestBookableDate: string;
    schedule?: UpdatingSchedule | null;
}) => {
    const isUpdate = schedule !== null;
    const { url } = usePage();
    const { can } = usePermissions();
    const isStaff = can(permissions.scheduleView);
    const isProfile = url.startsWith(showProfile.url());
    const scheduleHref = isProfile
        ? profileSchedule.url()
        : scheduleIndex.url();
    const studioToday = useMemo(
        () => fromDayKey(studioNow.date),
        [studioNow.date],
    );

    const [month, setMonth] = useState(() =>
        schedule === null ? studioToday : fromDayKey(schedule.date),
    );
    const [result, setResult] = useState<BookingResult>(null);
    const [isSending, setIsSending] = useState(false);
    const isSendingRef = useRef(false);
    const [client, setClient] = useState<ClientAccount | null>(null);
    const [sales, setSales] = useState<AssignableSales | null>(null);

    const methods = useForm<BookingFormValues>({
        resolver: zodResolver(
            isUpdate
                ? updateScheduleSchema
                : isStaff
                  ? staffBookingSchema
                  : staffBookingSchema.omit({ clientId: true, salesId: true }),
        ) as any,
        mode: 'onTouched',
        reValidateMode: 'onChange',
        defaultValues: {
            date: schedule === null ? (null as any) : fromDayKey(schedule.date),
            arrivalTime: schedule?.arrivalTime ?? '',
            purpose: schedule?.title ?? '',
            notes: schedule?.notes ?? '',
            clientId: null,
            salesId: null,
            reason: '',
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

        const outcome =
            schedule === null
                ? await sendBooking({ ...values, date: values.date })
                : await sendUpdate(schedule.id, {
                      ...values,
                      date: values.date,
                  });

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

            // The appointment itself can no longer change (approved, moved or cancelled meanwhile).
            if (outcome.errors.schedule) {
                setResult({
                    status: 'error',
                    message: outcome.errors.schedule,
                });
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
            clientName: client?.name ?? null,
            salesName: sales?.name ?? null,
        });
    };

    const handleSuccessClose = () => {
        if (isUpdate) {
            router.visit(scheduleHref);

            return;
        }

        setResult(null);
        reset();
        setClient(null);
        setSales(null);
        setMonth(studioToday);
    };

    const handleRetry = async () => {
        await handleSubmit(onSubmit)();
    };

    const dateError = formState.errors.date?.message;

    if (
        !can(isUpdate ? permissions.scheduleUpdate : permissions.scheduleCreate)
    ) {
        return null;
    }

    const heading = isUpdate ? 'Update appointment' : 'Book an appointment';
    const description = isUpdate
        ? !isStaff && schedule.status === 'confirmed'
            ? 'Change the day, time or details, and say why. The team confirms it again.'
            : isStaff &&
                (schedule.status === 'confirmed' ||
                    schedule.status === 'awaiting_sales')
              ? 'Change the day, time or details, and say why. A new time goes back to its sales member to confirm.'
              : 'Change the day, time or details, and say why.'
        : isStaff
          ? 'Choose the client and the sales member who confirms it, then the day and time.'
          : 'Pick a day and tell us when to expect you';

    return (
        <div className="w-full">
            <Head title={heading} />
            <PageHeading title={heading} description={description} />

            <div className="mt-6 grid gap-6 lg:grid-cols-2 lg:gap-8">
                <div className="order-2 space-y-6 lg:order-1">
                    <FormProvider {...methods}>
                        <form
                            onSubmit={handleSubmit(onSubmit)}
                            className="space-y-6"
                        >
                            {isUpdate && schedule.clientName ? (
                                <div data-testid="updating-client">
                                    <p className="text-sm font-medium text-foreground">
                                        Client
                                    </p>
                                    <p className="mt-1 text-sm font-bold">
                                        {schedule.clientName}
                                    </p>
                                </div>
                            ) : null}

                            {isStaff && !isUpdate ? (
                                <BookingClientPicker
                                    value={client}
                                    onChange={(account) => {
                                        setClient(account);
                                        setValue(
                                            'clientId',
                                            account?.id ?? null,
                                            {
                                                shouldValidate:
                                                    account !== null,
                                            },
                                        );
                                    }}
                                    error={formState.errors.clientId?.message}
                                />
                            ) : null}

                            {isStaff && !isUpdate ? (
                                <SalesPicker
                                    value={sales}
                                    onChange={(person) => {
                                        setSales(person);
                                        setValue(
                                            'salesId',
                                            person?.id ?? null,
                                            {
                                                shouldValidate: person !== null,
                                            },
                                        );
                                    }}
                                    error={formState.errors.salesId?.message}
                                />
                            ) : null}

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

                            {isUpdate ? (
                                <FormField
                                    id="reason"
                                    label="Reason for change *"
                                    error={formState.errors.reason?.message}
                                >
                                    {({ describedBy, invalid }) => (
                                        <Textarea
                                            id="reason"
                                            {...methods.register('reason')}
                                            rows={3}
                                            className="rounded-none"
                                            placeholder="e.g. The client asked for a later time"
                                            aria-invalid={invalid}
                                            aria-describedby={describedBy}
                                        />
                                    )}
                                </FormField>
                            ) : null}

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
                                    ) : isUpdate ? (
                                        'Update appointment'
                                    ) : isStaff ? (
                                        'Book appointment'
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

            <ResultDialog
                result={
                    result?.status === 'success'
                        ? {
                              status: 'success',
                              title: isUpdate
                                  ? 'Appointment updated'
                                  : isStaff
                                    ? 'Appointment booked'
                                    : 'Appointment requested',
                              description: isUpdate ? (
                                  <>
                                      It is now on{' '}
                                      {formatFullDate.format(result.date)} at{' '}
                                      {slotLabel(result.arrivalTime)}. The
                                      client is told in their Regray chat.
                                  </>
                              ) : isStaff ? (
                                  <>
                                      {result.clientName} is expected on{' '}
                                      {formatFullDate.format(result.date)} at{' '}
                                      {slotLabel(result.arrivalTime)}.{' '}
                                      {result.salesName ?? 'The sales member'}{' '}
                                      has been asked to confirm it; the client
                                      is told in their Regray chat once it is
                                      confirmed.
                                  </>
                              ) : (
                                  <>
                                      We&apos;ll expect you on{' '}
                                      {formatFullDate.format(result.date)} at{' '}
                                      {slotLabel(result.arrivalTime)}.
                                  </>
                              ),
                              actions: (
                                  <>
                                      <Button asChild>
                                          <Link href={scheduleHref}>
                                              Back to schedule
                                          </Link>
                                      </Button>
                                      {isUpdate ? null : (
                                          <Button
                                              variant="outline"
                                              onClick={handleSuccessClose}
                                          >
                                              Book another
                                          </Button>
                                      )}
                                  </>
                              ),
                          }
                        : result?.status === 'error'
                          ? {
                                status: 'error',
                                title: isUpdate
                                    ? "We couldn't update the appointment"
                                    : "We couldn't book your appointment",
                                description:
                                    result.message ??
                                    'Something went wrong on our side. Your answers are still here, so you can try again.',
                                actions: (
                                    <>
                                        {/* Held until a 429's wait is over, so a retry cannot extend it. */}
                                        <Countdown
                                            key={result.retryAt ?? 'now'}
                                            until={result.retryAt ?? null}
                                        >
                                            {(retryIn) => (
                                                <Button
                                                    onClick={handleRetry}
                                                    disabled={
                                                        isSending || retryIn > 0
                                                    }
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
                                    </>
                                ),
                            }
                          : null
                }
                onClose={() =>
                    result?.status === 'success'
                        ? handleSuccessClose()
                        : setResult(null)
                }
                locked={isSending}
            />
        </div>
    );
};

export default Page;
