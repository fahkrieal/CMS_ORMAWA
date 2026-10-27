<template>
  <FullScreenLayout>
    <div class="relative p-6 bg-white z-1 dark:bg-gray-900 sm:p-0">
      <div class="relative flex flex-col justify-center w-full h-screen lg:flex-row dark:bg-gray-900">
        
        <!-- Kolom Kiri: Form Login -->
        <div class="flex flex-col flex-1 w-full lg:w-5/12">
          <div class="w-full max-w-md pt-10 mx-auto px-6 sm:px-0">
            <router-link
              to="/"
              class="inline-flex items-center text-sm font-medium text-gray-500 transition-colors hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100"
            >
              <svg class="stroke-current mr-2" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none">
                <path d="M12.7083 5L7.5 10.2083L12.7083 15.4167" stroke="" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
              </svg>
              Kembali ke Beranda
            </router-link>
          </div>
          
          <div class="flex flex-col justify-center flex-1 w-full max-w-md mx-auto px-6 sm:px-0">
            <!-- Step 1: Form Login Utama -->
            <div v-if="!isMfaStep">
              <div class="mb-8">
                <h1 class="mb-2 font-bold text-gray-900 text-2xl dark:text-white">
                  Selamat Datang Kembali
                </h1>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                  Masuk untuk melanjutkan ke ORMAWA ITI.
                </p>
              </div>
                
              <form @submit.prevent="handleSubmit">
                <div class="space-y-5">
                  <!-- Email -->
                  <div>
                    <label for="email" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                      Email <span class="text-error-500">*</span>
                    </label>
                    <input
                      v-model="email"
                      type="email"
                      id="email"
                      name="email"
                      autocomplete="email"
                      placeholder="contoh: Ormawa@.iti.ac.id"
                      :class="[
                        'h-11 w-full rounded-lg border bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:outline-hidden focus:ring-3 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30',
                        formErrors.email 
                          ? 'border-error-500 focus:border-error-500 focus:ring-error-500/10' 
                          : 'border-gray-300 focus:border-brand-300 focus:ring-brand-500/10 dark:border-gray-700 dark:focus:border-brand-800'
                      ]"
                      :aria-invalid="!!formErrors.email"
                    />
                    <p v-if="formErrors.email" class="mt-1.5 text-xs text-error-500">{{ formErrors.email }}</p>
                  </div>
                  
                  <!-- Password -->
                  <div>
                    <label for="password" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                      Kata Sandi <span class="text-error-500">*</span>
                    </label>
                    <div class="relative">
                      <input
                        v-model="password"
                        :type="showPassword ? 'text' : 'password'"
                        id="password"
                        name="password"
                        autocomplete="current-password"
                        placeholder="Masukkan kata sandi Anda"
                        :class="[
                          'h-11 w-full rounded-lg border bg-transparent py-2.5 pl-4 pr-11 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:outline-hidden focus:ring-3 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30',
                          formErrors.password 
                            ? 'border-error-500 focus:border-error-500 focus:ring-error-500/10' 
                            : 'border-gray-300 focus:border-brand-300 focus:ring-brand-500/10 dark:border-gray-700 dark:focus:border-brand-800'
                        ]"
                        :aria-invalid="!!formErrors.password"
                      />
                      <button 
                        type="button"
                        :aria-label="showPassword ? 'Sembunyikan password' : 'Tampilkan password'"
                        @click="togglePasswordVisibility" 
                        class="absolute z-30 text-gray-500 -translate-y-1/2 cursor-pointer right-4 top-1/2 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 focus:outline-hidden"
                      >
                        <svg v-if="!showPassword" class="fill-current" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                          <path fill-rule="evenodd" clip-rule="evenodd" d="M10.0002 13.8619C7.23361 13.8619 4.86803 12.1372 3.92328 9.70241C4.86804 7.26761 7.23361 5.54297 10.0002 5.54297C12.7667 5.54297 15.1323 7.26762 16.0771 9.70243C15.1323 12.1372 12.7667 13.8619 10.0002 13.8619ZM10.0002 4.04297C6.48191 4.04297 3.49489 6.30917 2.4155 9.4593C2.3615 9.61687 2.3615 9.78794 2.41549 9.94552C3.49488 13.0957 6.48191 15.3619 10.0002 15.3619C13.5184 15.3619 16.5055 13.0957 17.5849 9.94555C17.6389 9.78797 17.6389 9.6169 17.5849 9.45932C16.5055 6.30919 13.5184 4.04297 10.0002 4.04297ZM9.99151 7.84413C8.96527 7.84413 8.13333 8.67606 8.13333 9.70231C8.13333 10.7286 8.96527 11.5605 9.99151 11.5605H10.0064C11.0326 11.5605 11.8646 10.7286 11.8646 9.70231C11.8646 8.67606 11.0326 7.84413 10.0064 7.84413H9.99151Z" fill="currentColor" />
                        </svg>
                        <svg v-else class="fill-current" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                          <path fill-rule="evenodd" clip-rule="evenodd" d="M4.63803 3.57709C4.34513 3.2842 3.87026 3.2842 3.57737 3.57709C3.28447 3.86999 3.28447 4.34486 3.57737 4.63775L4.85323 5.91362C3.74609 6.84199 2.89363 8.06395 2.4155 9.45936C2.3615 9.61694 2.3615 9.78801 2.41549 9.94558C3.49488 13.0957 6.48191 15.3619 10.0002 15.3619C11.255 15.3619 12.4422 15.0737 13.4994 14.5598L15.3625 16.4229C15.6554 16.7158 16.1302 16.7158 16.4231 16.4229C16.716 16.13 16.716 15.6551 16.4231 15.3622L4.63803 3.57709ZM12.3608 13.4212L10.4475 11.5079C10.3061 11.5423 10.1584 11.5606 10.0002 11.5606H9.99151C8.96527 11.5606 8.13333 10.7286 8.13333 9.70237C8.13333 9.5461 8.15262 9.39434 8.18895 9.24933L5.91885 6.97923C5.03505 7.69015 4.34057 8.62704 3.92328 9.70247C4.86803 12.1373 7.23361 13.8619 10.0002 13.8619C10.8326 13.8619 11.6287 13.7058 12.3608 13.4212ZM16.0771 9.70249C15.7843 10.4569 15.3552 11.1432 14.8199 11.7311L15.8813 12.7925C16.6329 11.9813 17.2187 11.0143 17.5849 9.94561C17.6389 9.78803 17.6389 9.61696 17.5849 9.45938C16.5055 6.30925 13.5184 4.04303 10.0002 4.04303C9.13525 4.04303 8.30244 4.17999 7.52218 4.43338L8.75139 5.66259C9.1556 5.58413 9.57311 5.54303 10.0002 5.54303C12.7667 5.54303 15.1323 7.26768 16.0771 9.70249Z" fill="currentColor" />
                        </svg>
                      </button>
                    </div>
                    <p v-if="formErrors.password" class="mt-1.5 text-xs text-error-500">{{ formErrors.password }}</p>
                  </div>
                  
                  <!-- Checkbox & Forgot Password -->
                  <div class="flex items-center justify-between">
                    <div>
                      <label for="keepLoggedIn" class="flex items-center text-sm font-normal text-gray-700 cursor-pointer select-none dark:text-gray-400">
                        <div class="relative">
                          <input v-model="keepLoggedIn" type="checkbox" id="keepLoggedIn" class="sr-only" />
                          <div :class="keepLoggedIn ? 'border-brand-500 bg-brand-500' : 'bg-transparent border-gray-300 dark:border-gray-700'" class="mr-3 flex h-5 w-5 items-center justify-center rounded-md border-[1.25px]">
                            <span :class="keepLoggedIn ? '' : 'opacity-0'">
                              <svg width="14" height="14" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M11.6666 3.5L5.24992 9.91667L2.33325 7" stroke="white" stroke-width="1.94437" stroke-linecap="round" stroke-linejoin="round" />
                              </svg>
                            </span>
                          </div>
                        </div>
                        Ingat sesi saya
                      </label>
                    </div>
                    <router-link to="/forgot-password" class="text-sm font-medium text-brand-500 hover:text-brand-600 dark:text-brand-400">
                      Lupa kata sandi?
                    </router-link>
                  </div>
                  
                  <!-- Button -->
                  <div>
                    <button 
                      type="submit" 
                      :disabled="authStore.isLoading"
                      class="flex items-center justify-center w-full px-4 py-3 text-sm font-medium text-white transition rounded-lg bg-brand-500 shadow-theme-xs hover:bg-brand-600 disabled:opacity-70 disabled:cursor-not-allowed"
                    >
                      <svg v-if="authStore.isLoading" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                      </svg>
                      {{ authStore.isLoading ? 'Memproses...' : 'Masuk' }}
                    </button>
                  </div>
                </div>
              </form>
              
              <div class="mt-6 text-center">
                <p class="text-sm font-medium text-gray-700 dark:text-gray-400">
                  Organisasi Anda belum terdaftar? 
                  <router-link to="/register" class="text-brand-500 hover:text-brand-600 dark:text-brand-400">
                    Daftarkan Sekarang
                  </router-link>
                </p>
              </div>
            </div>

            <!-- Step 2: Form Verifikasi 2FA / MFA -->
            <div v-else>
              <div class="mb-8">
                <div class="inline-flex items-center justify-center w-12 h-12 mb-4 rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-950/50 dark:text-brand-400">
                  <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                  </svg>
                </div>
                <h1 class="mb-2 font-bold text-gray-900 text-2xl dark:text-white">
                  Verifikasi Dua Faktor
                </h1>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                  Akun Anda dilindungi dengan autentikasi dua faktor (MFA). Masukkan kode 6 digit dari aplikasi autentikator atau kode pemulihan (recovery code).
                </p>
              </div>

              <form @submit.prevent="handleMfaSubmit">
                <div class="space-y-5">
                  <div>
                    <label for="mfaCode" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                      Kode Autentikasi / Recovery Code <span class="text-error-500">*</span>
                    </label>
                    <input
                      v-model="mfaCode"
                      type="text"
                      id="mfaCode"
                      name="mfaCode"
                      autocomplete="one-time-code"
                      placeholder="Contoh: 123456 atau XXXX-XXXX"
                      autofocus
                      :class="[
                        'h-11 w-full rounded-lg border bg-transparent px-4 py-2.5 text-sm text-gray-800 tracking-wider shadow-theme-xs placeholder:text-gray-400 focus:outline-hidden focus:ring-3 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30',
                        mfaError 
                          ? 'border-error-500 focus:border-error-500 focus:ring-error-500/10' 
                          : 'border-gray-300 focus:border-brand-300 focus:ring-brand-500/10 dark:border-gray-700 dark:focus:border-brand-800'
                      ]"
                    />
                    <p v-if="!mfaError" class="mt-1.5 text-[11px] text-gray-500 dark:text-gray-400">
                      Gunakan kode 6 digit dari aplikasi autentikator atau salah satu kode pemulihan cadangan (sekali pakai).
                    </p>
                    <p v-if="mfaError" class="mt-1.5 text-xs text-error-500">{{ mfaError }}</p>
                  </div>

                  <div>
                    <button 
                      type="submit" 
                      :disabled="authStore.isLoading"
                      class="flex items-center justify-center w-full px-4 py-3 text-sm font-medium text-white transition rounded-lg bg-brand-500 shadow-theme-xs hover:bg-brand-600 disabled:opacity-70 disabled:cursor-not-allowed"
                    >
                      <svg v-if="authStore.isLoading" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                      </svg>
                      {{ authStore.isLoading ? 'Memverifikasi...' : 'Verifikasi & Masuk' }}
                    </button>
                  </div>

                  <div class="text-center pt-2">
                    <button
                      type="button"
                      @click="cancelMfa"
                      class="text-sm font-medium text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200 transition"
                    >
                      &larr; Batalkan dan kembali ke login
                    </button>
                  </div>
                </div>
              </form>
            </div>
            </div>
          </div>
        
        <!-- Kolom Kanan: Identitas Kampus (Branding) -->
        <div class="relative items-center hidden w-full h-full lg:w-7/12 bg-brand-950 dark:bg-gray-950 lg:grid overflow-hidden">
          <div class="flex items-center justify-center z-1">
            <common-grid-shape />
            <div class="flex flex-col items-center max-w-lg text-center px-6">
              <router-link to="/" class="flex flex-col items-center mb-6 group">
                <div class="h-16 w-16 rounded-2xl bg-white p-2 mb-4 shadow-xl flex items-center justify-center border border-white/20 shrink-0">
                  <img src="/images/logo/iti-logo.png" alt="Institut Teknologi Indonesia" class="h-full w-full object-contain" />
                </div>
                <h2 class="text-2xl font-extrabold text-white tracking-tight">ORMAWA ITI</h2>
                <span class="text-xs text-blue-300 font-semibold uppercase tracking-wider mt-1">Institut Teknologi Indonesia</span>
              </router-link>
              <h3 class="text-xl font-bold text-white mb-4">Portal Manajemen Organisasi Kemahasiswaan</h3>
              <p class="text-base text-gray-300 dark:text-white/70 leading-relaxed">
                Kelola konten, organisasi, pengguna, dan aktivitas kemahasiswaan dalam satu platform yang terintegrasi secara profesional.
              </p>
            </div>
          </div>
        </div>
        
      </div>
    </div>
  </FullScreenLayout>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import CommonGridShape from '@/components/common/CommonGridShape.vue'
