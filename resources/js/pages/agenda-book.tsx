import { zodResolver } from '@hookform/resolvers/zod';
import { Head, Link, usePage } from '@inertiajs/react';
import { useMemo, useRef, useState, useEffect } from 'react';
import { FormProvider, useForm } from 'react-hook-form';
import BookingCalendar from '@/components/agenda/booking-calendar';
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
import { index as agendaIndex } from '@/routes/agenda';
import { agenda as profileAgenda } from '@/routes/profile';
import { usePermissions } from '@/hooks/use-permissions';
import { formatFullDate, atTime, startOfDay } from '@/lib/date';
import {
    bookingSchema,
    bookingSlots,
    BOOKING_CLOSE_HOUR,
} from '@/lib/booking-schema';
import type { z } from 'zod';

type BookingFormValues = z.infer<typeof bookingSchema>;

type BookingResult =
    | { status: 'success'; date: Date; arrivalTime: string }
    | { status: 'error' }
    | null;

const submitBooking = async (_values: BookingFormValues): Promise<void> => {
    const delay = 1200 + Math.random() * 600;
    return new Promise((resolve, reject) => {
        window.setTimeout(() => {
            if (
                import.meta.env.DEV &&
                window.location.search.includes('simulate=error')
            ) {
                reject(new Error('simulated'));
            } else {
                resolve();
            }
        }, delay);
    });
};

const Page = () => {
    const { url } = usePage();
    const { can } = usePermissions();
    const isProfile = url.startsWith('/profile');
    const agendaHref = isProfile ? profileAgenda.url() : agendaIndex.url();
    const slots = useMemo(() => bookingSlots(), []);

    const [month, setMonth] = useState(() => startOfDay(new Date()));
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

    const { handleSubmit, setValue, watch, reset, formState } = methods;
    const date = watch('date');
    const arrivalTime = watch('arrivalTime');

    const [now] = useState(() => new Date());

    const isDisabled = (day: Date): boolean => {
        const d = startOfDay(day);
        const today = startOfDay(now);
        if (d < today) {
            return true;
        }
        if (d.getTime() === today.getTime()) {
            if (now.getHours() >= BOOKING_CLOSE_HOUR) {
                return true;
            }
        }
        return false;
    };

    const availableSlots = useMemo(() => {
        if (!date) {
            return slots;
        }
        const d = startOfDay(date);
        const today = startOfDay(now);
        if (d.getTime() !== today.getTime()) {
            return slots;
        }
        return slots.filter((s) => {
            const [hm, period] = s.split(' ');
            const [hStr, mStr] = hm.split(':');
            let h = parseInt(hStr, 10);
            const m = parseInt(mStr, 10);
            if (period === 'PM' && h !== 12) {
                h += 12;
            }
            if (period === 'AM' && h === 12) {
                h = 0;
            }
            const slotTime = atTime(d, h, m);
            return slotTime >= now;
        });
    }, [date, now, slots]);

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
        try {
            await submitBooking(values);
        } catch {
            isSendingRef.current = false;
            setIsSending(false);
            setResult({ status: 'error' });
            return;
        }
        isSendingRef.current = false;
        setIsSending(false);
        setResult({
            status: 'success',
            date: values.date,
            arrivalTime: values.arrivalTime,
        });
    };

    const handleSuccessClose = () => {
        setResult(null);
        reset();
        setMonth(startOfDay(new Date()));
    };

    const handleRetry = async () => {
        await handleSubmit(onSubmit)();
    };

    const dateError = formState.errors.date?.message;

    if (!can(permissions.agendaCreate)) {
        return null;
    }

    return (
        <div className="mx-auto w-full max-w-6xl">
            <Head title="Book a visit" />
            <PageHeading
                title="Book a visit"
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
                                        <SelectContent className="rounded-none">
                                            {availableSlots.map((slot) => (
                                                <SelectItem
                                                    key={slot}
                                                    value={slot}
                                                    className="rounded-none"
                                                >
                                                    {slot}
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
                                    <Link href={agendaHref}>
                                        Back to agenda
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
                                        'Request visit'
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
                    className="rounded-none sm:max-w-md"
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
                                <DialogHeader className="mt-6">
                                    <DialogTitle
                                        tabIndex={-1}
                                        className="outline-none"
                                    >
                                        Visit requested
                                    </DialogTitle>
                                    <DialogDescription className="mt-2">
                                        We&apos;ll expect you on{' '}
                                        {formatFullDate.format(result.date)} at{' '}
                                        {result.arrivalTime}.
                                    </DialogDescription>
                                </DialogHeader>
                            </div>
                            <DialogFooter className="mt-8 flex-col gap-3 sm:flex-row sm:justify-center">
                                <Button asChild>
                                    <Link href={agendaHref}>
                                        Back to agenda
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
                                <DialogHeader className="mt-6">
                                    <DialogTitle
                                        tabIndex={-1}
                                        className="outline-none"
                                    >
                                        We couldn&apos;t book your visit
                                    </DialogTitle>
                                    <DialogDescription className="mt-2">
                                        Something went wrong on our side. Your
                                        answers are still here, so you can try
                                        again.
                                    </DialogDescription>
                                </DialogHeader>
                            </div>
                            <DialogFooter className="mt-8 flex-col gap-3 sm:flex-row sm:justify-center">
                                <Button
                                    onClick={handleRetry}
                                    disabled={isSending}
                                >
                                    {isSending ? (
                                        <>
                                            <Spinner />
                                            Sending...
                                        </>
                                    ) : (
                                        'Try again'
                                    )}
                                </Button>
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
