import { X } from 'lucide-react';

export const ResultBadge = ({ status }: { status: 'success' | 'error' }) => {
    const isSuccess = status === 'success';

    return (
        <span
            aria-hidden
            className={`grid size-16 shrink-0 place-items-center rounded-md bg-black text-white ${
                isSuccess ? 'badge-pop' : 'error-shake'
            }`}
        >
            {isSuccess ? (
                <svg
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    strokeWidth={3}
                    strokeLinecap="square"
                    className="size-8"
                >
                    <path
                        d="M5 13l4 4 10-11"
                        pathLength={18}
                        className="check-draw"
                    />
                </svg>
            ) : (
                <X className="size-8" />
            )}
        </span>
    );
};
