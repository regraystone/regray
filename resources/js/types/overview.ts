/** The Overview page's props (OverviewService). */

export type FigureFormat =
    | 'count'
    | 'money'
    | 'percent'
    | 'signedPercent'
    | 'decimal'
    | 'days'
    | 'hours';

/** One number card. `previous` is null where the figure is as of now. */
export type OverviewFigure = {
    key: string;
    label: string;
    value: number | string | null;
    format: FigureFormat;
    previous: number | string | null;
    note: string | null;
    /** False for a figure as of today, which the dates leave alone. */
    dated: boolean;
};

/** One row of a list: an inquiry, a visit or a chat. */
export type OverviewRow = {
    title: string;
    detail: string;
    at: string | null;
    atLabel?: string;
    url: string;
    status?: string | null;
};

/** Something waiting on the reader: how many, and the oldest few. */
export type OverviewTask = {
    key: string;
    label: string;
    count: number;
    items: OverviewRow[];
    url: string;
};

export type TrendBucket = 'day' | 'week' | 'month';

export type OverviewTrend = {
    bucket: TrendBucket;
    series: { key: string; label: string }[];
    points: ({ start: string } & Record<string, number | string>)[];
};

export type StatusCount = {
    status: string;
    label: string;
    count: number;
    total?: string;
    url: string;
};

/** A deeper look within a section: the funnel, speed, by service… */
type InsightBase = { title: string; note: string };

export type OverviewInsight =
    | (InsightBase & {
          kind: 'funnel';
          steps: { key: string; label: string; count: number }[];
      })
    | (InsightBase & {
          kind: 'speed' | 'onTime' | 'quotes' | 'visits';
          figures: OverviewFigure[];
      })
    | (InsightBase & {
          kind: 'byService';
          rows: {
              service: string;
              won: number;
              value: string;
              lost: number;
              closing: number | null;
          }[];
      })
    | (InsightBase & {
          kind: 'sources';
          channels: { key: string; label: string; count: number }[];
          places: { label: string; count: number }[];
      })
    | (InsightBase & {
          kind: 'ageing';
          buckets: {
              bucket: string;
              label: string;
              count: number;
              total: string;
          }[];
      })
    | (InsightBase & {
          kind: 'popular';
          stones: PopularRow[];
          brands: PopularRow[];
      });

export type PopularRow = {
    label: string;
    detail: string | null;
    inquiries: number;
    won: number;
};

type SectionBase = {
    title: string;
    note: string;
    figures: OverviewFigure[];
    tasks: OverviewTask[];
    insights?: OverviewInsight[];
};

export type SalesSection = SectionBase & {
    kind: 'sales';
    pipeline: StatusCount[];
    trend: OverviewTrend;
    upcoming: OverviewRow[];
};

export type MarketingSection = SectionBase & {
    kind: 'marketing';
    trend: OverviewTrend;
    upcoming: OverviewRow[];
};

export type AccountingSection = SectionBase & {
    kind: 'accounting';
    paymentStatuses: StatusCount[];
    transactions: (OverviewRow & { amount: string; kind: string })[];
};

export type LogisticsSection = SectionBase & {
    kind: 'logistics';
    steps: StatusCount[];
    upcoming: OverviewRow[];
};

export type TechnicalSection = SectionBase & {
    kind: 'technical';
    inquiries: OverviewRow[];
    upcoming: OverviewRow[];
};

export type TeamSection = SectionBase & {
    kind: 'team';
    marketingTeam: {
        id: number;
        name: string;
        newLeads: number;
        won: number;
        lost: number;
        closing: number | null;
        sold: number;
        soldValue: string;
    }[];
    salesTeam: {
        id: number;
        name: string;
        won: number;
        value: string;
        lost: number;
        closing: number | null;
        open: number;
        openValue: string;
    }[];
    pipeline: StatusCount[];
    trend: OverviewTrend;
};

export type AdminSection = SectionBase & { kind: 'admin' };

export type OverviewSection =
    | SalesSection
    | MarketingSection
    | AccountingSection
    | LogisticsSection
    | TechnicalSection
    | TeamSection
    | AdminSection;

export type OverviewRangeProps = {
    from: string;
    to: string;
    days: number;
    bucket: TrendBucket;
    previousFrom: string;
    previousTo: string;
};

export type OverviewPageProps = {
    range: OverviewRangeProps;
    showsDates: boolean;
    subject: { id: number; name: string; roles: string[] };
    viewAs: { options: { id: number; name: string; role: string }[] } | null;
    viewingAs: number | null;
    sections: OverviewSection[];
};
