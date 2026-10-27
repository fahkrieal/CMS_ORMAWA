<?php

use App\Models\Committee;
use App\Models\Member;
use App\Models\Organization;
use App\Models\OrganizationPeriod;
use App\Models\User;
use Carbon\Carbon;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    // Pastikan roles & permissions terdaftar
    Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'Admin Organisasi', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'Anggota', 'guard_name' => 'web']);

    Permission::firstOrCreate(['name' => 'structure.view', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'structure.manage', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'periods.manage', 'guard_name' => 'web']);

    $adminRole = Role::findByName('Admin Organisasi', 'web');
    $adminRole->givePermissionTo(['structure.view', 'structure.manage', 'periods.manage']);

    $timestamp = microtime(true);

    $this->orgA = Organization::create([
        'nama' => 'HIMATIF Test ' . $timestamp,
        'jenis' => 'HMPS',
        'subdomain' => 'himatif' . str_replace('.', '', (string)$timestamp),
        'status' => 'active',
        'modul_aktif' => ['structure' => true, 'public_website_enabled' => true],
    ]);

    $this->orgB = Organization::create([
        'nama' => 'BEM Test ' . $timestamp,
        'jenis' => 'BEM',
        'subdomain' => 'bem' . str_replace('.', '', (string)$timestamp),
        'status' => 'active',
        'modul_aktif' => ['structure' => true, 'public_website_enabled' => true],
    ]);

    $this->adminA = User::create([
        'name' => 'Admin HIMATIF ' . $timestamp,
        'email' => 'admin_hima_' . $timestamp . '@example.com',
        'password' => bcrypt('password123'),
        'organization_id' => $this->orgA->id,
        'status' => 'active',
    ]);
    $this->adminA->assignRole('Admin Organisasi');

    $this->adminB = User::create([
        'name' => 'Admin BEM ' . $timestamp,
        'email' => 'admin_bem_' . $timestamp . '@example.com',
        'password' => bcrypt('password123'),
        'organization_id' => $this->orgB->id,
        'status' => 'active',
    ]);
    $this->adminB->assignRole('Admin Organisasi');

    $this->superAdmin = User::create([
        'name' => 'Super Admin ' . $timestamp,
        'email' => 'superadmin_' . $timestamp . '@example.com',
        'password' => bcrypt('password123'),
        'status' => 'active',
    ]);
    $this->superAdmin->assignRole('Super Admin');
});

// Skenario 1: Anggota yang baru dibuat dapat langsung dipilih saat menambahkan pengurus
test('skenario 1: newly created member is immediately selectable for committee assignment', function () {
    $period = OrganizationPeriod::create([
        'organization_id' => $this->orgA->id,
        'period_name' => '2025/2026',
        'start_date' => Carbon::today()->subMonths(2),
        'end_date' => Carbon::today()->addMonths(10),
        'status' => 'active',
    ]);

    // Tambah anggota baru via endpoint members
    $res = $this->actingAs($this->adminA)->postJson('/api/members', [
        'nama' => 'Ahmad Fauzi',
        'nim' => '1152026001',
        'email' => 'fauzi@example.com',
        'status' => 'active',
    ]);
    $res->assertStatus(201);
    $memberId = $res->json('data.id');

    // Cek dropdown linkable members untuk periode tersebut
    $linkableRes = $this->actingAs($this->adminA)->getJson('/api/committees/linkable-members?organization_period_id=' . $period->id);
    $linkableRes->assertStatus(200);

    $members = collect($linkableRes->json('data'));
    expect($members->pluck('id')->contains($memberId))->toBeTrue();
    expect($members->firstWhere('id', $memberId)['nama'])->toBe('Ahmad Fauzi');
});

