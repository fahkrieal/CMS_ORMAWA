<template>
  <AdminLayout>
    <PageBreadcrumb pageTitle="Pengaturan Akun" />

    <div class="space-y-6 max-w-4xl">
      <!-- Page Header & Subtitle -->
      <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Pengaturan Akun</h1>
        <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-1">
          Kelola kredensial keamanan, kata sandi, dan status sesi akun Anda.
        </p>
      </div>

      <!-- Feedback Alert -->
      <transition
        enter-active-class="transition ease-out duration-200"
        enter-from-class="opacity-0 -translate-y-1"
        enter-to-class="opacity-100 translate-y-0"
        leave-active-class="transition ease-in duration-150"
        leave-from-class="opacity-100 translate-y-0"
        leave-to-class="opacity-0 -translate-y-1"
      >
        <div
          v-if="statusMessage"
          :class="[
            'p-4 rounded-xl border flex items-center justify-between gap-3 text-xs sm:text-sm font-medium shadow-2xs',
            statusType === 'success'
              ? 'bg-emerald-50 border-emerald-200 text-emerald-800 dark:bg-emerald-950/40 dark:border-emerald-800 dark:text-emerald-300'
              : 'bg-rose-50 border-rose-200 text-rose-800 dark:bg-rose-950/40 dark:border-rose-800 dark:text-rose-300'
          ]"
          role="status"
        >
          <div class="flex items-center gap-2.5">
            <svg v-if="statusType === 'success'" class="w-5 h-5 text-emerald-600 dark:text-emerald-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
            </svg>
            <svg v-else class="w-5 h-5 text-rose-600 dark:text-rose-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span>{{ statusMessage }}</span>
          </div>
          <button @click="statusMessage = ''" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
            &times;
          </button>
        </div>
      </transition>

      <div class="grid grid-cols-1 md:grid-cols-12 gap-6">
        
        <!-- Left / Top Column: Security Section (Ganti Kata Sandi) - 7 Cols -->
        <div class="md:col-span-7 space-y-6">
          <div class="rounded-2xl border border-gray-200 bg-white p-6 sm:p-7 shadow-theme-xs dark:border-gray-800 dark:bg-gray-900 space-y-6">
            <div>
              <h2 class="text-base font-bold text-gray-900 dark:text-white">
                Keamanan & Kata Sandi
              </h2>
              <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                Perbarui kata sandi Anda secara berkala untuk menjaga keamanan akses akun.
              </p>
            </div>

            <form @submit.prevent="handlePasswordSubmit" class="space-y-4">
              <!-- Kata Sandi Saat Ini -->
              <div>
                <label for="current-password" class="mb-1.5 block text-xs font-bold text-gray-700 dark:text-gray-300">
                  Kata Sandi Saat Ini <span class="text-rose-500">*</span>
                </label>
                <div class="relative">
                  <input
                    id="current-password"
                    v-model="passwordForm.current_password"
                    :type="showCurrentPassword ? 'text' : 'password'"
                    required
                    placeholder="••••••••"
                    :disabled="isSubmitting"
                    class="w-full rounded-xl border border-gray-300 bg-white pr-10 pl-4 py-2.5 text-xs sm:text-sm text-gray-900 focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                  />
                  <button
                    type="button"
                    @click="showCurrentPassword = !showCurrentPassword"
                    class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                    tabindex="-1"
                  >
                    <svg v-if="showCurrentPassword" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />
                    </svg>
                    <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                  </button>
                </div>
                <p v-if="errors.current_password" class="mt-1 text-xs text-rose-500">{{ errors.current_password }}</p>
              </div>

              <!-- Kata Sandi Baru -->
              <div>
                <label for="new-password" class="mb-1.5 block text-xs font-bold text-gray-700 dark:text-gray-300">
                  Kata Sandi Baru <span class="text-rose-500">*</span>
                </label>
                <div class="relative">
                  <input
                    id="new-password"
                    v-model="passwordForm.password"
                    :type="showNewPassword ? 'text' : 'password'"
                    required
                    placeholder="Minimal 8 karakter"
                    :disabled="isSubmitting"
                    class="w-full rounded-xl border border-gray-300 bg-white pr-10 pl-4 py-2.5 text-xs sm:text-sm text-gray-900 focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                  />
                  <button
                    type="button"
                    @click="showNewPassword = !showNewPassword"
                    class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                    tabindex="-1"
                  >
                    <svg v-if="showNewPassword" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />
                    </svg>
                    <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                  </button>
                </div>
                <p v-if="errors.password" class="mt-1 text-xs text-rose-500">{{ errors.password }}</p>
              </div>

              <!-- Konfirmasi Kata Sandi Baru -->
              <div>
                <label for="password-confirmation" class="mb-1.5 block text-xs font-bold text-gray-700 dark:text-gray-300">
                  Konfirmasi Kata Sandi Baru <span class="text-rose-500">*</span>
                </label>
                <div class="relative">
                  <input
                    id="password-confirmation"
                    v-model="passwordForm.password_confirmation"
                    :type="showConfirmPassword ? 'text' : 'password'"
                    required
                    placeholder="Ulangi kata sandi baru"
                    :disabled="isSubmitting"
                    class="w-full rounded-xl border border-gray-300 bg-white pr-10 pl-4 py-2.5 text-xs sm:text-sm text-gray-900 focus:border-brand-500 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                  />
                  <button
                    type="button"
                    @click="showConfirmPassword = !showConfirmPassword"
                    class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                    tabindex="-1"
                  >
                    <svg v-if="showConfirmPassword" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />
                    </svg>
                    <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                  </button>
                </div>
                <p v-if="errors.password_confirmation" class="mt-1 text-xs text-rose-500">{{ errors.password_confirmation }}</p>
              </div>

              <!-- Action Button -->
              <div class="pt-3 flex justify-end">
                <button
                  type="submit"
                  :disabled="isSubmitting"
                  class="inline-flex items-center justify-center gap-2 rounded-xl bg-brand-500 hover:bg-brand-600 px-5 py-2.5 text-xs sm:text-sm font-bold text-white shadow-xs transition duration-150 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  <svg v-if="isSubmitting" class="w-4 h-4 animate-spin text-white" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                  </svg>
                  <span>{{ isSubmitting ? 'Menyimpan...' : 'Perbarui Kata Sandi' }}</span>
                </button>
              </div>
            </form>
          </div>

          <!-- Card Autentikasi Dua Faktor (2FA / TOTP) -->
          <div class="rounded-2xl border border-gray-200 bg-white p-6 sm:p-7 shadow-theme-xs dark:border-gray-800 dark:bg-gray-900 space-y-6">
            <div class="flex items-start justify-between gap-4">
              <div>
                <h2 class="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
                  <span>Autentikasi Dua Faktor (2FA / TOTP)</span>
                </h2>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                  Tambahkan lapisan proteksi akun menggunakan standar RFC 6238 (Google Authenticator, Microsoft Authenticator).
                </p>
              </div>
              <span
                :class="[
                  'px-2.5 py-1 rounded-full text-xs font-semibold shrink-0',
                  isTwoFactorEnabled
                    ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400'
                    : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400'
                ]"
              >
                {{ isTwoFactorEnabled ? 'Aktif' : 'Nonaktif' }}
              </span>
            </div>

            <!-- Kondisi 1: 2FA Aktif -->
            <div v-if="isTwoFactorEnabled" class="space-y-4">
              <div class="p-4 rounded-xl bg-emerald-50/70 border border-emerald-200/80 dark:bg-emerald-950/20 dark:border-emerald-800/40 text-xs text-emerald-800 dark:text-emerald-300">
                <p class="font-semibold mb-1">Proteksi Akun Aktif</p>
                <p class="text-emerald-700 dark:text-emerald-400">Setiap login memerlukan kata sandi dan kode OTP 6 digit dari aplikasi autentikator atau kode pemulihan cadangan Anda.</p>
              </div>

              <!-- Dialog/Box Buka Kode Pemulihan Baru -->
              <div v-if="newRecoveryCodes.length > 0" class="p-4 rounded-xl bg-amber-50 border border-amber-200 dark:bg-amber-950/30 dark:border-amber-800 dark:text-amber-200 space-y-3">
                <div class="flex items-center justify-between">
                  <p class="text-xs font-bold text-amber-900 dark:text-amber-300">Kode Pemulihan Baru Dibuat:</p>
                  <span class="text-[10px] uppercase font-bold px-2 py-0.5 rounded bg-rose-100 text-rose-700 dark:bg-rose-900/50 dark:text-rose-300 border border-rose-200 dark:border-rose-900/40">Sangat Rahasia</span>
                </div>

                <!-- Alert Peringatan -->
                <div class="p-3 rounded-lg border border-rose-200 bg-rose-50/90 dark:border-rose-900/60 dark:bg-rose-950/40 text-xs text-rose-900 dark:text-rose-200 space-y-1">
                  <p class="font-bold text-[11px] text-rose-950 dark:text-rose-100 flex items-center gap-1.5">
                    <span>⚠️</span>
                    <span>JANGAN SAMPAI HILANG ATAU DIBERIKAN KEPADA SIAPAPUN!</span>
                  </p>
                  <p class="text-[11px] text-rose-800 dark:text-rose-300 leading-relaxed">
                    Kode pemulihan lama Anda sudah hangus. Simpan 8 kode baru ini di tempat aman. Jika HP atau aplikasi autentikator hilang, kode ini adalah <u>satu-satunya cara memulihkan akses akun Anda</u>.
                  </p>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 font-mono text-xs text-gray-800 dark:text-gray-200 bg-white dark:bg-gray-800 p-3 rounded-lg border border-amber-200 dark:border-amber-700/50">
                  <span v-for="(code, idx) in newRecoveryCodes" :key="idx" class="text-center py-1 bg-gray-50 dark:bg-gray-900 rounded">{{ code }}</span>
                </div>
                <div class="flex justify-end gap-2">
                  <button type="button" @click="copyRecoveryCodes(newRecoveryCodes)" class="text-xs px-3 py-1.5 font-medium rounded-lg bg-amber-600 hover:bg-amber-700 text-white">
                    {{ copiedCodes ? 'Disalin!' : 'Salin Semua Kode' }}
                  </button>
                  <button type="button" @click="newRecoveryCodes = []" class="text-xs px-3 py-1.5 font-medium rounded-lg bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-200">
                    Tutup
                  </button>
                </div>
              </div>

              <!-- Form Minta Password untuk Regenerate Recovery Codes -->
              <div v-if="showRegeneratePrompt" class="p-4 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/40 space-y-3">
                <p class="text-xs font-bold text-gray-800 dark:text-gray-200">Konfirmasi Kata Sandi untuk Buat Ulang Kode Pemulihan:</p>
                <input
                  v-model="actionPassword"
                  type="password"
                  placeholder="Masukkan kata sandi saat ini"
                  class="w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-xs text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                />
                <p v-if="actionError" class="text-xs text-rose-500">{{ actionError }}</p>
                <div class="flex gap-2">
                  <button type="button" :disabled="isActionLoading" @click="handleRegenerateCodes" class="text-xs px-3 py-2 rounded-xl bg-brand-500 hover:bg-brand-600 text-white font-medium">
                    {{ isActionLoading ? 'Memproses...' : 'Buat Ulang Kode' }}
                  </button>
                  <button type="button" @click="showRegeneratePrompt = false; actionPassword = ''; actionError = ''" class="text-xs px-3 py-2 rounded-xl border border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300">
                    Batal
                  </button>
                </div>
              </div>

              <!-- Form Minta Password untuk Disable MFA -->
              <div v-if="showDisablePrompt" class="p-4 rounded-xl border border-rose-200 bg-rose-50/50 dark:border-rose-900/50 dark:bg-rose-950/20 space-y-3">
                <p class="text-xs font-bold text-rose-900 dark:text-rose-300">Konfirmasi Nonaktifkan 2FA:</p>
                <p class="text-[11px] text-rose-700 dark:text-rose-400">Tindakan ini akan menurunkan level keamanan akun Anda. Masukkan kata sandi saat ini untuk melanjutkan.</p>
                <input
                  v-model="actionPassword"
                  type="password"
                  placeholder="Masukkan kata sandi saat ini"
                  class="w-full rounded-xl border border-rose-300 bg-white px-3 py-2 text-xs text-gray-900 dark:border-rose-800 dark:bg-gray-800 dark:text-white"
                />
                <p v-if="actionError" class="text-xs text-rose-500">{{ actionError }}</p>
                <div class="flex gap-2">
                  <button type="button" :disabled="isActionLoading" @click="handleDisableMfa" class="text-xs px-3 py-2 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-medium">
                    {{ isActionLoading ? 'Memproses...' : 'Ya, Nonaktifkan 2FA' }}
                  </button>
                  <button type="button" @click="showDisablePrompt = false; actionPassword = ''; actionError = ''" class="text-xs px-3 py-2 rounded-xl border border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300">
                    Batal
                  </button>
                </div>
              </div>

              <!-- Action buttons when 2FA is active and no prompts open -->
              <div v-if="!showRegeneratePrompt && !showDisablePrompt && newRecoveryCodes.length === 0" class="flex flex-wrap gap-2 pt-2">
                <button
                  type="button"
                  @click="showRegeneratePrompt = true; actionPassword = ''; actionError = ''"
                  class="text-xs px-3.5 py-2 rounded-xl border border-gray-300 hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800 font-medium text-gray-700 dark:text-gray-200 transition"
                >
                  Buat Ulang Kode Pemulihan
                </button>
                <button
                  type="button"
                  @click="showDisablePrompt = true; actionPassword = ''; actionError = ''"
                  class="text-xs px-3.5 py-2 rounded-xl border border-rose-200 hover:bg-rose-50 text-rose-600 dark:border-rose-900/50 dark:hover:bg-rose-950/40 dark:text-rose-400 font-medium transition"
                >
                  Nonaktifkan 2FA
                </button>
              </div>
            </div>

            <!-- Kondisi 2: 2FA Belum Aktif -->
            <div v-else class="space-y-4">
              <!-- Jika belum mulai setup -->
              <div v-if="!mfaSetupData" class="space-y-3">
                <p class="text-xs text-gray-600 dark:text-gray-400">
                  Autentikasi dua faktor menambahkan lapisan keamanan ekstra. Setelah diaktifkan, Anda akan diminta memasukkan kode 6 digit dari aplikasi autentikator setiap kali melakukan login.
                </p>
                <button
                  type="button"
                  :disabled="isMfaSettingUp"
                  @click="startMfaSetup"
                  class="inline-flex items-center gap-2 text-xs font-bold px-4 py-2.5 rounded-xl bg-brand-500 hover:bg-brand-600 text-white shadow-2xs transition disabled:opacity-50"
                >
                  <svg v-if="isMfaSettingUp" class="w-4 h-4 animate-spin text-white" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                  </svg>
                  <span>{{ isMfaSettingUp ? 'Menyiapkan...' : 'Aktifkan Autentikasi 2FA' }}</span>
                </button>
              </div>

              <!-- Jika sedang dalam alur setup -->
              <div v-else class="space-y-5 border-t border-gray-100 dark:border-gray-800 pt-4">
                <!-- Langkah 1: Pindai QR Code atau Kunci Rahasia -->
                <div class="space-y-3">
                  <p class="text-xs font-bold text-gray-800 dark:text-gray-200">1. Hubungkan ke Aplikasi Autentikator (Google / Microsoft Authenticator):</p>
                  <p class="text-[11px] text-gray-500 dark:text-gray-400">
                    Buka Google Authenticator atau Microsoft Authenticator di ponsel Anda, lalu pindai (scan) QR Code di bawah atau masukkan kode rahasia secara manual:
                  </p>
                  <div class="flex flex-col sm:flex-row items-center gap-4 bg-gray-50 dark:bg-gray-800/60 p-4 rounded-xl border border-gray-200 dark:border-gray-700">
                    <div class="bg-white p-2 rounded-lg border border-gray-200 shadow-2xs shrink-0 flex items-center justify-center">
                      <img
                        :src="'https://quickchart.io/qr?text=' + encodeURIComponent(mfaSetupData.otpauth_url) + '&size=140&ecLevel=M'"
                        alt="QR Code MFA"
                        class="w-32 h-32 object-contain"
                        @error="(e: any) => { e.target.src = 'https://api.qrserver.com/v1/create-qr-code/?size=140x140&margin=0&data=' + encodeURIComponent(mfaSetupData.otpauth_url) }"
                      />
                    </div>
                    <div class="space-y-2 text-center sm:text-left flex-1 min-w-0">
                      <p class="text-[11px] text-gray-500 dark:text-gray-400 font-medium">Jika tidak dapat memindai, masukkan Kunci Manual (Setup Key) berikut:</p>
                      <div class="flex items-center gap-2 bg-white dark:bg-gray-800 p-2 rounded-lg border border-gray-200 dark:border-gray-700">
                        <code class="font-mono text-xs font-bold text-brand-600 dark:text-brand-400 tracking-wider flex-1 select-all break-all">
                          {{ mfaSetupData.secret }}
                        </code>
                        <button type="button" @click="copySecret(mfaSetupData.secret)" class="text-xs px-2.5 py-1 rounded bg-gray-100 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-200">
                          {{ copiedSecret ? 'Tersalin' : 'Salin' }}
                        </button>
                      </div>
                      <p class="text-[10px] text-gray-400">Tipe Akun: <strong>Time-based (TOTP)</strong></p>
                    </div>
                  </div>
                </div>

                <!-- Langkah 2: Simpan Recovery Codes -->
                <div class="space-y-3">
                  <div class="flex items-center justify-between">
                    <p class="text-xs font-bold text-gray-800 dark:text-gray-200">2. Simpan 8 Kode Pemulihan Cadangan:</p>
                    <span class="text-[10px] uppercase font-bold px-2 py-0.5 rounded bg-rose-50 text-rose-600 dark:bg-rose-950/40 dark:text-rose-400 border border-rose-200 dark:border-rose-900/40">Sangat Rahasia</span>
                  </div>

                  <!-- Alert Peringatan Keamanan Utama -->
                  <div class="p-3.5 rounded-xl border border-rose-200 bg-rose-50/80 dark:border-rose-900/50 dark:bg-rose-950/30 text-xs text-rose-900 dark:text-rose-300 space-y-2 shadow-2xs">
                    <div class="flex items-center gap-2 font-bold text-rose-800 dark:text-rose-200">
                      <svg class="w-4 h-4 text-rose-600 dark:text-rose-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                      </svg>
                      <span>PERINGATAN PENTING — JANGAN SAMPAI HILANG ATAU DIBERIKAN KE ORANG LAIN!</span>
                    </div>
                    <ul class="text-[11px] text-rose-900/90 dark:text-rose-300/90 space-y-1.5 list-disc list-inside leading-relaxed pl-0.5">
                      <li>
                        <strong class="text-rose-950 dark:text-rose-100">JANGAN PERNAH DIBERIKAN KE SIAPAPUN:</strong> Siapapun yang memiliki kode ini dapat membobol akun Anda tanpa aplikasi autentikator. Admin kampus atau teknisi tidak akan pernah meminta kode ini.
                      </li>
                      <li>
                        <strong class="text-rose-950 dark:text-rose-100">JANGAN SAMPAI HILANG:</strong> Simpan di tempat yang aman (misal: password manager, catatan aman terenkripsi, atau cetak fisik). Jika HP Anda hilang atau rusak dan Anda tidak memiliki kode ini, <u>Anda akan terkunci permanen dan kehilangan akses akun selamanya!</u>
                      </li>
                    </ul>
                  </div>

                  <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 font-mono text-xs text-gray-800 dark:text-gray-200 bg-gray-50 dark:bg-gray-800/80 p-3 rounded-xl border border-gray-200 dark:border-gray-700">
                    <button
                      type="button"
                      v-for="(code, idx) in mfaSetupData.recovery_codes"
                      :key="idx"
                      @click="mfaConfirmCode = code"
                      title="Klik untuk memilih kode ini langsung sebagai konfirmasi aktivasi"
                      class="text-center py-1.5 px-2 bg-white hover:bg-brand-50 hover:text-brand-700 hover:border-brand-300 dark:bg-gray-900 dark:hover:bg-gray-800 rounded border border-gray-200 dark:border-gray-700 transition cursor-pointer font-bold text-gray-700 dark:text-gray-200"
                    >
                      {{ code }}
                    </button>
                  </div>
                  <div class="flex items-center justify-between text-[11px] text-gray-500 dark:text-gray-400">
                    <span>💡 <em>Klik salah satu kode di atas untuk langsung mengisinya ke kolom verifikasi.</em></span>
                    <button type="button" @click="copyRecoveryCodes(mfaSetupData.recovery_codes)" class="font-medium text-brand-600 dark:text-brand-400 hover:underline">
                      {{ copiedCodes ? 'Semua Kode Disalin!' : 'Salin Semua Kode' }}
                    </button>
                  </div>
                </div>

                <!-- Langkah 3: Konfirmasi Kode OTP / Recovery Code -->
                <div class="space-y-2 border-t border-gray-100 dark:border-gray-800 pt-3">
                  <p class="text-xs font-bold text-gray-800 dark:text-gray-200">3. Masukkan Kode Konfirmasi OTP atau Recovery Code:</p>
                  <p class="text-[11px] text-gray-500 dark:text-gray-400">
                    Masukkan kode 6 digit dari aplikasi autentikator Anda atau gunakan salah satu kode pemulihan cadangan di atas:
                  </p>
                  <div class="flex items-center gap-2">
                    <input
                      v-model="mfaConfirmCode"
                      type="text"
                      maxlength="15"
                      placeholder="123456 atau XXXX-XXXX"
                      class="w-56 rounded-xl border border-gray-300 bg-white px-3 py-2 text-center text-sm font-mono tracking-wider text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                    />
                    <button
                      type="button"
                      :disabled="isMfaConfirming || !mfaConfirmCode"
                      @click="handleConfirmMfa"
                      class="text-xs font-bold px-4 py-2.5 rounded-xl bg-brand-500 hover:bg-brand-600 text-white shadow-2xs transition disabled:opacity-50"
                    >
                      {{ isMfaConfirming ? 'Memverifikasi...' : 'Konfirmasi & Aktifkan' }}
                    </button>
                    <button
                      type="button"
                      @click="cancelMfaSetup"
                      class="text-xs px-3 py-2.5 rounded-xl border border-gray-300 dark:border-gray-700 text-gray-600 dark:text-gray-300"
                    >
                      Batal
                    </button>
                  </div>
                  <div v-if="mfaSetupData.current_otp" class="flex items-center gap-2 pt-1">
                    <button
                      type="button"
                      @click="mfaConfirmCode = mfaSetupData.current_otp"
                      class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-50 hover:bg-emerald-100 dark:bg-emerald-950/40 dark:hover:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 text-xs font-semibold transition cursor-pointer"
                    >
                      <span>⚡ Isi Otomatis OTP Server: <strong>{{ mfaSetupData.current_otp }}</strong></span>
                    </button>
                    <span class="text-[10px] text-gray-500">(Mode Pengembangan Lokal)</span>
                  </div>
                  <p v-if="mfaConfirmError" class="text-xs text-rose-500">{{ mfaConfirmError }}</p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Right / Bottom Column: Session & Account Info - 5 Cols -->
        <div class="md:col-span-5 space-y-6">
          
          <!-- Sesi Aktif Card -->
          <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-gray-900 space-y-4">
            <h2 class="text-base font-bold text-gray-900 dark:text-white border-b border-gray-100 dark:border-gray-800 pb-3">
              Sesi & Keamanan
            </h2>

            <div class="space-y-3 text-xs">
              <div class="flex items-center justify-between py-1 border-b border-gray-50 dark:border-gray-800/50">
                <span class="text-gray-500 dark:text-gray-400">Status Sesi</span>
                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400 font-semibold">
                  <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                  Aktif
                </span>
              </div>

              <div class="flex items-center justify-between py-1 border-b border-gray-50 dark:border-gray-800/50">
                <span class="text-gray-500 dark:text-gray-400">Metode Autentikasi</span>
                <span class="font-semibold text-gray-800 dark:text-gray-200">Laravel Sanctum</span>
              </div>

              <div class="flex items-center justify-between py-1 border-b border-gray-50 dark:border-gray-800/50">
                <span class="text-gray-500 dark:text-gray-400">Status 2FA</span>
                <span :class="['font-semibold', isTwoFactorEnabled ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-500']">
                  {{ isTwoFactorEnabled ? 'Aktif' : 'Nonaktif' }}
                </span>
              </div>

              <div class="flex items-center justify-between py-1 border-b border-gray-50 dark:border-gray-800/50">
                <span class="text-gray-500 dark:text-gray-400">Masa Berlaku Token</span>
                <span class="font-semibold text-gray-800 dark:text-gray-200">2 Jam / 24 Jam</span>
              </div>

              <div class="flex items-center justify-between py-1">
                <span class="text-gray-500 dark:text-gray-400">Tingkat Hak Akses</span>
                <span class="font-semibold text-brand-600 dark:text-brand-400">{{ userRole }}</span>
              </div>
            </div>

            <div class="pt-3 border-t border-gray-100 dark:border-gray-800 space-y-2">
              <button
                type="button"
                @click="handleLogout"
                class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-gray-50 hover:bg-gray-100 text-gray-700 dark:bg-gray-800/60 dark:hover:bg-gray-800 dark:text-gray-300 px-4 py-2.5 text-xs font-bold transition shadow-2xs"
              >
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                </svg>
                <span>Keluar dari Perangkat Ini</span>
              </button>

              <button
                type="button"
                @click="handleLogoutAllDevices"
                class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-600 dark:bg-rose-950/30 dark:hover:bg-rose-900/40 dark:text-rose-400 px-4 py-2.5 text-xs font-bold transition shadow-2xs"
              >
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <span>Keluar dari Semua Perangkat</span>
              </button>
            </div>
          </div>

          <!-- Institutional Notice Card -->
          <div class="rounded-2xl border border-gray-200 bg-gray-50/70 p-5 dark:border-gray-800 dark:bg-gray-800/40 space-y-2">
            <h3 class="text-xs font-bold text-gray-800 dark:text-gray-200 flex items-center gap-1.5">
              <span>🛡️</span>
              <span>Kebijakan Keamanan Akun</span>
            </h3>
            <p class="text-[11px] text-gray-500 dark:text-gray-400 leading-relaxed">
              Akun pengurus terikat secara resmi dengan institusi Institut Teknologi Indonesia. Jangan membagikan kata sandi Anda kepada pihak lain.
            </p>
          </div>

        </div>

      </div>
    </div>
  </AdminLayout>
