<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Committee;
use App\Models\Member;
use App\Models\Organization;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Illuminate\Validation\Rule;

class MemberController extends Controller
{
    /**
     * 1. Daftar Anggota Organisasi (Tenant-Isolated, Search, Filter, Pagination)
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        if (!$user || (!$user->hasRole('Super Admin') && !$user->can('structure.view') && !$user->can('organizations.view'))) {
            abort(403, 'Anda tidak memiliki izin untuk melihat data anggota.');
        }

        $organizationId = $user->hasRole('Super Admin') && $request->filled('organization_id')
            ? (int) $request->organization_id
            : (int) $user->organization_id;

        $query = Member::with(['user:id,name,email,status', 'organization:id,nama']);

        if ($organizationId) {
            $query->where('organization_id', $organizationId);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('study_program') || $request->filled('jurusan')) {
            $prog = $request->study_program ?? $request->jurusan;
            $query->where('study_program', 'like', "%{$prog}%");
        }

        if ($request->filled('angkatan')) {
            $angkatan = trim($request->angkatan);
            $query->where(function ($q) use ($angkatan) {
                $q->whereYear('join_date', $angkatan)
                  ->orWhere('study_program', 'like', "%{$angkatan}%");
            });
        }

        if ($request->filled('has_account')) {
            if ($request->has_account === 'yes' || $request->has_account === '1' || $request->has_account === 'true') {
                $query->whereNotNull('user_id');
            } elseif ($request->has_account === 'no' || $request->has_account === '0' || $request->has_account === 'false') {
                $query->whereNull('user_id');
            }
        }

        if ($request->filled('search')) {
            $search = '%' . trim($request->search) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', $search)
                  ->orWhere('nim', 'like', $search)
                  ->orWhere('email', 'like', $search)
                  ->orWhere('study_program', 'like', $search);
            });
        }

        $perPage = $request->get('per_page', 15);

        if ($perPage === 'all' || $perPage == -1) {
            $members = $query->orderBy('name', 'asc')->get();
            return response()->json([
                'status' => 'success',
                'data'   => $members,
                'meta'   => [
                    'total' => $members->count(),
                ]
            ]);
        }

        $paginated = $query->orderBy('name', 'asc')->paginate((int)$perPage);

        return response()->json([
            'status' => 'success',
            'data'   => $paginated->items(),
            'meta'   => [
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
            ]
        ]);
    }

    /**
     * 2. Detail 1 Anggota beserta riwayat kepengurusan
     */
    public function show($id)
    {
        $user = auth()->user();
        if (!$user || (!$user->hasRole('Super Admin') && !$user->can('structure.view') && !$user->can('organizations.view'))) {
            abort(403, 'Anda tidak memiliki izin untuk melihat detail anggota.');
        }

        $member = Member::with([
            'user:id,name,email,status',
            'organization:id,nama,subdomain',
            'committees' => function ($q) {
                $q->with('organizationPeriod')->latest('created_at');
            }
        ])->findOrFail($id);

        if (!$user->hasRole('Super Admin') && (int)$member->organization_id !== (int)$user->organization_id) {
            abort(403, 'Anda tidak memiliki akses ke anggota organisasi lain.');
        }

        return response()->json([
            'status' => 'success',
            'data'   => $member,
        ]);
    }

