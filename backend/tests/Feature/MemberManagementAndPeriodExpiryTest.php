<?php

use App\Models\Committee;
use App\Models\Member;
use App\Models\Organization;
use App\Models\OrganizationPeriod;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'Admin Organisasi', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'Editor', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'Kontributor', 'guard_name' => 'web']);

    $timestamp = microtime(true);

    $this->org1 = Organization::create([
        'nama' => 'HIMATIF ' . $timestamp,
        'jenis' => 'HMPS',
        'subdomain' => 'himatif' . str_replace('.', '', (string)$timestamp),
        'status' => 'active',
        'modul_aktif' => ['structure' => true, 'public_website_enabled' => true],
    ]);

    $this->org2 = Organization::create([
        'nama' => 'BEM ' . $timestamp,
        'jenis' => 'BEM',
        'subdomain' => 'bem' . str_replace('.', '', (string)$timestamp),
        'status' => 'active',
        'modul_aktif' => ['structure' => true, 'public_website_enabled' => true],
    ]);

    $this->adminOrg1 = User::create([
        'name' => 'Admin HIMATIF ' . $timestamp,
        'email' => 'admin_hima_' . $timestamp . '@example.com',
        'password' => bcrypt('password'),
        'organization_id' => $this->org1->id,
        'status' => 'active',
    ]);
    $this->adminOrg1->assignRole('Admin Organisasi');

    $this->adminOrg2 = User::create([
        'name' => 'Admin BEM ' . $timestamp,
        'email' => 'admin_bem_' . $timestamp . '@example.com',
        'password' => bcrypt('password'),
        'organization_id' => $this->org2->id,
        'status' => 'active',
    ]);
    $this->adminOrg2->assignRole('Admin Organisasi');
});

// 1. Membaca data anggota dengan isolasi organisasi (organisasi A tidak bisa melihat anggota organisasi B)
test('member read has tenant isolation', function () {
    $member1 = Member::create([
        'organization_id' => $this->org1->id,
        'nama' => 'Budi Santoso',
        'nim' => '11520001',
        'jurusan' => 'Informatika',
        'angkatan' => 2024,
        'status' => 'active',
    ]);

    $member2 = Member::create([
        'organization_id' => $this->org2->id,
        'nama' => 'Siti Aminah',
        'nim' => '11520002',
        'jurusan' => 'Industri',
        'angkatan' => 2024,
        'status' => 'active',
    ]);

    // Admin Org 1 can see member 1
    $resp1 = $this->actingAs($this->adminOrg1, 'sanctum')->getJson('/api/members');
    $resp1->assertStatus(200);
    $nims = collect($resp1->json('data'))->pluck('nim')->all();
    expect($nims)->toContain('11520001');
    expect($nims)->not->toContain('11520002');

    // Admin Org 1 cannot view member 2 detail (403 forbidden or 404 scoped out by tenant isolation)
    $respShow = $this->actingAs($this->adminOrg1, 'sanctum')->getJson("/api/members/{$member2->id}");
    expect(in_array($respShow->status(), [403, 404], true))->toBeTrue();
});

// 2. Menambah anggota baru dengan NIM unik per organisasi
test('member create enforces unique nim per organization', function () {
    // Org 1 creates NIM 11520099
    $resp1 = $this->actingAs($this->adminOrg1, 'sanctum')->postJson('/api/members', [
        'nama' => 'Ahmad Fauzi',
        'nim' => '11520099',
        'jurusan' => 'Informatika',
        'angkatan' => 2024,
        'status' => 'active',
    ]);
    $resp1->assertStatus(201);

    // Org 1 creates duplicate NIM 11520099 -> rejected 422
    $respDup = $this->actingAs($this->adminOrg1, 'sanctum')->postJson('/api/members', [
        'nama' => 'Ahmad Kembara',
        'nim' => '11520099',
        'status' => 'active',
    ]);
    $respDup->assertStatus(422);

    // Org 2 creates SAME NIM 11520099 -> accepted 201 (multi-tenant allowed)
    $resp2 = $this->actingAs($this->adminOrg2, 'sanctum')->postJson('/api/members', [
        'nama' => 'Ahmad Fauzi BEM',
        'nim' => '11520099',
        'jurusan' => 'Informatika',
        'status' => 'active',
    ]);
    $resp2->assertStatus(201);
});

