import { Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import PageHeading from '@/components/page-heading';
import { Button } from '@/components/ui/button';
import { useInquireRoute } from '@/hooks/use-inquire-route';

const Inquiry = () => {
    // The stepper is guarded by an action permission rather than a `view` one,
    // so it is not a page this sidebar item links to.
    const inquire = useInquireRoute();

    return (
        <div className="mx-auto w-full max-w-6xl">
            <PageHeading
                title="Inquiry"
                description="Inquiries raised by your team, and the quotes that follow them"
                action={
                    inquire ? (
                        <Button asChild>
                            <Link href={inquire.url()}>
                                <Plus aria-hidden />
                                Inquire
                            </Link>
                        </Button>
                    ) : null
                }
            />

            <div className="flex min-h-[40vh] items-center justify-center border border-sidebar-border bg-background">
                <h3 className="text-3xl font-bold tracking-[-0.03em]">
                    Inquiry Page
                </h3>
            </div>
        </div>
    );
};

export default Inquiry;
