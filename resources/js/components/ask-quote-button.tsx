import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { useInquireRoute } from '@/hooks/use-inquire-route';

/**
 * Straight to the inquiry stepper from a product's profile (a stone or a
 * piece of furniture), for whoever may send one. Placed like Home's "Message
 * us", fixed to the corner and clear of the phone's bars; the layout lifts
 * the profile's save heart above it. The stepper takes no product to start
 * with, so this opens it empty.
 */
export default function AskQuoteButton() {
    const inquireRoute = useInquireRoute();

    if (!inquireRoute) {
        return null;
    }

    return (
        <Button
            asChild
            size="lg"
            className="fixed right-[max(1rem,env(safe-area-inset-right,0px))] bottom-[max(1rem,env(safe-area-inset-bottom,0px))] z-40 shadow-lg"
        >
            <Link href={inquireRoute.url()}>Ask a Quote</Link>
        </Button>
    );
}
