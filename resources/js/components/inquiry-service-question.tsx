import { useFormContext, useWatch } from 'react-hook-form';
import { fieldMessage } from '@/components/form-field';
import ServiceOptionSelect from '@/components/service-option-select';
import { useInquiryCatalog } from '@/hooks/use-inquiry-catalog';
import { serviceChoiceLabel } from '@/lib/inquiry-flow';
import type {
    InquiryDraftInput,
    InquiryDraftValues,
} from '@/lib/inquiry-schema';

const SERVICE_INFO_ID = 'inquiry-service-info';

/**
 * The first question: a service type, then one of its services, then what that
 * service includes, and, for a service the team installs, that installation
 * comes at an extra cost. The second dropdown and the panel wait for the choice before
 * them. The page owns the writes, because changing the type clears the service.
 */
const InquiryServiceQuestion = ({
    onServiceType,
    onService,
}: {
    onServiceType: (serviceTypeId: string) => void;
    onService: (serviceId: string) => void;
}) => {
    const { serviceTypes } = useInquiryCatalog();
    const {
        control,
        formState: { errors },
    } = useFormContext<InquiryDraftInput, unknown, InquiryDraftValues>();
    const serviceTypeId = useWatch({ control, name: 'serviceTypeId' });
    const serviceId = useWatch({ control, name: 'serviceId' });

    const serviceType = serviceTypes.find(({ id }) => id === serviceTypeId);
    const service = serviceType?.services.find(({ id }) => id === serviceId);
    const error = fieldMessage(errors.serviceId);

    return (
        <div className="grid items-start gap-6 md:grid-cols-2">
            <div className="space-y-6">
                <div>
                    <ServiceOptionSelect
                        id="inquiry-service-type"
                        label="Service type"
                        required
                        value={serviceTypeId}
                        onChange={onServiceType}
                        // With no type chosen, this is the only place to say so.
                        error={serviceType === undefined ? error : undefined}
                        options={serviceTypes}
                    />
                </div>

                {serviceType === undefined ? null : (
                    <div>
                        <ServiceOptionSelect
                            id="inquiry-service"
                            label={serviceChoiceLabel(serviceType.id)}
                            required
                            value={serviceId}
                            onChange={onService}
                            error={error}
                            options={serviceType.services}
                            describedBy={
                                service === undefined
                                    ? undefined
                                    : SERVICE_INFO_ID
                            }
                        />
                    </div>
                )}
            </div>

            {/* What the service picked includes, beside the dropdowns from tablets up. */}
            {service === undefined ? null : (
                <div
                    id={SERVICE_INFO_ID}
                    className="flex flex-col gap-3 border-l-2 border-foreground bg-muted px-6 py-5.5"
                    data-testid="service-info"
                >
                    <p className="text-xs font-semibold tracking-[0.12em] text-foreground uppercase">
                        {service.name}
                    </p>
                    <p className="text-[0.9375rem] leading-normal">
                        {service.description}
                    </p>
                    <ul className="space-y-2">
                        {service.features.map((feature) => (
                            <li
                                key={feature}
                                className="flex gap-2.5 text-sm leading-normal text-secondary-foreground before:content-['—']"
                            >
                                {feature}
                            </li>
                        ))}
                    </ul>
                    {/* Every service the team installs says so, from its own setting, so no wording has to be kept in step. */}
                    {service.fulfillment === 'installed' ? (
                        <p
                            className="mt-1 border-t border-border pt-3 text-sm leading-normal"
                            data-testid="installation-cost-note"
                        >
                            <span className="font-semibold">
                                Installation comes at an extra cost.
                            </span>{' '}
                            Our team will include it in your quotation.
                        </p>
                    ) : null}
                </div>
            )}
        </div>
    );
};

export default InquiryServiceQuestion;
