import { Link } from '@inertiajs/react';
import { ArrowLeft, MessageCircle, RotateCcw } from 'lucide-react';
import Countdown from '@/components/countdown';
import ResultDialog from '@/components/result-dialog';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { retryLabel } from '@/lib/rate-limit';
import { index as inboxIndex } from '@/routes/inbox';

export type InquiryResultState =
    | {
          status: 'success';
          /** What the request is known by, so the reader can recognise it. */
          title: string;
          /** The reference the backend gave the inquiry. */
          reference: string;
      }
    | {
          status: 'error';
          /** What went wrong, in the backend's words. */
          message?: string;
          /**
           * When a 429 lets the request go again (a Date.now() timestamp), or
           * null. Try again is held until then, so a retry cannot extend the wait.
           */
          retryAt?: number | null;
      };

/**
 * What happened to the request, in a dialog over the page.
 *
 * Success has already emptied the form behind it, so closing it leaves a fresh
 * inquiry. Failure leaves every answer on Review, which is what the wording
 * promises before it offers a retry.
 */
const InquiryResult = ({
    result,
    canChat,
    inquiriesHref,
    onTryAgain,
    onClose,
    onInquireAgain,
    submitting,
}: {
    result: InquiryResultState | null;
    /** The chat is only offered to someone allowed to open it. */
    canChat: boolean;
    /**
     * The list this reader keeps their inquiries on, or null if they may open
     * neither, in which case no link to a list is offered at all.
     */
    inquiriesHref: string | null;
    onTryAgain: () => void;
    onClose: () => void;
    onInquireAgain: () => void;
    /** True while a retry is in flight, which locks the dialog. */
    submitting: boolean;
}) => {
    const success =
        result?.status === 'success'
            ? {
                  status: 'success' as const,
                  title: 'Quote requested',
                  description: (
                      <>
                          We&apos;ve received your request for {result.title}.
                          <span className="mt-1 block">
                              Your reference is{' '}
                              <span className="font-bold text-foreground">
                                  {result.reference}
                              </span>
                              .
                          </span>
                      </>
                  ),
                  actions: (
                      <>
                          {/*
                           * Chat first, because a sent request is most useful as
                           * a conversation. A reader without the chat gets the
                           * list they can open, and a reader with neither gets no
                           * link rather than one that would refuse them.
                           */}
                          {canChat ? (
                              <Button asChild>
                                  <Link
                                      href={inboxIndex.url({
                                          query: { conversation: 'support' },
                                      })}
                                  >
                                      <MessageCircle aria-hidden />
                                      Message us
                                  </Link>
                              </Button>
                          ) : inquiriesHref ? (
                              <Button asChild>
                                  <Link href={inquiriesHref}>My inquiries</Link>
                              </Button>
                          ) : null}
                          <Button
                              type="button"
                              variant="outline"
                              onClick={onInquireAgain}
                          >
                              Start over
                          </Button>
                      </>
                  ),
              }
            : null;

    const failure =
        result?.status === 'error'
            ? {
                  status: 'error' as const,
                  title: "We couldn't send your request",
                  // The same sentence the server flashes when the save itself fails.
                  description:
                      result.message ??
                      'We could not save your inquiry. Your answers are still here, so you can try again.',
                  actions: (
                      <>
                          <Countdown
                              key={result.retryAt ?? 'now'}
                              until={result.retryAt ?? null}
                          >
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
                          </Countdown>
                          <Button
                              type="button"
                              variant="outline"
                              onClick={onClose}
                              disabled={submitting}
                          >
                              <ArrowLeft aria-hidden />
                              Back to review
                          </Button>
                      </>
                  ),
              }
            : null;

    return (
        <ResultDialog
            result={success ?? failure}
            onClose={onClose}
            locked={submitting}
        />
    );
};

export default InquiryResult;
