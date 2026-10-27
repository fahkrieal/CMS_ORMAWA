import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import router from '@/router'
import { authService } from '@/services/authService'
import type { UserProfile } from '@/types/api'

export type AuthStatus = 'AUTH_LOADING' | 'AUTHENTICATED' | 'UNAUTHENTICATED'

export const useAuthStore = defineStore('auth', () => {
  const status = ref<AuthStatus>('AUTH_LOADING')
  const user = ref<UserProfile | null>(null)
  const role = ref<string | null>(null)
  const organization_id = ref<number | null>(null)
  const token = ref<string | null>(null)
  const isLoading = ref(false)
  const isInitialized = ref(false)
  const error = ref<string | null>(null)

  const isAuthenticated = computed(() => status.value === 'AUTHENTICATED' && !!token.value && !!user.value)

  const persistAuth = (authToken: string, authUser: UserProfile) => {
    token.value = authToken
    user.value = authUser
    role.value = authUser.role ?? null
    organization_id.value = authUser.organization?.id ?? null
    status.value = 'AUTHENTICATED'
    localStorage.setItem('auth_token', authToken)
    localStorage.setItem('auth_user', JSON.stringify(authUser))
    localStorage.setItem('auth_role', authUser.role ?? '')
    localStorage.setItem('auth_organization', authUser.organization?.id?.toString() ?? '')
  }

  const clearAuth = async (shouldRedirect = true) => {
    token.value = null
    user.value = null
    role.value = null
    organization_id.value = null
    status.value = 'UNAUTHENTICATED'
    localStorage.removeItem('auth_token')
    localStorage.removeItem('auth_user')
    localStorage.removeItem('auth_role')
    localStorage.removeItem('auth_organization')
    error.value = null

    if (shouldRedirect && router.currentRoute.value.meta.requiresAuth) {
      await router.replace('/login')
    }
  }

  let restorePromise: Promise<boolean> | null = null

  const restoreSession = async (): Promise<boolean> => {
    if (restorePromise) {
      return restorePromise
    }

    restorePromise = (async () => {
      status.value = 'AUTH_LOADING'
      isLoading.value = true

      const storedToken = localStorage.getItem('auth_token')

      if (!storedToken) {
        token.value = null
        user.value = null
        role.value = null
        organization_id.value = null
        status.value = 'UNAUTHENTICATED'
        isLoading.value = false
        isInitialized.value = true
        restorePromise = null
        return false
      }

      // Temporarily set token in memory so outgoing requests include Authorization header
      token.value = storedToken

      try {
        const currentUser = await authService.me()
        user.value = currentUser
        role.value = currentUser.role ?? null
        organization_id.value = currentUser.organization?.id ?? null
        status.value = 'AUTHENTICATED'

        localStorage.setItem('auth_user', JSON.stringify(currentUser))
        localStorage.setItem('auth_role', currentUser.role ?? '')
        localStorage.setItem('auth_organization', currentUser.organization?.id?.toString() ?? '')
        isLoading.value = false
        isInitialized.value = true
        restorePromise = null
        return true
      } catch {
        isLoading.value = false
        isInitialized.value = true
        restorePromise = null
        await clearAuth(false)
        return false
      }
    })()

    return restorePromise
  }

  const login = async (email: string, password: string, keepLoggedIn = false) => {
    isLoading.value = true
    error.value = null

    try {
      const response = await authService.login(email, password, keepLoggedIn)

      if (response.mfa_required) {
        isLoading.value = false
        return {
          mfaRequired: true,
          mfaToken: response.mfa_token,
          message: response.message,
        }
      }

      if (response.access_token && response.user) {
        persistAuth(response.access_token, response.user)
        isLoading.value = false
        isInitialized.value = true

        if (role.value === 'Super Admin') {
          await router.push('/dashboard')
        } else {
          await router.push('/organization/dashboard')
        }

        return { success: true }
      }

      isLoading.value = false
      return { success: false }
    } catch (err: any) {
      isLoading.value = false
      let msg = 'Gagal melakukan login. Silakan coba lagi.'
      if (err && typeof err === 'object') {
        if (err.status === 401 || err.status === 422) {
          msg = 'Email atau kata sandi yang Anda masukkan salah.'
        } else if (err.status === 403) {
          msg = err.message || 'Akun Anda tidak memiliki akses ke sistem.'
        } else if (err.status === 500) {
          msg = 'Terjadi kesalahan pada server. Silakan coba lagi nanti.'
        } else if (err.status === undefined && err.message) {
          msg = 'Tidak dapat terhubung ke server. Periksa koneksi internet Anda.'
        }
      } else if (err instanceof Error) {
        msg = err.message
      }
      error.value = msg
      return { success: false, error: msg }
    }
  }

  const verifyMfa = async (mfaToken: string, code: string) => {
    isLoading.value = true
    error.value = null

    try {
      const response = await authService.verifyMfa(mfaToken, code)
      if (response.access_token && response.user) {
        persistAuth(response.access_token, response.user)
        isLoading.value = false
        isInitialized.value = true

        if (role.value === 'Super Admin') {
          await router.push('/dashboard')
        } else {
          await router.push('/organization/dashboard')
        }

        return true
      }
      isLoading.value = false
      return false
    } catch (err: any) {
      isLoading.value = false
      let msg = 'Kode verifikasi dua faktor tidak valid.'
      if (err?.data?.errors?.code?.[0]) {
        msg = err.data.errors.code[0]
      } else if (err?.message) {
        msg = err.message
      }
      error.value = msg
      return false
    }
  }

  const logout = async (allDevices = false) => {
    try {
      await authService.logout(allDevices)
    } catch {
      // Ignore API errors during logout
    }
    await clearAuth(true)
  }

  // Handle global auth expiration event
  if (typeof window !== 'undefined') {
    window.addEventListener('auth:expired', () => {
      clearAuth(true)
    })
  }

  const setUser = (updatedUser: Partial<UserProfile>) => {
    if (!user.value) return
    user.value = { ...user.value, ...updatedUser } as UserProfile
    if (updatedUser.role) role.value = updatedUser.role
    if (updatedUser.organization?.id) organization_id.value = updatedUser.organization.id
    localStorage.setItem('auth_user', JSON.stringify(user.value))
    if (updatedUser.role) localStorage.setItem('auth_role', updatedUser.role)
    if (updatedUser.organization?.id) localStorage.setItem('auth_organization', updatedUser.organization.id.toString())
  }

  const hasPermission = (permissionName: string): boolean => {
    if (role.value === 'Super Admin') return true
    if (!user.value?.permissions || !Array.isArray(user.value.permissions)) return false
    return user.value.permissions.includes(permissionName)
  }

  return {
    status,
    isAuthenticated,
    isInitialized,
    user,
    role,
    organization_id,
    token,
    isLoading,
    error,
    login,
    verifyMfa,
    logout,
    restoreSession,
    clearAuth,
    setUser,
    hasPermission,
  }
})

