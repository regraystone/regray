import { Sparkles } from 'lucide-react';
import { useFormContext, useWatch } from 'react-hook-form';
import { fieldMessage } from '@/components/form-field';
import { InquiryActionBar } from '@/components/inquiry-question';
import ServiceOptionSelect from '@/components/service-option-select';
import { Button } from '@/components/ui/button';
import { useInquiryCatalog } from '@/hooks/use-inquiry-catalog';
import { workProblems, workRowLabel } from '@/lib/inquiry-flow';
import type {
    InquiryDraftInput,
    InquiryDraftValues,
} from '@/lib/inquiry-schema';

/**
 * The second question: one row per stone, each with the service it needs, and a
 * furniture type where that service asks for one. "Help me choose" is one row
 * with no stone.
 *
 * The page owns every write, because a change here has consequences it keeps
 * together: clearing what a new option no longer offers, and re-checking rows
 * once Next has been pressed.
 */
const InquiryStoneWork = ({
    rowKeys,
    onOption,
    onFurnitureType,
    onNext,
}: {
    rowKeys: string[];
    onOption: (key: string, serviceId: string) => void;
    onFurnitureType: (key: string, type: string) => void;
    onNext: () => void;
}) => {
    const { stones, services, furnitureTypes } = useInquiryCatalog();
    const {
        control,
        formState: { errors },
    } = useFormContext<InquiryDraftInput, unknown, InquiryDraftValues>();
    const work = useWatch({ control, name: 'stoneWork' });

    const unfinished = new Set(
        workProblems(
            rowKeys,
            work,
            services,
            furnitureTypes.map(({ id }) => id),
        ).map(({ key }) => key),
    );
    const ready = rowKeys.length - unfinished.size;

    return (
        <div>
            <ul className="grid grid-cols-1 gap-x-6 gap-y-8 sm:grid-cols-2">
                {rowKeys.map((key) => {
                    const entry = work[key];
                    const option = services.find(
                        ({ id }) => id === entry?.serviceId,
                    );
                    const label = workRowLabel(key, stones);
                    const stone = stones.find(({ slug }) => slug === key);
                    const rowErrors = errors.stoneWork?.[key];

                    return (
                        <li
                            key={key}
                            className="min-w-0 space-y-4"
                            data-testid="stone-work-row"
                        >
                            <div className="flex min-w-0 items-center gap-4">
                                {stone?.thumbnail ? (
                                    <img
                                        src={stone.thumbnail}
                                        alt=""
                                        loading="lazy"
                                        className="size-14 shrink-0 rounded-md object-cover"
                                    />
                                ) : (
                                    <span
                                        aria-hidden
                                        className="grid size-14 shrink-0 place-items-center rounded-md bg-secondary text-secondary-foreground"
                                    >
                                        <Sparkles className="size-5" />
                                    </span>
                                )}
                                <p className="min-w-0 text-base font-bold break-words">
                                    {label}
                                </p>
                            </div>

                            <div>
                                <ServiceOptionSelect
                                    id={`stone-work-${key}`}
                                    labelContext={label}
                                    value={entry?.serviceId ?? ''}
                                    onChange={(serviceId) =>
                                        onOption(key, serviceId)
                                    }
                                    error={fieldMessage(rowErrors?.serviceId)}
                                    options={services}
                                />
                            </div>

                            {option?.needsFurniture ? (
                                <div>
                                    <ServiceOptionSelect
                                        id={`stone-work-${key}-furniture-type`}
                                        label="Which furniture?"
                                        labelContext={label}
                                        value={entry?.furnitureType ?? ''}
                                        onChange={(type) =>
                                            onFurnitureType(key, type)
                                        }
                                        error={fieldMessage(
                                            rowErrors?.furnitureType,
                                        )}
                                        options={furnitureTypes}
                                    />
                                </div>
                            ) : null}
                        </li>
                    );
                })}
            </ul>

            <InquiryActionBar>
                <p
                    aria-live="polite"
                    className="min-w-0 flex-1 text-sm font-bold text-foreground"
                >
                    {ready} of {rowKeys.length} ready
                </p>

                {/*
                 * Left enabled, like the other questions: a press that is refused
                 * says what is missing beside each row, which a greyed-out button
                 * cannot.
                 */}
                <Button
                    type="button"
                    onClick={onNext}
                    className="w-full sm:w-auto"
                >
                    Next
                </Button>
            </InquiryActionBar>
        </div>
    );
};

export default InquiryStoneWork;
