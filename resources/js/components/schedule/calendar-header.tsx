import { ChevronLeft, ChevronRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { formatMonthYear } from '@/lib/date';

export const CalendarHeader = ({
    month,
    onPrevMonth,
    onNextMonth,
    disablePrevious,
    prevLabel = 'Previous month',
    nextLabel = 'Next month',
}: {
    month: Date;
    onPrevMonth: () => void;
    onNextMonth: () => void;
    disablePrevious?: boolean;
    prevLabel?: string;
    nextLabel?: string;
}) => {
    return (
        <div className="mb-3 flex items-center justify-between gap-2">
            <p className="text-sm font-bold tracking-[-0.02em]">
                {formatMonthYear.format(month)}
            </p>

            <div className="flex items-center gap-1">
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    onClick={onPrevMonth}
                    aria-label={prevLabel}
                    className="size-8 px-0"
                    disabled={disablePrevious}
                >
                    <ChevronLeft aria-hidden />
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    onClick={onNextMonth}
                    aria-label={nextLabel}
                    className="size-8 px-0"
                >
                    <ChevronRight aria-hidden />
                </Button>
            </div>
        </div>
    );
};
