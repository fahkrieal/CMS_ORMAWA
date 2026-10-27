<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-8">
    <!-- Breadcrumb -->
    <nav class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-[#424751]" aria-label="Breadcrumb">
      <router-link to="/" class="hover:text-[#00346F]">Beranda</router-link>
      <span class="text-[#737783]">&rsaquo;</span>
      <router-link :to="`/organizations/${subdomain}`" class="hover:text-[#00346F]">{{ orgName }}</router-link>
      <span class="text-[#737783]">&rsaquo;</span>
      <span class="text-[#00346F] font-bold">Struktur Kepengurusan</span>
    </nav>

    <!-- Header & Period Filter -->
    <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4 border-b border-[#C2C6D3] pb-5">
      <div>
        <span class="text-[11px] font-bold uppercase tracking-wider text-[#00346F]">Bagan Kepengurusan</span>
        <h1 class="text-2xl sm:text-3xl md:text-4xl font-extrabold text-[#191C1D] mt-0.5">Struktur Organisasi</h1>
        <p class="text-xs sm:text-sm text-[#424751] mt-1 max-w-2xl">
          Susunan fungsionaris dan badan pengurus resmi {{ orgName }}.
        </p>
      </div>

      <!-- Selector Periode Kepengurusan -->
      <div v-if="availablePeriods.length > 0" class="flex flex-col sm:flex-row sm:items-center gap-2">
        <label for="period-select" class="text-xs font-semibold text-[#424751]">Pilih Periode:</label>
        <select
          id="period-select"
          v-model="selectedPeriodId"
          @change="onPeriodChange"
          class="rounded-lg border border-[#C2C6D3] bg-white px-3 py-2 text-xs font-medium text-[#191C1D] shadow-2xs focus:border-[#00346F] focus:outline-hidden"
        >
          <option v-for="p in availablePeriods" :key="p.id" :value="p.id">
            {{ p.period_name }} {{ p.is_ongoing ? '(Aktif / Berjalan)' : (p.calculated_status === 'expired' ? '(Arsip)' : '') }}
          </option>
        </select>
      </div>
    </div>

    <!-- Active/Selected Period Info Banner -->
    <div
      v-if="selectedPeriod"
      class="rounded-xl border p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 text-xs"
      :class="[
        selectedPeriod.is_ongoing
          ? 'border-emerald-200 bg-emerald-50/60 text-emerald-900'
          : selectedPeriod.calculated_status === 'expired'
            ? 'border-amber-200 bg-amber-50/60 text-amber-900'
            : 'border-blue-200 bg-blue-50/60 text-blue-900'
      ]"
    >
      <div class="flex items-center gap-2">
        <span
          class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold uppercase tracking-wider"
          :class="[
            selectedPeriod.is_ongoing
              ? 'bg-emerald-200 text-emerald-800'
              : selectedPeriod.calculated_status === 'expired'
                ? 'bg-amber-200 text-amber-800'
                : 'bg-blue-200 text-blue-800'
          ]"
        >
          {{ selectedPeriod.is_ongoing ? 'Periode Berjalan' : (selectedPeriod.calculated_status === 'expired' ? 'Arsip Periode Lampau' : 'Periode Mendatang') }}
        </span>
        <span class="font-bold text-sm">{{ selectedPeriod.period_name }}</span>
      </div>
      <div v-if="selectedPeriod.start_date && selectedPeriod.end_date" class="text-gray-600 font-medium">
        Masa Kepengurusan: {{ formatDate(selectedPeriod.start_date) }} s.d. {{ formatDate(selectedPeriod.end_date) }}
      </div>
    </div>

    <!-- Loading Skeleton -->
    <div v-if="isLoading" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-5">
      <div v-for="i in 8" :key="i" class="h-44 rounded-xl bg-white border border-[#C2C6D3] p-4 flex flex-col items-center justify-center animate-pulse space-y-3">
        <div class="h-16 w-16 rounded-full bg-[#E1E3E4]"></div>
        <div class="h-4 bg-[#E1E3E4] rounded w-24"></div>
        <div class="h-3 bg-[#E1E3E4] rounded w-16"></div>
      </div>
    </div>

    <!-- Error State -->
    <div v-else-if="errorMessage" class="py-16 text-center text-xs text-rose-600 border border-dashed border-rose-300 rounded-xl bg-rose-50/50 p-6">
      <p class="font-bold mb-2">{{ errorMessage }}</p>
      <button @click="loadStructure" class="px-4 py-1.5 bg-[#00346F] text-white rounded text-xs font-semibold hover:bg-[#002855]">
        Coba Lagi
      </button>
    </div>

    <!-- Empty State -->
    <div v-else-if="members.length === 0" class="py-16 text-center border border-dashed border-[#C2C6D3] rounded-xl bg-white p-8">
      <div class="h-12 w-12 rounded-full bg-[#F3F4F5] flex items-center justify-center mx-auto text-[#737783] mb-3 text-lg font-bold">
        👥
      </div>
      <h3 class="text-sm font-bold text-[#191C1D]">Belum ada data kepengurusan publik.</h3>
      <p class="text-xs text-[#737783] mt-1 max-w-md mx-auto">
        {{ selectedPeriod && selectedPeriod.calculated_status === 'expired' 
          ? 'Tidak ada data pengurus yang tersimpan pada arsip periode ini.'
          : 'Organisasi ini belum memiliki struktur kepengurusan aktif yang sedang berjalan.' }}
      </p>
    </div>

    <!-- Structure Cards Grouped by Department -->
    <div v-else class="space-y-10">
      <div v-for="(deptMembers, deptName) in byDepartment" :key="deptName" class="space-y-4">
        <div class="flex items-center gap-3">
          <h2 class="text-base sm:text-lg font-extrabold text-[#191C1D]">{{ deptName }}</h2>
          <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-gray-100 text-gray-600">{{ deptMembers.length }}</span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-5">
          <div
            v-for="m in deptMembers"
            :key="m.id"
            class="rounded-xl border border-[#C2C6D3] bg-white p-5 space-y-3 shadow-2xs flex flex-col items-center text-center hover:border-[#00346F] transition relative"
          >
            <!-- Member Avatar / Photo -->
            <div class="h-20 w-20 rounded-full border-2 border-[#E1E3E4] bg-[#F8F9FA] overflow-hidden flex items-center justify-center shrink-0">
              <img
                v-if="(m.photo_url || m.photo) && !failedPhotos.has(m.id)"
                :src="m.photo_url || m.photo || undefined"
                :alt="`Foto ${m.name}`"
                class="h-full w-full object-cover"
                loading="lazy"
                @error="handleImgError(m.id)"
              />
              <span v-else class="text-lg font-extrabold text-[#00346F]">
                {{ getInitials(m.name) }}
              </span>
            </div>

            <div class="space-y-1 w-full">
              <h3 class="text-sm font-bold text-[#191C1D] truncate" :title="m.name">{{ m.name }}</h3>
              <p class="text-xs font-semibold text-[#00346F] truncate" :title="m.position">{{ m.position }}</p>
              <p v-if="m.department" class="text-[11px] text-[#737783] truncate" :title="m.department">{{ m.department }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { publicService } from '@/services/publicService'
import { useSeoMeta } from '@/composables/useSeoMeta'
import type { PublicOrganization, PublicCommittee } from '@/types/public'

const props = defineProps<{ organization?: PublicOrganization }>()
const route = useRoute()

const members = ref<PublicCommittee[]>([])
const byDepartment = ref<Record<string, PublicCommittee[]>>({})
const availablePeriods = ref<Array<{ id: number; period_name: string; start_date?: string; end_date?: string; calculated_status?: string; is_ongoing?: boolean }>>([])
const selectedPeriod = ref<{ id: number; period_name: string; start_date?: string; end_date?: string; calculated_status?: string; is_ongoing?: boolean } | null>(null)
const selectedPeriodId = ref<number | null>(null)

const isLoading = ref(true)
const errorMessage = ref('')
const failedPhotos = ref<Set<number>>(new Set())

const subdomain = computed(() => props.organization?.subdomain || (route.params.slug as string) || '')
const orgName = computed(() => props.organization?.nama || 'Organisasi')

useSeoMeta(() => ({
  title: `Struktur Kepengurusan — ${orgName.value} | ORMAWA ITI`,
  description: `Susunan kepengurusan resmi dari ${orgName.value} Institut Teknologi Indonesia.`,
  ogType: 'website',
}))

const getInitials = (name?: string) => {
  if (!name) return 'P'
  const parts = name.trim().split(/\s+/)
  if (parts.length === 1) return parts[0].substring(0, 2).toUpperCase()
  return (parts[0][0] + parts[1][0]).toUpperCase()
}

const formatDate = (dateStr?: string) => {
  if (!dateStr) return '-'
  try {
    const d = new Date(dateStr)
    return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
  } catch {
    return dateStr
  }
}

const handleImgError = (memberId: number) => {
  failedPhotos.value.add(memberId)
}

const onPeriodChange = () => {
  loadStructure(selectedPeriodId.value ?? undefined)
}

const loadStructure = async (periodId?: number) => {
  if (!subdomain.value) return
  isLoading.value = true
  errorMessage.value = ''
  try {
    const res = await publicService.getStructure(subdomain.value, periodId ? { period_id: periodId } : undefined)
    members.value = res?.members || []
    byDepartment.value = res?.by_department || {}
    availablePeriods.value = res?.available_periods || []
    selectedPeriod.value = res?.selected_period || null
    if (res?.selected_period?.id) {
      selectedPeriodId.value = res.selected_period.id
    }
  } catch (err: any) {
    errorMessage.value = err.message || 'Gagal memuat struktur kepengurusan.'
  } finally {
    isLoading.value = false
  }
}

watch(() => subdomain.value, (newVal) => {
  if (newVal) loadStructure()
})

onMounted(() => {
  loadStructure()
})
</script>
