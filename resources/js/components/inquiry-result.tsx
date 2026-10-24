import { Link } from '@inertiajs/react';
import { ArrowLeft, MessageCircle, RotateCcw } from 'lucide-react';
import { useEffect, useRef, type ReactNode } from 'react';
import Countdown from '@/components/countdown';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { retryLabel } from '@/lib/rate-limit';
import { index as messageIndex } from '@/routes/message';
import { ResultBadge } from './result-badge';

type InquiryResultProps = {
    status: 'success' | 'error';
    /**
     * What the request is known by, so the reader can recognise it in the list
     * they are about to be sent to. The name of the first project, which is what
     * the whole request is called everywhere else.
     */
    title: string;
    /** The reference the backend gave the inquiry, shown on success. */
    reference?: string;
    /** What went wrong, in the backend's words, shown on error. */
    message?: string;
    /**
     * When a 429 lets the request go again (a Date.now() timestamp), or null.
     * Try again is held until then, so a retry cannot extend the wait.
     */
    retryAt?: number | null;
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
    title,
    reference,
    message,
    retryAt = null,
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

    const badge = <ResultBadge status={status} />;

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
                      <Link
                          href={messageIndex.url({
                              query: { conversation: 'support' },
                          })}
                      >
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
              <Countdown key={`retry-${retryAt ?? 'now'}`} until={retryAt}>
                  {(retryIn) => (
                      <Button
                          type="button"
                          onClick={onTryAgain}
                          disabled={submitting || retryIn > 0}
                      >
                          {submitting ? (
                              <>
                                  <Spinner />
                                  Sending...
                              </>
                          ) : (
                              <>
                                  <RotateCcw aria-hidden />
                                  {retryLabel(retryIn)}
                              </>
                          )}
                      </Button>
                  )}
              </Countdown>,
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
            className="mx-auto flex w-full max-w-xl flex-col items-center rounded-md border border-sidebar-border bg-background px-6 py-16 text-center"
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
                        We&apos;ve received your request for {title}.
                    </p>
                    {reference ? (
                        <p className="mt-1 text-sm text-secondary-foreground">
                            Your reference is{' '}
                            <span className="font-bold text-foreground">
                                {reference}
                            </span>
                            .
                        </p>
                    ) : null}
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
                        {/*
                         * The same sentence the server flashes when the save
                         * itself fails, so the reader hears one promise
                         * whichever side the failure was on.
                         */}
                        {message ??
                            'We could not save your inquiry. Your answers are still here, so you can try again.'}
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