// Skenario 2: Perubahan nama anggota langsung tercermin pada struktur organisasi dan riwayat jabatan
test('skenario 2: member name change immediately reflects in committee structure and history', function () {
    $period = OrganizationPeriod::create([
        'organization_id' => $this->orgA->id,
        'period_name' => '2025/2026',
        'start_date' => Carbon::today()->subMonths(2),
        'end_date' => Carbon::today()->addMonths(10),
        'status' => 'active',
    ]);

    $member = Member::create([
        'organization_id' => $this->orgA->id,
        'name' => 'Budi Asli',
        'nim' => '1152026002',
        'status' => 'active',
    ]);

    $committee = Committee::create([
        'organization_id' => $this->orgA->id,
        'organization_period_id' => $period->id,
        'member_id' => $member->id,
        'name' => $member->name,
        'position' => 'Sekretaris',
        'department' => 'BPH',
        'status' => 'active',
        'status_mode' => 'auto',
    ]);

    // Perbarui nama anggota
    $updateRes = $this->actingAs($this->adminA)->putJson('/api/members/' . $member->id, [
        'nama' => 'Budi Diperbarui',
        'nim' => '1152026002',
        'status' => 'active',
    ]);
    $updateRes->assertStatus(200);

    // Cek pada model committee dan API endpoint committees
    $refreshedCommittee = Committee::find($committee->id);
    expect($refreshedCommittee->name)->toBe('Budi Diperbarui');

    $apiRes = $this->actingAs($this->adminA)->getJson('/api/committees/' . $committee->id);
    $apiRes->assertStatus(200);
    expect($apiRes->json('data.name'))->toBe('Budi Diperbarui');
});

// Skenario 3: Penambahan pengurus terhubung ke periode yang dipilih, bukan periode default yang salah
test('skenario 3: committee placement links to specified period not arbitrary default', function () {
    $period1 = OrganizationPeriod::create([
        'organization_id' => $this->orgA->id,
        'period_name' => '2024/2025',
        'start_date' => Carbon::today()->subYears(2),
        'end_date' => Carbon::today()->subYear(),
        'status' => 'expired',
    ]);

    $period2 = OrganizationPeriod::create([
        'organization_id' => $this->orgA->id,
        'period_name' => '2025/2026',
        'start_date' => Carbon::today()->subMonths(1),
        'end_date' => Carbon::today()->addMonths(11),
        'status' => 'active',
    ]);

    $member = Member::create([
        'organization_id' => $this->orgA->id,
        'name' => 'Cahya Lestari',
        'nim' => '1152026003',
        'status' => 'active',
    ]);

    $createRes = $this->actingAs($this->adminA)->postJson('/api/committees', [
        'organization_period_id' => $period2->id,
        'member_id' => $member->id,
        'name' => $member->name,
        'position' => 'Bendahara',
        'status' => 'active',
    ]);
    $createRes->assertStatus(201);

    $committeeId = $createRes->json('data.id');
    $committee = Committee::find($committeeId);

    expect((int)$committee->organization_period_id)->toBe((int)$period2->id);
    expect($committee->period)->toBe('2025/2026');
});

// Skenario 4: Perubahan tanggal periode otomatis memperbarui status efektif pengurus pada mode otomatis
test('skenario 4: changing period dates automatically updates effective status in auto mode', function () {
    $period = OrganizationPeriod::create([
        'organization_id' => $this->orgA->id,
        'period_name' => '2025/2026',
        'start_date' => Carbon::today()->subMonths(3),
        'end_date' => Carbon::today()->addMonths(6),
        'status' => 'active',
    ]);

    $member = Member::create([
        'organization_id' => $this->orgA->id,
        'name' => 'Dina Amelia',
        'nim' => '1152026004',
        'status' => 'active',
    ]);

    $committee = Committee::create([
        'organization_id' => $this->orgA->id,
        'organization_period_id' => $period->id,
        'member_id' => $member->id,
        'name' => $member->name,
        'position' => 'Kepala Divisi Media',
        'status' => 'active',
        'status_mode' => 'auto',
    ]);

    expect($committee->is_active_effective)->toBeTrue();
    expect($committee->effective_status)->toBe('active');

    // Ubah end_date periode ke masa lalu (menjadikannya kedaluwarsa)
    $this->actingAs($this->superAdmin)->putJson('/api/organization-periods/' . $period->id, [
        'period_name' => '2025/2026',
        'start_date' => Carbon::today()->subMonths(6)->toDateString(),
        'end_date' => Carbon::today()->subDay()->toDateString(),
    ])->assertStatus(200);

    $committee->refresh();
    expect($committee->is_active_effective)->toBeFalse();
    expect($committee->effective_status)->toBe('expired');
});

