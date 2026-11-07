import { useFormContext, useWatch } from 'react-hook-form';
import { fieldMessage } from '@/components/form-field';
import { InquiryActionBar } from '@/components/inquiry-question';
import ServiceOptionSelect from '@/components/service-option-select';
import { Button } from '@/components/ui/button';
import { useInquiryCatalog } from '@/hooks/use-inquiry-catalog';
import { serviceChoiceLabel } from '@/lib/inquiry-flow';
import type {
    InquiryDraftInput,
    InquiryDraftValues,
} from '@/lib/inquiry-schema';

const SERVICE_INFO_ID = 'inquiry-service-info';

/**
 * The first question: a service type, then one of its services, then what that
 * service includes. The second dropdown and the panel wait for the choice before
 * them. The page owns the writes, because changing the type clears the service.
 */
const InquiryServiceQuestion = ({
    onServiceType,
    onService,
    onNext,
}: {
    onServiceType: (serviceTypeId: string) => void;
    onService: (serviceId: string) => void;
    onNext: () => void;
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
        <div className="space-y-6">
            <div>
                <ServiceOptionSelect
                    id="inquiry-service-type"
                    label="Service type"
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
                        value={serviceId}
                        onChange={onService}
                        error={error}
                        options={serviceType.services}
                        describedBy={
                            service === undefined ? undefined : SERVICE_INFO_ID
                        }
                    />
                </div>
            )}

            {service === undefined ? null : (
                <div
                    id={SERVICE_INFO_ID}
                    className="rounded-md border border-sidebar-border bg-secondary p-4 sm:max-w-md"
                    data-testid="service-info"
                >
                    <p className="text-sm">{service.description}</p>
                    <ul className="mt-3 list-disc space-y-1 pl-5 text-sm text-secondary-foreground">
                        {service.features.map((feature) => (
                            <li key={feature}>{feature}</li>
                        ))}
                    </ul>
                </div>
            )}

            <InquiryActionBar>
                <span className="min-w-0 flex-1" />
                {/*
                 * Left enabled, like the other questions: a press that is refused
                 * says what is missing, which a greyed-out button cannot.
                 */}
                <Button
                    type="button"
                    onClick={onNext}
                    className="w-full sm:w-auto"
                >
                    Next
                </Button>
            </InquiryActionBar>
        </div>
    );
};

export default InquiryServiceQuestion;
