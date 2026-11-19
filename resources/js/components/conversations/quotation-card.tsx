import { Download, ExternalLink, FileText } from 'lucide-react';
import {
    CARD_COLUMN,
    CARD_DETAILS,
    CARD_MEDIA,
    CARD_SURFACE,
} from '@/components/conversations/card-layout';
import CardHeader from '@/components/conversations/card-header';
import { buttonVariants } from '@/components/ui/button';
import { formatFileSize } from '@/lib/message-attachments';
import { quotationSubline } from '@/lib/thread-cards';
import type { QuotationCard as QuotationCardData } from '@/types/message';

/**
 * A quotation Sales sent, as a card in a thread, built from the
 * `quotation_card` message. Every quotation and every revision posts its own,
 * right after the inquiry card that announced it ("Quotation sent",
 * "Quotation revised"), so an older one stays as it was.
 *
 * What it shows is the snapshot taken when it was posted: the file's name,
 * the inquiry's material count and name, and the file's size. The preview is
 * always the PDF icon, never a picture of the document.
 *
 * Two actions and only two, both plain links to the inquiry's file route:
 * View PDF in a new tab, and Download (`?download=1`, an attachment). The
 * route decides who may open the file; a reader who may not, such as a client
 * opening a revision a later one has replaced, gets its 404.
 */
const QuotationCard = ({ card }: { card: QuotationCardData }) => {
    const size = formatFileSize(card.sizeBytes);
    const action = buttonVariants({
        variant: 'outline',
        size: 'sm',
        className: 'min-w-0 px-2 @lg:px-3',
    });

    return (
        <div className={CARD_COLUMN} data-testid="quotation-card">
            <CardHeader
                headline="Quotation"
                occurredAt={card.occurredAt}
                label
            />

            <div className={CARD_SURFACE}>
                <span
                    aria-hidden
                    className={`${CARD_MEDIA} flex flex-col items-center justify-center gap-1 text-secondary-foreground`}
                    data-testid="quotation-card-preview"
                >
                    <FileText className="size-10" strokeWidth={1.5} />
                    <span className="text-[0.65rem] font-bold tracking-wider uppercase">
                        PDF
                    </span>
                </span>

                <div className={CARD_DETAILS}>
                    <p
                        className="truncate text-sm font-bold text-foreground"
                        title={card.fileName}
                        data-testid="quotation-card-filename"
                    >
                        {card.fileName}
                    </p>
                    <p
                        className="truncate text-xs text-secondary-foreground"
                        data-testid="quotation-card-subline"
                    >
                        {quotationSubline(card.materialCount, card.inquiryName)}
                    </p>
                    <p
                        className="truncate text-xs text-secondary-foreground"
                        data-testid="quotation-card-size"
                    >
                        {size}
                    </p>

                    <div
                        className="mt-auto grid grid-cols-1 gap-2 pt-1 @xs:grid-cols-2 @lg:flex @lg:flex-wrap"
                        data-testid="quotation-card-actions"
                    >
                        <a
                            href={card.viewUrl}
                            target="_blank"
                            rel="noopener"
                            aria-label={`View PDF ${card.fileName} (opens in new tab)`}
                            className={action}
                            data-testid="quotation-card-view"
                        >
                            <span className="truncate">View PDF</span>
                            <ExternalLink className="size-3.5" aria-hidden />
                        </a>
                        <a
                            href={card.downloadUrl}
                            download={card.fileName}
                            aria-label={`Download ${card.fileName}, ${size}`}
                            className={action}
                            data-testid="quotation-card-download"
                        >
                            <span className="truncate">Download</span>
                            <Download className="size-3.5" aria-hidden />
                        </a>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default QuotationCard;