// Skenario 5: Periode sebelum tanggal mulai menghasilkan status pengurus upcoming
test('skenario 5: period before start date evaluates to upcoming status', function () {
    $period = OrganizationPeriod::create([
        'organization_id' => $this->orgA->id,
        'period_name' => '2027/2028',
        'start_date' => Carbon::today()->addMonths(2),
        'end_date' => Carbon::today()->addMonths(14),
        'status' => 'active',
    ]);

    $member = Member::create([
        'organization_id' => $this->orgA->id,
        'name' => 'Eko Prasetyo',
        'nim' => '1152026005',
        'status' => 'active',
    ]);

    $committee = Committee::create([
        'organization_id' => $this->orgA->id,
        'organization_period_id' => $period->id,
        'member_id' => $member->id,
        'name' => $member->name,
        'position' => 'Anggota Divisi',
        'status' => 'active',
        'status_mode' => 'auto',
    ]);

    expect($period->calculated_status)->toBe('upcoming');
    expect($committee->effective_status)->toBe('upcoming');
    expect($committee->is_active_effective)->toBeFalse();
});

// Skenario 6: Periode dalam rentang tanggal menghasilkan status pengurus aktif pada mode otomatis
test('skenario 6: period within date range evaluates to active effective status in auto mode', function () {
    $period = OrganizationPeriod::create([
        'organization_id' => $this->orgA->id,
        'period_name' => '2025/2026',
        'start_date' => Carbon::today()->subMonth(),
        'end_date' => Carbon::today()->addMonth(),
        'status' => 'active',
    ]);

    $member = Member::create([
        'organization_id' => $this->orgA->id,
        'name' => 'Fitri Handayani',
        'nim' => '1152026006',
        'status' => 'active',
    ]);

    $committee = Committee::create([
        'organization_id' => $this->orgA->id,
        'organization_period_id' => $period->id,
        'member_id' => $member->id,
        'name' => $member->name,
        'position' => 'Humas',
        'status' => 'active',
        'status_mode' => 'auto',
    ]);

    expect($period->calculated_status)->toBe('ongoing');
    expect($committee->effective_status)->toBe('active');
    expect($committee->is_active_effective)->toBeTrue();
});

// Skenario 7: Periode setelah tanggal berakhir menghasilkan status pengurus expired
test('skenario 7: period after end date evaluates to expired status', function () {
    $period = OrganizationPeriod::create([
        'organization_id' => $this->orgA->id,
        'period_name' => '2023/2024',
        'start_date' => Carbon::today()->subYears(2),
        'end_date' => Carbon::today()->subMonths(6),
        'status' => 'expired',
    ]);

    $member = Member::create([
        'organization_id' => $this->orgA->id,
        'name' => 'Gilang Ramadhan',
        'nim' => '1152026007',
        'status' => 'active',
    ]);

    $committee = Committee::create([
        'organization_id' => $this->orgA->id,
        'organization_period_id' => $period->id,
        'member_id' => $member->id,
        'name' => $member->name,
        'position' => 'Kastrat',
        'status' => 'active',
        'status_mode' => 'auto',
    ]);

    expect($period->calculated_status)->toBe('expired');
    expect($committee->effective_status)->toBe('expired');
    expect($committee->is_active_effective)->toBeFalse();
});

// Skenario 8: Status manual dapat diubah oleh role yang berhak dan tercatat audit trail
test('skenario 8: manual status can be toggled by authorized user with audit trail', function () {
    $period = OrganizationPeriod::create([
        'organization_id' => $this->orgA->id,
        'period_name' => '2025/2026',
        'start_date' => Carbon::today()->subMonth(),
        'end_date' => Carbon::today()->addMonths(11),
        'status' => 'active',
    ]);

    $member = Member::create([
        'organization_id' => $this->orgA->id,
        'name' => 'Hendra Setiawan',
        'nim' => '1152026008',
        'status' => 'active',
    ]);

    $committee = Committee::create([
        'organization_id' => $this->orgA->id,
        'organization_period_id' => $period->id,
        'member_id' => $member->id,
        'name' => $member->name,
        'position' => 'Staff Logistik',
        'status' => 'active',
        'status_mode' => 'auto',
    ]);

    // Ubah ke mode manual: nonaktif karena cuti studi
    $res = $this->actingAs($this->adminA)->putJson('/api/committees/' . $committee->id, [
        'status_mode' => 'manual',
        'status' => 'inactive',
        'status_reason' => 'Cuti akademik semester genap',
    ]);
    $res->assertStatus(200);

    $committee->refresh();
    expect($committee->status_mode)->toBe('manual');
    expect($committee->status)->toBe('inactive');
    expect($committee->status_reason)->toBe('Cuti akademik semester genap');
    expect((int)$committee->status_changed_by)->toBe((int)$this->adminA->id);
    expect($committee->status_changed_at)->not->toBeNull();
    expect($committee->status_changed_by_user)->not->toBeNull();
    expect($committee->status_changed_by_user->name)->toBe($this->adminA->name);
    expect($committee->is_active_effective)->toBeFalse();
});

