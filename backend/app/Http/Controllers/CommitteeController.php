<?php

namespace App\Http\Controllers;

use App\Models\Committee;
use App\Models\Member;
use App\Models\OrganizationPeriod;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CommitteeController extends Controller
{
    /**
     * 1. Ambil daftar pengurus (mendukung filter periode, member, status, pencarian, dan effective_active)
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        if (!$user || (!$user->hasRole('Super Admin') && !$user->can('structure.view'))) {
            abort(403, 'Unauthorized. Anda tidak memiliki izin untuk melihat data pengurus.');
        }

        $query = Committee::with([
            'user:id,name,email',
            'member',
            'statusChangedBy:id,name',
            'organizationPeriod',
            'organization:id,nama'
        ]);

        if (!$user->hasRole('Super Admin')) {
            $query->where('organization_id', $user->organization_id);
        } elseif ($request->filled('organization_id')) {
            $query->where('organization_id', (int) $request->organization_id);
        }

        // Filter periode
        if ($request->filled('period')) {
            $query->where(function($q) use ($request) {
                $q->where('period', $request->period)
                  ->orWhereHas('organizationPeriod', function($qp) use ($request) {
                      $qp->where('period_name', $request->period);
                  });
            });
        }

        if ($request->filled('organization_period_id')) {
            $query->where('organization_period_id', $request->organization_period_id);
        }

        // Filter member
        if ($request->filled('member_id')) {
            $query->where('member_id', $request->member_id);
        }

        // Filter effective_active
        if ($request->has('effective_active') && filter_var($request->effective_active, FILTER_VALIDATE_BOOLEAN)) {
            $query->effectiveActive();
        } elseif ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $searchTerm = '%' . trim($request->search) . '%';
            $query->where(function($q) use ($searchTerm) {
                $q->where('name', 'like', $searchTerm)
                  ->orWhere('position', 'like', $searchTerm)
                  ->orWhere('department', 'like', $searchTerm)
                  ->orWhereHas('member', function($qm) use ($searchTerm) {
                      $qm->where('name', 'like', $searchTerm)
                         ->orWhere('nim', 'like', $searchTerm);
                  });
            });
        }

        $committees = $query->latest()->get()->map(function ($item) {
            return [
                'id'                     => $item->id,
                'organization_id'        => $item->organization_id,
                'organization_period_id' => $item->organization_period_id,
                'period_name'            => $item->organizationPeriod?->period_name ?? $item->period,
                'member_id'              => $item->member_id,
                'member'                 => $item->member,
                'name'                   => $item->name,
                'position'               => $item->position,
                'department'             => $item->department,
                'period'                 => $item->period,
                'start_date'             => $item->start_date?->format('Y-m-d'),
                'end_date'               => $item->end_date?->format('Y-m-d'),
                'notes'                  => $item->notes,
                'photo'                  => $item->photo,
                'photo_url'              => $item->photo_url,
                'status'                 => $item->status,
                'status_mode'            => $item->status_mode ?? 'auto',
                'status_reason'          => $item->status_reason,
                'status_changed_by'      => $item->status_changed_by,
                'status_changed_at'      => $item->status_changed_at?->toIso8601String(),
                'status_changed_by_user' => $item->statusChangedBy,
                'is_active_effective'    => $item->is_active_effective,
                'effective_status'       => $item->effective_status,
                'user_id'                => $item->user_id,
                'user'                   => $item->user,
                'organization_period'    => $item->organizationPeriod,
                'organization'           => $item->organization,
                'created_at'             => $item->created_at?->toIso8601String(),
                'updated_at'             => $item->updated_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'status' => 'success',
            'data'   => $committees
        ]);
    }

    /**
     * 2. Ambil detail 1 pengurus
     */
    public function show($id)
    {
        $user = auth()->user();
        if (!$user || (!$user->hasRole('Super Admin') && !$user->can('structure.view'))) {
            abort(403, 'Unauthorized. Anda tidak memiliki izin untuk melihat data pengurus.');
        }

        $committee = Committee::with([
            'user:id,name,email',
            'member',
            'statusChangedBy:id,name',
            'organizationPeriod',
            'organization:id,nama'
        ])->findOrFail($id);

        if (!$user->hasRole('Super Admin') && (int)$committee->organization_id !== (int)$user->organization_id) {
            abort(403, 'Unauthorized. Anda tidak dapat melihat pengurus organisasi lain.');
        }

        return response()->json([
            'status' => 'success',
            'data'   => [
                'id'                     => $committee->id,
                'organization_id'        => $committee->organization_id,
                'organization_period_id' => $committee->organization_period_id,
                'period_name'            => $committee->organizationPeriod?->period_name ?? $committee->period,
                'member_id'              => $committee->member_id,
                'member'                 => $committee->member,
                'name'                   => $committee->name,
                'position'               => $committee->position,
                'department'             => $committee->department,
                'period'                 => $committee->period,
                'start_date'             => $committee->start_date?->format('Y-m-d'),
                'end_date'               => $committee->end_date?->format('Y-m-d'),
                'notes'                  => $committee->notes,
                'photo'                  => $committee->photo,
                'photo_url'              => $committee->photo_url,
                'status'                 => $committee->status,
                'status_mode'            => $committee->status_mode ?? 'auto',
                'status_reason'          => $committee->status_reason,
                'status_changed_by'      => $committee->status_changed_by,
                'status_changed_at'      => $committee->status_changed_at?->toIso8601String(),
                'status_changed_by_user' => $committee->statusChangedBy,
                'is_active_effective'    => $committee->is_active_effective,
                'effective_status'       => $committee->effective_status,
                'user_id'                => $committee->user_id,
                'user'                   => $committee->user,
                'organization_period'    => $committee->organizationPeriod,
                'organization'           => $committee->organization,
                'created_at'             => $committee->created_at?->toIso8601String(),
                'updated_at'             => $committee->updated_at?->toIso8601String(),
            ]
        ]);
    }

    /**
     * 3. Ambil daftar anggota yang valid untuk ditugaskan sebagai pengurus
     */
    public function linkableMembers(Request $request)
    {
        $user = auth()->user();
        if (!$user || (!$user->hasRole('Super Admin') && !$user->can('structure.manage'))) {
            abort(403, 'Unauthorized. Anda tidak memiliki izin untuk mengelola struktur pengurus.');
        }

        $organizationId = $user->hasRole('Super Admin') && $request->filled('organization_id')
            ? (int) $request->organization_id
            : (int) $user->organization_id;

        if (!$organizationId) {
            return response()->json([
                'status' => 'success',
                'data'   => []
            ]);
        }

        $periodId = $request->get('organization_period_id');
        $currentCommitteeId = $request->get('current_committee_id');

        $members = Member::where('organization_id', $organizationId)
            ->with('user:id,name,email')
            ->orderBy('name')
            ->get();

        $alreadyAssignedMemberIds = [];
        if ($periodId) {
            $assignedQuery = Committee::where('organization_id', $organizationId)
                ->where('organization_period_id', $periodId)
                ->whereNotNull('member_id');

            if ($currentCommitteeId) {
                $assignedQuery->where('id', '!=', (int)$currentCommitteeId);
            }

            $alreadyAssignedMemberIds = $assignedQuery->pluck('member_id')->map(fn($id) => (int)$id)->all();
        }

        $data = $members->map(function ($m) use ($alreadyAssignedMemberIds) {
            return [
                'id'                  => $m->id,
                'nama'                => $m->name,
                'name'                => $m->name,
                'nim'                 => $m->nim,
                'jurusan'             => $m->study_program,
                'study_program'       => $m->study_program,
                'angkatan'            => $m->angkatan,
                'status'              => $m->status,
                'user_id'             => $m->user_id,
                'user'                => $m->user,
                'is_already_assigned' => in_array((int)$m->id, $alreadyAssignedMemberIds, true),
            ];
        });

        return response()->json([
            'status' => 'success',
            'data'   => $data
        ]);
    }

    /**
     * 4. Ambil daftar user yang valid untuk ditautkan ke pengurus (Tenant-Isolated, No Super Admin, Active Only)
     */
    public function linkableUsers(Request $request)
    {
        $user = auth()->user();
        if (!$user || (!$user->hasRole('Super Admin') && !$user->can('structure.manage'))) {
            abort(403, 'Unauthorized. Anda tidak memiliki izin untuk mengelola struktur pengurus.');
        }

        $organizationId = $user->hasRole('Super Admin') && $request->has('organization_id')
            ? (int) $request->organization_id
            : (int) $user->organization_id;

        if (!$organizationId) {
            return response()->json([
                'status' => 'success',
                'data'   => []
            ]);
        }

        $periodId = $request->get('organization_period_id');
        $currentCommitteeId = $request->get('current_committee_id');

        $users = User::query()
            ->where('organization_id', $organizationId)
            ->where('status', 'active')
            ->whereNotNull('organization_id')
            ->whereDoesntHave('roles', function ($q) {
                $q->where('name', 'Super Admin');
            })
            ->with('roles')
            ->orderBy('name')
            ->get();

        $alreadyLinkedUserIds = [];
        if ($periodId) {
            $alreadyLinkedQuery = Committee::where('organization_id', $organizationId)
                ->where('organization_period_id', $periodId)
                ->whereNotNull('user_id');

            if ($currentCommitteeId) {
                $alreadyLinkedQuery->where('id', '!=', (int)$currentCommitteeId);
            }

            $alreadyLinkedUserIds = $alreadyLinkedQuery->pluck('user_id')->map(fn($id) => (int)$id)->all();
        }

        $data = $users->map(function ($u) use ($alreadyLinkedUserIds) {
            $roleName = $u->roles->first()?->name ?? 'Anggota';
            return [
                'id'                => $u->id,
                'name'              => $u->name,
                'email'             => $u->email,
                'role'              => $roleName,
                'organization_id'   => $u->organization_id,
                'status'            => $u->status,
                'is_already_linked' => in_array((int)$u->id, $alreadyLinkedUserIds, true),
            ];
        });

        return response()->json([
            'status' => 'success',
            'data'   => $data
        ]);
    }

    /**
     * 5. Tambah Data Pengurus Baru
     */
    public function store(Request $request)
    {
        $user = auth()->user();
        if (!$user || (!$user->hasRole('Super Admin') && !$user->can('structure.manage'))) {
            abort(403, 'Anda tidak memiliki izin untuk menambah data pengurus.');
        }

        $request->validate([
            'member_id'              => 'nullable|exists:members,id',
            'name'                   => 'required_without:member_id|nullable|string|max:255',
            'position'               => 'required|string|max:255',
            'department'             => 'nullable|string|max:255',
            'period'                 => 'required_without:organization_period_id|nullable|string|max:100',
            'organization_period_id' => 'nullable|exists:organization_periods,id',
            'start_date'             => 'nullable|date',
            'end_date'               => 'nullable|date|after_or_equal:start_date',
            'notes'                  => 'nullable|string|max:1000',
            'photo'                  => 'nullable|image|mimes:jpg,jpeg,png,webp|max:3072',
            'user_id'                => 'nullable|exists:users,id',
            'status'                 => 'nullable|in:active,inactive',
            'status_mode'            => 'nullable|in:auto,manual',
            'status_reason'          => 'nullable|string|max:255',
        ]);

        $organizationId = $user->hasRole('Super Admin') && $request->filled('organization_id') 
            ? (int) $request->organization_id 
            : (int) $user->organization_id;

        // Validasi kepemilikan organization_period_id
        $periodModel = null;
        if ($request->organization_period_id) {
            $periodModel = OrganizationPeriod::find($request->organization_period_id);
            if ($periodModel && (int)$periodModel->organization_id !== $organizationId && !$user->hasRole('Super Admin')) {
                abort(422, 'Periode kepengurusan yang dipilih tidak valid untuk organisasi ini.');
            }
        }

        // Resolusi Member (Single Source of Truth)
        $memberModel = null;
        if ($request->member_id) {
            $memberModel = Member::find($request->member_id);
            if (!$memberModel || ((int)$memberModel->organization_id !== $organizationId && !$user->hasRole('Super Admin'))) {
                abort(422, 'Anggota yang dipilih tidak terdaftar di organisasi ini.');
            }

            // Jika status pengurus aktif tapi anggota nonaktif, tolak penugasan
            $desiredStatus = $request->status ?? 'active';
            if ($desiredStatus === 'active' && $memberModel->status !== 'active') {
                abort(422, 'Anggota ini berstatus ' . $memberModel->status . '. Aktifkan status anggota terlebih dahulu sebelum menugaskan ke kepengurusan aktif.');
            }
        } else if ($request->filled('name')) {
            // Jika name dikirim tanpa member_id, cari apakah anggota dengan nama ini sudah ada di organisasi
            $existingMember = Member::where('organization_id', $organizationId)
                ->where('name', trim($request->name))
                ->first();

            if ($existingMember) {
                $memberModel = $existingMember;
                $request->merge(['member_id' => $existingMember->id]);
            } else {
                // Buat entitas member otomatis agar tidak ada pengurus liar tanpa entitas anggota
                $tempNim = 'AUTO' . date('ymd') . rand(100, 999);
                $memberModel = Member::create([
                    'organization_id' => $organizationId,
                    'name'            => trim($request->name),
                    'nim'             => $tempNim,
                    'status'          => 'active',
                    'notes'           => 'Dibuat otomatis dari penambahan pengurus',
                ]);
                $request->merge(['member_id' => $memberModel->id]);
            }
        }

        // Validasi keunikan Ketua per periode
        $positionTrimmed = trim($request->position);
        if (in_array(strtolower($positionTrimmed), ['ketua', 'ketua umum', 'presiden'], true) && ($request->status ?? 'active') === 'active') {
            $periodIdToCheck = $request->organization_period_id;
            if ($periodIdToCheck) {
                $existingLeader = Committee::where('organization_id', $organizationId)
                    ->where('organization_period_id', $periodIdToCheck)
                    ->whereIn(\Illuminate\Support\Facades\DB::raw('LOWER(position)'), ['ketua', 'ketua umum', 'presiden'])
                    ->where('status', 'active')
                    ->first();

                if ($existingLeader) {
                    abort(422, "Jabatan {$positionTrimmed} sudah terisi oleh {$existingLeader->name} pada periode ini.");
                }
            }
        }

        // Validasi user_id (Keamanan Linking Akun)
        $linkedUser = null;
        $targetUserId = $request->user_id;
        if (!$targetUserId && $memberModel && $memberModel->user_id) {
            $targetUserId = $memberModel->user_id;
        }

        if ($targetUserId) {
            $linkedUser = User::with('roles')->find($targetUserId);
            if (!$linkedUser) {
                abort(422, 'Akun yang dipilih tidak ditemukan.');
            }

            if ($linkedUser->hasRole('Super Admin')) {
                abort(422, 'Akun Super Admin tidak dapat ditautkan sebagai pengurus organisasi.');
            }

            if ((int)$linkedUser->organization_id !== $organizationId && !$user->hasRole('Super Admin')) {
                abort(422, 'Akun yang dipilih tidak dapat ditautkan ke organisasi ini.');
            }

            if ($linkedUser->status !== 'active') {
                abort(422, 'Akun yang dipilih tidak aktif dan tidak dapat ditautkan.');
            }

            $periodIdToCheck = $request->organization_period_id;
            if ($periodIdToCheck) {
                $isAlreadyLinked = Committee::where('organization_id', $organizationId)
                    ->where('organization_period_id', $periodIdToCheck)
                    ->where('user_id', $linkedUser->id)
                    ->exists();

                if ($isAlreadyLinked) {
                    abort(422, 'Akun ini sudah ditautkan ke pengurus lain pada periode tersebut.');
                }
            }
        }

        $photoPath = null;
        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('committees', 'public');
        }

        $periodString = $request->period;
        if ($periodModel && !$periodString) {
            $periodString = $periodModel->period_name;
        }

        $committeeName = trim($memberModel ? $memberModel->name : ($request->name ?? ''));
        $statusMode = $request->input('status_mode', 'auto');
        $statusReason = $request->input('status_reason');

        $committee = Committee::create([
            'organization_id'        => $organizationId,
            'organization_period_id' => $request->organization_period_id,
            'member_id'              => $memberModel?->id ?? $request->member_id,
            'user_id'                => $targetUserId,
            'name'                   => $committeeName,
            'position'               => $positionTrimmed,
            'department'             => $request->department ? trim($request->department) : null,
            'period'                 => $periodString ?? '-',
            'start_date'             => $request->start_date ?? $periodModel?->start_date,
            'end_date'               => $request->end_date ?? $periodModel?->end_date,
            'notes'                  => $request->notes,
            'photo'                  => $photoPath,
            'status'                 => $request->status ?? 'active',
            'status_mode'            => $statusMode,
            'status_reason'          => $statusReason,
            'status_changed_by'      => $statusMode === 'manual' ? $user->id : null,
            'status_changed_at'      => $statusMode === 'manual' ? now() : null,
        ]);

        ActivityLogService::log(
            'committee_created',
            'committees',
            "Pengurus {$committee->name} ({$committee->position}) berhasil ditambahkan ke periode {$committee->period}",
            $committee,
            [
                'organization_id'        => $organizationId,
                'committee_id'           => $committee->id,
                'member_id'              => $committee->member_id,
                'name'                   => $committee->name,
                'position'               => $committee->position,
                'organization_period_id' => $committee->organization_period_id,
                'status_mode'            => $statusMode,
                'status'                 => $committee->status,
            ]
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Data pengurus berhasil ditambahkan!',
            'data'    => $committee->load(['user:id,name,email', 'member', 'statusChangedBy:id,name', 'organizationPeriod'])
        ], 201);
    }

    /**
     * 6. Update Data Pengurus
     */
    public function update(Request $request, $id)
    {
        $user = auth()->user();
        if (!$user || (!$user->hasRole('Super Admin') && !$user->can('structure.manage'))) {
            abort(403, 'Anda tidak memiliki izin untuk mengubah data pengurus.');
        }

        $committee = Committee::with(['user', 'member'])->findOrFail($id);
        $organizationId = (int) $committee->organization_id;

        if (!$user->hasRole('Super Admin') && (int)$user->organization_id !== $organizationId) {
            abort(403, 'Unauthorized. Anda tidak dapat mengubah pengurus organisasi lain.');
        }

        $request->validate([
            'member_id'              => 'nullable|exists:members,id',
            'name'                   => 'sometimes|required|string|max:255',
            'position'               => 'sometimes|required|string|max:255',
            'department'             => 'nullable|string|max:255',
            'period'                 => 'nullable|string|max:100',
            'organization_period_id' => 'nullable|exists:organization_periods,id',
            'start_date'             => 'nullable|date',
            'end_date'               => 'nullable|date|after_or_equal:start_date',
            'notes'                  => 'nullable|string|max:1000',
            'status'                 => 'sometimes|required|in:active,inactive',
            'status_mode'            => 'nullable|in:auto,manual',
            'status_reason'          => 'nullable|string|max:255',
            'photo'                  => 'nullable|image|mimes:jpg,jpeg,png,webp|max:3072',
            'user_id'                => 'nullable|exists:users,id',
        ]);

        $periodIdToCheck = $request->organization_period_id ?? $committee->organization_period_id;
        $periodModel = $periodIdToCheck ? OrganizationPeriod::find($periodIdToCheck) : null;
        if ($periodModel && (int)$periodModel->organization_id !== $organizationId && !$user->hasRole('Super Admin')) {
            abort(422, 'Periode kepengurusan yang dipilih tidak valid untuk organisasi ini.');
        }

        // Resolusi member_id jika diubah
        $memberModel = null;
        if ($request->has('member_id') && $request->member_id) {
            $memberModel = Member::find($request->member_id);
            if (!$memberModel || ((int)$memberModel->organization_id !== $organizationId && !$user->hasRole('Super Admin'))) {
                abort(422, 'Anggota yang dipilih tidak terdaftar di organisasi ini.');
            }
        }

        $targetMember = $memberModel ?? $committee->member;
        $desiredStatus = $request->status ?? $committee->status;
        if ($targetMember && $desiredStatus === 'active' && $targetMember->status !== 'active') {
            abort(422, 'Anggota ini berstatus ' . $targetMember->status . '. Aktifkan status anggota terlebih dahulu.');
        }

        // Validasi keunikan Ketua per periode
        $positionTrimmed = $request->has('position') ? trim($request->position) : $committee->position;
        $statusToCheck = $request->status ?? $committee->status;
        if (in_array(strtolower($positionTrimmed), ['ketua', 'ketua umum', 'presiden'], true) && $statusToCheck === 'active') {
            if ($periodIdToCheck) {
                $existingLeader = Committee::where('organization_id', $organizationId)
                    ->where('organization_period_id', $periodIdToCheck)
                    ->whereIn(\Illuminate\Support\Facades\DB::raw('LOWER(position)'), ['ketua', 'ketua umum', 'presiden'])
                    ->where('status', 'active')
                    ->where('id', '!=', $committee->id)
                    ->first();

                if ($existingLeader) {
                    abort(422, "Jabatan {$positionTrimmed} sudah terisi oleh {$existingLeader->name} pada periode ini.");
                }
            }
        }

        // Validasi user_id jika diubah
        $newLinkedUser = null;
        if ($request->has('user_id') && $request->user_id) {
            $newLinkedUser = User::with('roles')->find($request->user_id);
            if (!$newLinkedUser) {
                abort(422, 'Akun yang dipilih tidak ditemukan.');
            }

            if ($newLinkedUser->hasRole('Super Admin')) {
                abort(422, 'Akun Super Admin tidak dapat ditautkan sebagai pengurus organisasi.');
            }

            if ((int)$newLinkedUser->organization_id !== $organizationId && !$user->hasRole('Super Admin')) {
                abort(422, 'Akun yang dipilih tidak dapat ditautkan ke organisasi ini.');
            }

            if ($newLinkedUser->status !== 'active') {
                abort(422, 'Akun yang dipilih tidak aktif dan tidak dapat ditautkan.');
            }

            if ($periodIdToCheck) {
                $isAlreadyLinked = Committee::where('organization_id', $organizationId)
                    ->where('organization_period_id', $periodIdToCheck)
                    ->where('user_id', $newLinkedUser->id)
                    ->where('id', '!=', $committee->id)
                    ->exists();

                if ($isAlreadyLinked) {
                    abort(422, 'Akun ini sudah ditautkan ke pengurus lain pada periode tersebut.');
                }
            }
        }

        $updateData = $request->only([
            'position', 'department', 'period', 'organization_period_id', 
            'member_id', 'status', 'status_mode', 'status_reason', 'user_id', 
            'start_date', 'end_date', 'notes'
        ]);

        if ($memberModel) {
            $updateData['name'] = $memberModel->name;
        } elseif ($request->has('name') && !$request->has('member_id')) {
            $updateData['name'] = trim($request->name);
        }

        $targetMode = $request->status_mode ?? $committee->status_mode ?? 'auto';
        if ($targetMode === 'auto') {
            $updateData['status'] = 'active';
            $updateData['status_mode'] = 'auto';
        }

        // Tangani perubahan status manual vs otomatis
        $statusChanged = ($request->has('status') && $request->status !== $committee->status);
        $modeChanged = ($request->has('status_mode') && $request->status_mode !== $committee->status_mode);

        if ($statusChanged || $modeChanged) {
            $updateData['status_changed_by'] = $user->id;
            $updateData['status_changed_at'] = now();
        }

        if ($request->hasFile('photo')) {
            if ($committee->photo && Storage::disk('public')->exists($committee->photo)) {
                Storage::disk('public')->delete($committee->photo);
            }
            $updateData['photo'] = $request->file('photo')->store('committees', 'public');
        }

        if ($request->has('organization_period_id') && !$request->has('period') && $periodModel) {
            $updateData['period'] = $periodModel->period_name;
        }

        $previousPosition = $committee->position;
        $previousStatus = $committee->status;
        $committee->update($updateData);

        ActivityLogService::log(
            'committee_updated',
            'committees',
            "Data pengurus {$committee->name} diperbarui (Jabatan: {$previousPosition} -> {$committee->position}, Status: {$previousStatus} -> {$committee->status})",
            $committee,
            [
                'organization_id' => $organizationId,
                'committee_id'    => $committee->id,
                'changed_by'      => $user->name,
                'changes'         => $updateData,
            ]
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Data pengurus berhasil diperbarui!',
            'data'    => $committee->load(['user:id,name,email', 'member', 'statusChangedBy:id,name', 'organizationPeriod'])
        ]);
    }

    /**
     * 7. Hapus Data Pengurus
     */
    public function destroy($id)
    {
        $user = auth()->user();
        if (!$user || (!$user->hasRole('Super Admin') && !$user->can('structure.manage'))) {
            abort(403, 'Anda tidak memiliki izin untuk menghapus data pengurus.');
        }

        $committee = Committee::findOrFail($id);

        if (!$user->hasRole('Super Admin') && (int)$committee->organization_id !== (int)$user->organization_id) {
            abort(403, 'Unauthorized. Anda tidak dapat menghapus pengurus organisasi lain.');
        }

        if ($committee->photo && Storage::disk('public')->exists($committee->photo)) {
            Storage::disk('public')->delete($committee->photo);
        }

        $committeeName = $committee->name;
        $committee->delete();

        ActivityLogService::log(
            'committee_deleted',
            'committees',
            "Pengurus {$committeeName} dihapus dari struktur",
            null,
            ['id' => $id]
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Data pengurus berhasil dihapus!'
        ]);
    }
}
