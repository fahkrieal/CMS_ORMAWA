import apiClient from '@/services/api'
import type {
  PublicOrganization,
  PublicArticle,
  PublicAgenda,
  PublicAnnouncement,
  PublicMedia,
  PublicDocument,
  PublicCommittee,
  PublicHomeSummary,
  PublicPaginationMeta,
  PlatformSettings,
} from '@/types/public'

export interface PublicPaginationResponse<T> {
  status: string
  data: T[]
  meta: PublicPaginationMeta
}

export const publicService = {
  // GET /api/public/home
  async getHome(): Promise<PublicHomeSummary> {
    const res = await apiClient.get('/public/home')
    return res.data.data
  },

  // GET /api/public/platform-settings
  async getPlatformSettings(): Promise<PlatformSettings> {
    const res = await apiClient.get('/public/platform-settings')
    return res.data.data
  },

  // GET /api/public/organizations
  async getOrganizations(params?: {
    jenis?: string
    search?: string
    page?: number
    per_page?: number
  }): Promise<PublicPaginationResponse<PublicOrganization>> {
    const res = await apiClient.get('/public/organizations', { params })
    return res.data
  },

  // GET /api/public/organizations/{slug}
  async getOrganizationBySlug(slug: string): Promise<PublicOrganization> {
    const res = await apiClient.get(`/public/organizations/${slug}`)
    return res.data.data
  },

  // GET /api/public/articles (Global News Hub)
  async getGlobalArticles(params?: {
    search?: string
    organization?: string
    category?: string
    tag?: string
    sort?: 'latest' | 'oldest'
    page?: number
    per_page?: number
  }): Promise<PublicPaginationResponse<PublicArticle>> {
    const res = await apiClient.get('/public/articles', { params })
    return res.data
  },

  // GET /api/public/categories (Global categories)
  async getGlobalCategories(): Promise<Array<{ id: number; name: string; slug: string }>> {
    const res = await apiClient.get('/public/categories')
    return res.data.data
  },

  // GET /api/public/agenda (Global Agenda Hub)
  async getGlobalAgenda(params?: {
    tab?: 'upcoming' | 'today' | 'past' | 'all'
    search?: string
    organization?: string
    page?: number
    per_page?: number
  }): Promise<PublicPaginationResponse<PublicAgenda>> {
    const res = await apiClient.get('/public/agenda', { params })
    return res.data
  },

  // GET /api/public/announcements (Global Announcement Hub)
  async getGlobalAnnouncements(params?: {
    priority?: 'urgent' | 'high' | 'normal' | 'low'
    search?: string
    organization?: string
    page?: number
    per_page?: number
  }): Promise<PublicPaginationResponse<PublicAnnouncement>> {
    const res = await apiClient.get('/public/announcements', { params })
    return res.data
  },

  // GET /api/public/organizations/{slug}/articles
  async getArticles(
    slug: string,
    params?: {
      search?: string
      category?: string
      tag?: string
      page?: number
      per_page?: number
    }
  ): Promise<PublicPaginationResponse<PublicArticle>> {
    const res = await apiClient.get(`/public/organizations/${slug}/articles`, { params })
    return res.data
  },

  // GET /api/public/organizations/{slug}/articles/{articleSlug}
  async getArticleBySlug(slug: string, articleSlug: string): Promise<PublicArticle> {
    const res = await apiClient.get(`/public/organizations/${slug}/articles/${articleSlug}`)
    return res.data.data
  },

  // GET /api/public/organizations/{slug}/agenda
  async getAgenda(
    slug: string,
    params?: {
      tab?: 'upcoming' | 'today' | 'past' | 'all'
      page?: number
      per_page?: number
    }
  ): Promise<PublicPaginationResponse<PublicAgenda>> {
    const res = await apiClient.get(`/public/organizations/${slug}/agenda`, { params })
    return res.data
  },

  // GET /api/public/organizations/{slug}/agenda/{id}
  async getAgendaDetail(slug: string, id: string | number): Promise<PublicAgenda> {
    const res = await apiClient.get(`/public/organizations/${slug}/agenda/${id}`)
    return res.data.data
  },

  // GET /api/public/organizations/{slug}/announcements
  async getAnnouncements(slug: string): Promise<PublicAnnouncement[]> {
    const res = await apiClient.get(`/public/organizations/${slug}/announcements`)
    return res.data.data
  },

  // GET /api/public/organizations/{slug}/gallery
  async getGallery(
    slug: string,
    params?: { page?: number; per_page?: number }
  ): Promise<PublicPaginationResponse<PublicMedia>> {
    const res = await apiClient.get(`/public/organizations/${slug}/gallery`, { params })
    return res.data
  },

  // GET /api/public/organizations/{slug}/documents
  async getDocuments(
    slug: string,
    params?: { page?: number; per_page?: number }
  ): Promise<PublicPaginationResponse<PublicDocument>> {
    const res = await apiClient.get(`/public/organizations/${slug}/documents`, { params })
    return res.data
  },

  // GET /api/public/organizations/{slug}/structure
  async getStructure(slug: string, params?: { period_id?: number }): Promise<{
    organization: { nama: string; subdomain: string; logo?: string; current_period?: string }
    selected_period?: { id: number; period_name: string; start_date?: string; end_date?: string; calculated_status?: string; is_ongoing?: boolean } | null
    available_periods?: Array<{ id: number; period_name: string; start_date?: string; end_date?: string; calculated_status?: string; is_ongoing?: boolean }>
    members: PublicCommittee[]
    by_department: Record<string, PublicCommittee[]>
  }> {
    const res = await apiClient.get(`/public/organizations/${slug}/structure`, { params })
    return res.data.data
  },
}