</template>

<script setup lang="ts">
import { ref, reactive, computed } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { authService } from '@/services/authService'
import type { MfaSetupResponse } from '@/types/api'
import AdminLayout from '@/components/layout/AdminLayout.vue'
import PageBreadcrumb from '@/components/common/PageBreadcrumb.vue'

const authStore = useAuthStore()

const passwordForm = reactive({
  current_password: '',
  password: '',
  password_confirmation: '',
})

const showCurrentPassword = ref(false)
const showNewPassword = ref(false)
const showConfirmPassword = ref(false)

const isSubmitting = ref(false)
const statusMessage = ref('')
const statusType = ref<'success' | 'error'>('success')
const errors = reactive<{ current_password?: string; password?: string; password_confirmation?: string }>({})

const userRole = computed(() => authStore.role || authStore.user?.role || 'Anggota')
const isTwoFactorEnabled = computed(() => !!authStore.user?.two_factor_enabled)

// MFA Setup & Management State
const isMfaSettingUp = ref(false)
const mfaSetupData = ref<MfaSetupResponse | null>(null)
const mfaConfirmCode = ref('')
const mfaConfirmError = ref('')
const isMfaConfirming = ref(false)
const copiedSecret = ref(false)
const copiedCodes = ref(false)

const newRecoveryCodes = ref<string[]>([])
const showRegeneratePrompt = ref(false)
const showDisablePrompt = ref(false)
const actionPassword = ref('')
const actionError = ref('')
const isActionLoading = ref(false)