// Skenario 9: Status manual tidak menghapus riwayat kepengurusan
test('skenario 9: manual status does not delete committee history', function () {
    $period = OrganizationPeriod::create([
        'organization_id' => $this->orgA->id,
        'period_name' => '2025/2026',
        'start_date' => Carbon::today()->subMonth(),
        'end_date' => Carbon::today()->addMonths(11),
        'status' => 'active',
    ]);

    $member = Member::create([
        'organization_id' => $this->orgA->id,
        'name' => 'Indra Permana',
        'nim' => '1152026009',
        'status' => 'active',
    ]);

    $committee = Committee::create([
        'organization_id' => $this->orgA->id,
        'organization_period_id' => $period->id,
        'member_id' => $member->id,
        'name' => $member->name,
        'position' => 'Ketua Pelaksana',
        'status' => 'active',
        'status_mode' => 'auto',
    ]);

    // Admin menonaktifkan status penempatan
    $this->actingAs($this->adminA)->putJson('/api/committees/' . $committee->id, [
        'status_mode' => 'manual',
        'status' => 'inactive',
        'status_reason' => 'Reshuffle internal',
    ])->assertStatus(200);

    // Pastikan data pengurus tetap ada di database dan riwayat periode
    $listRes = $this->actingAs($this->adminA)->getJson('/api/committees?organization_period_id=' . $period->id);
    $listRes->assertStatus(200);
    $items = collect($listRes->json('data'));
    expect($items->firstWhere('id', $committee->id))->not->toBeNull();
    expect($items->firstWhere('id', $committee->id)['status'])->toBe('inactive');
});

// Skenario 10: Status manual tidak dapat membuat pengurus periode kedaluwarsa tampil aktif di struktur publik berjalan
test('skenario 10: manual active status cannot make expired period committee active on ongoing public roster', function () {
    $expiredPeriod = OrganizationPeriod::create([
        'organization_id' => $this->orgA->id,
        'period_name' => '2023/2024',
        'start_date' => Carbon::today()->subYears(2),
        'end_date' => Carbon::today()->subYear(),
        'status' => 'expired',
    ]);

    $member = Member::create([
        'organization_id' => $this->orgA->id,
        'name' => 'Joko Widodo',
        'nim' => '1152026010',
        'status' => 'active',
    ]);

    // Meskipun manual status diset active, karena periodenya kedaluwarsa, is_active_effective harus false
    $committee = Committee::create([
        'organization_id' => $this->orgA->id,
        'organization_period_id' => $expiredPeriod->id,
        'member_id' => $member->id,
        'name' => $member->name,
        'position' => 'Ketua Umum',
        'status' => 'active',
        'status_mode' => 'manual',
        'status_reason' => 'Ditetapkan manual',
    ]);

    expect($committee->is_active_effective)->toBeFalse();
    expect($committee->effective_status)->toBe('expired');

    // Cek endpoint publik organisasi
    $publicRes = $this->getJson('/api/public/organizations/' . $this->orgA->subdomain . '/structure');
    $publicRes->assertStatus(200);
    $publicCommittees = collect($publicRes->json('data'));
    expect($publicCommittees->pluck('id')->contains($committee->id))->toBeFalse();
});

