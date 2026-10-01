import { useEffect, useMemo, useRef, useState } from 'react';
import { useFormContext } from 'react-hook-form';
import { ArrowLeft, FileText, Pencil } from 'lucide-react';
import AlertError from '@/components/alert-error';
import { formatSize } from '@/components/file-upload';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { serviceOptions, stones } from '@/data/constant';
import {
    REQUIREMENTS_STEP,
    SERVICES_STEP,
    STONES_STEP,
    type InquiryFormInput,
    type InquiryFormValues,
} from '@/lib/inquiry-schema';

/**
 * `Intl` rather than `toISOString()`, which reports the UTC day: a reader east
 * of Greenwich would be shown the day before the one they picked. The `T00:00`
 * is what pins the reading to their own timezone, since a bare `YYYY-MM-DD` is
 * read as UTC midnight.
 */
const neededByFormatter = new Intl.DateTimeFormat(undefined, {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
});

const formatNeededBy = (value: string): string =>
    value === '' ? '' : neededByFormatter.format(new Date(`${value}T00:00`));

/** One answer, or the fact that there was none. */
const NotProvided = () => (
    <span className="font-normal text-secondary-foreground">Not provided</span>
);

const SummaryRow = ({
    label,
    value,
    multiline = false,
}: {
    label: string;
    value: string;
    multiline?: boolean;
}) => (
    <div className="min-w-0">
        <dt className="text-xs font-bold tracking-[0.2em] text-secondary-foreground uppercase">
            {label}
        </dt>
        <dd
            className={`mt-1.5 text-sm font-bold break-words text-foreground ${
                // Notes keep the reader's own line breaks, or a list of short
                // answers arrives as one paragraph.
                multiline ? 'whitespace-pre-wrap' : ''
            }`}
        >
            {value === '' ? <NotProvided /> : value}
        </dd>
    </div>
);

/**
 * The title, its count, the way to change it, and the section's complaint.
 *
 * The rule under the title is the only thing marking where a section begins, so
 * it does the work a box used to: it holds the heading to the content beneath it
 * and lets both run the full width of the page.
 */
const SectionHeader = ({
    title,
    count,
    message,
    changeLabel,
    onChange,
    locked,
}: {
    title: string;
    count?: string;
    message?: string;
    changeLabel: string;
    onChange: () => void;
    locked: boolean;
}) => (
    <>
        <div className="flex flex-wrap items-center justify-between gap-x-4 gap-y-3 border-b border-sidebar-border pb-4">
            <h3 className="text-lg font-bold tracking-[-0.03em] text-foreground sm:text-xl">
                {title}
            </h3>

            <div className="flex items-center gap-4">
                {count ? (
                    <p className="text-xs font-bold tracking-[0.2em] text-secondary-foreground uppercase">
                        {count}
                    </p>
                ) : null}

                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={onChange}
                    disabled={locked}
                    aria-label={changeLabel}
                    className="shrink-0"
                >
                    <Pencil aria-hidden />
                    Change
                </Button>
            </div>
        </div>

        {/*
         * The same complaint is said twice on purpose: once in the list at the
         * top, which is what a screen reader meets first, and once here, under
         * the rule, which is where the reader is looking when they decide what
         * to change.
         */}
        {message ? (
            <p
                role="alert"
                className="mt-5 text-[0.8rem] font-semibold text-secondary-foreground"
            >
                {message}
            </p>
        ) : null}
    </>
);

/**
 * A thumbnail for one attachment.
 *
 * The object URL is made here rather than borrowed from the uploader, because
 * the uploader revokes its own on unmount and the Review step is a different
 * component that outlives neither the other nor this one. Revoked on the way
 * out for the same reason.
 */
const AttachmentPreview = ({ file }: { file: File }) => {
    const [url, setUrl] = useState<string | null>(null);

    useEffect(() => {
        if (!file.type.startsWith('image/')) {
            return;
        }

        const objectUrl = URL.createObjectURL(file);

        setUrl(objectUrl);

        return () => URL.revokeObjectURL(objectUrl);
    }, [file]);

    if (url === null) {
        return <FileText className="size-5" aria-hidden />;
    }

    return <img src={url} alt="" className="size-full object-cover" />;
};

type InquiryReviewProps = {
    onChangeStones: () => void;
    onChangeServices: () => void;
    onChangeDetails: () => void;
    /** One short complaint per step, from the whole-form check. */
    sectionMessages: Partial<Record<number, string>>;
    /** The same complaints in full, for the alert above the page. */
    validationErrors: string[];
    /** True while the request is in flight, so nothing can change under it. */
    locked: boolean;
    onRequestQuote: () => void;
};

