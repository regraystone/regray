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
import type { QuestionOption } from '@/types/service-admin';

/** A question the service asks, chosen from the Questions page's list. */
export type AddedQuestion = {
    id: number;
    name: string;
    required: boolean;
};

/**
 * "Ask another question": choose one from the Questions page's list. A new
 * question is written on the Questions page: "Add a new question" goes there
 * with the form kept, and comes back with it asked (lib/admin-handoff).
 */
const QuestionAdder = ({
    available,
    onAdd,
    onAddNew,
}: {
    /** The list's questions the service doesn't ask yet. */
    available: QuestionOption[];
    onAdd: (question: AddedQuestion) => void;
    /** Keep the form and write a new question on the Questions page. */
    onAddNew: () => void;
}) => {
    const [chosen, setChosen] = useState('');
    const addNew = (
        <button
            type="button"
            onClick={onAddNew}
            className="font-bold text-foreground underline underline-offset-4"
            data-testid="service-add-new-question"
        >
            Add a new question
        </button>
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
                        Not on the list? {addNew}. You come back here with it
                        asked.
                    </p>
                </>
            ) : (
                <p className="text-sm text-secondary-foreground">
                    It already asks every question on the list. {addNew}.
                </p>
            )}
        </div>
    );
};

export default QuestionAdder;