// Skenario 11: Anggota berstatus nonaktif tidak dapat menjadi pengurus aktif
test('skenario 11: inactive member cannot be an active committee member', function () {
    $period = OrganizationPeriod::create([
        'organization_id' => $this->orgA->id,
        'period_name' => '2025/2026',
        'start_date' => Carbon::today()->subMonth(),
        'end_date' => Carbon::today()->addMonths(11),
        'status' => 'active',
    ]);

    $inactiveMember = Member::create([
        'organization_id' => $this->orgA->id,
        'name' => 'Kartika Sari',
        'nim' => '1152026011',
        'status' => 'inactive',
    ]);

    // Menugaskan anggota nonaktif sebagai pengurus aktif harus ditolak oleh API
    $createRes = $this->actingAs($this->adminA)->postJson('/api/committees', [
        'organization_period_id' => $period->id,
        'member_id' => $inactiveMember->id,
        'name' => $inactiveMember->name,
        'position' => 'Sekretaris',
        'status' => 'active',
    ]);
    $createRes->assertStatus(422);

    // Jika dipaksa di database, accessor is_active_effective harus false
    $forcedCommittee = Committee::create([
        'organization_id' => $this->orgA->id,
        'organization_period_id' => $period->id,
        'member_id' => $inactiveMember->id,
        'name' => $inactiveMember->name,
        'position' => 'Sekretaris',
        'status' => 'active',
        'status_mode' => 'auto',
    ]);
    expect($forcedCommittee->is_active_effective)->toBeFalse();
    expect($forcedCommittee->effective_status)->toBe('inactive');
});

// Skenario 12: Periode lama tetap dapat diakses pada riwayat kepengurusan beserta susunan pengurus aslinya
test('skenario 12: historical periods and original committee roster remain accessible in archive', function () {
    $archivePeriod = OrganizationPeriod::create([
        'organization_id' => $this->orgA->id,
        'period_name' => '2022/2023',
        'start_date' => Carbon::today()->subYears(3),
        'end_date' => Carbon::today()->subYears(2),
        'status' => 'expired',
    ]);

    $member = Member::create([
        'organization_id' => $this->orgA->id,
        'name' => 'Lukman Hakim',
        'nim' => '1152026012',
        'status' => 'alumni',
    ]);

    $committee = Committee::create([
        'organization_id' => $this->orgA->id,
        'organization_period_id' => $archivePeriod->id,
        'member_id' => $member->id,
        'name' => $member->name,
        'position' => 'Ketua Umum',
        'status' => 'active',
        'status_mode' => 'auto',
    ]);

    // Mengambil arsip via query organization_period_id
    $archiveRes = $this->actingAs($this->adminA)->getJson('/api/committees?organization_period_id=' . $archivePeriod->id);
    $archiveRes->assertStatus(200);
    $items = collect($archiveRes->json('data'));
    expect($items->firstWhere('id', $committee->id))->not->toBeNull();
    expect($items->firstWhere('id', $committee->id)['position'])->toBe('Ketua Umum');

    // Mengambil arsip via public endpoint dengan ?period_id=
    $publicArchiveRes = $this->getJson('/api/public/organizations/' . $this->orgA->subdomain . '/structure?period_id=' . $archivePeriod->id);
    $publicArchiveRes->assertStatus(200);
    $publicItems = collect($publicArchiveRes->json('data'));
    expect($publicItems->firstWhere('id', $committee->id))->not->toBeNull();
});

