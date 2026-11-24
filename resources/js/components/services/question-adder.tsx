import { Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { index as serviceListIndex } from '@/routes/dashboard/services/lists';
import type { QuestionOption } from '@/types/service-admin';

/** A question the service asks, chosen from the Questions page's list. */
export type AddedQuestion = {
    id: number;
    name: string;
    required: boolean;
};

/**
 * "Ask another question": choose one from the Questions page's list. A new
 * question is written there, under Services, not here.
 */
const QuestionAdder = ({
    available,
    onAdd,
}: {
    /** The list's questions the service doesn't ask yet. */
    available: QuestionOption[];
    onAdd: (question: AddedQuestion) => void;
}) => {
    const [chosen, setChosen] = useState('');
    const questionsLink = (
        <Link
            href={serviceListIndex.url('questions')}
            className="font-bold text-foreground underline underline-offset-4"
        >
            Questions page
        </Link>
    );

    return (
        <div className="space-y-3 border border-dashed border-sidebar-border p-3">
            <p className="text-sm font-bold">Ask another question</p>

            {available.length > 0 ? (
                <>
                    <div className="flex flex-col gap-2 sm:flex-row sm:items-start">
                        <div className="min-w-0 flex-1">
                            <label
                                htmlFor="service-add-question"
                                className="sr-only"
                            >
                                Question
                            </label>
                            <Select value={chosen} onValueChange={setChosen}>
                                <SelectTrigger
                                    id="service-add-question"
                                    className="w-full rounded-none"
                                >
                                    <SelectValue placeholder="Choose a question" />
                                </SelectTrigger>
                                <SelectContent>
                                    {available.map((question) => (
                                        <SelectItem
                                            key={question.id}
                                            value={String(question.id)}
                                        >
                                            {question.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <Button
                            type="button"
                            variant="outline"
                            disabled={chosen === ''}
                            onClick={() => {
                                const question = available.find(
                                    (option) => option.id === Number(chosen),
                                );

                                if (question) {
                                    onAdd({
                                        id: question.id,
                                        name: question.name,
                                        required: true,
                                    });
                                }

                                setChosen('');
                            }}
                        >
                            <Plus aria-hidden />
                            Add question
                        </Button>
                    </div>
                    <p className="text-xs text-secondary-foreground">
                        Not on the list? Add it on the {questionsLink}.
                    </p>
                </>
            ) : (
                <p className="text-sm text-secondary-foreground">
                    It already asks every question on the list. Add a new one on
                    the {questionsLink}.
                </p>
            )}
        </div>
    );
};

export default QuestionAdder;