const startMfaSetup = async () => {
  isMfaSettingUp.value = true
  mfaConfirmError.value = ''
  try {
    const data = await authService.setupMfa()
    mfaSetupData.value = data
  } catch (err: any) {
    statusType.value = 'error'
    statusMessage.value = err?.data?.message || err?.message || 'Gagal memulai penyiapan 2FA.'
  } finally {
    isMfaSettingUp.value = false
  }
}

const cancelMfaSetup = () => {
  mfaSetupData.value = null
  mfaConfirmCode.value = ''
  mfaConfirmError.value = ''
  copiedSecret.value = false
  copiedCodes.value = false
}

const handleConfirmMfa = async () => {
  const cleanCode = mfaConfirmCode.value.replace(/\s+/g, '').trim()
  if (!cleanCode || cleanCode.length < 6) {
    mfaConfirmError.value = 'Masukkan 6 digit kode OTP atau kode pemulihan cadangan.'
    return
  }

  isMfaConfirming.value = true
  mfaConfirmError.value = ''

  try {
    const res = await authService.confirmMfa(cleanCode)
    authStore.setUser({ two_factor_enabled: true })
    statusType.value = 'success'
    statusMessage.value = res.message || 'Autentikasi dua faktor berhasil diaktifkan.'
    cancelMfaSetup()
  } catch (err: any) {
    mfaConfirmError.value = err?.data?.errors?.code?.[0] || err?.data?.message || 'Kode konfirmasi tidak valid. Silakan coba lagi.'
  } finally {
    isMfaConfirming.value = false
  }
}