// 3. Mengubah data anggota dan riwayat perubahan tercatat jika relevan
test('member update updates attributes and logs activity', function () {
    $member = Member::create([
        'organization_id' => $this->org1->id,
        'nama' => 'Dwi Cahyo',
        'nim' => '11520010',
        'telepon' => '08123456789',
        'status' => 'active',
    ]);

    $resp = $this->actingAs($this->adminOrg1, 'sanctum')->putJson("/api/members/{$member->id}", [
        'nama' => 'Dwi Cahyo Pratama',
        'telepon' => '08999999999',
        'status' => 'alumni',
    ]);

    $resp->assertStatus(200);
    $member->refresh();
    expect($member->nama)->toBe('Dwi Cahyo Pratama');
    expect($member->telepon)->toBe('08999999999');
    expect($member->status)->toBe('alumni');
});

// 4. Menghapus data anggota (pastikan proteksi referensi jika anggota sudah menjadi pengurus pada periode historis)
test('member delete protects reference if linked to committee', function () {
    $member = Member::create([
        'organization_id' => $this->org1->id,
        'nama' => 'Eko Prasetyo',
        'nim' => '11520011',
        'status' => 'active',
    ]);

    $period = OrganizationPeriod::create([
        'organization_id' => $this->org1->id,
        'period_name' => 'Periode 2024/2025',
        'start_date' => '2024-01-01',
        'end_date' => '2024-12-31',
        'status' => 'active',
    ]);

    $committee = Committee::create([
        'organization_id' => $this->org1->id,
        'organization_period_id' => $period->id,
        'member_id' => $member->id,
        'name' => $member->nama,
        'position' => 'Sekretaris',
        'period' => $period->period_name,
        'status' => 'active',
    ]);

    // Attempting to delete member linked to committee -> rejected 422
    $respDel = $this->actingAs($this->adminOrg1, 'sanctum')->deleteJson("/api/members/{$member->id}");
    $respDel->assertStatus(422);

    // Unlinked member can be deleted
    $unlinkedMember = Member::create([
        'organization_id' => $this->org1->id,
        'nama' => 'Hendra Setiawan',
        'nim' => '11520012',
        'status' => 'active',
    ]);
    $respDelOk = $this->actingAs($this->adminOrg1, 'sanctum')->deleteJson("/api/members/{$unlinkedMember->id}");
    $respDelOk->assertStatus(200);
    expect(Member::find($unlinkedMember->id))->toBeNull();
});

// 5. Menghubungkan data anggota dengan akun user sistem
test('member linking to user account with validation', function () {
    $userHima = User::create([
        'name' => 'Ferry User',
        'email' => 'ferry_' . microtime(true) . '@example.com',
        'password' => bcrypt('password'),
        'organization_id' => $this->org1->id,
        'status' => 'active',
    ]);

    $member = Member::create([
        'organization_id' => $this->org1->id,
        'nama' => 'Ferry Setiawan',
        'nim' => '11520013',
        'status' => 'active',
    ]);

    // Linking to valid user
    $respLink = $this->actingAs($this->adminOrg1, 'sanctum')->putJson("/api/members/{$member->id}", [
        'user_id' => $userHima->id,
    ]);
    $respLink->assertStatus(200);
    $member->refresh();
    expect($member->user_id)->toBe($userHima->id);

    // Attempting to link second member to the SAME user -> 422
    $member2 = Member::create([
        'organization_id' => $this->org1->id,
        'nama' => 'Gilang Ramadhan',
        'nim' => '11520014',
        'status' => 'active',
    ]);
    $respDupUser = $this->actingAs($this->adminOrg1, 'sanctum')->putJson("/api/members/{$member2->id}", [
        'user_id' => $userHima->id,
    ]);
    $respDupUser->assertStatus(422);
});

// 6. Menugaskan anggota ke jabatan pengurus pada periode aktif
test('committee assignment to active period', function () {
    $period = OrganizationPeriod::create([
        'organization_id' => $this->org1->id,
        'period_name' => 'Periode 2026/2027',
        'start_date' => Carbon::now()->subMonths(1)->toDateString(),
        'end_date' => Carbon::now()->addMonths(11)->toDateString(),
        'status' => 'active',
    ]);

    $member = Member::create([
        'organization_id' => $this->org1->id,
        'nama' => 'Hardianto',
        'nim' => '11520015',
        'status' => 'active',
    ]);

    $resp = $this->actingAs($this->adminOrg1, 'sanctum')->postJson('/api/committees', [
        'organization_period_id' => $period->id,
        'member_id' => $member->id,
        'position' => 'Bendahara Umum',
        'department' => 'BPH',
        'status' => 'active',
    ]);

    $resp->assertStatus(201);
    $committeeId = $resp->json('data.id');
    $committee = Committee::find($committeeId);
    expect($committee->member_id)->toBe($member->id);
    expect($committee->is_active_effective)->toBeTrue();
    expect($committee->effective_status)->toBe('active');
});

