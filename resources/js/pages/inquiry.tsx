import { Plus } from 'lucide-react';
import PageHeading from '@/components/page-heading';
import { Button } from '@/components/ui/button';
import { useInquiryEntry } from '@/hooks/use-inquiry-entry';

const Inquiry = () => {
    // The stepper is guarded by an action permission rather than a `view` one,
    // so it is not a page this sidebar item links to. The entry hook owns the
    // click, so a saved draft is offered the choice of continuing it before the
    // stepper is opened.
    const { inquire, onInquireClick, draftPrompt } = useInquiryEntry();

    return (
        <div className="mx-auto w-full max-w-6xl">
            <PageHeading
                title="Inquiry"
                description="Inquiries raised by your team, and the quotes that follow them"
                action={
                    inquire ? (
                        <Button type="button" onClick={onInquireClick}>
                            <Plus aria-hidden />
                            Inquire
                        </Button>
                    ) : null
                }
            />

            <div className="flex min-h-[40vh] items-center justify-center rounded-md border border-sidebar-border bg-background">
                <h3 className="text-3xl font-bold tracking-[-0.03em]">
                    Inquiry Page
                </h3>
            </div>

            {draftPrompt}
        </div>
    );
};

export default Inquiry;