const handleDisableMfa = async () => {
  if (!actionPassword.value) {
    actionError.value = 'Kata sandi saat ini wajib diisi.'
    return
  }

  isActionLoading.value = true
  actionError.value = ''

  try {
    const res = await authService.disableMfa(actionPassword.value)
    authStore.setUser({ two_factor_enabled: false })
    statusType.value = 'success'
    statusMessage.value = res.message || 'Autentikasi dua faktor telah dinonaktifkan.'
    showDisablePrompt.value = false
    actionPassword.value = ''
  } catch (err: any) {
    actionError.value = err?.data?.errors?.current_password?.[0] || err?.data?.message || 'Kata sandi salah.'
  } finally {
    isActionLoading.value = false
  }
}

const handleRegenerateCodes = async () => {
  if (!actionPassword.value) {
    actionError.value = 'Kata sandi saat ini wajib diisi.'
    return
  }

  isActionLoading.value = true
  actionError.value = ''

  try {
    const res = await authService.regenerateRecoveryCodes(actionPassword.value)
    newRecoveryCodes.value = res.recovery_codes
    showRegeneratePrompt.value = false
    actionPassword.value = ''
    statusType.value = 'success'
    statusMessage.value = res.message || 'Kode pemulihan baru berhasil dibuat.'
  } catch (err: any) {
    actionError.value = err?.data?.errors?.current_password?.[0] || err?.data?.message || 'Kata sandi salah.'
  } finally {
    isActionLoading.value = false
  }
}

