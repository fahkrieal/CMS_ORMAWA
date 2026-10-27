import api from './api'
import type { ApiListResponse, ApiSingleResponse, Member } from '@/types/api'

export interface MemberFilterParams {
  search?: string
  status?: string
  angkatan?: string
  jurusan?: string
  organization_id?: number
  page?: number
  per_page?: number
}

export interface MemberImportResult {
  status: string
  mode: 'preview' | 'commit'
  total_rows: number
  success_count: number
  error_count: number
  preview: Array<{
    row: number
    nim: string
    nama: string
    email?: string
    telepon?: string
    jurusan?: string
    angkatan?: string
    status: string
    catatan?: string
    action: string
  }>
  errors: Array<{
    row: number
    nim?: string
    nama?: string
    message: string
  }>
}

export const memberService = {
  async list(params?: MemberFilterParams) {
    const { data } = await api.get<{
      status: string
      data: Member[]
      meta?: {
        current_page: number
        last_page: number
        per_page: number
        total: number
      }
    }>('/members', { params })
    return data
  },

  async getById(id: number) {
    const { data } = await api.get<ApiSingleResponse<Member>>(`/members/${id}`)
    return data.data
  },

  async create(payload: Partial<Member>) {
    const { data } = await api.post<ApiSingleResponse<Member>>('/members', payload)
    return data.data
  },

  async update(id: number, payload: Partial<Member>) {
    const { data } = await api.put<ApiSingleResponse<Member>>(`/members/${id}`, payload)
    return data.data
  },

  async delete(id: number) {
    const { data } = await api.delete<{ status: string; message: string }>(`/members/${id}`)
    return data
  },

  async downloadTemplate() {
    const response = await api.get('/members/template', {
      responseType: 'blob'
    })
    const url = window.URL.createObjectURL(new Blob([response.data]))
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', 'template_anggota_ormawa.csv')
    document.body.appendChild(link)
    link.click()
    link.remove()
  },

  async export(params?: MemberFilterParams) {
    const response = await api.get('/members/export', {
      params,
      responseType: 'blob'
    })
    const url = window.URL.createObjectURL(new Blob([response.data]))
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', `data_anggota_ormawa_${new Date().toISOString().slice(0, 10)}.csv`)
    document.body.appendChild(link)
    link.click()
    link.remove()
  },

  async import(file: File, mode: 'preview' | 'commit' = 'preview') {
    const formData = new FormData()
    formData.append('file', file)
    formData.append('mode', mode)

    const { data } = await api.post<MemberImportResult>('/members/import', formData, {
      headers: {
        'Content-Type': 'multipart/form-data'
      }
    })
    return data
  }
}