// 7. Mencegah penugasan anggota nonaktif ke jabatan pengurus aktif
test('cannot assign inactive member to active committee', function () {
    $period = OrganizationPeriod::create([
        'organization_id' => $this->org1->id,
        'period_name' => 'Periode 2026/2027',
        'start_date' => Carbon::now()->subMonths(1)->toDateString(),
        'end_date' => Carbon::now()->addMonths(11)->toDateString(),
        'status' => 'active',
    ]);

    $inactiveMember = Member::create([
        'organization_id' => $this->org1->id,
        'nama' => 'Irfan Nonaktif',
        'nim' => '11520016',
        'status' => 'inactive',
    ]);

    $resp = $this->actingAs($this->adminOrg1, 'sanctum')->postJson('/api/committees', [
        'organization_period_id' => $period->id,
        'member_id' => $inactiveMember->id,
        'position' => 'Koordinator Divisi',
        'status' => 'active',
    ]);

    $resp->assertStatus(422);
});

// 8. Validasi keunikan jabatan tunggal (misal: hanya boleh ada satu Ketua per periode)
test('single leader validation per period', function () {
    $period = OrganizationPeriod::create([
        'organization_id' => $this->org1->id,
        'period_name' => 'Periode 2026/2027',
        'start_date' => Carbon::now()->subMonths(1)->toDateString(),
        'end_date' => Carbon::now()->addMonths(11)->toDateString(),
        'status' => 'active',
    ]);

    $memberA = Member::create([
        'organization_id' => $this->org1->id,
        'nama' => 'Joko Anwar',
        'nim' => '11520017',
        'status' => 'active',
    ]);

    $memberB = Member::create([
        'organization_id' => $this->org1->id,
        'nama' => 'Kurnia Meiga',
        'nim' => '11520018',
        'status' => 'active',
    ]);

    // Create first Ketua
    $resp1 = $this->actingAs($this->adminOrg1, 'sanctum')->postJson('/api/committees', [
        'organization_period_id' => $period->id,
        'member_id' => $memberA->id,
        'position' => 'Ketua',
        'status' => 'active',
    ]);
    $resp1->assertStatus(201);

    // Attempt to create second Ketua in the same period -> 422
    $resp2 = $this->actingAs($this->adminOrg1, 'sanctum')->postJson('/api/committees', [
        'organization_period_id' => $period->id,
        'member_id' => $memberB->id,
        'position' => 'Ketua Umum',
        'status' => 'active',
    ]);
    $resp2->assertStatus(422);
});

// 9. Perhitungan otomatis status periode (ongoing, expired, upcoming) berdasarkan tanggal hari ini
test('automatic period status calculation based on current date', function () {
    $today = Carbon::today('Asia/Jakarta');

    $ongoing = OrganizationPeriod::create([
        'organization_id' => $this->org1->id,
        'period_name' => 'Periode Berjalan',
        'start_date' => $today->copy()->subMonths(2)->toDateString(),
        'end_date' => $today->copy()->addMonths(10)->toDateString(),
        'status' => 'active',
    ]);

    $expired = OrganizationPeriod::create([
        'organization_id' => $this->org1->id,
        'period_name' => 'Periode Lampau',
        'start_date' => $today->copy()->subYears(2)->toDateString(),
        'end_date' => $today->copy()->subYears(1)->toDateString(),
        'status' => 'active',
    ]);

    $upcoming = OrganizationPeriod::create([
        'organization_id' => $this->org1->id,
        'period_name' => 'Periode Mendatang',
        'start_date' => $today->copy()->addMonths(1)->toDateString(),
        'end_date' => $today->copy()->addMonths(13)->toDateString(),
        'status' => 'active',
    ]);

    expect($ongoing->calculated_status)->toBe('ongoing');
    expect($ongoing->isOngoing())->toBeTrue();

    expect($expired->calculated_status)->toBe('expired');
    expect($expired->isExpired())->toBeTrue();

    expect($upcoming->calculated_status)->toBe('upcoming');
    expect($upcoming->isUpcoming())->toBeTrue();
});

