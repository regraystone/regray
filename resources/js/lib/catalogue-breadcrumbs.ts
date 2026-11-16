export type Crumb = { label: string; href?: string };

type NamedRecord = { data?: { slug: string; name: string } };
type BrandedRecord = {
    data?: { name: string; brand: { slug: string; name: string } };
};

/**
 * The workspace header's trail on a brand, stone or furniture page:
 * Regray / Dashboard / Home / Brand / Product. Null on any other page, or when
 * the payload has nothing to name. Guests see no breadcrumbs.
 */
export const catalogueCrumbs = ({
    component,
    props,
    homeHref,
    brandHref,
}: {
    component: string;
    props: Record<string, unknown>;
    homeHref: string;
    brandHref: (slug: string) => string;
}): Crumb[] | null => {
    const trail = (...rest: Crumb[]): Crumb[] => [
        { label: 'Regray' },
        { label: 'Dashboard' },
        { label: 'Home', href: homeHref },
        ...rest,
    ];

    if (component === 'brand-profile') {
        const brand = (props.brand as NamedRecord | undefined)?.data;

        return brand
            ? trail({ label: brand.name, href: brandHref(brand.slug) })
            : null;
    }

    const product =
        component === 'stone-profile'
            ? (props.stone as BrandedRecord | undefined)?.data
            : component === 'furniture-profile'
              ? (props.furniture as BrandedRecord | undefined)?.data
              : undefined;

    return product
        ? trail(
              {
                  label: product.brand.name,
                  href: brandHref(product.brand.slug),
              },
              { label: product.name },
          )
        : null;
};