    /**
     * 3. Tambah Anggota Baru
     */
    public function store(Request $request)
    {
        $user = auth()->user();
        if (!$user || (!$user->hasRole('Super Admin') && !$user->can('structure.manage'))) {
            abort(403, 'Anda tidak memiliki izin untuk menambah data anggota.');
        }

        $organizationId = $user->hasRole('Super Admin') && $request->filled('organization_id')
            ? (int) $request->organization_id
            : (int) $user->organization_id;

        if (!$organizationId) {
            abort(422, 'Organisasi wajib ditentukan.');
        }

        // Normalize aliases
        if ($request->has('nama') && !$request->has('name')) {
            $request->merge(['name' => $request->nama]);
        }
        if ($request->has('telepon') && !$request->has('phone')) {
            $request->merge(['phone' => $request->telepon]);
        }
        if ($request->has('jurusan') && !$request->has('study_program')) {
            $request->merge(['study_program' => $request->jurusan]);
        }
        if ($request->has('catatan') && !$request->has('notes')) {
            $request->merge(['notes' => $request->catatan]);
        }
        if ($request->has('angkatan') && !$request->has('join_date') && strlen((string)$request->angkatan) === 4) {
            $request->merge(['join_date' => "{$request->angkatan}-09-01"]);
        }

        $validated = $request->validate([
            'nim'           => [
                'required',
                'string',
                'max:50',
                Rule::unique('members')->where('organization_id', $organizationId)
            ],
            'name'          => 'required|string|max:255',
            'email'         => 'nullable|email|max:255',
            'phone'         => 'nullable|string|max:50',
            'study_program' => 'nullable|string|max:100',
            'join_date'     => 'nullable|date',
            'status'        => 'nullable|in:active,inactive,alumni',
            'user_id'       => 'nullable|exists:users,id',
            'notes'         => 'nullable|string|max:1000',
        ], [
            'nim.required'  => 'NIM wajib diisi.',
            'nim.unique'    => 'NIM ini sudah terdaftar sebagai anggota pada organisasi Anda.',
            'name.required' => 'Nama lengkap wajib diisi.',
            'email.email'   => 'Format email tidak valid.',
        ]);

        // Validasi user_id jika ditautkan
        if (!empty($validated['user_id'])) {
            $linkedUser = User::find($validated['user_id']);
            if (!$linkedUser || ($linkedUser->organization_id && (int)$linkedUser->organization_id !== $organizationId && !$user->hasRole('Super Admin'))) {
                abort(422, 'Akun pengguna yang dipilih tidak valid untuk organisasi ini.');
            }
            if ($linkedUser->hasRole('Super Admin')) {
                abort(422, 'Akun Super Admin tidak dapat ditautkan sebagai anggota organisasi.');
            }
            // Cegah user yang sama ditautkan ke banyak anggota di organisasi ini
            $isAlreadyLinked = Member::where('organization_id', $organizationId)
                ->where('user_id', $validated['user_id'])
                ->exists();
            if ($isAlreadyLinked) {
                abort(422, 'Akun pengguna ini sudah ditautkan ke anggota lain.');
            }
        }

        $member = Member::create([
            'organization_id' => $organizationId,
            'user_id'         => $validated['user_id'] ?? null,
            'nim'             => trim($validated['nim']),
            'name'            => trim($validated['name']),
            'email'           => !empty($validated['email']) ? trim($validated['email']) : null,
            'phone'           => !empty($validated['phone']) ? trim($validated['phone']) : null,
            'study_program'   => !empty($validated['study_program']) ? trim($validated['study_program']) : null,
            'join_date'       => $validated['join_date'] ?? now()->toDateString(),
            'status'          => $validated['status'] ?? 'active',
            'notes'           => $validated['notes'] ?? null,
        ]);

        ActivityLogService::log(
            'member_created',
            'members',
            "Menambahkan anggota baru: {$member->name} (NIM: {$member->nim})",
            $member,
            ['organization_id' => $organizationId, 'nim' => $member->nim, 'name' => $member->name]
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Data anggota berhasil ditambahkan!',
            'data'    => $member->load('user:id,name,email')
        ], 201);
    }

    /**
     * 4. Update Data Anggota
     */
    public function update(Request $request, $id)
    {
        $user = auth()->user();
        if (!$user || (!$user->hasRole('Super Admin') && !$user->can('structure.manage'))) {
            abort(403, 'Anda tidak memiliki izin untuk mengubah data anggota.');
        }

        $member = Member::findOrFail($id);
        $organizationId = (int) $member->organization_id;

        if (!$user->hasRole('Super Admin') && (int)$user->organization_id !== $organizationId) {
            abort(403, 'Anda tidak dapat mengubah anggota organisasi lain.');
        }

        // Normalize aliases
        if ($request->has('nama') && !$request->has('name')) {
            $request->merge(['name' => $request->nama]);
        }
        if ($request->has('telepon') && !$request->has('phone')) {
            $request->merge(['phone' => $request->telepon]);
        }
        if ($request->has('jurusan') && !$request->has('study_program')) {
            $request->merge(['study_program' => $request->jurusan]);
        }
        if ($request->has('catatan') && !$request->has('notes')) {
            $request->merge(['notes' => $request->catatan]);
        }
        if ($request->has('angkatan') && !$request->has('join_date') && strlen((string)$request->angkatan) === 4) {
            $request->merge(['join_date' => "{$request->angkatan}-09-01"]);
        }

        $validated = $request->validate([
            'nim'           => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('members')->where('organization_id', $organizationId)->ignore($member->id)
            ],
            'name'          => 'sometimes|required|string|max:255',
            'email'         => 'nullable|email|max:255',
            'phone'         => 'nullable|string|max:50',
            'study_program' => 'nullable|string|max:100',
            'join_date'     => 'nullable|date',
            'status'        => 'sometimes|required|in:active,inactive,alumni',
            'user_id'       => 'nullable|exists:users,id',
            'notes'         => 'nullable|string|max:1000',
        ]);

        if (array_key_exists('user_id', $validated) && !empty($validated['user_id'])) {
            $linkedUser = User::find($validated['user_id']);
            if (!$linkedUser || ($linkedUser->organization_id && (int)$linkedUser->organization_id !== $organizationId && !$user->hasRole('Super Admin'))) {
                abort(422, 'Akun pengguna yang dipilih tidak valid untuk organisasi ini.');
            }
            if ($linkedUser->hasRole('Super Admin')) {
                abort(422, 'Akun Super Admin tidak dapat ditautkan sebagai anggota organisasi.');
            }
            $isAlreadyLinked = Member::where('organization_id', $organizationId)
                ->where('user_id', $validated['user_id'])
                ->where('id', '!=', $member->id)
                ->exists();
            if ($isAlreadyLinked) {
                abort(422, 'Akun pengguna ini sudah ditautkan ke anggota lain.');
            }
        }

        $oldStatus = $member->status;
        $member->update($validated);

        ActivityLogService::log(
            'member_updated',
            'members',
            "Memperbarui data anggota: {$member->name} (NIM: {$member->nim})",
            $member,
            ['organization_id' => $organizationId, 'changes' => $validated]
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Data anggota berhasil diperbarui!',
            'data'    => $member->load('user:id,name,email')
        ]);
    }

    /**
     * 5. Hapus Data Anggota
     */
    public function destroy($id)
    {
        $user = auth()->user();
        if (!$user || (!$user->hasRole('Super Admin') && !$user->can('structure.manage'))) {
            abort(403, 'Anda tidak memiliki izin untuk menghapus data anggota.');
        }

        $member = Member::withCount('committees')->findOrFail($id);
        $organizationId = (int) $member->organization_id;

        if (!$user->hasRole('Super Admin') && (int)$user->organization_id !== $organizationId) {
            abort(403, 'Anda tidak dapat menghapus anggota organisasi lain.');
        }

        // Jika anggota memiliki referensi kepengurusan (aktif maupun lampau), tolak penghapusan untuk melindungi audit trail
        if ($member->committees_count > 0 || Committee::where('member_id', $member->id)->exists()) {
            abort(422, 'Anggota ini tidak dapat dihapus karena tercatat dalam struktur kepengurusan organisasi. Ubah status menjadi Alumni atau nonaktif.');
        }

        $memberName = $member->name;
        $memberNim = $member->nim;
        $member->delete();

        ActivityLogService::log(
            'member_deleted',
            'members',
            "Menghapus data anggota: {$memberName} (NIM: {$memberNim})",
            null,
            ['organization_id' => $organizationId, 'nim' => $memberNim, 'name' => $memberName]
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Data anggota berhasil dihapus.'
        ]);
    }

    /**
     * 6. Download Template CSV
     */
    public function template()
    {
        $filename = "template_anggota_ormawa.csv";

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
            'Expires'             => '0',
        ];

        $callback = function () {
            $file = fopen('php://output', 'w');

            // Header kolom format Indonesia
            fputcsv($file, [
                'nim',
                'nama',
                'email',
                'telepon',
                'jurusan',
                'angkatan',
                'status',
                'catatan'
            ]);

            // Sample rows
            fputcsv($file, [
                '11522001',
                'Ahmad Fauzi',
                'ahmad.fauzi@mahasiswa.iti.ac.id',
                '081234567890',
                'Informatika',
                '2024',
                'active',
                'Anggota aktif angkatan 2024'
            ]);
            fputcsv($file, [
                '11522002',
                'Siti Nurhaliza',
                'siti.nurhaliza@mahasiswa.iti.ac.id',
                '081987654321',
                'Informatika',
                '2024',
                'active',
                'Divisi Minat dan Bakat'
            ]);

            fclose($file);
        };

        return Response::stream($callback, 200, $headers);
    }

    /**
     * 7. Export Data Anggota ke CSV
     */
    public function export(Request $request)
    {
        $user = auth()->user();
        if (!$user || (!$user->hasRole('Super Admin') && !$user->can('structure.view') && !$user->can('organizations.view'))) {
            abort(403, 'Anda tidak memiliki izin untuk mengekspor data anggota.');
        }

        $organizationId = $user->hasRole('Super Admin') && $request->filled('organization_id')
            ? (int) $request->organization_id
            : (int) $user->organization_id;

        $org = Organization::find($organizationId);
        $orgNameSlug = $org ? str_replace(' ', '_', strtolower($org->nama)) : 'ormawa';
        $filename = "data_anggota_{$orgNameSlug}_" . date('Ymd_His') . ".csv";

        $query = Member::query();
        if ($organizationId) {
            $query->where('organization_id', $organizationId);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('study_program') || $request->filled('jurusan')) {
            $query->where('study_program', 'like', '%' . ($request->study_program ?? $request->jurusan) . '%');
        }

        if ($request->filled('search')) {
            $search = '%' . trim($request->search) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', $search)
                  ->orWhere('nim', 'like', $search)
                  ->orWhere('email', 'like', $search);
            });
        }

        $members = $query->orderBy('name', 'asc')->get();

        ActivityLogService::log(
            'members_exported',
            'members',
            "Mengekspor {$members->count()} data anggota",
            null,
            ['organization_id' => $organizationId, 'total' => $members->count()]
        );

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
            'Expires'             => '0',
        ];

        $callback = function () use ($members) {
            $file = fopen('php://output', 'w');

            fputcsv($file, [
                'ID',
                'NIM',
                'Nama Lengkap',
                'Email',
                'Nomor Telepon',
                'Program Studi',
                'Tanggal Bergabung',
                'Status',
                'Catatan'
            ]);

            foreach ($members as $m) {
                fputcsv($file, [
                    $m->id,
                    $m->nim,
                    $m->name,
                    $m->email ?? '-',
                    $m->phone ?? '-',
                    $m->study_program ?? '-',
                    $m->join_date ? $m->join_date->format('Y-m-d') : '-',
                    $m->status,
                    $m->notes ?? '-'
                ]);
            }

            fclose($file);
        };

        return Response::stream($callback, 200, $headers);
    }

    /**
     * 8. Import Data Anggota (Dua Mode: Preview & Commit dalam DB::transaction)
     */
    public function import(Request $request)
    {
        $user = auth()->user();
        if (!$user || (!$user->hasRole('Super Admin') && !$user->can('structure.manage'))) {
            abort(403, 'Anda tidak memiliki izin untuk mengimpor data anggota.');
        }

        $organizationId = $user->hasRole('Super Admin') && $request->filled('organization_id')
            ? (int) $request->organization_id
            : (int) $user->organization_id;

        if (!$organizationId) {
            abort(422, 'Organisasi target wajib ditentukan.');
        }

        $request->validate([
            'file'   => 'required|file|mimes:csv,txt|max:5120',
            'mode'   => 'nullable|in:preview,commit',
            'commit' => 'nullable|in:true,false,1,0',
        ], [
            'file.required' => 'File CSV wajib diunggah.',
            'file.mimes'    => 'Format file harus berupa CSV (.csv).',
            'file.max'      => 'Ukuran file maksimal 5MB.',
        ]);

        $mode = $request->input('mode');
        $shouldCommit = ($mode === 'commit') || filter_var($request->input('commit', false), FILTER_VALIDATE_BOOLEAN);

        $path = $request->file('file')->getRealPath();
        $fileHandle = fopen($path, 'r');
        if (!$fileHandle) {
            abort(422, 'Gagal membuka file yang diunggah.');
        }

        // Handle optional BOM
        $bom = fread($fileHandle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($fileHandle);
        }

        $header = fgetcsv($fileHandle, 1000, ',');
        if (!$header) {
            fclose($fileHandle);
            abort(422, 'File CSV kosong.');
        }

        $headerNormalized = array_map(function ($h) {
            return strtolower(trim(preg_replace('/[^a-zA-Z0-9_]/', '', $h)));
        }, $header);

        $findHeader = function (array $candidates) use ($headerNormalized) {
            foreach ($candidates as $c) {
                $idx = array_search($c, $headerNormalized, true);
                if ($idx !== false) {
                    return $idx;
                }
            }
            return false;
        };

        $nimIndex = $findHeader(['nim']);
        $nameIndex = $findHeader(['name', 'nama']);

        if ($nimIndex === false || $nameIndex === false) {
            fclose($fileHandle);
            abort(422, "Kolom wajib 'nim' dan 'nama'/'name' tidak ditemukan pada header CSV.");
        }

        $emailIndex = $findHeader(['email', 'surel']);
        $phoneIndex = $findHeader(['phone', 'telepon', 'no_hp', 'hp', 'telp']);
        $studyProgIndex = $findHeader(['study_program', 'jurusan', 'prodi']);
        $joinDateIndex = $findHeader(['join_date', 'angkatan', 'tahun_masuk']);
        $statusIndex = $findHeader(['status']);
        $notesIndex = $findHeader(['notes', 'catatan', 'keterangan']);

        $existingNims = Member::where('organization_id', $organizationId)->pluck('nim')->all();
        $existingNimMap = array_fill_keys($existingNims, true);

        $fileNims = [];
        $validRows = [];
        $invalidRows = [];
        $rowNumber = 1; // 1 is header

        while (($row = fgetcsv($fileHandle, 2000, ',')) !== false) {
            $rowNumber++;

            // Skip empty rows
            if (empty(array_filter($row))) {
                continue;
            }

            $nim = isset($row[$nimIndex]) ? trim($row[$nimIndex]) : '';
            $name = isset($row[$nameIndex]) ? trim($row[$nameIndex]) : '';
            $email = ($emailIndex !== false && isset($row[$emailIndex])) ? trim($row[$emailIndex]) : null;
            $phone = ($phoneIndex !== false && isset($row[$phoneIndex])) ? trim($row[$phoneIndex]) : null;
            $studyProg = ($studyProgIndex !== false && isset($row[$studyProgIndex])) ? trim($row[$studyProgIndex]) : null;
            $joinDate = ($joinDateIndex !== false && isset($row[$joinDateIndex])) ? trim($row[$joinDateIndex]) : null;
            $status = ($statusIndex !== false && isset($row[$statusIndex])) ? strtolower(trim($row[$statusIndex])) : 'active';
            $notes = ($notesIndex !== false && isset($row[$notesIndex])) ? trim($row[$notesIndex]) : null;

            $errors = [];

            if (empty($nim)) {
                $errors[] = 'NIM tidak boleh kosong.';
            }

            if (empty($name)) {
                $errors[] = 'Nama tidak boleh kosong.';
            }

            if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Format email '{$email}' tidak valid.";
            }

            if (!in_array($status, ['active', 'inactive', 'alumni'], true)) {
                $status = 'active';
            }

            // Normalisasi tanggal jika angkatan ditulis 4 digit tahun
            if (!empty($joinDate) && strlen($joinDate) === 4 && is_numeric($joinDate)) {
                $joinDate = "{$joinDate}-09-01";
            } elseif (!empty($joinDate)) {
                try {
                    $joinDate = \Carbon\Carbon::parse($joinDate)->toDateString();
                } catch (\Throwable) {
                    $joinDate = now()->toDateString();
                }
            } else {
                $joinDate = now()->toDateString();
            }

            // Cek duplikasi di dalam file itu sendiri
            if (!empty($nim)) {
                if (isset($fileNims[$nim])) {
                    $errors[] = "Duplikasi NIM {$nim} terdeteksi di dalam file CSV (baris {$fileNims[$nim]} dan {$rowNumber}).";
                } else {
                    $fileNims[$nim] = $rowNumber;
                }
            }

            $action = isset($existingNimMap[$nim]) ? 'update' : 'create';

            $rowData = [
                'row'           => $rowNumber,
                'nim'           => $nim,
                'name'          => $name,
                'nama'          => $name,
                'email'         => $email,
                'phone'         => $phone,
                'telepon'       => $phone,
                'study_program' => $studyProg,
                'jurusan'       => $studyProg,
                'join_date'     => $joinDate,
                'angkatan'      => $joinDate ? substr($joinDate, 0, 4) : null,
                'status'        => $status,
                'notes'         => $notes,
                'catatan'       => $notes,
                'action'        => $action,
            ];

            if (empty($errors)) {
                $validRows[] = $rowData;
            } else {
                $invalidRows[] = [
                    'row'     => $rowNumber,
                    'nim'     => $nim,
                    'name'    => $name,
                    'nama'    => $name,
                    'message' => implode(' ', $errors),
                ];
            }
        }

        fclose($fileHandle);

        // Jika mode COMMIT dan tidak ada error fatal
        if ($shouldCommit) {
            DB::beginTransaction();
            try {
                $createdCount = 0;
                $updatedCount = 0;

                foreach ($validRows as $item) {
                    $member = Member::where('organization_id', $organizationId)
                        ->where('nim', $item['nim'])
                        ->first();

                    if ($member) {
                        $member->update([
                            'name'          => $item['name'],
                            'email'         => $item['email'] ?: $member->email,
                            'phone'         => $item['phone'] ?: $member->phone,
                            'study_program' => $item['study_program'] ?: $member->study_program,
                            'status'        => $item['status'],
                            'notes'         => $item['notes'] ?: $member->notes,
                        ]);
                        $updatedCount++;
                    } else {
                        Member::create([
                            'organization_id' => $organizationId,
                            'nim'             => $item['nim'],
                            'name'            => $item['name'],
                            'email'           => $item['email'],
                            'phone'           => $item['phone'],
                            'study_program'   => $item['study_program'],
                            'join_date'       => $item['join_date'],
                            'status'          => $item['status'],
                            'notes'           => $item['notes'],
                        ]);
                        $createdCount++;
                    }
                }

                DB::commit();

                ActivityLogService::log(
                    'members_imported',
                    'members',
                    "Impor anggota massal: {$createdCount} ditambahkan, {$updatedCount} diperbarui",
                    null,
                    [
                        'organization_id' => $organizationId,
                        'created'         => $createdCount,
                        'updated'         => $updatedCount,
                        'failed'          => count($invalidRows),
                    ]
                );

                return response()->json([
                    'status'        => 'success',
                    'mode'          => 'commit',
                    'message'       => "Berhasil mengimpor data! {$createdCount} anggota baru ditambahkan, {$updatedCount} diperbarui.",
                    'total_rows'    => count($validRows) + count($invalidRows),
                    'success_count' => count($validRows),
                    'error_count'   => count($invalidRows),
                    'errors'        => $invalidRows,
                ]);
            } catch (\Throwable $e) {
                DB::rollBack();
                abort(500, 'Gagal memproses transaksi database impor: ' . $e->getMessage());
            }
        }

        // Mode PREVIEW (Tidak mengubah database sama sekali)
        return response()->json([
            'status'        => 'success',
            'mode'          => 'preview',
            'message'       => 'Validasi preview berkas CSV selesai.',
            'total_rows'    => count($validRows) + count($invalidRows),
            'success_count' => count($validRows),
            'error_count'   => count($invalidRows),
            'preview'       => array_slice($validRows, 0, 50),
            'errors'        => $invalidRows,
        ]);
    }
}
