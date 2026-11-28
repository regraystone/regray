import { Check, Plus } from 'lucide-react';
import { MAX_PROJECTS } from '@/lib/inquiry-schema';

/**
 * The top of Inquire's first step: one request can hold several products,
 * which clients did not know (Sir Mark's comment), so it is said before
 * anything is asked. It lists the products already in this quote, ticked, and
 * the one being filled in now, and says where the next one is added: on
 * Review, once this one is done, as each product needs its own answers first.
 */
const InquiryOneQuote = ({
    projects,
    current,
}: {
    /** The products already in the quote, in order. */
    projects: { id: string; name: string }[];
    /** Which product is being filled in now, from 1: a new one is one past the list. */
    current: number;
}) => {
    const rows = [
        ...projects.map((project, index) => ({
            key: project.id,
            number: index + 1,
            name: project.name,
        })),
        ...(current > projects.length
            ? [{ key: 'new', number: current, name: '' }]
            : []),
    ];
    const canAddMore = rows.length < MAX_PROJECTS;

    return (
        <section
            aria-labelledby="one-quote-heading"
            className="mb-6 border border-foreground/30 px-4 py-4 sm:px-5"
            data-testid="one-quote"
        >
            <h2
                id="one-quote-heading"
                className="text-xs font-semibold tracking-[0.12em] uppercase"
            >
                One quote · up to {MAX_PROJECTS} products
            </h2>
            <p className="mt-1.5 text-sm leading-normal text-secondary-foreground">
                Ask for several products in one request, like stones, an
                installation and a table. We send them to our team together.
            </p>

            <ol className="mt-3 space-y-2 text-sm">
                {rows.map((row) => {
                    const isCurrent = row.number === current;

                    return (
                        <li
                            key={row.key}
                            className="flex items-center gap-3"
                            aria-current={isCurrent ? 'step' : undefined}
                        >
                            <span
                                aria-hidden
                                className={`grid size-6 shrink-0 place-items-center rounded-full border text-xs font-semibold ${
                                    isCurrent
                                        ? 'border-foreground bg-foreground text-background'
                                        : 'border-foreground'
                                }`}
                            >
                                {isCurrent ? (
                                    row.number
                                ) : (
                                    <Check className="size-3.5" />
                                )}
                            </span>
                            <span className="min-w-0 truncate">
                                {isCurrent ? (
                                    <>
                                        <span className="font-semibold">
                                            Product {row.number}
                                        </span>
                                        <span className="text-secondary-foreground">
                                            {' '}
                                            · filling in now
                                            {row.name ? ` (${row.name})` : ''}
                                        </span>
                                    </>
                                ) : (
                                    <>
                                        <span className="font-semibold">
                                            Product {row.number}
                                        </span>
                                        <span className="text-secondary-foreground">
                                            {' '}
                                            · {row.name}
                                        </span>
                                    </>
                                )}
                            </span>
                        </li>
                    );
                })}

                {canAddMore ? (
                    <li className="flex items-center gap-3 text-secondary-foreground">
                        <span
                            aria-hidden
                            className="grid size-6 shrink-0 place-items-center rounded-full border border-dashed border-foreground/50"
                        >
                            <Plus className="size-3.5" />
                        </span>
                        <span>
                            Add another product on Review, once this one is
                            done.
                        </span>
                    </li>
                ) : null}
            </ol>
        </section>
    );
};

export default InquiryOneQuote;