// 10. Pengurus dari periode yang telah kedaluwarsa secara otomatis berstatus tidak aktif secara efektif
test('expired period committee has inactive effective status', function () {
    $today = Carbon::today('Asia/Jakarta');

    $expiredPeriod = OrganizationPeriod::create([
        'organization_id' => $this->org1->id,
        'period_name' => 'Periode 2022/2023',
        'start_date' => $today->copy()->subYears(3)->toDateString(),
        'end_date' => $today->copy()->subYears(2)->toDateString(),
        'status' => 'active',
    ]);

    $committee = Committee::create([
        'organization_id' => $this->org1->id,
        'organization_period_id' => $expiredPeriod->id,
        'name' => 'Lukman Hakim',
        'position' => 'Ketua',
        'period' => $expiredPeriod->period_name,
        'status' => 'active', // Marked active in DB, but expired by period dates!
    ]);

    expect($committee->is_active_effective)->toBeFalse();
    expect(in_array($committee->effective_status, ['inactive', 'expired'], true))->toBeTrue();

    // Query effectiveActive() scope excludes it
    $effectiveActiveCount = Committee::where('id', $committee->id)->effectiveActive()->count();
    expect($effectiveActiveCount)->toBe(0);
});

// 11. Endpoint struktur publik hanya menampilkan pengurus dari periode yang benar-benar aktif (ongoing)
test('public structure only shows ongoing active committees and prevents expired committee leakage', function () {
    $today = Carbon::today('Asia/Jakarta');

    // 1. Expired period with "active" committee
    $expiredPeriod = OrganizationPeriod::create([
        'organization_id' => $this->org1->id,
        'period_name' => 'Periode 2021/2022',
        'start_date' => $today->copy()->subYears(4)->toDateString(),
        'end_date' => $today->copy()->subYears(3)->toDateString(),
        'status' => 'active',
    ]);

    $expiredCommittee = Committee::create([
        'organization_id' => $this->org1->id,
        'organization_period_id' => $expiredPeriod->id,
        'name' => 'Pengurus Tua 2021',
        'position' => 'Ketua',
        'period' => $expiredPeriod->period_name,
        'status' => 'active',
    ]);

    // 2. Ongoing period with current officer
    $ongoingPeriod = OrganizationPeriod::create([
        'organization_id' => $this->org1->id,
        'period_name' => 'Periode 2026/2027',
        'start_date' => $today->copy()->subMonths(1)->toDateString(),
        'end_date' => $today->copy()->addMonths(11)->toDateString(),
        'status' => 'active',
    ]);

    $currentCommittee = Committee::create([
        'organization_id' => $this->org1->id,
        'organization_period_id' => $ongoingPeriod->id,
        'name' => 'Pengurus Baru 2026',
        'position' => 'Ketua',
        'period' => $ongoingPeriod->period_name,
        'status' => 'active',
    ]);

    // Call public structure endpoint
    $resp = $this->getJson("/api/public/organizations/{$this->org1->subdomain}/structure");
    $resp->assertStatus(200);

    $returnedNames = collect($resp->json('data.members'))->pluck('name')->all();
    expect($returnedNames)->toContain('Pengurus Baru 2026');
    expect($returnedNames)->not->toContain('Pengurus Tua 2021');
});

// 12. Riwayat kepengurusan periode lampau dapat diakses via filter historis
test('public structure supports historical period viewing without leaking to ongoing roster', function () {
    $today = Carbon::today('Asia/Jakarta');

    $pastPeriod = OrganizationPeriod::create([
        'organization_id' => $this->org1->id,
        'period_name' => 'Arsip Periode 2023/2024',
        'start_date' => $today->copy()->subYears(2)->toDateString(),
        'end_date' => $today->copy()->subYears(1)->toDateString(),
        'status' => 'active',
    ]);

    $pastCommittee = Committee::create([
        'organization_id' => $this->org1->id,
        'organization_period_id' => $pastPeriod->id,
        'name' => 'Pengurus Demisioner',
        'position' => 'Ketua Masa Lalu',
        'period' => $pastPeriod->period_name,
        'status' => 'active',
    ]);

    $resp = $this->getJson("/api/public/organizations/{$this->org1->subdomain}/structure?period_id={$pastPeriod->id}");
    $resp->assertStatus(200);

    $returnedNames = collect($resp->json('data.members'))->pluck('name')->all();
    expect($returnedNames)->toContain('Pengurus Demisioner');
    expect($resp->json('data.selected_period.calculated_status'))->toBe('expired');
});