/**
 * Step 4 of the inquiry flow: everything the reader chose, and the one button
 * that sends it.
 *
 * This is a project sheet rather than another picker. Steps 1 and 2 are grids
 * and a list of things to tap, and step 3 is a form of empty controls; a page
 * of boxes to choose from one more time would ask the reader to check their own
 * answers by re-deciding them.
 *
 * It is one ruled document, not a stack of cards. Three bordered boxes said the
 * three parts were three separate objects to be dealt with one at a time; a
 * title, a rule and the answers under it say they are one sheet of notes, read
 * straight down. It also hands the whole width to the content, which is what
 * lets the stones be a contact sheet six across and the specification rows sit
 * three to a row.
 *
 * Values come from the stepper's one form rather than from props, so nothing
 * shown here can disagree with what the next send would actually carry.
 */
const InquiryReview = ({
    onChangeStones,
    onChangeServices,
    onChangeDetails,
    sectionMessages,
    validationErrors,
    locked,
    onRequestQuote,
}: InquiryReviewProps) => {
    const { watch } = useFormContext<
        InquiryFormInput,
        unknown,
        InquiryFormValues
    >();

    const stoneIds = watch('stoneIds');
    const serviceIds = watch('serviceIds');
    const projectName = watch('projectName').trim();
    const location = watch('location');
    const landmark = watch('landmark').trim();
    const edge = watch('edge');
    const finish = watch('finish');
    const areaSqm = watch('areaSqm').trim();
    const neededBy = watch('neededBy');
    const notes = watch('notes');
    const attachments = watch('attachments');

    /*
     * Looked up in the order they were picked rather than in the order the
     * catalogue lists them, because the order they were picked in is the
     * reader's own and is what the sheet should be reading back.
     */
    const chosenStones = useMemo(
        () =>
            stoneIds
                .map((id) => stones.find((stone) => stone.id === id))
                .filter((stone): stone is (typeof stones)[number] =>
                    Boolean(stone),
                ),
        [stoneIds],
    );

    const chosenServices = useMemo(
        () =>
            serviceIds
                .map((id) =>
                    serviceOptions.find((service) => service.id === id),
                )
                .filter((service): service is (typeof serviceOptions)[number] =>
                    Boolean(service),
                ),
        [serviceIds],
    );

    const alertRef = useRef<HTMLDivElement>(null);
    const hasMovedFocus = useRef(false);

    /*
     * Focus follows the alert, once per batch of complaints. The flag matters
     * because re-validating re-renders with the same messages, and dragging
     * focus back on every re-render would fight whatever the reader was doing
     * with it in between.
     */
    useEffect(() => {
        if (validationErrors.length === 0) {
            hasMovedFocus.current = false;
            return;
        }

        if (hasMovedFocus.current) {
            return;
        }

        hasMovedFocus.current = true;
        alertRef.current?.focus();
    }, [validationErrors]);

    return (
        <div>
            {/* Above everything, because it is the answer to the button. */}
            <div ref={alertRef} tabIndex={-1} className="outline-none">
                {validationErrors.length > 0 ? (
                    <AlertError
                        errors={validationErrors}
                        title="A few things need attention"
                    />
                ) : null}
            </div>

            {/*
             * The three sections, as one document: a title, a rule, the answers.
             * The `mt-8` reads as the gap below the stepper whether or not the
             * alert above has shown itself, so the first rule always sits the
             * same distance down the page.
             *
             * There is no summary of the counts above them, and no numbering on
             * the headings. Two lines of glance told the reader nothing the
             * sections do not, and a number beside a title claims the sections
             * have an order worth keeping track of, when all they are is three
             * things they answered on three screens.
             */}
            <div className="mt-8 space-y-12">
                <section>
                    <SectionHeader
                        title="Stones"
                        count={`${stoneIds.length} selected`}
                        message={sectionMessages[STONES_STEP]}
                        changeLabel="Change stones"
                        onChange={onChangeStones}
                        locked={locked}
                    />

                    {/*
                     * A contact sheet: as many thumbnails across as the width
                     * allows, so the reader sees the selection as a set of
                     * materials rather than as cards they might pick again.
                     */}
                    <div className="mt-6 grid grid-cols-2 gap-x-4 gap-y-5 sm:grid-cols-3 lg:grid-cols-6">
                        {chosenStones.map((stone) => (
                            <div key={stone.id} className="min-w-0">
                                <img
                                    src={stone.thumbnail}
                                    alt=""
                                    loading="lazy"
                                    className="aspect-square w-full object-cover"
                                />
                                <p className="mt-2 truncate text-sm font-bold text-foreground">
                                    {stone.name}
                                </p>
                            </div>
                        ))}
                    </div>
                </section>

                <section>
                    <SectionHeader
                        title="Services"
                        count={`${serviceIds.length} selected`}
                        message={sectionMessages[SERVICES_STEP]}
                        changeLabel="Change services"
                        onChange={onChangeServices}
                        locked={locked}
                    />

                    {/*
                     * Three to a row, because a list one name to a row stretched
                     * across the page reads as nine separate lines of prose
                     * rather than as nine things included.
                     */}
                    <ul className="mt-6 grid gap-x-8 gap-y-4 sm:grid-cols-2 lg:grid-cols-3">
                        {chosenServices.map((service) => (
                            <li
                                key={service.id}
                                className="flex min-w-0 items-center gap-3"
                            >
                                <img
                                    src={service.image}
                                    alt=""
                                    loading="lazy"
                                    className="size-11 shrink-0 object-cover"
                                />
                                <span className="min-w-0 truncate text-sm font-bold text-foreground">
                                    {service.name}
                                </span>
                            </li>
                        ))}
                    </ul>
                </section>

                <section>
                    <SectionHeader
                        title="Project details"
                        message={sectionMessages[REQUIREMENTS_STEP]}
                        changeLabel="Change project details"
                        onChange={onChangeDetails}
                        locked={locked}
                    />

                    {/* The stone profile's own specification rows, three across. */}
                    <dl className="mt-6 grid gap-x-10 gap-y-6 sm:grid-cols-2 lg:grid-cols-3">
                        <SummaryRow label="Project name" value={projectName} />
                        <SummaryRow
                            label="Location"
                            value={[location, landmark]
                                .filter(Boolean)
                                .join(', ')}
                        />
                        <SummaryRow label="Edge profile" value={edge} />
                        <SummaryRow label="Finish" value={finish} />
                        <SummaryRow
                            label="Approximate area"
                            value={areaSqm === '' ? '' : `${areaSqm} sqm`}
                        />
                        <SummaryRow
                            label="Needed by"
                            value={formatNeededBy(neededBy)}
                        />
                        {/* Notes span the row: a paragraph in one third of the
                         * width wraps into something unreadable. */}
                        <div className="sm:col-span-2 lg:col-span-3">
                            <SummaryRow label="Notes" value={notes} multiline />
                        </div>
                    </dl>

                    <div className="mt-10 border-t border-sidebar-border pt-6">
                        <h4 className="text-[0.65rem] font-bold tracking-[0.2em] text-secondary-foreground uppercase">
                            Attachments
                        </h4>

                        {attachments.length === 0 ? (
                            <p className="mt-2 text-sm text-secondary-foreground">
                                Not provided
                            </p>
                        ) : (
                            /*
                             * Two to a row, each with its own rule: a `divide-y`
                             * across two columns only rules the left one, which
                             * leaves the right column looking like it is missing
                             * its top border.
                             */
                            <ul className="mt-4 grid gap-x-8 sm:grid-cols-2">
                                {attachments.map((attachment) => (
                                    <li
                                        key={attachment.id}
                                        className="flex min-w-0 items-center gap-3 border-t border-sidebar-border py-3"
                                    >
                                        <span className="grid size-10 shrink-0 place-items-center overflow-hidden border border-sidebar-border bg-background text-secondary-foreground">
                                            <AttachmentPreview
                                                file={attachment.file}
                                            />
                                        </span>
                                        <span className="min-w-0 flex-1">
                                            <span
                                                className="block truncate text-sm font-bold text-foreground"
                                                title={attachment.file.name}
                                            >
                                                {attachment.file.name}
                                            </span>
                                            <span className="block text-xs text-secondary-foreground">
                                                {formatSize(
                                                    attachment.file.size,
                                                )}
                                            </span>
                                        </span>
                                        {/*
                                         * A file still in flight is shown as
                                         * such, because the send is waiting on it
                                         * and an unexplained wait reads as a
                                         * broken button.
                                         */}
                                        {attachment.status === 'uploading' ? (
                                            <span className="flex shrink-0 items-center gap-1.5 text-xs text-secondary-foreground">
                                                <Spinner className="size-3.5 motion-reduce:animate-none" />
                                                {attachment.progress}%
                                            </span>
                                        ) : null}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                </section>
            </div>

            {/*
             * The same bar as the other steps, at the same `mt-6`, with the one
             * button that actually sends anything. The sections above are spaced
             * further apart, but the gap into the bar is the one the reader sees
             * on all four steps and it should not change on the last one.
             */}
            <div className="sticky bottom-0 z-10 mt-6 flex flex-col gap-3 border-t border-sidebar-border bg-background py-3 sm:flex-row sm:items-center sm:justify-between">
                <p className="min-w-0 flex-1 text-xs text-secondary-foreground">
                    Nothing is sent until you request a quote
                </p>

                <div className="flex w-full shrink-0 flex-col-reverse gap-3 sm:w-auto sm:flex-row sm:items-center">
                    <Button
                        type="button"
                        variant="outline"
                        disabled={locked}
                        onClick={onChangeDetails}
                        className="w-full cursor-pointer sm:w-auto"
                    >
                        <ArrowLeft aria-hidden />
                        Back
                    </Button>
                    <Button
                        type="button"
                        disabled={locked}
                        onClick={onRequestQuote}
                        className="w-full sm:w-auto"
                    >
                        {locked ? (
                            <>
                                <Spinner />
                                Sending...
                            </>
                        ) : (
                            'Request a Quote'
                        )}
                    </Button>
                </div>
            </div>
        </div>
    );
};

export default InquiryReview;
