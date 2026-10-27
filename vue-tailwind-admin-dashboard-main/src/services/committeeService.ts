import api from './api'
import type { ApiListResponse, ApiSingleResponse, Committee } from '@/types/api'

export interface CommitteeFilterParams {
  period?: string
  organization_period_id?: number
  member_id?: number
  status?: string
  search?: string
  effective_active?: boolean
  organization_id?: number
}

export interface LinkableUser {
  id: number
  name: string
  email: string
  role: string
  organization_id: number
  status: string
  is_already_linked?: boolean
  is_linked?: boolean
}

export interface LinkableMember {
  id: number
  nama: string
  nim: string
  jurusan?: string
  angkatan?: string
  status: string
  user_id?: number | null
  user?: {
    id: number
    name: string
    email: string
  } | null
  is_already_assigned?: boolean
}

export const committeeService = {
  async list(params?: CommitteeFilterParams) {
    const { data } = await api.get<ApiListResponse<Committee>>('/committees', { params })
    return data.data
  },

  async getById(id: number) {
    const { data } = await api.get<ApiSingleResponse<Committee>>(`/committees/${id}`)
    return data.data
  },

  async getLinkableUsers(params?: { organization_id?: number; organization_period_id?: number; current_committee_id?: number }) {
    const { data } = await api.get<ApiListResponse<LinkableUser>>('/organization-users/linkable', { params })
    return data.data
  },

  async getLinkableMembers(params?: { organization_id?: number; organization_period_id?: number; current_committee_id?: number }) {
    const { data } = await api.get<ApiListResponse<LinkableMember>>('/committees/linkable-members', { params })
    return data.data
  },

  async create(formData: FormData | Partial<Committee>) {
    const headers = formData instanceof FormData ? { 'Content-Type': 'multipart/form-data' } : undefined
    const { data } = await api.post<ApiSingleResponse<Committee>>('/committees', formData, { headers })
    return data.data
  },

  async update(id: number, formData: FormData | Partial<Committee>) {
    const headers = formData instanceof FormData ? { 'Content-Type': 'multipart/form-data' } : undefined
    if (formData instanceof FormData) {
      const { data } = await api.post<ApiSingleResponse<Committee>>(`/committees/${id}`, formData, { headers })
      return data.data
    } else {
      const { data } = await api.put<ApiSingleResponse<Committee>>(`/committees/${id}`, formData)
      return data.data
    }
  },

  async delete(id: number) {
    const { data } = await api.delete<{ status: string; message: string }>(`/committees/${id}`)
    return data
  },
}