// Skenario 13: Data dari organisasi lain tidak dapat dipilih, diubah, atau bocor
test('skenario 13: cross-organization data cannot mix or leak across tenants', function () {
    $periodB = OrganizationPeriod::create([
        'organization_id' => $this->orgB->id,
        'period_name' => '2025/2026',
        'start_date' => Carbon::today()->subMonth(),
        'end_date' => Carbon::today()->addMonths(11),
        'status' => 'active',
    ]);

    $memberB = Member::create([
        'organization_id' => $this->orgB->id,
        'name' => 'Member Milik BEM',
        'nim' => '1152026013',
        'status' => 'active',
    ]);

    $committeeB = Committee::create([
        'organization_id' => $this->orgB->id,
        'organization_period_id' => $periodB->id,
        'member_id' => $memberB->id,
        'name' => $memberB->name,
        'position' => 'Staff BEM',
        'status' => 'active',
    ]);

    // Admin Org A mencoba memasukkan member Org B ke pengurus Org A -> ditolak
    $periodA = OrganizationPeriod::create([
        'organization_id' => $this->orgA->id,
        'period_name' => '2025/2026',
        'start_date' => Carbon::today()->subMonth(),
        'end_date' => Carbon::today()->addMonths(11),
        'status' => 'active',
    ]);

    $leakAttempt = $this->actingAs($this->adminA)->postJson('/api/committees', [
        'organization_period_id' => $periodA->id,
        'member_id' => $memberB->id,
        'name' => $memberB->name,
        'position' => 'Staff Penyelundup',
        'status' => 'active',
    ]);
    $leakAttempt->assertStatus(422);

    // Admin Org A mencoba mengedit pengurus Org B -> Ditolak (404/403 karena isolasi tenant menyembunyikan data org lain)
    $editAttempt = $this->actingAs($this->adminA)->putJson('/api/committees/' . $committeeB->id, [
        'position' => 'Hacked Position',
    ]);
    expect(in_array($editAttempt->status(), [403, 404]))->toBeTrue();
});

// Skenario 14: Tidak terjadi duplikasi anggota saat penetapan pengurus berulang kali pada periode berbeda
test('skenario 14: no duplicate members created when assigning committee across multiple periods', function () {
    $period1 = OrganizationPeriod::create([
        'organization_id' => $this->orgA->id,
        'period_name' => '2024/2025',
        'start_date' => Carbon::today()->subYears(2),
        'end_date' => Carbon::today()->subYear(),
        'status' => 'expired',
    ]);

    $period2 = OrganizationPeriod::create([
        'organization_id' => $this->orgA->id,
        'period_name' => '2025/2026',
        'start_date' => Carbon::today()->subMonth(),
        'end_date' => Carbon::today()->addMonths(11),
        'status' => 'active',
    ]);

    $member = Member::create([
        'organization_id' => $this->orgA->id,
        'name' => 'Mega Utami',
        'nim' => '1152026014',
        'status' => 'active',
    ]);

    $countBefore = Member::where('organization_id', $this->orgA->id)->count();

    // Assign ke periode 1
    $this->actingAs($this->adminA)->postJson('/api/committees', [
        'organization_period_id' => $period1->id,
        'member_id' => $member->id,
        'name' => $member->name,
        'position' => 'Staff',
        'status' => 'active',
    ])->assertStatus(201);

    // Assign orang yang sama ke periode 2
    $this->actingAs($this->adminA)->postJson('/api/committees', [
        'organization_period_id' => $period2->id,
        'member_id' => $member->id,
        'name' => $member->name,
        'position' => 'Ketua Divisi',
        'status' => 'active',
    ])->assertStatus(201);

    $countAfter = Member::where('organization_id', $this->orgA->id)->count();
    expect($countAfter)->toBe($countBefore);
});

// Skenario 15: Perubahan periode dan status menampilkan data yang konsisten
test('skenario 15: changes to period and status display consistently across views', function () {
    $period = OrganizationPeriod::create([
        'organization_id' => $this->orgA->id,
        'period_name' => '2025/2026',
        'start_date' => Carbon::today()->subMonth(),
        'end_date' => Carbon::today()->addMonths(5),
        'status' => 'active',
    ]);

    $member = Member::create([
        'organization_id' => $this->orgA->id,
        'name' => 'Naufal Rizqi',
        'nim' => '1152026015',
        'status' => 'active',
    ]);

    $committee = Committee::create([
        'organization_id' => $this->orgA->id,
        'organization_period_id' => $period->id,
        'member_id' => $member->id,
        'name' => $member->name,
        'position' => 'Kepala Lab',
        'status' => 'active',
        'status_mode' => 'auto',
    ]);

    // Cek konsistensi awal
    expect($period->calculated_status)->toBe('ongoing');
    expect($committee->is_active_effective)->toBeTrue();

    // Ubah periode menjadi lewat
    $period->update([
        'end_date' => Carbon::today()->subDays(2),
    ]);

    $period->refresh();
    $committee->refresh();

    expect($period->calculated_status)->toBe('expired');
    expect($committee->is_active_effective)->toBeFalse();
    expect($committee->effective_status)->toBe('expired');
});

