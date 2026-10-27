<template>
  <AdminLayout>
    <PageBreadcrumb pageTitle="Manajemen Anggota & Kepengurusan" />

    <div class="space-y-6">
      <!-- Top Header & Navigation Banner -->
      <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6 shadow-2xs">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <div class="flex items-center gap-2">
              <span class="inline-flex items-center rounded-md bg-brand-50 px-2 py-1 text-xs font-medium text-brand-700 ring-1 ring-inset ring-brand-700/10 dark:bg-brand-950/40 dark:text-brand-300">
                Modul Organisasi
              </span>
              <span v-if="ongoingPeriod" class="inline-flex items-center rounded-md bg-emerald-50 px-2 py-1 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/20 dark:bg-emerald-950/40 dark:text-emerald-400">
                Periode Aktif: {{ ongoingPeriod.period_name }}
              </span>
              <span v-else class="inline-flex items-center rounded-md bg-amber-50 px-2 py-1 text-xs font-medium text-amber-700 ring-1 ring-inset ring-amber-600/20 dark:bg-amber-950/40 dark:text-amber-400">
                Tidak Ada Periode Berjalan
              </span>
            </div>
            <h1 class="mt-2 text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
              <UsersIcon class="h-7 w-7 text-brand-500" />
              Manajemen Anggota & Kepengurusan
            </h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
              Pusat pengelolaan identitas anggota, struktur fungsionaris periode aktif, arsip kepengurusan, dan impor/ekspor data.
            </p>
          </div>

          <!-- Quick Action Buttons -->
          <div class="flex flex-wrap items-center gap-2.5">
            <button
              v-if="canManage"
              @click="openCreateMemberModal"
              class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3.5 py-2 text-sm font-medium text-gray-700 shadow-2xs hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700 transition"
            >
              <UserPlusIcon class="h-4 w-4 text-gray-500" />
              <span>Tambah Anggota</span>
            </button>
            <button
              v-if="canManage"
              @click="openCreateCommitteeModal"
              class="inline-flex items-center gap-1.5 rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white shadow-2xs hover:bg-brand-600 transition"
            >
              <PlusIcon class="h-4 w-4" />
              <span>Tambah Pengurus</span>
            </button>
          </div>
        </div>

        <!-- Tab Navigation Bar -->
        <div class="mt-6 border-b border-gray-200 dark:border-gray-800">
          <nav class="-mb-px flex space-x-6 overflow-x-auto" aria-label="Tabs">
            <button
              v-for="tab in tabs"
              :key="tab.id"
              @click="activeTab = tab.id"
              class="whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm flex items-center gap-2 transition"
              :class="[
                activeTab === tab.id
                  ? 'border-brand-500 text-brand-600 dark:text-brand-400'
                  : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-200'
              ]"
            >
              <component :is="tab.icon" class="h-4 w-4" />
              {{ tab.label }}
              <span
                v-if="tab.badge !== undefined"
                class="ml-1.5 rounded-full py-0.5 px-2 text-xs font-semibold"
                :class="activeTab === tab.id ? 'bg-brand-50 text-brand-600 dark:bg-brand-950/60 dark:text-brand-300' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400'"
              >
                {{ tab.badge }}
              </span>
            </button>
          </nav>
        </div>
      </div>

      <!-- ========================================== -->
      <!-- TAB 1: RINGKASAN (HUB) -->
      <!-- ========================================== -->
      <div v-if="activeTab === 'summary'" class="space-y-6">
        <!-- Stat Cards -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="flex items-center justify-between">
              <span class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total Anggota</span>
              <div class="h-9 w-9 rounded-lg bg-blue-50 dark:bg-blue-950/50 flex items-center justify-center text-blue-600">
                <UsersIcon class="h-5 w-5" />
              </div>
            </div>
            <p class="mt-3 text-2xl font-bold text-gray-900 dark:text-white">{{ stats.totalMembers }}</p>
            <p class="mt-1 text-xs text-gray-500">Terdaftar di organisasi ini</p>
          </div>

          <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="flex items-center justify-between">
              <span class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Anggota Aktif</span>
              <div class="h-9 w-9 rounded-lg bg-emerald-50 dark:bg-emerald-950/50 flex items-center justify-center text-emerald-600">
                <UserCheckIcon class="h-5 w-5" />
              </div>
            </div>
            <p class="mt-3 text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ stats.activeMembers }}</p>
            <p class="mt-1 text-xs text-gray-500">Status keanggotaan aktif</p>
          </div>

          <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="flex items-center justify-between">
              <span class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Pengurus Berjalan</span>
              <div class="h-9 w-9 rounded-lg bg-brand-50 dark:bg-brand-950/50 flex items-center justify-center text-brand-600">
                <ShieldCheckIcon class="h-5 w-5" />
              </div>
            </div>
            <p class="mt-3 text-2xl font-bold text-brand-600 dark:text-brand-400">{{ stats.effectiveCommittees }}</p>
            <p class="mt-1 text-xs text-gray-500">Fungsionaris efektif saat ini</p>
          </div>

          <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="flex items-center justify-between">
              <span class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total Periode</span>
              <div class="h-9 w-9 rounded-lg bg-purple-50 dark:bg-purple-950/50 flex items-center justify-center text-purple-600">
                <CalendarClockIcon class="h-5 w-5" />
              </div>
            </div>
            <p class="mt-3 text-2xl font-bold text-purple-600 dark:text-purple-400">{{ periods.length }}</p>
            <p class="mt-1 text-xs text-gray-500">Tercatat dalam riwayat</p>
          </div>
        </div>

        <!-- Ongoing Period Status Banner -->
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
              <CalendarIcon class="h-5 w-5 text-brand-500" />
              Status Periode & Kepengurusan Aktif
            </h3>
            <button
              @click="activeTab = 'periods'"
              class="text-xs font-semibold text-brand-600 hover:text-brand-700 dark:text-brand-400 flex items-center gap-1"
            >
              Lihat Semua Periode &rarr;
            </button>
          </div>

          <div v-if="ongoingPeriod" class="rounded-xl border border-emerald-200 bg-emerald-50/60 p-4 dark:border-emerald-900/40 dark:bg-emerald-950/20">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
              <div class="space-y-1">
                <div class="flex items-center gap-2">
                  <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-300">
                    Sedang Berjalan (Ongoing)
                  </span>
                  <h4 class="text-base font-bold text-emerald-950 dark:text-emerald-200">{{ ongoingPeriod.period_name }}</h4>
                </div>
                <p class="text-xs text-emerald-800 dark:text-emerald-300">
                  Masa Berlaku: <span class="font-semibold">{{ formatDate(ongoingPeriod.start_date) }}</span> s.d. <span class="font-semibold">{{ formatDate(ongoingPeriod.end_date) }}</span>
                </p>
              </div>
              <div class="flex items-center gap-3">
                <button
                  @click="activeTab = 'structure'"
                  class="rounded-lg bg-emerald-600 px-3.5 py-2 text-xs font-semibold text-white shadow-2xs hover:bg-emerald-700 transition"
                >
                  Kelola Struktur Periode Ini
                </button>
              </div>
            </div>
          </div>

          <div v-else class="rounded-xl border border-amber-200 bg-amber-50/70 p-4 dark:border-amber-900/40 dark:bg-amber-950/20">
            <div class="flex items-start gap-3">
              <AlertTriangleIcon class="h-5 w-5 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" />
              <div>
                <h4 class="text-sm font-bold text-amber-900 dark:text-amber-200">
                  Tidak ada periode kepengurusan yang sedang berjalan hari ini
                </h4>
                <p class="mt-1 text-xs text-amber-800 dark:text-amber-300">
                  Semua pengurus dari periode yang telah lewat otomatis diset kedaluwarsa dan tidak ditampilkan sebagai pengurus aktif di website publik. Anda dapat mengajukan atau mengaktifkan periode kepengurusan baru di tab Periode.
                </p>
                <div class="mt-3">
                  <router-link
                    to="/organization/period"
                    class="inline-flex items-center gap-1.5 text-xs font-bold text-amber-800 hover:text-amber-950 underline dark:text-amber-300"
                  >
                    Buka Halaman Manajemen Periode
                  </router-link>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Quick Hub Actions -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div
            @click="activeTab = 'members'"
            class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] hover:border-brand-500 cursor-pointer transition shadow-2xs group"
          >
            <div class="h-10 w-10 rounded-lg bg-blue-50 dark:bg-blue-950/50 flex items-center justify-center text-blue-600 group-hover:scale-105 transition">
              <UsersIcon class="h-5 w-5" />
            </div>
            <h4 class="mt-3 font-bold text-gray-900 dark:text-white">Database Anggota</h4>
            <p class="mt-1 text-xs text-gray-500">Kelola buku induk anggota organisasi, pencarian NIM, angkatan, dan penautan akun pengguna.</p>
          </div>

          <div
            @click="activeTab = 'structure'"
            class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] hover:border-brand-500 cursor-pointer transition shadow-2xs group"
          >
            <div class="h-10 w-10 rounded-lg bg-brand-50 dark:bg-brand-950/50 flex items-center justify-center text-brand-600 group-hover:scale-105 transition">
              <LayersIcon class="h-5 w-5" />
            </div>
            <h4 class="mt-3 font-bold text-gray-900 dark:text-white">Bagan Struktur Organisasi</h4>
            <p class="mt-1 text-xs text-gray-500">Susun struktur pengurus berdasarkan periode, validasi keunikan ketua, dan publikasi profil.</p>
          </div>

          <div
            @click="activeTab = 'import-export'"
            class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] hover:border-brand-500 cursor-pointer transition shadow-2xs group"
          >
            <div class="h-10 w-10 rounded-lg bg-emerald-50 dark:bg-emerald-950/50 flex items-center justify-center text-emerald-600 group-hover:scale-105 transition">
              <FileSpreadsheetIcon class="h-5 w-5" />
            </div>
            <h4 class="mt-3 font-bold text-gray-900 dark:text-white">Impor & Ekspor Data</h4>
            <p class="mt-1 text-xs text-gray-500">Impor massal data anggota dengan mode preview validasi baris per baris atau unduh file CSV.</p>
          </div>
        </div>
      </div>

      <!-- ========================================== -->
      <!-- TAB 2: DATA ANGGOTA -->
      <!-- ========================================== -->
      <div v-if="activeTab === 'members'" class="space-y-4">
        <!-- Filter Bar -->
        <div class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03] shadow-2xs">
          <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3">
            <div class="relative">
              <SearchIcon class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
              <input
                v-model="memberFilters.search"
                @input="debounceFetchMembers"
                type="text"
                placeholder="Cari NIM atau nama..."
                class="w-full rounded-lg border border-gray-300 bg-white pl-9 pr-3 py-2 text-xs text-gray-900 focus:border-brand-500 focus:outline-hidden dark:border-gray-700 dark:bg-gray-800 dark:text-white"
              />
            </div>

            <div>
              <select
                v-model="memberFilters.status"
                @change="fetchMembers"
                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs text-gray-900 focus:border-brand-500 focus:outline-hidden dark:border-gray-700 dark:bg-gray-800 dark:text-white"
              >
                <option value="">Semua Status Keanggotaan</option>
                <option value="active">Aktif</option>
                <option value="inactive">Nonaktif</option>
                <option value="alumni">Alumni</option>
              </select>
            </div>

            <div>
              <input
                v-model="memberFilters.jurusan"
                @input="debounceFetchMembers"
                type="text"
                placeholder="Filter jurusan..."
                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs text-gray-900 focus:border-brand-500 focus:outline-hidden dark:border-gray-700 dark:bg-gray-800 dark:text-white"
              />
            </div>

            <div>
              <input
                v-model="memberFilters.angkatan"
                @input="debounceFetchMembers"
                type="text"
                placeholder="Filter angkatan (misal: 2024)..."
                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs text-gray-900 focus:border-brand-500 focus:outline-hidden dark:border-gray-700 dark:bg-gray-800 dark:text-white"
              />
            </div>
          </div>
        </div>

        <!-- Members Table -->
        <div class="rounded-2xl border border-gray-200 bg-white overflow-hidden dark:border-gray-800 dark:bg-white/[0.03] shadow-2xs">
          <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
              <thead class="bg-gray-50/80 border-b border-gray-200 dark:bg-gray-900/50 dark:border-gray-800">
                <tr>
                  <th class="py-3 px-4 font-semibold text-gray-700 dark:text-gray-300">NIM & Mahasiswa</th>
                  <th class="py-3 px-4 font-semibold text-gray-700 dark:text-gray-300">Program Studi</th>
                  <th class="py-3 px-4 font-semibold text-gray-700 dark:text-gray-300">Kontak</th>
                  <th class="py-3 px-4 font-semibold text-gray-700 dark:text-gray-300">Status Anggota</th>
                  <th class="py-3 px-4 font-semibold text-gray-700 dark:text-gray-300">Akun Sistem</th>
                  <th class="py-3 px-4 font-semibold text-gray-700 dark:text-gray-300 text-right">Aksi</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100 dark:divide-gray-800/60">
                <tr v-if="isLoadingMembers">
                  <td colspan="6" class="py-8 text-center text-gray-500">Memuat data anggota...</td>
                </tr>
                <tr v-else-if="membersList.length === 0">
                  <td colspan="6" class="py-12 text-center text-gray-500">
                    <UsersIcon class="h-8 w-8 mx-auto text-gray-300 mb-2" />
                    Belum ada data anggota yang cocok dengan filter.
                  </td>
                </tr>
                <tr v-for="m in membersList" :key="m.id" class="hover:bg-gray-50/60 dark:hover:bg-gray-800/30 transition">
                  <td class="py-3 px-4">
                    <div class="font-bold text-gray-900 dark:text-white">{{ m.nama }}</div>
                    <div class="text-[11px] font-mono text-gray-500">NIM: {{ m.nim }}</div>
                  </td>
                  <td class="py-3 px-4">
                    <div class="text-gray-800 dark:text-gray-200">{{ m.jurusan || '-' }}</div>
                    <div class="text-[11px] text-gray-400">Angkatan: {{ m.angkatan || '-' }}</div>
                  </td>
                  <td class="py-3 px-4">
                    <div class="text-gray-700 dark:text-gray-300">{{ m.email || '-' }}</div>
                    <div class="text-[11px] text-gray-400">{{ m.telepon || '-' }}</div>
                  </td>
                  <td class="py-3 px-4">
                    <span
                      class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold"
                      :class="[
                        m.status === 'active' ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-600/20' :
                        m.status === 'alumni' ? 'bg-blue-50 text-blue-700 ring-1 ring-blue-600/20' :
                        'bg-gray-100 text-gray-700 ring-1 ring-gray-400/20'
                      ]"
                    >
                      {{ m.status === 'active' ? 'Aktif' : (m.status === 'alumni' ? 'Alumni' : 'Nonaktif') }}
                    </span>
                  </td>
                  <td class="py-3 px-4">
                    <div v-if="m.user" class="inline-flex items-center gap-1.5 text-xs text-brand-600 dark:text-brand-400">
                      <UserCircleIcon class="h-4 w-4" />
                      <span class="truncate max-w-[120px]">{{ m.user.name }}</span>
                    </div>
                    <span v-else class="text-[11px] text-gray-400 italic">Tidak ditautkan</span>
                  </td>
                  <td class="py-3 px-4 text-right space-x-1">
                    <button
                      v-if="canManage"
                      @click="openEditMemberModal(m)"
                      class="p-1 text-gray-500 hover:text-brand-600 rounded hover:bg-gray-100 dark:hover:bg-gray-800 transition"
                      title="Edit Anggota"
                    >
                      <PencilIcon class="h-4 w-4" />
                    </button>
                    <button
                      v-if="canManage"
                      @click="deleteMember(m)"
                      class="p-1 text-gray-500 hover:text-rose-600 rounded hover:bg-rose-50 dark:hover:bg-rose-950/30 transition"
                      title="Hapus Anggota"
                    >
                      <Trash2Icon class="h-4 w-4" />
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Table Footer Pagination -->
          <div class="p-4 border-t border-gray-100 dark:border-gray-800 flex items-center justify-between text-xs text-gray-500">
            <span>Total: {{ memberPagination.total }} anggota</span>
            <div class="flex items-center gap-2">
              <button
                @click="changeMemberPage(memberPagination.page - 1)"
                :disabled="memberPagination.page <= 1"
                class="px-2.5 py-1 rounded border border-gray-200 disabled:opacity-40"
              >
                Sebelumnya
              </button>
              <span>Hal. {{ memberPagination.page }} dari {{ memberPagination.last_page }}</span>
              <button
                @click="changeMemberPage(memberPagination.page + 1)"
                :disabled="memberPagination.page >= memberPagination.last_page"
                class="px-2.5 py-1 rounded border border-gray-200 disabled:opacity-40"
              >
                Selanjutnya
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- ========================================== -->
      <!-- TAB 3: STRUKTUR ORGANISASI -->
      <!-- ========================================== -->
      <div v-if="activeTab === 'structure'" class="space-y-4">
        <!-- Period Selector & Status -->
        <div class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03] shadow-2xs flex flex-col md:flex-row md:items-center justify-between gap-4">
          <div class="flex flex-col sm:flex-row sm:items-center gap-3">
            <label class="text-xs font-semibold text-gray-700 dark:text-gray-300">Pilih Periode Struktur:</label>
            <select
              v-model="selectedStructurePeriodId"
              @change="fetchCommittees"
              class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs text-gray-900 focus:border-brand-500 focus:outline-hidden dark:border-gray-700 dark:bg-gray-800 dark:text-white"
            >
              <option v-for="p in periods" :key="p.id" :value="p.id">
                {{ p.period_name }} — {{ p.calculated_status === 'ongoing' ? 'Sedang Berjalan' : (p.calculated_status === 'expired' ? 'Kedaluwarsa' : 'Mendatang') }}
              </option>
            </select>
          </div>

          <div v-if="selectedPeriodObj" class="flex items-center gap-2">
            <span
              class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold"
              :class="[
                selectedPeriodObj.calculated_status === 'ongoing' ? 'bg-emerald-100 text-emerald-800' :
                selectedPeriodObj.calculated_status === 'expired' ? 'bg-amber-100 text-amber-800' :
                'bg-blue-100 text-blue-800'
              ]"
            >
              {{ selectedPeriodObj.calculated_status === 'ongoing' ? 'Sedang Berjalan (Publik Aktif)' : (selectedPeriodObj.calculated_status === 'expired' ? 'Periode Telah Berakhir (Arsip)' : 'Periode Mendatang') }}
            </span>
          </div>
        </div>

        <!-- Warning if Expired Period -->
        <div
          v-if="selectedPeriodObj && selectedPeriodObj.calculated_status === 'expired'"
          class="rounded-xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-900/40 dark:bg-amber-950/20 text-xs text-amber-900 dark:text-amber-300"
        >
          <div class="flex items-center gap-2 font-bold mb-1">
            <AlertTriangleIcon class="h-4 w-4 text-amber-600" />
            Periode ini telah kedaluwarsa berdasarkan tanggal berakhirnya ({{ formatDate(selectedPeriodObj.end_date) }}).
          </div>
          Pengurus dalam periode ini tersimpan sebagai riwayat arsip dan tidak ditampilkan sebagai pengurus aktif di struktur website publik.
        </div>

        <!-- Committees Table -->
        <div class="rounded-2xl border border-gray-200 bg-white overflow-hidden dark:border-gray-800 dark:bg-white/[0.03] shadow-2xs">
          <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
              <thead class="bg-gray-50/80 border-b border-gray-200 dark:bg-gray-900/50 dark:border-gray-800">
                <tr>
                  <th class="py-3 px-4 font-semibold text-gray-700 dark:text-gray-300">Pengurus</th>
                  <th class="py-3 px-4 font-semibold text-gray-700 dark:text-gray-300">Jabatan & Departemen</th>
                  <th class="py-3 px-4 font-semibold text-gray-700 dark:text-gray-300">Anggota Terkait</th>
                  <th class="py-3 px-4 font-semibold text-gray-700 dark:text-gray-300">Mode & Penempatan</th>
                  <th class="py-3 px-4 font-semibold text-gray-700 dark:text-gray-300">Status Efektif</th>
                  <th class="py-3 px-4 font-semibold text-gray-700 dark:text-gray-300 text-right">Aksi</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100 dark:divide-gray-800/60">
                <tr v-if="isLoadingCommittees">
                  <td colspan="6" class="py-8 text-center text-gray-500">Memuat susunan pengurus...</td>
                </tr>
                <tr v-else-if="committeesList.length === 0">
                  <td colspan="6" class="py-12 text-center text-gray-500">
                    Belum ada pengurus yang ditambahkan pada periode ini.
                  </td>
                </tr>
                <tr v-for="c in committeesList" :key="c.id" class="hover:bg-gray-50/60 dark:hover:bg-gray-800/30 transition">
                  <td class="py-3 px-4">
                    <div class="flex items-center gap-3">
                      <div class="h-9 w-9 rounded-full bg-gray-100 border border-gray-200 overflow-hidden flex items-center justify-center shrink-0">
                        <img v-if="c.photo_url || c.photo" :src="c.photo_url || c.photo || undefined" class="h-full w-full object-cover" />
                        <span v-else class="font-bold text-gray-500">{{ c.name.charAt(0) }}</span>
                      </div>
                      <div>
                        <div class="font-bold text-gray-900 dark:text-white">{{ c.name }}</div>
                        <div class="text-[11px] text-gray-400">{{ c.period_name || c.period }}</div>
                      </div>
                    </div>
                  </td>
                  <td class="py-3 px-4">
                    <div class="font-semibold text-brand-600 dark:text-brand-400">{{ c.position }}</div>
                    <div class="text-[11px] text-gray-500">{{ c.department || 'Pengurus Inti' }}</div>
                  </td>
                  <td class="py-3 px-4">
                    <div v-if="c.member" class="text-gray-800 dark:text-gray-200">
                      <div class="font-medium">{{ c.member.nama }}</div>
                      <div class="text-[11px] font-mono text-gray-400 flex items-center gap-1.5 mt-0.5">
                        <span>NIM: {{ c.member.nim }}</span>
                        <span
                          class="px-1.5 py-0.2 rounded-full text-[9px] font-semibold"
                          :class="c.member.status === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-600'"
                        >
                          {{ c.member.status === 'active' ? 'Anggota Aktif' : 'Anggota Nonaktif' }}
                        </span>
                      </div>
                    </div>
                    <span v-else class="text-[11px] text-gray-400 italic">Bukan anggota tetap</span>
                  </td>
                  <td class="py-3 px-4">
                    <div class="flex items-center gap-1.5 flex-wrap">
                      <span
                        class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold"
                        :class="c.status_mode === 'manual' ? 'bg-indigo-50 text-indigo-700 ring-1 ring-indigo-600/20 dark:bg-indigo-950/40 dark:text-indigo-300' : 'bg-slate-100 text-slate-700 ring-1 ring-slate-400/20 dark:bg-slate-800 dark:text-slate-300'"
                      >
                        {{ c.status_mode === 'manual' ? 'Mode Manual' : 'Mode Otomatis' }}
                      </span>
                      <span
                        v-if="c.status_mode === 'manual'"
                        class="inline-flex items-center px-1.5 py-0.2 rounded text-[10px] font-bold uppercase"
                        :class="c.status === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'"
                      >
                        {{ c.status === 'active' ? 'Active' : 'Inactive' }}
                      </span>
                    </div>
                    <div v-if="c.status_mode === 'manual' && c.status_reason" class="text-[10px] text-gray-500 italic mt-1 truncate max-w-[160px]" :title="c.status_reason">
                      "{{ c.status_reason }}"
                    </div>
                    <div v-if="c.status_changed_by_user" class="text-[10px] text-gray-400 mt-0.5">
                      Oleh: {{ c.status_changed_by_user.name }}
                    </div>
                  </td>
                  <td class="py-3 px-4">
                    <span
                      class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold"
                      :class="c.is_active_effective ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-600/20' : 'bg-gray-100 text-gray-600 ring-1 ring-gray-400/20'"
                    >
                      {{ c.is_active_effective ? 'Aktif Efektif' : 'Nonaktif / Kedaluwarsa' }}
                    </span>
                  </td>
                  <td class="py-3 px-4 text-right space-x-1">
                    <button
                      v-if="canManage"
                      @click="openQuickStatusModal(c)"
                      class="p-1 text-gray-500 hover:text-indigo-600 rounded hover:bg-indigo-50 dark:hover:bg-indigo-950/30 transition"
                      title="Ubah Mode / Status Penempatan"
                    >
                      <SlidersHorizontalIcon class="h-4 w-4" />
                    </button>
                    <button
                      v-if="canManage"
                      @click="openEditCommitteeModal(c)"
                      class="p-1 text-gray-500 hover:text-brand-600 rounded hover:bg-gray-100 dark:hover:bg-gray-800 transition"
                      title="Edit Pengurus"
                    >
                      <PencilIcon class="h-4 w-4" />
                    </button>
                    <button
                      v-if="canManage"
                      @click="deleteCommittee(c)"
                      class="p-1 text-gray-500 hover:text-rose-600 rounded hover:bg-rose-50 dark:hover:bg-rose-950/30 transition"
                      title="Hapus Pengurus"
                    >
                      <Trash2Icon class="h-4 w-4" />
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- ========================================== -->
      <!-- TAB 4: PERIODE KEPENGURUSAN -->
      <!-- ========================================== -->
      <div v-if="activeTab === 'periods'" class="space-y-4">
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] shadow-2xs flex items-center justify-between">
          <div>
            <h3 class="font-bold text-gray-900 dark:text-white">Daftar Periode Kepengurusan</h3>
            <p class="text-xs text-gray-500">Status periode dihitung secara otomatis setiap hari berdasarkan rentang tanggal kalender.</p>
          </div>
          <router-link
            to="/organization/period"
            class="rounded-lg bg-brand-500 px-3.5 py-2 text-xs font-semibold text-white shadow-2xs hover:bg-brand-600 transition"
          >
            Buka Modul Pengajuan Periode &rarr;
          </router-link>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          <div
            v-for="p in periods"
            :key="p.id"
            class="rounded-2xl border p-5 bg-white dark:bg-white/[0.03] space-y-3 shadow-2xs"
            :class="[
              p.calculated_status === 'ongoing' ? 'border-emerald-300 ring-2 ring-emerald-500/10' : 'border-gray-200 dark:border-gray-800'
            ]"
          >
            <div class="flex items-center justify-between">
              <span
                class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold uppercase tracking-wider"
                :class="[
                  p.calculated_status === 'ongoing' ? 'bg-emerald-100 text-emerald-800' :
                  p.calculated_status === 'expired' ? 'bg-gray-100 text-gray-600' :
                  'bg-blue-100 text-blue-800'
                ]"
              >
                {{ p.calculated_status === 'ongoing' ? 'Sedang Berjalan' : (p.calculated_status === 'expired' ? 'Kedaluwarsa' : 'Mendatang') }}
              </span>
              <span class="text-xs text-gray-400">ID: #{{ p.id }}</span>
            </div>

            <h4 class="text-base font-bold text-gray-900 dark:text-white">{{ p.period_name }}</h4>
            <div class="text-xs text-gray-600 dark:text-gray-300 space-y-1">
              <div>Mulai: <span class="font-medium">{{ formatDate(p.start_date) }}</span></div>
              <div>Berakhir: <span class="font-medium">{{ formatDate(p.end_date) }}</span></div>
            </div>

            <div class="pt-3 border-t border-gray-100 dark:border-gray-800 flex items-center justify-between">
              <button
                @click="viewPeriodStructure(p.id)"
                class="text-xs font-semibold text-brand-600 hover:text-brand-700 dark:text-brand-400"
              >
                Lihat Susunan Pengurus &rarr;
              </button>
              <button
                v-if="canManage"
                @click="openEditPeriodModal(p)"
                class="inline-flex items-center gap-1 text-xs font-semibold text-gray-600 hover:text-brand-600 dark:text-gray-400 dark:hover:text-brand-400 transition"
              >
                <PencilIcon class="h-3.5 w-3.5" />
                <span>Edit Periode</span>
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- ========================================== -->
      <!-- TAB 5: RIWAYAT KEPENGURUSAN (ARSIP) -->
      <!-- ========================================== -->
      <div v-if="activeTab === 'history'" class="space-y-4">
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] shadow-2xs">
          <h3 class="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
            <ArchiveIcon class="h-5 w-5 text-brand-500" />
            Arsip Kepengurusan Lampau
          </h3>
          <p class="mt-1 text-xs text-gray-500">
            Riwayat susunan pengurus dari tahun-tahun sebelumnya tetap tersimpan utuh dan tidak terhapus saat terjadi pergantian periode.
          </p>
        </div>

        <div v-if="expiredPeriods.length === 0" class="py-12 text-center text-gray-500 border border-dashed rounded-2xl bg-white dark:bg-white/[0.02]">
          Belum ada arsip periode terdahulu.
        </div>

        <div v-else class="space-y-4">
          <div
            v-for="ep in expiredPeriods"
            :key="ep.id"
            class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] shadow-2xs"
          >
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-gray-100 dark:border-gray-800 pb-3">
              <div>
                <h4 class="font-bold text-gray-900 dark:text-white">{{ ep.period_name }}</h4>
                <p class="text-xs text-gray-500">Masa jabatan: {{ formatDate(ep.start_date) }} s.d. {{ formatDate(ep.end_date) }}</p>
              </div>
              <button
                @click="viewPeriodStructure(ep.id)"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-gray-200 text-xs font-semibold text-gray-700 hover:bg-gray-50 transition"
              >
                Buka Struktur Periode Ini
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- ========================================== -->
      <!-- TAB 6: IMPORT & EXPORT -->
      <!-- ========================================== -->
      <div v-if="activeTab === 'import-export'" class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <!-- Card Ekspor -->
          <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] space-y-4 shadow-2xs">
            <div class="flex items-center gap-3">
              <div class="h-10 w-10 rounded-lg bg-emerald-50 dark:bg-emerald-950/50 flex items-center justify-center text-emerald-600">
                <DownloadIcon class="h-5 w-5" />
              </div>
              <div>
                <h3 class="font-bold text-gray-900 dark:text-white">Ekspor Data Anggota</h3>
                <p class="text-xs text-gray-500">Unduh data anggota organisasi saat ini ke dalam berkas CSV.</p>
              </div>
            </div>

            <p class="text-xs text-gray-600 dark:text-gray-400">
              Format ekspor mencakup NIM, Nama Lengkap, Email, Telepon, Program Studi, Angkatan, dan Status Keanggotaan.
            </p>

            <button
              @click="exportMembersCsv"
              class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2.5 text-xs font-semibold text-white shadow-2xs hover:bg-emerald-700 transition"
            >
              <DownloadIcon class="h-4 w-4" />
              <span>Unduh CSV Data Anggota</span>
            </button>
          </div>

          <!-- Card Template -->
          <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] space-y-4 shadow-2xs">
            <div class="flex items-center gap-3">
              <div class="h-10 w-10 rounded-lg bg-blue-50 dark:bg-blue-950/50 flex items-center justify-center text-blue-600">
                <FileSpreadsheetIcon class="h-5 w-5" />
              </div>
              <div>
                <h3 class="font-bold text-gray-900 dark:text-white">Template Impor CSV</h3>
                <p class="text-xs text-gray-500">Gunakan format kolom resmi untuk menghindari kesalahan validasi.</p>
              </div>
            </div>

            <p class="text-xs text-gray-600 dark:text-gray-400">
              Header wajib: <code class="bg-gray-100 px-1 py-0.5 rounded text-gray-800">nim, nama, email, telepon, jurusan, angkatan, status, catatan</code>
            </p>

            <button
              @click="downloadTemplateCsv"
              class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-xs font-semibold text-gray-700 shadow-2xs hover:bg-gray-50 transition"
            >
              <DownloadIcon class="h-4 w-4" />
              <span>Unduh Template CSV</span>
            </button>
          </div>
        </div>

        <!-- Section Impor CSV dengan Preview Dua Tahap -->
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] space-y-4 shadow-2xs">
          <div class="flex items-center gap-3">
            <div class="h-10 w-10 rounded-lg bg-purple-50 dark:bg-purple-950/50 flex items-center justify-center text-purple-600">
              <UploadCloudIcon class="h-5 w-5" />
            </div>
            <div>
              <h3 class="font-bold text-gray-900 dark:text-white">Impor Anggota Massal (Dua Tahap: Validasi & Eksekusi)</h3>
              <p class="text-xs text-gray-500">
                Data akan diverifikasi terlebih dahulu sebelum benar-benar dimasukkan ke database.
              </p>
            </div>
          </div>

          <!-- File Picker -->
          <div class="border-2 border-dashed border-gray-300 rounded-xl p-6 text-center dark:border-gray-700">
            <input
              type="file"
              ref="fileInputRef"
              accept=".csv,text/csv"
              @change="onFileSelected"
              class="hidden"
              id="csv-file-input"
            />
            <label for="csv-file-input" class="cursor-pointer">
              <UploadCloudIcon class="h-10 w-10 mx-auto text-gray-400 mb-2" />
              <div class="text-xs font-semibold text-gray-800 dark:text-gray-200">
                {{ selectedFile ? selectedFile.name : 'Pilih file CSV untuk diimpor' }}
              </div>
              <p class="text-[11px] text-gray-400 mt-1">Maksimal 5MB, format .csv</p>
            </label>
          </div>

          <div v-if="selectedFile" class="flex items-center gap-3">
            <button
              @click="runImportPreview"
              :disabled="isImporting"
              class="inline-flex items-center gap-2 rounded-lg bg-brand-500 px-4 py-2 text-xs font-semibold text-white shadow-2xs hover:bg-brand-600 disabled:opacity-50 transition"
            >
              <EyeIcon class="h-4 w-4" />
              <span>{{ isImporting ? 'Memvalidasi...' : 'Tahap 1: Cek & Validasi Baris (Preview)' }}</span>
            </button>

            <button
              v-if="importPreviewResult && importPreviewResult.errors.length === 0 && importPreviewResult.success_count > 0"
              @click="runImportCommit"
              :disabled="isImporting"
              class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-xs font-semibold text-white shadow-2xs hover:bg-emerald-700 disabled:opacity-50 transition"
            >
              <CheckCircleIcon class="h-4 w-4" />
              <span>Tahap 2: Proses & Simpan ke Database ({{ importPreviewResult.success_count }} data)</span>
            </button>
          </div>

          <!-- Preview & Errors Table -->
          <div v-if="importPreviewResult" class="space-y-4 pt-4 border-t border-gray-100 dark:border-gray-800">
            <div class="flex items-center justify-between text-xs font-semibold">
              <span class="text-gray-700 dark:text-gray-300">Hasil Analisis Berkas:</span>
              <div class="flex items-center gap-3">
                <span class="text-emerald-600">Valid: {{ importPreviewResult.success_count }} baris</span>
                <span v-if="importPreviewResult.error_count > 0" class="text-rose-600">Error: {{ importPreviewResult.error_count }} baris</span>
              </div>
            </div>

            <!-- Error Listing if any -->
            <div v-if="importPreviewResult.errors.length > 0" class="rounded-xl border border-rose-200 bg-rose-50/70 p-4 dark:border-rose-900/50 dark:bg-rose-950/20 text-xs">
              <h5 class="font-bold text-rose-800 dark:text-rose-300 mb-2">Terdapat Kesalahan pada Berkas CSV:</h5>
              <ul class="list-disc list-inside space-y-1 text-rose-700 dark:text-rose-400">
                <li v-for="err in importPreviewResult.errors" :key="err.row">
                  Baris {{ err.row }}: {{ err.message }} (NIM: {{ err.nim || '-' }})
                </li>
              </ul>
            </div>

            <!-- Preview Data Table -->
            <div class="rounded-xl border border-gray-200 overflow-hidden">
              <table class="w-full text-left text-[11px]">
                <thead class="bg-gray-50 border-b">
                  <tr>
                    <th class="py-2 px-3">Baris</th>
                    <th class="py-2 px-3">NIM</th>
                    <th class="py-2 px-3">Nama</th>
                    <th class="py-2 px-3">Jurusan</th>
                    <th class="py-2 px-3">Angkatan</th>
                    <th class="py-2 px-3">Status</th>
                    <th class="py-2 px-3">Aksi Terdeteksi</th>
                  </tr>
                </thead>
                <tbody class="divide-y">
                  <tr v-for="row in importPreviewResult.preview" :key="row.row">
                    <td class="py-2 px-3 font-mono">{{ row.row }}</td>
                    <td class="py-2 px-3 font-bold">{{ row.nim }}</td>
                    <td class="py-2 px-3">{{ row.nama }}</td>
                    <td class="py-2 px-3">{{ row.jurusan || '-' }}</td>
                    <td class="py-2 px-3">{{ row.angkatan || '-' }}</td>
                    <td class="py-2 px-3">{{ row.status }}</td>
                    <td class="py-2 px-3">
                      <span class="inline-flex px-1.5 py-0.5 rounded text-[10px] font-semibold uppercase" :class="row.action === 'update' ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800'">
                        {{ row.action === 'update' ? 'Perbarui' : 'Tambah Baru' }}
                      </span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ========================================== -->
    <!-- MODAL TAMBAH / EDIT ANGGOTA -->
    <!-- ========================================== -->
    <div
      v-if="isMemberModalOpen"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
    >
      <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl dark:bg-gray-900 border border-gray-200 dark:border-gray-800">
        <div class="flex items-center justify-between border-b pb-3 mb-4">
          <h3 class="font-bold text-gray-900 dark:text-white">
            {{ editingMemberId ? 'Edit Data Anggota' : 'Tambah Anggota Baru' }}
          </h3>
          <button @click="isMemberModalOpen = false" class="text-gray-400 hover:text-gray-600">
            <XIcon class="h-5 w-5" />
          </button>
        </div>

        <form @submit.prevent="saveMember" class="space-y-3.5 text-xs">
          <div>
            <label class="block font-semibold text-gray-700 dark:text-gray-300 mb-1">NIM (Nomor Induk Mahasiswa) *</label>
            <input
              v-model="memberForm.nim"
              required
              type="text"
              class="w-full rounded-lg border border-gray-300 p-2 focus:border-brand-500 focus:outline-hidden dark:bg-gray-800 dark:border-gray-700 dark:text-white"
            />
          </div>

          <div>
            <label class="block font-semibold text-gray-700 dark:text-gray-300 mb-1">Nama Lengkap *</label>
            <input
              v-model="memberForm.nama"
              required
              type="text"
              class="w-full rounded-lg border border-gray-300 p-2 focus:border-brand-500 focus:outline-hidden dark:bg-gray-800 dark:border-gray-700 dark:text-white"
            />
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block font-semibold text-gray-700 dark:text-gray-300 mb-1">Program Studi / Jurusan</label>
              <input
                v-model="memberForm.jurusan"
                type="text"
                class="w-full rounded-lg border border-gray-300 p-2 focus:border-brand-500 focus:outline-hidden dark:bg-gray-800 dark:border-gray-700 dark:text-white"
              />
            </div>
            <div>
              <label class="block font-semibold text-gray-700 dark:text-gray-300 mb-1">Angkatan</label>
              <input
                v-model="memberForm.angkatan"
                type="text"
                placeholder="misal: 2024"
                class="w-full rounded-lg border border-gray-300 p-2 focus:border-brand-500 focus:outline-hidden dark:bg-gray-800 dark:border-gray-700 dark:text-white"
              />
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block font-semibold text-gray-700 dark:text-gray-300 mb-1">Email</label>
              <input
                v-model="memberForm.email"
                type="email"
                class="w-full rounded-lg border border-gray-300 p-2 focus:border-brand-500 focus:outline-hidden dark:bg-gray-800 dark:border-gray-700 dark:text-white"
              />
            </div>
            <div>
              <label class="block font-semibold text-gray-700 dark:text-gray-300 mb-1">Nomor Telepon</label>
              <input
                v-model="memberForm.telepon"
                type="text"
                class="w-full rounded-lg border border-gray-300 p-2 focus:border-brand-500 focus:outline-hidden dark:bg-gray-800 dark:border-gray-700 dark:text-white"
              />
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block font-semibold text-gray-700 dark:text-gray-300 mb-1">Status Keanggotaan</label>
              <select
                v-model="memberForm.status"
                class="w-full rounded-lg border border-gray-300 p-2 focus:border-brand-500 focus:outline-hidden dark:bg-gray-800 dark:border-gray-700 dark:text-white"
              >
                <option value="active">Aktif</option>
                <option value="inactive">Nonaktif</option>
                <option value="alumni">Alumni</option>
              </select>
            </div>
            <div>
              <label class="block font-semibold text-gray-700 dark:text-gray-300 mb-1">Tautkan Akun Sistem (User)</label>
              <select
                v-model="memberForm.user_id"
                class="w-full rounded-lg border border-gray-300 p-2 focus:border-brand-500 focus:outline-hidden dark:bg-gray-800 dark:border-gray-700 dark:text-white"
              >
                <option :value="null">-- Tidak Ditautkan --</option>
                <option v-for="u in linkableUsersList" :key="u.id" :value="u.id">
                  {{ u.name }} ({{ u.email }})
                </option>
              </select>
            </div>
          </div>

          <div>
            <label class="block font-semibold text-gray-700 dark:text-gray-300 mb-1">Catatan</label>
            <textarea
              v-model="memberForm.catatan"
              rows="2"
              class="w-full rounded-lg border border-gray-300 p-2 focus:border-brand-500 focus:outline-hidden dark:bg-gray-800 dark:border-gray-700 dark:text-white"
            ></textarea>
          </div>

          <div class="flex items-center justify-end gap-2 pt-3 border-t">
            <button
              type="button"
              @click="isMemberModalOpen = false"
              class="px-3.5 py-2 rounded-lg border text-gray-600 hover:bg-gray-50"
            >
              Batal
            </button>
            <button
              type="submit"
              :disabled="isSubmitting"
              class="px-4 py-2 rounded-lg bg-brand-500 text-white font-semibold hover:bg-brand-600 disabled:opacity-50"
            >
              {{ isSubmitting ? 'Menyimpan...' : 'Simpan Anggota' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- ========================================== -->
    <!-- MODAL TAMBAH / EDIT PENGURUS -->
    <!-- ========================================== -->
    <div
      v-if="isCommitteeModalOpen"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
    >
      <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl dark:bg-gray-900 border border-gray-200 dark:border-gray-800 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between border-b pb-3 mb-4">
          <h3 class="font-bold text-gray-900 dark:text-white">
            {{ editingCommitteeId ? 'Edit Data Pengurus' : 'Tambah Pengurus Baru' }}
          </h3>
          <button @click="isCommitteeModalOpen = false" class="text-gray-400 hover:text-gray-600">
            <XIcon class="h-5 w-5" />
          </button>
        </div>

        <form @submit.prevent="saveCommittee" class="space-y-3.5 text-xs">
          <!-- Pilih Anggota Terdaftar -->
          <div>
            <label class="block font-semibold text-gray-700 dark:text-gray-300 mb-1">Pilih Anggota Organisasi *</label>
            <select
              v-model="committeeForm.member_id"
              @change="onCommitteeMemberSelected"
              class="w-full rounded-lg border border-gray-300 p-2 focus:border-brand-500 focus:outline-hidden dark:bg-gray-800 dark:border-gray-700 dark:text-white"
            >
              <option :value="null">-- Input Nama Manual / Bukan Anggota Tetap --</option>
              <option v-for="m in linkableMembersList" :key="m.id" :value="m.id">
                {{ m.nama }} (NIM: {{ m.nim }}) - {{ m.status === 'active' ? 'Aktif' : 'Nonaktif' }}
              </option>
            </select>
          </div>

          <div>
            <label class="block font-semibold text-gray-700 dark:text-gray-300 mb-1">Nama Pengurus *</label>
            <input
              v-model="committeeForm.name"
              required
              type="text"
              class="w-full rounded-lg border border-gray-300 p-2 focus:border-brand-500 focus:outline-hidden dark:bg-gray-800 dark:border-gray-700 dark:text-white"
            />
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block font-semibold text-gray-700 dark:text-gray-300 mb-1">Jabatan *</label>
              <input
                v-model="committeeForm.position"
                required
                type="text"
                placeholder="misal: Ketua, Sekretaris"
                class="w-full rounded-lg border border-gray-300 p-2 focus:border-brand-500 focus:outline-hidden dark:bg-gray-800 dark:border-gray-700 dark:text-white"
              />
            </div>
            <div>
              <label class="block font-semibold text-gray-700 dark:text-gray-300 mb-1">Departemen / Bidang</label>
              <input
                v-model="committeeForm.department"
                type="text"
                placeholder="misal: BPH, Humas"
                class="w-full rounded-lg border border-gray-300 p-2 focus:border-brand-500 focus:outline-hidden dark:bg-gray-800 dark:border-gray-700 dark:text-white"
              />
            </div>
          </div>

          <div>
            <label class="block font-semibold text-gray-700 dark:text-gray-300 mb-1">Periode Kepengurusan *</label>
            <select
              v-model="committeeForm.organization_period_id"
              required
              class="w-full rounded-lg border border-gray-300 p-2 focus:border-brand-500 focus:outline-hidden dark:bg-gray-800 dark:border-gray-700 dark:text-white"
            >
              <option v-for="p in periods" :key="p.id" :value="p.id">
                {{ p.period_name }}
              </option>
            </select>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block font-semibold text-gray-700 dark:text-gray-300 mb-1">Tanggal Mulai Jabatan</label>
              <input
                v-model="committeeForm.start_date"
                type="date"
                class="w-full rounded-lg border border-gray-300 p-2 focus:border-brand-500 focus:outline-hidden dark:bg-gray-800 dark:border-gray-700 dark:text-white"
              />
            </div>
            <div>
              <label class="block font-semibold text-gray-700 dark:text-gray-300 mb-1">Tanggal Berakhir Jabatan</label>
              <input
                v-model="committeeForm.end_date"
                type="date"
                class="w-full rounded-lg border border-gray-300 p-2 focus:border-brand-500 focus:outline-hidden dark:bg-gray-800 dark:border-gray-700 dark:text-white"
              />
            </div>
          </div>

          <div>
            <label class="block font-semibold text-gray-700 dark:text-gray-300 mb-1">Mode Evaluasi Status</label>
            <select
              v-model="committeeForm.status_mode"
              class="w-full rounded-lg border border-gray-300 p-2 focus:border-brand-500 focus:outline-hidden dark:bg-gray-800 dark:border-gray-700 dark:text-white"
            >
              <option value="auto">Mode Otomatis (Evaluasi dinamis tanggal periode & keaktifan anggota)</option>
              <option value="manual">Mode Manual (Ditetapkan eksplisit oleh pengurus / administrator)</option>
            </select>
          </div>

          <div v-if="committeeForm.status_mode === 'manual'" class="grid grid-cols-2 gap-3 p-3 rounded-xl bg-amber-50/70 border border-amber-200 dark:bg-amber-950/20 dark:border-amber-900/40">
            <div>
              <label class="block font-semibold text-amber-950 dark:text-amber-200 mb-1">Status Penempatan *</label>
              <select
                v-model="committeeForm.status"
                class="w-full rounded-lg border border-gray-300 p-2 focus:border-brand-500 focus:outline-hidden dark:bg-gray-800 dark:border-gray-700 dark:text-white"
              >
                <option value="active">Active (Aktif)</option>
                <option value="inactive">Inactive (Nonaktif)</option>
              </select>
            </div>
            <div>
              <label class="block font-semibold text-amber-950 dark:text-amber-200 mb-1">Alasan Perubahan *</label>
              <input
                v-model="committeeForm.status_reason"
                :required="committeeForm.status_mode === 'manual'"
                type="text"
                placeholder="misal: Cuti akademik, penugasan khusus"
                class="w-full rounded-lg border border-gray-300 p-2 focus:border-brand-500 focus:outline-hidden dark:bg-gray-800 dark:border-gray-700 dark:text-white"
              />
            </div>
          </div>

          <div v-else class="rounded-xl bg-blue-50/70 p-3 text-xs text-blue-900 border border-blue-200 dark:bg-blue-950/20 dark:border-blue-900/40 dark:text-blue-300">
            <span class="font-bold">Info Mode Otomatis:</span> Keaktifan pengurus akan aktif secara otomatis selama tanggal periode kepengurusan sedang berjalan dan status keanggotaan aktif.
          </div>

          <div>
            <label class="block font-semibold text-gray-700 dark:text-gray-300 mb-1">Tautkan Akun Sistem (User Login)</label>
            <select
              v-model="committeeForm.user_id"
              class="w-full rounded-lg border border-gray-300 p-2 focus:border-brand-500 focus:outline-hidden dark:bg-gray-800 dark:border-gray-700 dark:text-white"
            >
              <option :value="null">-- Tidak Ditautkan --</option>
              <option v-for="u in linkableUsersList" :key="u.id" :value="u.id">
                {{ u.name }} ({{ u.email }})
              </option>
            </select>
          </div>

          <div>
            <label class="block font-semibold text-gray-700 dark:text-gray-300 mb-1">Foto Pengurus</label>
            <input
              type="file"
              accept="image/*"
              @change="onCommitteePhotoSelected"
              class="w-full text-xs text-gray-500 file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-gray-100 hover:file:bg-gray-200"
            />
          </div>

          <div>
            <label class="block font-semibold text-gray-700 dark:text-gray-300 mb-1">Catatan / Keterangan</label>
            <textarea
              v-model="committeeForm.notes"
              rows="2"
              class="w-full rounded-lg border border-gray-300 p-2 focus:border-brand-500 focus:outline-hidden dark:bg-gray-800 dark:border-gray-700 dark:text-white"
            ></textarea>
          </div>

          <div class="flex items-center justify-end gap-2 pt-3 border-t">
            <button
              type="button"
              @click="isCommitteeModalOpen = false"
              class="px-3.5 py-2 rounded-lg border text-gray-600 hover:bg-gray-50"
            >
              Batal
            </button>
            <button
              type="submit"
              :disabled="isSubmitting"
              class="px-4 py-2 rounded-lg bg-brand-500 text-white font-semibold hover:bg-brand-600 disabled:opacity-50"
            >
              {{ isSubmitting ? 'Menyimpan...' : 'Simpan Pengurus' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- ========================================== -->
    <!-- MODAL QUICK UBAH STATUS / MODE PENGURUS -->
    <!-- ========================================== -->
    <div
      v-if="isQuickStatusModalOpen && quickStatusCommittee"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
    >
      <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl dark:bg-gray-900 border border-gray-200 dark:border-gray-800">
        <div class="flex items-center justify-between border-b pb-3 mb-4">
          <h3 class="font-bold text-gray-900 dark:text-white">
            Ubah Mode & Status Pengurus
          </h3>
          <button @click="isQuickStatusModalOpen = false" class="text-gray-400 hover:text-gray-600">
            <XIcon class="h-5 w-5" />
          </button>
        </div>

        <div class="text-xs space-y-3.5">
          <div class="rounded-xl bg-gray-50 dark:bg-gray-800/50 p-3 border border-gray-200/60 dark:border-gray-700">
            <p class="font-bold text-sm text-gray-900 dark:text-white">{{ quickStatusCommittee.name }}</p>
            <p class="text-gray-500 mt-0.5">{{ quickStatusCommittee.position }} &bull; {{ quickStatusCommittee.period_name || quickStatusCommittee.period }}</p>
          </div>

          <div>
            <label class="block font-semibold text-gray-700 dark:text-gray-300 mb-1">Mode Evaluasi Status</label>
            <select
              v-model="quickStatusForm.status_mode"
              class="w-full rounded-lg border border-gray-300 p-2 focus:border-brand-500 focus:outline-hidden dark:bg-gray-800 dark:border-gray-700 dark:text-white"
            >
              <option value="auto">Mode Otomatis (Evaluasi dinamis tanggal & anggota)</option>
              <option value="manual">Mode Manual (Penetapan eksplisit administrator)</option>
            </select>
          </div>

          <div v-if="quickStatusForm.status_mode === 'manual'" class="space-y-3 p-3 rounded-xl bg-amber-50/70 border border-amber-200 dark:bg-amber-950/20 dark:border-amber-900/40">
            <div>
              <label class="block font-semibold text-amber-950 dark:text-amber-200 mb-1">Status Penempatan Manual *</label>
              <select
                v-model="quickStatusForm.status"
                class="w-full rounded-lg border border-gray-300 p-2 focus:border-brand-500 focus:outline-hidden dark:bg-gray-800 dark:border-gray-700 dark:text-white"
              >
                <option value="active">Active (Aktif)</option>
                <option value="inactive">Inactive (Nonaktif)</option>
              </select>
            </div>

            <div>
              <label class="block font-semibold text-amber-950 dark:text-amber-200 mb-1">Alasan Perubahan *</label>
              <textarea
                v-model="quickStatusForm.status_reason"
                rows="2"
                required
                placeholder="Tuliskan alasan perubahan status..."
                class="w-full rounded-lg border border-gray-300 p-2 focus:border-brand-500 focus:outline-hidden dark:bg-gray-800 dark:border-gray-700 dark:text-white"
              ></textarea>
            </div>
          </div>

          <div v-else class="rounded-xl bg-blue-50/70 p-3 text-xs text-blue-900 border border-blue-200 dark:bg-blue-950/20 dark:border-blue-900/40 dark:text-blue-300">
            <span class="font-bold">Info:</span> Kembali ke mode otomatis akan mengevaluasi keaktifan pengurus berdasarkan masa berlaku periode dan status keanggotaan.
          </div>

          <div class="flex items-center justify-end gap-2 pt-3 border-t">
            <button
              type="button"
              @click="isQuickStatusModalOpen = false"
              class="px-3.5 py-2 rounded-lg border text-gray-600 hover:bg-gray-50"
            >
              Batal
            </button>
            <button
              type="button"
              @click="submitQuickStatus"
              :disabled="isSubmitting"
              class="px-4 py-2 rounded-lg bg-brand-500 text-white font-semibold hover:bg-brand-600 disabled:opacity-50"
            >
              {{ isSubmitting ? 'Menyimpan...' : 'Simpan Perubahan' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- ========================================== -->
    <!-- MODAL EDIT PERIODE KEPENGURUSAN -->
    <!-- ========================================== -->
    <div
      v-if="isEditPeriodModalOpen && editingPeriod"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
    >
      <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl dark:bg-gray-900 border border-gray-200 dark:border-gray-800">
        <div class="flex items-center justify-between border-b pb-3 mb-4">
          <h3 class="font-bold text-gray-900 dark:text-white">
            Edit Periode Kepengurusan
          </h3>
          <button @click="isEditPeriodModalOpen = false" class="text-gray-400 hover:text-gray-600">
            <XIcon class="h-5 w-5" />
          </button>
        </div>

        <!-- Warning Banner Sinkronisasi Otomatis -->
        <div class="rounded-xl border border-amber-200 bg-amber-50/80 p-3.5 dark:border-amber-900/40 dark:bg-amber-950/20 text-xs text-amber-900 dark:text-amber-300 mb-4">
          <div class="flex items-start gap-2.5">
            <AlertTriangleIcon class="h-4 w-4 text-amber-600 shrink-0 mt-0.5" />
            <div>
              <span class="font-bold">PERINGATAN SINKRONISASI OTOMATIS:</span>
              <p class="mt-0.5 text-[11px] leading-relaxed">
                Mengubah tanggal mulai atau tanggal berakhir periode akan secara otomatis memperbarui status efektivitas seluruh pengurus yang terhubung pada periode ini. Pengurus dalam periode yang berakhir di masa lalu langsung berstatus kedaluwarsa tanpa perlu penonaktifan manual.
              </p>
            </div>
          </div>
        </div>

        <form @submit.prevent="savePeriod" class="space-y-3.5 text-xs">
          <div>
            <label class="block font-semibold text-gray-700 dark:text-gray-300 mb-1">Nama / Label Periode *</label>
            <input
              v-model="periodForm.period_name"
              required
              type="text"
              class="w-full rounded-lg border border-gray-300 p-2 focus:border-brand-500 focus:outline-hidden dark:bg-gray-800 dark:border-gray-700 dark:text-white"
            />
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block font-semibold text-gray-700 dark:text-gray-300 mb-1">Tanggal Mulai *</label>
              <input
                v-model="periodForm.start_date"
                required
                type="date"
                class="w-full rounded-lg border border-gray-300 p-2 focus:border-brand-500 focus:outline-hidden dark:bg-gray-800 dark:border-gray-700 dark:text-white"
              />
            </div>
            <div>
              <label class="block font-semibold text-gray-700 dark:text-gray-300 mb-1">Tanggal Berakhir *</label>
              <input
                v-model="periodForm.end_date"
                required
                type="date"
                class="w-full rounded-lg border border-gray-300 p-2 focus:border-brand-500 focus:outline-hidden dark:bg-gray-800 dark:border-gray-700 dark:text-white"
              />
            </div>
          </div>

          <div>
            <label class="block font-semibold text-gray-700 dark:text-gray-300 mb-1">Catatan</label>
            <textarea
              v-model="periodForm.notes"
              rows="2"
              class="w-full rounded-lg border border-gray-300 p-2 focus:border-brand-500 focus:outline-hidden dark:bg-gray-800 dark:border-gray-700 dark:text-white"
            ></textarea>
          </div>

          <div class="flex items-center justify-end gap-2 pt-3 border-t">
            <button
              type="button"
              @click="isEditPeriodModalOpen = false"
              class="px-3.5 py-2 rounded-lg border text-gray-600 hover:bg-gray-50"
            >
              Batal
            </button>
            <button
              type="submit"
              :disabled="isSubmitting"
              class="px-4 py-2 rounded-lg bg-brand-500 text-white font-semibold hover:bg-brand-600 disabled:opacity-50"
            >
              {{ isSubmitting ? 'Menyimpan...' : 'Simpan Perubahan Periode' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import AdminLayout from '@/components/layout/AdminLayout.vue'
import PageBreadcrumb from '@/components/common/PageBreadcrumb.vue'
import { memberService, type MemberFilterParams, type MemberImportResult } from '@/services/memberService'
import { committeeService, type LinkableMember, type LinkableUser } from '@/services/committeeService'
import { organizationPeriodService } from '@/services/organizationPeriodService'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import type { Member, Committee, OrganizationPeriod } from '@/types/api'

import {
  UsersIcon,
  UserCheckIcon,
  UserPlusIcon,
  UserCircleIcon,
  ShieldCheckIcon,
  CalendarClockIcon,
  CalendarIcon,
  PlusIcon,
  SearchIcon,
  PencilIcon,
  Trash2Icon,
  AlertTriangleIcon,
  XIcon,
  LayersIcon,
  ArchiveIcon,
  FileSpreadsheetIcon,
  DownloadIcon,
  UploadCloudIcon,
  CheckCircleIcon,
  EyeIcon,
  SlidersHorizontalIcon,
} from 'lucide-vue-next'

const route = useRoute()
const authStore = useAuthStore()
const toast = useToastStore()

const canManage = computed(() => {
  const role = authStore.role
  return role === 'Super Admin' || role === 'Admin Organisasi' || authStore.hasPermission('structure.manage')
})

// Tab Navigation Setup
const activeTab = ref('summary')

const tabs = computed(() => [
  { id: 'summary', label: 'Ringkasan', icon: LayersIcon },
  { id: 'members', label: 'Data Anggota', icon: UsersIcon, badge: stats.value.totalMembers },
  { id: 'structure', label: 'Struktur Organisasi', icon: ShieldCheckIcon, badge: stats.value.effectiveCommittees },
  { id: 'periods', label: 'Periode Kepengurusan', icon: CalendarClockIcon },
  { id: 'history', label: 'Riwayat Kepengurusan', icon: ArchiveIcon },
  { id: 'import-export', label: 'Import & Export', icon: FileSpreadsheetIcon },
])

// Stats
const stats = ref({
  totalMembers: 0,
  activeMembers: 0,
  effectiveCommittees: 0,
})

// Periods Data
const periods = ref<OrganizationPeriod[]>([])
const ongoingPeriod = computed(() => periods.value.find(p => p.calculated_status === 'ongoing' || p.is_ongoing))
const expiredPeriods = computed(() => periods.value.filter(p => p.calculated_status === 'expired'))

// Members Tab State
const isLoadingMembers = ref(false)
const membersList = ref<Member[]>([])
const memberFilters = reactive<MemberFilterParams>({
  search: '',
  status: '',
  jurusan: '',
  angkatan: '',
})
const memberPagination = reactive({
  page: 1,
  last_page: 1,
  total: 0,
})

// Committees Tab State
const isLoadingCommittees = ref(false)
const committeesList = ref<Committee[]>([])
const selectedStructurePeriodId = ref<number | null>(null)
const selectedPeriodObj = computed(() => periods.value.find(p => p.id === selectedStructurePeriodId.value))

// Linkable Helpers
const linkableMembersList = ref<LinkableMember[]>([])
const linkableUsersList = ref<LinkableUser[]>([])

// Modals State
const isMemberModalOpen = ref(false)
const editingMemberId = ref<number | null>(null)
const memberForm = reactive({
  nama: '',
  nim: '',
  email: '',
  telepon: '',
  jurusan: '',
  angkatan: '',
  status: 'active',
  user_id: null as number | null,
  catatan: '',
})

const isCommitteeModalOpen = ref(false)
const editingCommitteeId = ref<number | null>(null)
const committeeForm = reactive({
  name: '',
  position: '',
  department: '',
  organization_period_id: null as number | null,
  member_id: null as number | null,
  user_id: null as number | null,
  start_date: '',
  end_date: '',
  status: 'active',
  status_mode: 'auto' as 'auto' | 'manual',
  status_reason: '',
  notes: '',
  photo: null as File | null,
})

// Quick Manual Status Toggle Modal
const isQuickStatusModalOpen = ref(false)
const quickStatusCommittee = ref<Committee | null>(null)
const quickStatusForm = reactive({
  status_mode: 'auto' as 'auto' | 'manual',
  status: 'active' as 'active' | 'inactive',
  status_reason: '',
})

// Edit Period Modal
const isEditPeriodModalOpen = ref(false)
const editingPeriod = ref<OrganizationPeriod | null>(null)
const periodForm = reactive({
  period_name: '',
  start_date: '',
  end_date: '',
  notes: '',
})

const isSubmitting = ref(false)

// Import/Export State
const selectedFile = ref<File | null>(null)
const fileInputRef = ref<HTMLInputElement | null>(null)
const isImporting = ref(false)
const importPreviewResult = ref<MemberImportResult | null>(null)

// Format date helper
const formatDate = (dateStr?: string) => {
  if (!dateStr) return '-'
  try {
    const d = new Date(dateStr)
    return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
  } catch {
    return dateStr
  }
}

// Fetch Periods
const fetchPeriods = async () => {
  try {
    const orgId = (authStore.user as any)?.organization_id || authStore.user?.organization?.id || route.query.organization_id || 1
    const res = await organizationPeriodService.getPeriods(orgId)
    periods.value = res.data || []
    if (!selectedStructurePeriodId.value) {
      if (ongoingPeriod.value) {
        selectedStructurePeriodId.value = ongoingPeriod.value.id
      } else if (periods.value.length > 0) {
        selectedStructurePeriodId.value = periods.value[0].id
      }
    }
  } catch (err: any) {
    console.error(err)
  }
}

// Fetch Members
let debounceTimer: any = null
const debounceFetchMembers = () => {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => {
    fetchMembers()
  }, 350)
}

const fetchMembers = async () => {
  isLoadingMembers.value = true
  try {
    const res = await memberService.list({
      ...memberFilters,
      page: memberPagination.page,
    })
    membersList.value = res.data || []
    if (res.meta) {
      memberPagination.page = res.meta.current_page
      memberPagination.last_page = res.meta.last_page
      memberPagination.total = res.meta.total
      stats.value.totalMembers = res.meta.total
    }
    // Calculate active count
    stats.value.activeMembers = membersList.value.filter(m => m.status === 'active').length
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Gagal memuat daftar anggota.')
  } finally {
    isLoadingMembers.value = false
  }
}

const changeMemberPage = (newPage: number) => {
  if (newPage >= 1 && newPage <= memberPagination.last_page) {
    memberPagination.page = newPage
    fetchMembers()
  }
}

// Fetch Committees for Selected Period
const fetchCommittees = async () => {
  isLoadingCommittees.value = true
  try {
    const data = await committeeService.list({
      organization_period_id: selectedStructurePeriodId.value ?? undefined,
    })
    committeesList.value = data || []
    stats.value.effectiveCommittees = committeesList.value.filter(c => c.is_active_effective).length
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Gagal memuat susunan pengurus.')
  } finally {
    isLoadingCommittees.value = false
  }
}

const viewPeriodStructure = (periodId: number) => {
  selectedStructurePeriodId.value = periodId
  activeTab.value = 'structure'
  fetchCommittees()
}

// Linkables
const loadLinkables = async () => {
  try {
    const [mems, usrs] = await Promise.all([
      committeeService.getLinkableMembers({
        organization_period_id: selectedStructurePeriodId.value ?? undefined,
      }),
      committeeService.getLinkableUsers({
        organization_period_id: selectedStructurePeriodId.value ?? undefined,
      })
    ])
    linkableMembersList.value = mems || []
    linkableUsersList.value = usrs || []
  } catch (err) {
    console.error(err)
  }
}

// Member CRUD
const openCreateMemberModal = () => {
  editingMemberId.value = null
  Object.assign(memberForm, {
    nama: '',
    nim: '',
    email: '',
    telepon: '',
    jurusan: '',
    angkatan: '',
    status: 'active',
    user_id: null,
    catatan: '',
  })
  loadLinkables()
  isMemberModalOpen.value = true
}

const openEditMemberModal = (member: Member) => {
  editingMemberId.value = member.id
  Object.assign(memberForm, {
    nama: member.nama,
    nim: member.nim,
    email: member.email || '',
    telepon: member.telepon || '',
    jurusan: member.jurusan || '',
    angkatan: member.angkatan || '',
    status: member.status || 'active',
    user_id: member.user_id || null,
    catatan: member.catatan || '',
  })
  loadLinkables()
  isMemberModalOpen.value = true
}

const saveMember = async () => {
  isSubmitting.value = true
  try {
    if (editingMemberId.value) {
      await memberService.update(editingMemberId.value, memberForm)
      toast.success('Data anggota berhasil diperbarui!')
    } else {
      await memberService.create(memberForm)
      toast.success('Anggota baru berhasil ditambahkan!')
    }
    isMemberModalOpen.value = false
    fetchMembers()
    loadLinkables()
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Gagal menyimpan data anggota.')
  } finally {
    isSubmitting.value = false
  }
}

const deleteMember = async (member: Member) => {
  if (!confirm(`Hapus data anggota ${member.nama} (${member.nim})?`)) return
  try {
    await memberService.delete(member.id)
    toast.success('Anggota berhasil dihapus!')
    fetchMembers()
    loadLinkables()
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Gagal menghapus anggota.')
  }
}

// Committee CRUD
const openCreateCommitteeModal = () => {
  editingCommitteeId.value = null
  Object.assign(committeeForm, {
    name: '',
    position: '',
    department: '',
    organization_period_id: selectedStructurePeriodId.value || ongoingPeriod.value?.id || null,
    member_id: null,
    user_id: null,
    start_date: selectedPeriodObj.value?.start_date || '',
    end_date: selectedPeriodObj.value?.end_date || '',
    status: 'active',
    status_mode: 'auto',
    status_reason: '',
    notes: '',
    photo: null,
  })
  loadLinkables()
  isCommitteeModalOpen.value = true
}

const onCommitteeMemberSelected = () => {
  if (committeeForm.member_id) {
    const mem = linkableMembersList.value.find(m => m.id === committeeForm.member_id)
    if (mem) {
      committeeForm.name = mem.nama
      if (mem.user_id) committeeForm.user_id = mem.user_id
    }
  }
}

const openEditCommitteeModal = (c: Committee) => {
  editingCommitteeId.value = c.id
  Object.assign(committeeForm, {
    name: c.name,
    position: c.position,
    department: c.department || '',
    organization_period_id: c.organization_period_id || null,
    member_id: c.member_id || null,
    user_id: c.user_id || null,
    start_date: c.start_date || '',
    end_date: c.end_date || '',
    status: c.status_mode === 'manual' ? (c.status || 'active') : 'active',
    status_mode: c.status_mode || 'auto',
    status_reason: c.status_reason || '',
    notes: c.notes || '',
    photo: null,
  })
  loadLinkables()
  isCommitteeModalOpen.value = true
}

const onCommitteePhotoSelected = (e: any) => {
  if (e.target.files && e.target.files[0]) {
    committeeForm.photo = e.target.files[0]
  }
}

const saveCommittee = async () => {
  isSubmitting.value = true
  try {
    if (committeeForm.status_mode === 'auto') {
      committeeForm.status = 'active'
    }

    const formData = new FormData()
    formData.append('name', committeeForm.name)
    formData.append('position', committeeForm.position)
    if (committeeForm.department) formData.append('department', committeeForm.department)
    if (committeeForm.organization_period_id) formData.append('organization_period_id', String(committeeForm.organization_period_id))
    if (committeeForm.member_id) formData.append('member_id', String(committeeForm.member_id))
    if (committeeForm.user_id) formData.append('user_id', String(committeeForm.user_id))
    if (committeeForm.start_date) formData.append('start_date', committeeForm.start_date)
    if (committeeForm.end_date) formData.append('end_date', committeeForm.end_date)
    formData.append('status', committeeForm.status)
    formData.append('status_mode', committeeForm.status_mode)
    if (committeeForm.status_reason) formData.append('status_reason', committeeForm.status_reason)
    if (committeeForm.notes) formData.append('notes', committeeForm.notes)
    if (committeeForm.photo) formData.append('photo', committeeForm.photo)

    if (editingCommitteeId.value) {
      await committeeService.update(editingCommitteeId.value, formData)
      toast.success('Data pengurus berhasil diperbarui!')
    } else {
      await committeeService.create(formData)
      toast.success('Pengurus baru berhasil ditambahkan!')
    }
    isCommitteeModalOpen.value = false
    fetchCommittees()
    loadLinkables()
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Gagal menyimpan pengurus.')
  } finally {
    isSubmitting.value = false
  }
}

const deleteCommittee = async (c: Committee) => {
  if (!confirm(`Hapus ${c.name} (${c.position}) dari struktur kepengurusan?`)) return
  try {
    await committeeService.delete(c.id)
    toast.success('Pengurus berhasil dihapus!')
    fetchCommittees()
    loadLinkables()
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Gagal menghapus pengurus.')
  }
}

// Quick Status Actions
const openQuickStatusModal = (c: Committee) => {
  quickStatusCommittee.value = c
  quickStatusForm.status_mode = c.status_mode || 'auto'
  quickStatusForm.status = c.status || 'active'
  quickStatusForm.status_reason = c.status_reason || ''
  isQuickStatusModalOpen.value = true
}

const submitQuickStatus = async () => {
  if (!quickStatusCommittee.value) return
  if (quickStatusForm.status_mode === 'manual' && !quickStatusForm.status_reason.trim()) {
    toast.error('Alasan perubahan status wajib diisi untuk mode manual.')
    return
  }

  isSubmitting.value = true
  try {
    const payload = {
      status_mode: quickStatusForm.status_mode,
      status: quickStatusForm.status,
      status_reason: quickStatusForm.status_mode === 'manual' ? quickStatusForm.status_reason : null,
    }
    await committeeService.update(quickStatusCommittee.value.id, payload)
    toast.success('Status pengurus berhasil diperbarui!')
    isQuickStatusModalOpen.value = false
    await fetchCommittees()
    await loadLinkables()
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Gagal mengubah status pengurus.')
  } finally {
    isSubmitting.value = false
  }
}

// Edit Period Actions
const openEditPeriodModal = (p: OrganizationPeriod) => {
  editingPeriod.value = p
  periodForm.period_name = p.period_name
  periodForm.start_date = p.start_date ? p.start_date.substring(0, 10) : ''
  periodForm.end_date = p.end_date ? p.end_date.substring(0, 10) : ''
  periodForm.notes = p.notes || ''
  isEditPeriodModalOpen.value = true
}

const savePeriod = async () => {
  if (!editingPeriod.value) return
  if (periodForm.end_date < periodForm.start_date) {
    toast.error('Tanggal berakhir tidak boleh lebih awal dari tanggal mulai.')
    return
  }

  isSubmitting.value = true
  try {
    await organizationPeriodService.updatePeriod(editingPeriod.value.id, {
      period_name: periodForm.period_name,
      start_date: periodForm.start_date,
      end_date: periodForm.end_date,
      notes: periodForm.notes,
    })
    toast.success('Periode kepengurusan berhasil diperbarui! Status efektivitas pengurus telah disinkronkan otomatis.')
    isEditPeriodModalOpen.value = false
    await fetchPeriods()
    await fetchCommittees()
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Gagal memperbarui periode.')
  } finally {
    isSubmitting.value = false
  }
}

// Import & Export
const downloadTemplateCsv = async () => {
  try {
    await memberService.downloadTemplate()
    toast.success('Template CSV berhasil diunduh.')
  } catch (err) {
    toast.error('Gagal mengunduh template CSV.')
  }
}

const exportMembersCsv = async () => {
  try {
    await memberService.export(memberFilters)
    toast.success('Data anggota berhasil diekspor ke CSV.')
  } catch (err) {
    toast.error('Gagal mengekspor data anggota.')
  }
}

const onFileSelected = (e: any) => {
  if (e.target.files && e.target.files[0]) {
    selectedFile.value = e.target.files[0]
    importPreviewResult.value = null
  }
}

const runImportPreview = async () => {
  if (!selectedFile.value) return
  isImporting.value = true
  try {
    const res = await memberService.import(selectedFile.value, 'preview')
    importPreviewResult.value = res
    if (res.errors.length > 0) {
      toast.warning(`Terdapat ${res.errors.length} baris tidak valid pada file CSV.`)
    } else {
      toast.success(`Validasi berhasil! ${res.success_count} data siap dimasukkan.`)
    }
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Gagal memvalidasi berkas CSV.')
  } finally {
    isImporting.value = false
  }
}

const runImportCommit = async () => {
  if (!selectedFile.value) return
  isImporting.value = true
  try {
    const res = await memberService.import(selectedFile.value, 'commit')
    toast.success(`Berhasil mengimpor ${res.success_count} anggota ke database!`)
    selectedFile.value = null
    importPreviewResult.value = null
    if (fileInputRef.value) fileInputRef.value.value = ''
    fetchMembers()
    activeTab.value = 'members'
  } catch (err: any) {
    toast.error(err.response?.data?.message || 'Gagal memproses impor ke database.')
  } finally {
    isImporting.value = false
  }
}

watch(() => route.query.tab, (newTab) => {
  if (newTab && typeof newTab === 'string') {
    activeTab.value = newTab
  }
})

onMounted(async () => {
  if (route.query.tab && typeof route.query.tab === 'string') {
    activeTab.value = route.query.tab
  }
  await fetchPeriods()
  await Promise.all([
    fetchMembers(),
    fetchCommittees(),
  ])
})
</script>
