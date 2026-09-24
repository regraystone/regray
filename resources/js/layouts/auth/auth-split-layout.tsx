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
        <div className="light flex min-h-screen bg-background text-foreground">
            <aside className="relative hidden w-[60%] shrink-0 lg:block">
                <div className="fixed inset-y-0 left-0 w-[60%] overflow-hidden">
                    <img
                        src={heroImage}
                        alt=""
                        aria-hidden="true"
                        className="size-full object-cover"
                    />
                    <div className="absolute inset-0 bg-black/30" />
                    <div className="absolute inset-0 flex flex-col justify-between p-10">
                        <Link
                            href={home()}
                            className="flex w-fit items-center gap-2 text-white"
                            aria-label="Regray One"
                        >
                            <img
                                src="/images/regray-logo.svg"
                                alt=""
                                aria-hidden="true"
                                className="size-7 shrink-0 object-contain invert"
                            />
                            <span className="text-lg font-extrabold">
                                Regray{' '}
                                <span className="font-normal text-white/70">
                                    One
                                </span>
                            </span>
                        </Link>

                        <div>
                            <p className="text-xs font-bold tracking-[0.16em] text-white/80 uppercase">
                                Bespoke Stone &amp; Furniture
                            </p>
                            <p className="mt-3 max-w-sm text-2xl leading-snug font-bold tracking-[-0.02em] text-white">
                                One account. Every inquiry, from first sketch to
                                final installation.
                            </p>
                        </div>
                    </div>
                </div>
            </aside>

            <main className="flex min-w-0 flex-1 items-center justify-center px-5 py-16 sm:px-10 lg:py-12">
                <div className="w-full max-w-md">
                    <Link
                        href={home()}
                        className="flex items-center justify-center gap-2 text-center lg:hidden"
                        aria-label="Regray One"
                    >
                        <img
                            src="/images/regray-logo.svg"
                            alt=""
                            aria-hidden="true"
                            className="size-7 shrink-0 object-contain"
                        />
                        <span className="text-lg font-extrabold">
                            Regray{' '}
                            <span className="font-normal text-secondary-foreground">
                                One
                            </span>
                        </span>
                    </Link>

                    <div className="mt-8 lg:mt-0">
                        <h1 className="text-[2.3rem] leading-tight font-bold tracking-[-0.04em]">
                            {title}
                        </h1>
                        {description ? (
                            <p className="mt-3 text-[0.9rem] leading-relaxed text-secondary-foreground">
                                {description}
                            </p>
                        ) : null}
                        <div className="mt-8">{children}</div>
                    </div>
                </div>
            </main>
        </div>
    );
}