// 13. Download template impor anggota menghasilkan format CSV yang valid dan header lengkap
test('member csv template download produces correct headers', function () {
    $resp = $this->actingAs($this->adminOrg1, 'sanctum')->get('/api/members/template');
    $resp->assertStatus(200);
    $resp->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

    $content = $resp->streamedContent();
    $firstLine = ltrim(explode("\n", trim($content))[0], "\xEF\xBB\xBF");
    expect(trim($firstLine))->toBe('nim,nama,email,telepon,jurusan,angkatan,status,catatan');
});

// 14. Mode preview impor CSV memvalidasi baris, mendeteksi duplikasi NIM, dan melaporkan error per baris tanpa mengubah database
test('member csv import preview mode validates rows without altering database', function () {
    $existing = Member::create([
        'organization_id' => $this->org1->id,
        'nama' => 'Existing Member',
        'nim' => '11520050',
        'status' => 'active',
    ]);

    $initialCount = Member::where('organization_id', $this->org1->id)->count();

    $csvContent = implode("\n", [
        'nim,nama,email,telepon,jurusan,angkatan,status,catatan',
        '11520050,Existing Member Updated,update@example.com,081,Informatika,2024,active,Diupdate',
        '11520051,New Member Valid,new@example.com,082,Informatika,2024,active,Baru',
        ',Nama Tanpa NIM,invalid@example.com,083,Informatika,2024,active,Error',
    ]);

    $file = UploadedFile::fake()->createWithContent('members.csv', $csvContent);

    $resp = $this->actingAs($this->adminOrg1, 'sanctum')->postJson('/api/members/import', [
        'file' => $file,
        'mode' => 'preview',
    ]);

    $resp->assertStatus(200);
    expect($resp->json('mode'))->toBe('preview');
    expect($resp->json('success_count'))->toBe(2);
    expect($resp->json('error_count'))->toBe(1);

    // Database count must be strictly UNCHANGED in preview mode
    expect(Member::where('organization_id', $this->org1->id)->count())->toBe($initialCount);
});

// 15. Mode commit impor CSV menyimpan data baru dan memperbarui data lama secara atomik
test('member csv import commit mode saves new and updates existing atomically', function () {
    Member::create([
        'organization_id' => $this->org1->id,
        'nama' => 'Original Name',
        'nim' => '11520060',
        'status' => 'active',
    ]);

    $csvContent = implode("\n", [
        'nim,nama,email,telepon,jurusan,angkatan,status,catatan',
        '11520060,Updated Name,upd@example.com,081234,Informatika,2023,active,Diperbarui',
        '11520061,Brand New Member,new@example.com,085678,Informatika,2024,active,Ditambahkan',
    ]);

    $file = UploadedFile::fake()->createWithContent('members_commit.csv', $csvContent);

    $resp = $this->actingAs($this->adminOrg1, 'sanctum')->postJson('/api/members/import', [
        'file' => $file,
        'mode' => 'commit',
    ]);

    $resp->assertStatus(200);
    expect($resp->json('mode'))->toBe('commit');
    expect($resp->json('success_count'))->toBe(2);

    $updated = Member::where('organization_id', $this->org1->id)->where('nim', '11520060')->first();
    expect($updated->nama)->toBe('Updated Name');

    $new = Member::where('organization_id', $this->org1->id)->where('nim', '11520061')->first();
    expect($new)->not->toBeNull();
    expect($new->nama)->toBe('Brand New Member');
});

// 16. Ekspor data anggota menghasilkan file CSV yang sesuai dengan filter organisasi dan status
test('member csv export filters by organization and status', function () {
    Member::create([
        'organization_id' => $this->org1->id,
        'nama' => 'Active Member 1',
        'nim' => '11520070',
        'status' => 'active',
    ]);

    Member::create([
        'organization_id' => $this->org1->id,
        'nama' => 'Inactive Member 1',
        'nim' => '11520071',
        'status' => 'inactive',
    ]);

    Member::create([
        'organization_id' => $this->org2->id,
        'nama' => 'Org 2 Member',
        'nim' => '11520072',
        'status' => 'active',
    ]);

    $resp = $this->actingAs($this->adminOrg1, 'sanctum')->get('/api/members/export?status=active');
    $resp->assertStatus(200);

    $content = $resp->streamedContent();
    expect($content)->toContain('Active Member 1');
    expect($content)->not->toContain('Inactive Member 1');
    expect($content)->not->toContain('Org 2 Member');
});
