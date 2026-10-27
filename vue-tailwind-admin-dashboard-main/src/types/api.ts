export interface Organization {
  id: number
  nama: string
  jenis: 'HMPS' | 'UKM' | 'BEM' | string
  subdomain: string
  logo?: string | null
  hero_image?: string | null
  warna_tema?: string
  email?: string | null
  telepon?: string | null
  alamat?: string | null
  media_sosial?: Record<string, any> | null
  label_menu?: Record<string, string> | null
  ga_tracking_id?: string | null
  modul_aktif?: Record<string, any>
  status: 'active' | 'inactive' | string
  current_period?: OrganizationPeriod
  users_count?: number
  committees_count?: number
  posts_count?: number
  published_posts_count?: number
  activities_count?: number
  announcements_count?: number
  media_count?: number
  editors_count?: number
  contributors_count?: number
  admin_user?: {
    id: number
    name: string
    email: string
  } | null
  created_at?: string
  updated_at?: string
}

export interface OrganizationPeriod {
  id: number
  organization_id: number
  period_name: string
  start_date: string
  end_date: string
  status: 'pending' | 'active' | 'expired' | 'rejected' | string
  calculated_status?: 'ongoing' | 'expired' | 'upcoming' | string
  is_ongoing?: boolean
  notes?: string
  rejection_reason?: string
  approved_by?: number
  approved_at?: string
  created_at?: string
  updated_at?: string
  organization?: Organization
  reviewer?: {
    id: number
    name: string
    email?: string
  }
}

export interface UserProfile {
  id: number
  name: string
  email: string
  avatar?: string | null
  role: string
  permissions?: string[]
  two_factor_enabled?: boolean
  status?: string
  organization_id?: number | null
  organization?: Organization | null
  created_at?: string
  updated_at?: string
}

export interface AuthResponse {
  message: string
  access_token?: string
  token_type?: string
  user?: UserProfile
  status?: string
  mfa_required?: boolean
  mfa_token?: string
}

export interface MfaSetupResponse {
  status: string
  secret: string
  otpauth_url: string
  recovery_codes: string[]
  current_otp?: string
}

export interface ApiListResponse<T> {
  status: string
  data: T[]
  message?: string
}

export interface ApiSingleResponse<T> {
  status: string
  data: T
  message?: string
}

export interface Category {
  id: number
  organization_id?: number | null
  name: string
  slug: string
  created_at?: string
  updated_at?: string
}

export interface Tag {
  id: number
  name: string
  slug: string
  created_at?: string
  updated_at?: string
}

export interface Post {
  id: number
  judul: string
  slug?: string
  konten: string
  excerpt?: string | null
  cover_image?: string | null
  meta_title?: string | null
  meta_description?: string | null
  status: string
  published_at?: string | null
  category_id?: number | null
  tags?: any
  category?: Category | null
  user_id?: number
  organization_id?: number
  created_at?: string
  updated_at?: string
  user?: { id: number; name: string }
  organization?: { id: number; nama: string }
}

export interface Activity {
  id: number
  judul: string
  nama_kegiatan?: string
  deskripsi: string
  tanggal_pelaksanaan: string
  start_date?: string
  tempat?: string
  lokasi?: string
  status: string
  user_id?: number
  organization_id?: number
  created_at?: string
  updated_at?: string
  user?: { id: number; name: string }
  organization?: { id: number; nama: string }
}

export interface Announcement {
  id: number
  judul: string
  konten?: string
  prioritas?: string
  status: string
  user_id?: number
  organization_id?: number
  created_at?: string
  updated_at?: string
  user?: { id: number; name: string }
  organization?: { id: number; nama: string }
}

export interface Media {
  id: number
  filename: string
  path?: string
  url?: string
  mime_type?: string
  size?: number
  organization_id?: number | null
  created_at?: string
  updated_at?: string
  organization?: { id: number; nama: string } | null
}

export interface Member {
  id: number
  organization_id: number
  nama: string
  nim: string
  email?: string | null
  telepon?: string | null
  jurusan?: string | null
  angkatan?: string | number | null
  status: 'active' | 'inactive' | 'alumni' | string
  user_id?: number | null
  catatan?: string | null
  created_at?: string
  updated_at?: string
  user?: {
    id: number
    name: string
    email?: string
  } | null
  organization?: Organization | null
  committees?: Committee[]
  active_committees_count?: number
}

export interface Committee {
  id: number
  organization_id: number
  organization_period_id?: number | null
  period_name?: string
  period?: string
  member_id?: number | null
  user_id?: number | null
  name: string
  nim?: string | null
  position: string
  department?: string | null
  start_date?: string | null
  end_date?: string | null
  notes?: string | null
  photo?: string | null
  photo_url?: string | null
  status: 'active' | 'inactive'
  status_mode?: 'auto' | 'manual'
  status_reason?: string | null
  status_changed_by?: number | null
  status_changed_at?: string | null
  status_changed_by_user?: {
    id: number
    name: string
  } | null
  is_active_effective?: boolean
  effective_status?: 'active' | 'inactive' | string
  created_at?: string
  updated_at?: string
  user?: {
    id: number
    name: string
    email?: string
  } | null
  member?: Member | null
  organization_period?: OrganizationPeriod | null
  organization?: Organization | null
}

export interface ForgotPasswordPayload {
  email: string
}

export interface ResetPasswordPayload {
  token: string
  email: string
  password: string
  password_confirmation: string
}

export interface PasswordResetResponse {
  status: string
  message: string
}

