export interface SiteIdentity {
    name: string;
    subtitle: string;
    logoMediaId: number | null;
    footerTagline: string;
}

export interface CategorySummary {
    id: number;
    name: string;
    slug: string;
    color: string;
    icon: string | null;
    description: string | null;
}

export interface SocialLinkSummary {
    platform: string;
    username: string | null;
    url: string;
    icon: string | null;
    open_new_tab: boolean;
}

export interface PostSummary {
    id: number;
    title: string;
    slug: string;
    excerpt: string | null;
    publishedAt: string | null;
    category: { name: string; slug: string; color: string } | null;
    coverUrl: string | null;
}

export interface PublicPageProps {
    site: SiteIdentity;
    categories: CategorySummary[];
    social: SocialLinkSummary[];
}