import FullScreenLayout from '@/components/layout/FullScreenLayout.vue'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'

const email = ref('')
const password = ref('')
const showPassword = ref(false)
const keepLoggedIn = ref(false)
const authStore = useAuthStore()
const toastStore = useToastStore()

const formErrors = ref({
  email: '',
  password: ''
})

const isMfaStep = ref(false)
const mfaToken = ref('')
const mfaCode = ref('')
const mfaError = ref('')

onMounted(() => {
  email.value = ''
  password.value = ''
  formErrors.value = { email: '', password: '' }
  authStore.error = null
  isMfaStep.value = false
  mfaToken.value = ''
  mfaCode.value = ''
  mfaError.value = ''
})

const togglePasswordVisibility = () => {
  showPassword.value = !showPassword.value
}

const validateForm = () => {
  let isValid = true
  formErrors.value = { email: '', password: '' }

  if (!email.value) {
    formErrors.value.email = 'Email wajib diisi.'
    isValid = false
  } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value)) {
    formErrors.value.email = 'Format email tidak valid.'
    isValid = false
  }

  if (!password.value) {
    formErrors.value.password = 'Kata sandi wajib diisi.'
    isValid = false
  }

  return isValid
}

const handleSubmit = async () => {
  if (!validateForm()) return
  
  const result = await authStore.login(email.value, password.value, keepLoggedIn.value)
  
  if (result.mfaRequired && result.mfaToken) {
    isMfaStep.value = true
    mfaToken.value = result.mfaToken
    mfaCode.value = ''
    mfaError.value = ''
    toastStore.info(result.message || 'Masukkan kode autentikator Anda.')
  } else if (result.success) {
    toastStore.success(`Selamat datang kembali, ${authStore.user?.name}.`)
  } else if (authStore.error) {
    toastStore.error(authStore.error)
  }
}

const handleMfaSubmit = async () => {
  mfaError.value = ''
  const cleanCode = mfaCode.value.replace(/\s+/g, '').trim()
  if (!cleanCode) {
    mfaError.value = 'Kode verifikasi wajib diisi.'
    return
  }

  const success = await authStore.verifyMfa(mfaToken.value, cleanCode)
  if (success) {
    toastStore.success(`Verifikasi 2FA berhasil. Selamat datang kembali, ${authStore.user?.name}.`)
  } else {
    mfaError.value = authStore.error || 'Kode verifikasi tidak valid atau telah kedaluwarsa.'
    toastStore.error(mfaError.value)
  }
}

const cancelMfa = () => {
  isMfaStep.value = false
  mfaToken.value = ''
  mfaCode.value = ''
  mfaError.value = ''
}
</script>