import { useInquiryCatalog } from '@/hooks/use-inquiry-catalog';

/**
 * What a project's services cover, as bullets, on the two sheets that list it.
 *
 * The wording the reader was shown when they picked, read from the catalogue
 * rather than written again here, so a project cannot end up describing its own
 * service in different words from the one it was chosen by. The marker and the
 * leading are the picker's too, which is what makes a bullet here visibly the
 * same line they ticked rather than a fresh one invented for this sheet.
 *
 * A project of nothing but a service that stands on its own has no stones to
 * draw, so this is the whole of what sits under its title. The requirements form
 * and the Review sheet are built to read the same, so both take it from one place
 * rather than each growing its own version of the only content there is.
 */
const InquiryServiceBullets = ({
    serviceIds,
}: {
    /** Every service the project is for, in the order they were picked. */
    serviceIds: string[];
}) => {
    const { services: catalogServices } = useInquiryCatalog();

    /*
     * Skipped rather than shown as an id: a service dropped from the catalogue
     * would have nothing to say, and the draft's own schema refuses one before
     * it can be committed, so there is no bullet to invent.
     */
    const services = serviceIds.flatMap((id) => {
        const service = catalogServices.find((entry) => entry.id === id);

        return service === undefined ? [] : [service];
    });

    /*
     * The name over the bullets, but only when there is more than one of them. A
     * project of a single service is already titled with that service, and a
     * heading repeating the line directly above it adds nothing.
     */
    const headed = services.length > 1;

    return (
        <>
            {services.map((service) => (
                <div key={service.id} className="mt-6">
                    {headed ? (
                        <p className="text-sm font-bold text-foreground">
                            {service.name}
                        </p>
                    ) : null}

                    <ul className={headed ? 'mt-2 space-y-1.5' : 'space-y-1.5'}>
                        {service.features.map((feature) => (
                            <li
                                key={feature}
                                className="flex items-start gap-2.5 text-sm leading-relaxed text-secondary-foreground"
                            >
                                <span
                                    className="mt-1.5 size-1.5 shrink-0 rounded-full bg-primary"
                                    aria-hidden
                                />
                                {feature}
                            </li>
                        ))}
                    </ul>
                </div>
            ))}
        </>
    );
};

export default InquiryServiceBullets;