const copySecret = async (text: string) => {
  try {
    await navigator.clipboard.writeText(text)
    copiedSecret.value = true
    setTimeout(() => { copiedSecret.value = false }, 2000)
  } catch {
    // fallback
  }
}

const copyRecoveryCodes = async (codes: string[]) => {
  try {
    await navigator.clipboard.writeText(codes.join('\n'))
    copiedCodes.value = true
    setTimeout(() => { copiedCodes.value = false }, 2500)
  } catch {
    // fallback
  }
}

const handlePasswordSubmit = async () => {
  errors.current_password = ''
  errors.password = ''
  errors.password_confirmation = ''
  statusMessage.value = ''

  if (!passwordForm.current_password) {
    errors.current_password = 'Kata sandi saat ini wajib diisi.'
    return
  }

  if (!passwordForm.password) {
    errors.password = 'Kata sandi baru wajib diisi.'
    return
  }

  if (passwordForm.password.length < 8) {
    errors.password = 'Kata sandi baru minimal 8 karakter.'
    return
  }

  if (passwordForm.password !== passwordForm.password_confirmation) {
    errors.password_confirmation = 'Konfirmasi kata sandi baru tidak cocok.'
    return
  }

  isSubmitting.value = true

  try {
    const res = await authService.updatePassword({
      current_password: passwordForm.current_password,
      password: passwordForm.password,
      password_confirmation: passwordForm.password_confirmation,
    })

    statusType.value = 'success'
    statusMessage.value = res.message || 'Kata sandi berhasil diperbarui.'

    // Reset password form
    passwordForm.current_password = ''
    passwordForm.password = ''
    passwordForm.password_confirmation = ''
  } catch (err: any) {
    statusType.value = 'error'
    if (err?.data?.errors) {
      if (err.data.errors.current_password) errors.current_password = err.data.errors.current_password[0]
      if (err.data.errors.password) errors.password = err.data.errors.password[0]
      if (err.data.errors.password_confirmation) errors.password_confirmation = err.data.errors.password_confirmation[0]
      statusMessage.value = err.data.message || 'Gagal memperbarui kata sandi.'
    } else {
      statusMessage.value = err?.data?.message || err?.message || 'Gagal memperbarui kata sandi. Periksa kata sandi saat ini.'
    }
  } finally {
    isSubmitting.value = false
  }
}

const handleLogout = async () => {
  await authStore.logout(false)
}

const handleLogoutAllDevices = async () => {
  await authStore.logout(true)
}
</script>
