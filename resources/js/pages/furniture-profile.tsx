import { Head, Link, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { Armchair, ArrowLeft, ArrowRight, Heart } from 'lucide-react';
import ProductPhotos, { productPhotos } from '@/components/product-photos';
import { useInquireRoute } from '@/hooks/use-inquire-route';
import { useSavedFurniture } from '@/hooks/use-saved-stones';
import { login } from '@/routes';
import { show as brandsShow } from '@/routes/brands';
import { show as brandFurnitureShow } from '@/routes/brands/furniture';
import { showroom } from '@/routes/company';
import { show as dashboardBrandShow } from '@/routes/dashboard/brands';
import { show as dashboardFurnitureShow } from '@/routes/dashboard/brands/furniture';
import { book } from '@/routes/profile/schedule';
import type {
    FurnitureCardData,
    FurnitureCustomization,
    FurnitureProfile as FurnitureProfileData,
    FurnitureStep,
} from '@/types/home';

type FurnitureProfileProps = {
    furniture: { data: FurnitureProfileData };
    customization: FurnitureCustomization;
    previous: FurnitureStep;
    next: FurnitureStep;
    related: FurnitureCardData[];
};

/** How many choices a row shows before "+N more". */
const SHOWN_CHOICES = 2;

/**
 * A row of what the piece can be made in or with: its first two choices,
 * and a "+N more" that shows the rest (and "Show less" to fold them again).
 */
const OptionRow = ({
    label,
    values,
    action,
}: {
    label: string;
    values: string[];
    action?: React.ReactNode;
}) => {
    const [expanded, setExpanded] = useState(false);
    // Folding away a single choice saves nothing, so a row folds only from two.
    const hidden =
        values.length - SHOWN_CHOICES >= 2 ? values.length - SHOWN_CHOICES : 0;
    const shown =
        expanded || hidden <= 0 ? values : values.slice(0, SHOWN_CHOICES);

    return (
        <div className="flex flex-col gap-3 border-t border-border pt-5">
            <dt className="text-xs font-semibold tracking-[0.12em] uppercase">
                {label}
            </dt>
            <dd className="flex flex-col gap-3">
                <ul className="flex flex-wrap items-center gap-2">
                    {shown.map((value) => (
                        <li
                            key={value}
                            className="border border-border px-3 py-1.5 text-sm text-secondary-foreground"
                        >
                            {value}
                        </li>
                    ))}
                    {hidden > 0 ? (
                        <li>
                            <button
                                type="button"
                                onClick={() => setExpanded((open) => !open)}
                                aria-expanded={expanded}
                                aria-label={
                                    expanded
                                        ? `Show fewer ${label.toLowerCase()} choices`
                                        : `Show ${hidden} more ${label.toLowerCase()} choices`
                                }
                                className="cursor-pointer px-1 py-1.5 text-sm text-foreground underline underline-offset-4 transition-opacity hover:opacity-70"
                            >
                                {expanded ? 'Show less' : `+${hidden} more`}
                            </button>
                        </li>
                    ) : null}
                </ul>
                {action}
            </dd>
        </div>
    );
};

/**
 * A piece of furniture: its photo (click to see it large, and its other
 * photos below to pick from) beside its type, brand, name and
 * description, then what a client may choose for it in an inquiry (the
 * stones, base, edge, finish and seats its Inquire service offers), with Ask
 * a quote and Save together. Below, more pieces of the same type. A
 * breadcrumb leads back to the brand, and the brand's previous and next
 * pieces are a click away.
 *
 * The same page serves guests (the public frame) and signed-in clients (the
 * workspace): links follow whichever the reader is. Saving needs an account,
 * so a guest's Save and Ask a quote go through Login first.
 */
const FurnitureProfile = ({
    furniture: { data: furniture },
    customization,
    previous,
    next,
    related,
}: FurnitureProfileProps) => {
    const { auth } = usePage<{ auth: { user: unknown } }>().props;
    const isSignedIn = auth.user != null;
    const inquireRoute = useInquireRoute();
    const { savedIds, toggleSave, canSave } = useSavedFurniture();
    const isSaved = savedIds.has(furniture.slug);
    const brand = furniture.brand;

    const brandUrl = (collection?: 'stones' | 'furniture') =>
        (isSignedIn ? dashboardBrandShow : brandsShow).url(
            { brand: brand.slug },
            collection ? { query: { collection } } : undefined,
        );
    const pieceUrl = (slug: string) =>
        (isSignedIn ? dashboardFurnitureShow : brandFurnitureShow).url({
            brand: brand.slug,
            furniture: slug,
        });
    const quoteUrl = isSignedIn ? (inquireRoute?.url() ?? null) : login.url();
    const visitUrl = isSignedIn ? book.url() : `${showroom.url()}#book`;
    const typeName = furniture.furnitureType.name;

    const actions = (compact: boolean) => (
        <div
            className={`grid gap-2 ${canSave || !isSignedIn ? 'grid-cols-[1fr_auto]' : 'grid-cols-1'}`}
        >
            {quoteUrl ? (
                <Link
                    href={quoteUrl}
                    className={`flex items-center justify-center bg-foreground text-[0.8125rem] font-semibold tracking-[0.1em] text-background uppercase transition-colors hover:bg-foreground/85 ${compact ? 'h-13' : 'h-14'}`}
                >
                    Ask a quote
                </Link>
            ) : null}
            {canSave ? (
                <button
                    type="button"
                    onClick={() => toggleSave(furniture.slug)}
                    aria-pressed={isSaved}
                    className={`flex cursor-pointer items-center gap-2 border border-foreground px-5 text-[0.8125rem] font-semibold tracking-[0.1em] uppercase transition-colors ${compact ? 'h-13' : 'h-14'} ${isSaved ? 'bg-foreground text-background' : 'hover:bg-muted'}`}
                >
                    <Heart
                        aria-hidden
                        className={`size-4 ${isSaved ? 'fill-current' : ''}`}
                    />
                    {isSaved ? 'Saved' : 'Save'}
                </button>
            ) : !isSignedIn ? (
                <Link
                    href={login.url()}
                    className={`flex items-center gap-2 border border-foreground px-5 text-[0.8125rem] font-semibold tracking-[0.1em] uppercase transition-colors hover:bg-muted ${compact ? 'h-13' : 'h-14'}`}
                >
                    <Heart aria-hidden className="size-4" />
                    Save
                </Link>
            ) : null}
        </div>
    );

    return (
        <div className="w-full pb-24 lg:pb-0">
            <Head title={furniture.name} />

            <div className="flex flex-wrap items-center justify-between gap-x-6 gap-y-3 pb-5 text-[0.8125rem]">
                <nav aria-label="Breadcrumb">
                    <ol className="flex flex-wrap items-center gap-2 text-secondary-foreground">
                        <li>
                            <Link
                                href={brandUrl()}
                                className="transition-colors hover:text-foreground"
                            >
                                {brand.name}
                            </Link>
                        </li>
                        <li aria-hidden>/</li>
                        <li>
                            <Link
                                href={brandUrl('furniture')}
                                className="transition-colors hover:text-foreground"
                            >
                                {typeName}
                            </Link>
                        </li>
                        <li aria-hidden>/</li>
                        <li aria-current="page" className="text-foreground">
                            {furniture.name}
                        </li>
                    </ol>
                </nav>
                {previous || next ? (
                    <div className="flex gap-5 text-xs tracking-[0.06em] text-secondary-foreground uppercase">
                        {previous ? (
                            <Link
                                href={pieceUrl(previous.slug)}
                                className="inline-flex items-center gap-1.5 transition-colors hover:text-foreground"
                            >
                                <ArrowLeft aria-hidden className="size-3.5" />
                                {previous.name}
                            </Link>
                        ) : null}
                        {next ? (
                            <Link
                                href={pieceUrl(next.slug)}
                                className="inline-flex items-center gap-1.5 transition-colors hover:text-foreground"
                            >
                                {next.name}
                                <ArrowRight aria-hidden className="size-3.5" />
                            </Link>
                        ) : null}
                    </div>
                ) : null}
            </div>

            <section
                aria-label={furniture.name}
                className="grid grid-cols-1 items-start gap-[clamp(1.5rem,4vw,4.5rem)] pb-[clamp(3.5rem,7vw,6rem)] md:grid-cols-[minmax(0,1fr)_minmax(0,1.15fr)] lg:grid-cols-[minmax(0,1fr)_minmax(22rem,1.2fr)]"
            >
                <ProductPhotos
                    key={furniture.slug}
                    name={furniture.name}
                    alt={`${furniture.name}, a ${typeName.toLowerCase()} piece by ${brand.name}`}
                    photos={productPhotos(
                        furniture.thumbnail,
                        furniture.gallery,
                        furniture.name,
                    )}
                    frameClassName="aspect-square max-w-md lg:max-w-[32rem]"
                    placeholder={
                        <Armchair
                            aria-hidden
                            className="size-16 text-secondary-foreground/50"
                        />
                    }
                />

                <div className="flex flex-col gap-7">
                    <div className="flex flex-col gap-3">
                        <p className="text-xs tracking-[0.14em] text-secondary-foreground uppercase">
                            {typeName} · {brand.name}
                        </p>
                        <h1 className="text-[clamp(2rem,3vw,2.875rem)] leading-[1.05] font-light tracking-[0.02em] uppercase">
                            {furniture.name}
                        </h1>
                        {furniture.description ? (
                            <p className="text-base leading-relaxed text-pretty text-secondary-foreground">
                                {furniture.description}
                            </p>
                        ) : null}
                    </div>

                    {customization.stones.length > 0 ||
                    customization.options.length > 0 ? (
                        <section
                            aria-labelledby="furniture-options"
                            className="flex flex-col gap-4"
                        >
                            <div className="flex flex-col gap-1">
                                <h2
                                    id="furniture-options"
                                    className="font-sans text-xs font-bold tracking-[0.16em] uppercase"
                                >
                                    Made to your choice
                                </h2>
                                <p className="text-sm text-secondary-foreground">
                                    Choose these when you send your inquiry; our
                                    team confirms them in your quotation.
                                </p>
                            </div>
                            <dl className="flex flex-col gap-5">
                                {customization.stones.length > 0 ? (
                                    <OptionRow
                                        label="Stone"
                                        values={customization.stones}
                                        action={
                                            <Link
                                                href={brandUrl('stones')}
                                                className="self-start border-b border-current text-[0.8125rem] text-secondary-foreground transition-colors hover:text-foreground"
                                            >
                                                Browse the stones
                                            </Link>
                                        }
                                    />
                                ) : null}
                                {customization.options.map((option) => (
                                    <OptionRow
                                        key={option.label}
                                        label={option.label}
                                        values={option.values}
                                    />
                                ))}
                            </dl>
                        </section>
                    ) : null}

                    <div className="flex flex-col gap-3 border-t border-border pt-5">
                        <div className="hidden lg:block">{actions(false)}</div>
                        <p className="text-[0.8125rem] leading-relaxed text-secondary-foreground">
                            Made to order. Prefer to see the slabs first?{' '}
                            <Link
                                href={visitUrl}
                                className="border-b border-current text-foreground"
                            >
                                Book a showroom visit
                            </Link>
                        </p>
                    </div>
                </div>
            </section>

            {related.length > 0 ? (
                <section
                    aria-labelledby="furniture-related"
                    className="border-t border-border pt-10 pb-[clamp(3rem,6vw,5rem)]"
                >
                    <div className="mb-7 flex items-baseline justify-between gap-4">
                        <h2
                            id="furniture-related"
                            className="text-[clamp(1.375rem,1.8vw,1.75rem)] font-light tracking-[0.02em] uppercase"
                        >
                            More {typeName.toLowerCase()}
                        </h2>
                        <Link
                            href={brandUrl('furniture')}
                            className="border-b border-current pb-1 text-[0.8125rem] tracking-[0.04em] whitespace-nowrap uppercase transition-opacity hover:opacity-70"
                        >
                            View all
                        </Link>
                    </div>
                    <ul className="grid grid-cols-2 gap-x-3.5 gap-y-8 md:gap-x-5 lg:grid-cols-4">
                        {related.map((piece) => (
                            <li key={piece.slug}>
                                <Link
                                    href={pieceUrl(piece.slug)}
                                    className="group flex flex-col gap-3"
                                >
                                    <div className="flex aspect-square items-center justify-center overflow-hidden bg-muted/50">
                                        {piece.thumbnail ? (
                                            <img
                                                src={piece.thumbnail}
                                                alt=""
                                                loading="lazy"
                                                className="size-full object-cover transition-transform duration-500 group-hover:scale-[1.03] motion-reduce:transition-none"
                                            />
                                        ) : (
                                            <Armchair
                                                aria-hidden
                                                className="size-10 text-secondary-foreground/50"
                                            />
                                        )}
                                    </div>
                                    <div className="flex items-baseline justify-between gap-3">
                                        <span className="font-display text-[0.9375rem] tracking-[0.04em] uppercase">
                                            {piece.name}
                                        </span>
                                        <span className="truncate text-xs text-secondary-foreground">
                                            {piece.furnitureType.name}
                                        </span>
                                    </div>
                                </Link>
                            </li>
                        ))}
                    </ul>
                </section>
            ) : null}

            {/* On phones and tablets, the two actions stay in reach. */}
            <div className="fixed inset-x-0 bottom-0 z-40 border-t border-border bg-background px-4 pt-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] lg:hidden">
                {actions(true)}
            </div>
        </div>
    );
};

export default FurnitureProfile;
