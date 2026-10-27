import api from './api'
import type { AuthResponse, UserProfile, ResetPasswordPayload, PasswordResetResponse, MfaSetupResponse } from '@/types/api'

export const authService = {
  async login(email: string, password: string, keepLoggedIn = false) {
    const { data } = await api.post<AuthResponse>('/login', {
      email,
      password,
      keep_logged_in: keepLoggedIn,
      remember: keepLoggedIn,
    })
    return data
  },

  async verifyMfa(mfa_token: string, code: string) {
    const { data } = await api.post<AuthResponse>('/mfa/verify', { mfa_token, code })
    return data
  },

  async setupMfa() {
    const { data } = await api.post<MfaSetupResponse>('/mfa/setup')
    return data
  },

  async confirmMfa(code: string) {
    const { data } = await api.post<{ status: string; message: string }>('/mfa/confirm', { code })
    return data
  },

  async disableMfa(password: string) {
    const { data } = await api.post<{ status: string; message: string }>('/mfa/disable', { password })
    return data
  },

  async regenerateRecoveryCodes(password: string) {
    const { data } = await api.post<{ status: string; message: string; recovery_codes: string[] }>('/mfa/recovery-codes', { password })
    return data
  },

  async me() {
    const { data } = await api.get<{ user: UserProfile }>('/me')
    return data.user
  },

  async logout(allDevices = false) {
    await api.post('/logout', { all_devices: allDevices })
  },

  async forgotPassword(email: string): Promise<PasswordResetResponse> {
    const { data } = await api.post<PasswordResetResponse>('/forgot-password', { email })
    return data
  },

  async resetPassword(payload: ResetPasswordPayload): Promise<PasswordResetResponse> {
    const { data } = await api.post<PasswordResetResponse>('/reset-password', payload)
    return data
  },

  async updateProfile(payload: FormData | { name: string; avatar?: string | null }) {
    const isFormData = typeof FormData !== 'undefined' && payload instanceof FormData
    const { data } = await api.post<{ message: string; user: UserProfile }>(
      '/profile',
      payload,
      isFormData ? { headers: { 'Content-Type': 'multipart/form-data' } } : undefined
    )
    return data
  },

  async updatePassword(payload: { current_password: string; password: string; password_confirmation: string }) {
    const { data } = await api.put<{ message: string }>('/password', payload)
    return data
  },
}
