import { Link } from '@inertiajs/react';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

const heroImage = '/images/regray-hero.jpg';

export default function AuthSplitLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    return (
        <div className="light flex min-h-screen flex-col bg-background text-foreground lg:flex-row">
            <aside className="relative w-full shrink-0 bg-black/30 sm:h-48 md:h-56 lg:h-auto lg:w-[60%]">
                <div className="absolute inset-0 overflow-hidden">
                    <img
                        src={heroImage}
                        alt=""
                        aria-hidden="true"
                        className="size-full object-cover"
                    />
                    <div className="absolute inset-0 bg-black/30" />
                    <div className="absolute inset-0 flex flex-col justify-between p-4 sm:p-6 lg:p-10">
                        <Link
                            href={home()}
                            className="flex w-fit items-center gap-2 text-white"
                            aria-label="Regray Studio"
                        >
                            <span className="text-base font-extrabold sm:text-lg lg:text-xl">
                                Regray Studio
                            </span>
                        </Link>

                        <div className="hidden sm:block">
                            <p className="text-xs font-bold tracking-[0.16em] text-white/80 uppercase">
                                Bespoke Stone &amp; Furniture
                            </p>
                            <p className="mt-2 max-w-sm text-lg font-bold tracking-[-0.02em] text-white sm:text-xl lg:mt-3 lg:text-2xl">
                                One account. Every inquiry, from first sketch to
                                final installation.
                            </p>
                        </div>
                    </div>
                </div>
            </aside>

            <main className="flex min-w-0 flex-1 items-center justify-center px-4 py-8 sm:px-6 sm:py-12 lg:px-8 lg:py-12">
                <div className="w-full max-w-md">
                    <Link
                        href={home()}
                        className="flex items-center justify-center gap-2.5 text-center lg:hidden"
                        aria-label="Regray Studio"
                    >
                        <span className="text-xl font-extrabold md:text-2xl">
                            Regray Studio
                        </span>
                    </Link>

                    <div className="mt-6 sm:mt-8 lg:mt-0">
                        <h1 className="text-2xl font-bold tracking-[-0.04em] sm:text-3xl lg:text-[2.3rem]">
                            {title}
                        </h1>
                        {description ? (
                            <p className="mt-2 text-sm leading-relaxed text-secondary-foreground sm:mt-3 sm:text-base">
                                {description}
                            </p>
                        ) : null}
                        <div className="mt-6 sm:mt-8">{children}</div>
                    </div>
                </div>
            </main>
        </div>
    );
}
