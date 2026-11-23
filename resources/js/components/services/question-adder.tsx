import { Plus } from 'lucide-react';
import { useState } from 'react';
import TagInput from '@/components/tag-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { ANSWER_TYPES, newQuestionProblem } from '@/lib/service-admin';
import { cn } from '@/lib/utils';
import type {
    AnswerType,
    QuestionOption,
    ServiceLimits,
} from '@/types/service-admin';

/** A question row the form adds: one from the list by id, or a new one. */
export type AddedQuestion = {
    id: number | null;
    name: string;
    answer_type: AnswerType | '';
    choices: string[];
    required: boolean;
};

/**
 * "Ask another question": choose one from the list, or write a new one with
 * what kind of answer it takes (and, for a list, its choices). A new one is
 * created when the service is saved, as a question for the whole project.
 */
const QuestionAdder = ({
    available,
    takenNames,
    limits,
    onAdd,
}: {
    /** The list's questions the service doesn't ask yet. */
    available: QuestionOption[];
    /** Every question's name already in the list or on the form. */
    takenNames: string[];
    limits: ServiceLimits;
    onAdd: (question: AddedQuestion) => void;
}) => {
    const [mode, setMode] = useState<'choose' | 'write'>(
        available.length > 0 ? 'choose' : 'write',
    );
    const [chosen, setChosen] = useState('');
    const [name, setName] = useState('');
    const [answerType, setAnswerType] = useState<AnswerType | ''>('');
    const [choices, setChoices] = useState<string[]>([]);
    const [problem, setProblem] = useState<string | null>(null);

    const addWritten = () => {
        const found = newQuestionProblem(
            { name, answerType, choices },
            takenNames,
            limits,
        );

        if (found) {
            setProblem(found);

            return;
        }

        onAdd({
            id: null,
            name: name.trim(),
            answer_type: answerType,
            choices: answerType === 'choice' ? choices : [],
            required: true,
        });
        setName('');
        setAnswerType('');
        setChoices([]);
        setProblem(null);
    };

    const modeButton = (value: 'choose' | 'write', label: string) => (
        <Button
            type="button"
            variant="ghost"
            size="sm"
            aria-pressed={mode === value}
            onClick={() => {
                setMode(value);
                setProblem(null);
            }}
            className={cn(
                'rounded-none border border-sidebar-border',
                mode === value && 'border-foreground bg-muted',
            )}
        >
            {label}
        </Button>
    );

    return (
        <div className="space-y-3 border border-dashed border-sidebar-border p-3">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <p className="text-sm font-bold">Ask another question</p>
                <div
                    className="flex"
                    role="group"
                    aria-label="How to add a question"
                >
                    {modeButton('choose', 'From the list')}
                    {modeButton('write', 'New question')}
                </div>
            </div>

            {mode === 'choose' ? (
                available.length > 0 ? (
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
                                        answer_type: question.answerType,
                                        choices: [],
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
                ) : (
                    <p className="text-sm text-secondary-foreground">
                        It already asks every question on the list. Write a new
                        one instead.
                    </p>
                )
            ) : (
                <div className="space-y-3">
                    <div>
                        <label
                            htmlFor="service-new-question"
                            className="text-sm"
                        >
                            Question
                        </label>
                        <Input
                            id="service-new-question"
                            value={name}
                            onChange={(event) => {
                                setName(event.target.value);
                                setProblem(null);
                            }}
                            maxLength={limits.questionNameMax}
                            placeholder="Do you need a sink cut-out?"
                            className="mt-1 rounded-none"
                            aria-describedby="service-new-question-hint"
                        />
                        <p
                            id="service-new-question-hint"
                            className="mt-1 text-xs text-secondary-foreground"
                        >
                            Asked once for the project. It joins the list, so
                            other services can ask it too.
                        </p>
                    </div>
                    <div>
                        <label
                            htmlFor="service-new-question-type"
                            className="text-sm"
                        >
                            The answer it takes
                        </label>
                        <Select
                            value={answerType}
                            onValueChange={(value) => {
                                setAnswerType(value as AnswerType);
                                setProblem(null);
                            }}
                        >
                            <SelectTrigger
                                id="service-new-question-type"
                                className="mt-1 w-full rounded-none"
                            >
                                <SelectValue placeholder="Choose the kind of answer" />
                            </SelectTrigger>
                            <SelectContent>
                                {ANSWER_TYPES.map((type) => (
                                    <SelectItem
                                        key={type.value}
                                        value={type.value}
                                    >
                                        {type.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    {answerType === 'choice' ? (
                        <div>
                            <label
                                htmlFor="service-new-question-choices"
                                className="text-sm"
                            >
                                Choices
                            </label>
                            <p
                                id="service-new-question-choices-hint"
                                className="mt-0.5 text-xs text-secondary-foreground"
                            >
                                The answers the client picks from. Press Enter
                                or a comma after each; at least 2,{' '}
                                {limits.choicesMax} at most.
                            </p>
                            <div className="mt-1">
                                <TagInput
                                    id="service-new-question-choices"
                                    value={choices}
                                    onChange={(next) => {
                                        setChoices(next);
                                        setProblem(null);
                                    }}
                                    max={limits.choicesMax}
                                    maxLength={limits.choiceMax}
                                    placeholder="Single bowl"
                                    describedBy="service-new-question-choices-hint"
                                />
                            </div>
                        </div>
                    ) : null}
                    {problem ? (
                        <p role="alert" className="text-sm text-destructive">
                            {problem}
                        </p>
                    ) : null}
                    <Button
                        type="button"
                        variant="outline"
                        onClick={addWritten}
                    >
                        <Plus aria-hidden />
                        Add question
                    </Button>
                </div>
            )}
        </div>
    );
};

export default QuestionAdder;