// Skenario 16: Endpoint publik menyembunyikan pengurus nonaktif / kedaluwarsa
test('skenario 16: public structure endpoint hides unauthorized and expired committees', function () {
    $ongoingPeriod = OrganizationPeriod::create([
        'organization_id' => $this->orgA->id,
        'period_name' => '2025/2026',
        'start_date' => Carbon::today()->subMonth(),
        'end_date' => Carbon::today()->addMonths(11),
        'status' => 'active',
    ]);

    $activeMember = Member::create([
        'organization_id' => $this->orgA->id,
        'name' => 'Oki Pratama',
        'nim' => '1152026016',
        'status' => 'active',
    ]);

    $inactiveMember = Member::create([
        'organization_id' => $this->orgA->id,
        'name' => 'Putri Ayu',
        'nim' => '1152026017',
        'status' => 'inactive',
    ]);

    // Pengurus 1: Aktif
    $c1 = Committee::create([
        'organization_id' => $this->orgA->id,
        'organization_period_id' => $ongoingPeriod->id,
        'member_id' => $activeMember->id,
        'name' => $activeMember->name,
        'position' => 'Ketua Umum',
        'status' => 'active',
        'status_mode' => 'auto',
    ]);

    // Pengurus 2: Nonaktif karena member nonaktif
    $c2 = Committee::create([
        'organization_id' => $this->orgA->id,
        'organization_period_id' => $ongoingPeriod->id,
        'member_id' => $inactiveMember->id,
        'name' => $inactiveMember->name,
        'position' => 'Sekretaris',
        'status' => 'active',
        'status_mode' => 'auto',
    ]);

    // Pengurus 3: Nonaktif manual
    $c3 = Committee::create([
        'organization_id' => $this->orgA->id,
        'organization_period_id' => $ongoingPeriod->id,
        'member_id' => $activeMember->id,
        'name' => $activeMember->name,
        'position' => 'Bendahara',
        'status' => 'inactive',
        'status_mode' => 'manual',
        'status_reason' => 'Mengundurkan diri',
    ]);

    $publicRes = $this->getJson('/api/public/organizations/' . $this->orgA->subdomain . '/structure');
    $publicRes->assertStatus(200);

    $visibleIds = collect($publicRes->json('data'))->pluck('id')->toArray();

    expect(in_array($c1->id, $visibleIds))->toBeTrue();
    expect(in_array($c2->id, $visibleIds))->toBeFalse();
    expect(in_array($c3->id, $visibleIds))->toBeFalse();
});

// Skenario 17: Tidak terjadi regresi pada alur login, otorisasi, dan permissions
test('skenario 17: no regressions on authentication, authorization and role permissions', function () {
    // 1. Login user aktif
    $loginRes = $this->postJson('/api/login', [
        'email' => $this->adminA->email,
        'password' => 'password123',
    ]);
    $loginRes->assertStatus(200);
    expect($loginRes->json('access_token'))->not->toBeNull();

    // 2. Akses tanpa token ditolak (401)
    $unauthRes = $this->getJson('/api/members');
    $unauthRes->assertStatus(401);

    // 3. User tanpa permission structure.manage tidak bisa membuat pengurus (403)
    $regularUser = User::create([
        'name' => 'Regular User',
        'email' => 'regular@example.com',
        'password' => bcrypt('password123'),
        'organization_id' => $this->orgA->id,
        'status' => 'active',
    ]);
    $regularUser->assignRole('Anggota');

    $period = OrganizationPeriod::create([
        'organization_id' => $this->orgA->id,
        'period_name' => '2025/2026',
        'start_date' => Carbon::today()->subMonth(),
        'end_date' => Carbon::today()->addMonths(11),
        'status' => 'active',
    ]);

    $member = Member::create([
        'organization_id' => $this->orgA->id,
        'name' => 'Qori Sandria',
        'nim' => '1152026018',
        'status' => 'active',
    ]);

    $forbiddenRes = $this->actingAs($regularUser)->postJson('/api/committees', [
        'organization_period_id' => $period->id,
        'member_id' => $member->id,
        'name' => $member->name,
        'position' => 'Anggota Divisi',
        'status' => 'active',
    ]);
    $forbiddenRes->assertStatus(403);
});
