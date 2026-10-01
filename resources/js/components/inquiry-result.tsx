import { Link } from '@inertiajs/react';
import { ArrowLeft, MessageCircle, RotateCcw, X } from 'lucide-react';
import { useEffect, useRef, type ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { index as messageIndex } from '@/routes/message';

type InquiryResultProps = {
    status: 'success' | 'error';
    projectName: string;
    /** The chat is only offered to someone allowed to open it. */
    canChat: boolean;
    /**
     * The list this reader keeps their inquiries on, or null if they may open
     * neither, in which case no link to a list is offered at all.
     */
    inquiriesHref: string | null;
    onTryAgain: () => void;
    onBackToReview: () => void;
    onInquireAgain: () => void;
    /** True while a retry is in flight, which locks the panel's buttons. */
    submitting: boolean;
};

/**
 * What happened to the request, in place of the Review sheet.
 *
 * The badge, the copy and the two buttons are the same shape either way, so the
 * panel is read as one thing that has failed rather than as a different screen:
 * the black square and the white glyph are the only difference, and they sit in
 * the same box at the same size.
 *
 * Success empties the form, which is why the sheet and the stepper cannot stay
 * behind this. Failure leaves every answer exactly where it was, which is what
 * the wording has to promise before it offers a retry.
 *
 * Focus lands on the heading because this panel is not somewhere the reader
 * asked to go: a keyboard user who pressed "Request a Quote" would otherwise
 * be left on a button that no longer exists.
 */
const InquiryResult = ({
    status,
    projectName,
    canChat,
    inquiriesHref,
    onTryAgain,
    onBackToReview,
    onInquireAgain,
    submitting,
}: InquiryResultProps) => {
    const headingRef = useRef<HTMLHeadingElement>(null);
    const isSuccess = status === 'success';

    useEffect(() => {
        headingRef.current?.focus();
    }, []);

    const badge = (
        /*
         * The black square with a white glyph that the cards use for a chosen
         * stone. `aria-hidden` because the heading beside it already says what
         * happened, and a bare check mark read aloud would only say "check".
         */
        <span
            aria-hidden
            className={`grid size-16 shrink-0 place-items-center bg-black text-white ${
                isSuccess ? 'badge-pop' : 'error-shake'
            }`}
        >
            {isSuccess ? (
                /*
                 * The tick draws itself after the badge has popped, which is why
                 * `pathLength` normalises the stroke: the dash is written in
                 * fractions of the path rather than in pixels, so the same two
                 * numbers animate any size of mark.
                 */
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

    /*
     * The two buttons, stacked on a phone and side by side from `sm`: there is
     * no third column to divide, and the sheet's bar is already the place for
     * the way back.
     *
     * Both labels are kept short because the panel is read once, at the end, and
     * the sentence above it has already said what each button leads to. Long
     * labels only made the pair an uneven wall of text where two plain verbs sit
     * better.
     */
    const actions: ReactNode[] = isSuccess
        ? [
              /*
               * Chat first, because a sent request is most useful as a
               * conversation. A reader without the chat gets the list they can
               * actually open, and a reader with neither gets no link rather
               * than one that would refuse them.
               */
              canChat ? (
                  <Button key="chat" asChild>
                      <Link href={messageIndex.url()}>
                          <MessageCircle aria-hidden />
                          Message us
                      </Link>
                  </Button>
              ) : inquiriesHref ? (
                  <Button key="inquiries" asChild>
                      <Link href={inquiriesHref}>My inquiries</Link>
                  </Button>
              ) : null,
              <Button
                  key="again"
                  type="button"
                  variant="outline"
                  onClick={onInquireAgain}
              >
                  Start over
              </Button>,
          ]
        : [
              <Button
                  key="retry"
                  type="button"
                  onClick={onTryAgain}
                  disabled={submitting}
              >
                  {submitting ? (
                      <>
                          <Spinner />
                          Sending...
                      </>
                  ) : (
                      <>
                          <RotateCcw aria-hidden />
                          Try again
                      </>
                  )}
              </Button>,
              <Button
                  key="back"
                  type="button"
                  variant="outline"
                  onClick={onBackToReview}
                  disabled={submitting}
              >
                  <ArrowLeft aria-hidden />
                  Back to review
              </Button>,
          ];

    return (
        <section
            role={isSuccess ? 'status' : 'alert'}
            className="mx-auto flex w-full max-w-xl flex-col items-center border border-sidebar-border bg-background px-6 py-16 text-center"
        >
            {badge}

            {isSuccess ? (
                <>
                    <h3
                        ref={headingRef}
                        tabIndex={-1}
                        className="mt-6 text-lg font-bold tracking-[-0.03em] outline-none"
                    >
                        Quote requested
                    </h3>
                    <p className="mt-2 max-w-sm text-sm text-foreground">
                        We&apos;ve received your request for {projectName}.
                    </p>
                </>
            ) : (
                <>
                    <h3
                        ref={headingRef}
                        tabIndex={-1}
                        className="mt-6 text-lg font-bold tracking-[-0.03em] outline-none"
                    >
                        We couldn&apos;t send your request
                    </h3>
                    <p className="mt-2 max-w-sm text-sm text-secondary-foreground">
                        Something went wrong on our side. Your answers are still
                        saved, so you can try again.
                    </p>
                </>
            )}

            <div className="mt-8 flex w-full flex-col gap-3 sm:flex-row sm:justify-center">
                {actions}
            </div>
        </section>
    );
};

export default InquiryResult;
