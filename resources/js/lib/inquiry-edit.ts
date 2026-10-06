import { serviceOptions } from '@/data/services';
import type { Inquiry } from '@/data/inquiries';
import type { InquiryFormInput } from '@/lib/inquiry-schema';

/**
 * What one stored service is called, matched back to the id the form works in.
 *
 * The stored inquiry keeps services as names, because the list shows them on
 * badges and never looks them up; the form is built in ids, because that is what
 * the pickers hand it. A name that is no longer in the catalogue resolves to
 * nothing rather than throwing, and the send's own whole-form validation is what
 * says a project has lost one of its services.
 */
const serviceIdFor = (name: string): string | null =>
    serviceOptions.find((service) => service.name === name)?.id ?? null;

/**
 * A stored draft, mapped into the stepper's form.
 *
 * Most keys carry across as they stand: name, stones, and the site and date are
 * the same answers in the form as in the record. Three need a translation.
 *
 * Services travel as names and are rebuilt into ids, in the order the client
 * picked them, so the reading back is the same list they chose from.
 *
 * The area is kept as a number by the record and as the string an `areaSqm`
 * field needs by the form, so it is widened rather than copied.
 *
 * Attachments cannot come across at all, and no attempt is made to fake it: a
 * stored file is a name, a size and a type, while the form's `InquiryAttachment`
 * needs the `File` itself to preview and to send, and a placeholder file that
 * never held bytes would be a lie the Review sheet repeats. Nothing is lost in
 * practice — there is no upload endpoint, so the bytes never reached the server
 * and every record's attachments are empty.
 */
export const inquiryToFormValues = (inquiry: Inquiry): InquiryFormInput => ({
    projects: inquiry.projects.map((project) => ({
        id: project.id,
        name: project.name,
        serviceIds: project.services
            .map(serviceIdFor)
            .filter((id): id is string => id !== null),
        stones: project.stones.map((selection) => ({ ...selection })),
        location: project.requirements.location,
        landmark: project.requirements.landmark ?? '',
        areaSqm:
            project.requirements.areaSqm === undefined
                ? ''
                : String(project.requirements.areaSqm),
        neededBy: project.requirements.neededBy ?? '',
        neededByTime: project.requirements.neededByTime ?? '',
        notes: project.requirements.notes ?? '',
        attachments: [],
    })),
});
