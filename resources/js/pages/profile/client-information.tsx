import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { useRef, type FormEvent } from 'react';
import ClientInformationController from '@/actions/App/Http/Controllers/Settings/ClientInformationController';
import FormField from '@/components/form-field';
import PageHeading from '@/components/page-heading';
import { Button } from '@/components/ui/button';
import { Combobox } from '@/components/ui/combobox';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { searchLocations } from '@/lib/inquiry-catalog';
import { DELIVERY_METHODS, PICKUP } from '@/lib/inquiry-flow';
import type {
    ClientInformationFormValues,
    ClientInformationValues,
} from '@/lib/client-information';
import { show as showProfile } from '@/routes/profile';

type PageProps = {
    information: ClientInformationValues;
    locations: string[];
};

/**
 * The client's own answer to "How would you like to get it?" and its site.
 *
 * There is no id in the URL, the form or the page props: the controller reads
 * the signed-in user's own row. A pickup has no site, so changing to it clears
 * one that was chosen for a delivery; the server applies the same rule.
 */
export default function ClientInformation({
    information,
    locations,
}: PageProps) {
    const formRef = useRef<HTMLFormElement>(null);
    const form = useForm<ClientInformationFormValues>({
        deliveryMethod: information.deliveryMethod,
        location: information.location ?? '',
    });

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();

        form.put(ClientInformationController.update.url(), {
            preserveScroll: true,
            onError: () => {
                /*
                 * FormField carries `aria-invalid` on every control, so the
                 * first one the server complained about gets the focus.
                 */
                window.requestAnimationFrame(() => {
                    formRef.current
                        ?.querySelector<HTMLElement>('[aria-invalid="true"]')
                        ?.focus();
                });
            },
        });
    };

    return (
        <>
            <Head title="Client Information" />

            <h1 className="sr-only">Client Information</h1>

            <div className="mx-auto w-full max-w-2xl">
                {/* Mobile back link; on larger screens the breadcrumb does this. */}
                <Link
                    href={showProfile.url()}
                    className="mb-5 inline-flex items-center gap-2 text-sm text-secondary-foreground transition-opacity hover:opacity-70 sm:hidden"
                >
                    <ArrowLeft className="size-4" aria-hidden />
                    Back to profile
                </Link>

                <div className="space-y-6">
                    <PageHeading
                        title="Client Information"
                        description="Choose how you would like to get it"
                    />

                    <Card className="border-border py-4 shadow-none sm:py-6">
                        <CardHeader className="px-4 sm:px-6">
                            <CardTitle>Your details</CardTitle>
                            <CardDescription>
                                These answers start a new request so you do not
                                have to give them again.
                            </CardDescription>
                        </CardHeader>

                        <CardContent className="px-4 sm:px-6">
                            <form
                                ref={formRef}
                                onSubmit={submit}
                                className="space-y-5"
                            >
                                <FormField
                                    id="deliveryMethod"
                                    label="How would you like to get it?"
                                    required
                                    error={form.errors.deliveryMethod}
                                >
                                    {(accessibility) => (
                                        <Select
                                            value={form.data.deliveryMethod}
                                            onValueChange={(value) => {
                                                form.setData(
                                                    'deliveryMethod',
                                                    value,
                                                );

                                                if (value === PICKUP) {
                                                    form.setData(
                                                        'location',
                                                        '',
                                                    );
                                                }
                                            }}
                                        >
                                            <SelectTrigger
                                                id="deliveryMethod"
                                                name="deliveryMethod"
                                                aria-invalid={
                                                    accessibility.invalid
                                                }
                                                aria-describedby={
                                                    accessibility.describedBy
                                                }
                                                className="w-full rounded-none text-base data-[size=default]:h-11"
                                            >
                                                <SelectValue placeholder="Choose one" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {DELIVERY_METHODS.map(
                                                    ({ value, title }) => (
                                                        <SelectItem
                                                            key={value}
                                                            value={value}
                                                            className="rounded-none"
                                                        >
                                                            {title}
                                                        </SelectItem>
                                                    ),
                                                )}
                                            </SelectContent>
                                        </Select>
                                    )}
                                </FormField>

                                {form.data.deliveryMethod === PICKUP ? null : (
                                    <FormField
                                        id="location"
                                        label="Location"
                                        required
                                        error={form.errors.location}
                                        hint="Used as the site on a new request."
                                    >
                                        {(accessibility) => (
                                            <Combobox
                                                id="location"
                                                value={form.data.location}
                                                options={locations}
                                                filter={(query) =>
                                                    searchLocations(
                                                        locations,
                                                        query,
                                                    )
                                                }
                                                listLabel="Cities and municipalities"
                                                placeholder="Search a city or municipality"
                                                emptyMessage="No city or municipality matches that."
                                                aria-describedby={
                                                    accessibility.describedBy
                                                }
                                                aria-invalid={
                                                    accessibility.invalid
                                                }
                                                className="rounded-none"
                                                onChange={(value) =>
                                                    form.setData(
                                                        'location',
                                                        value,
                                                    )
                                                }
                                            />
                                        )}
                                    </FormField>
                                )}

                                <div>
                                    <Button
                                        type="submit"
                                        disabled={form.processing}
                                        className="w-full sm:w-auto"
                                        data-test="client-information-save"
                                    >
                                        {form.processing
                                            ? 'Saving...'
                                            : 'Save information'}
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}
