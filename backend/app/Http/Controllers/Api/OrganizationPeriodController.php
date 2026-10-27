<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\OrganizationPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\ActivityLogService;
use App\Services\NotificationService;

use Carbon\Carbon;

class OrganizationPeriodController extends Controller
{
    /**
     * Get all periods across all organizations (Super Admin Governance)
     */
    public function globalIndex(Request $request)
    {
        if (!auth()->user()->hasRole('Super Admin')) {
            abort(403, 'Unauthorized. Super Admin only.');
        }

        $query = OrganizationPeriod::with(['organization', 'reviewer'])->latest('created_at');

        if ($request->has('status') && $request->status && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->has('organization_id') && $request->organization_id) {
            $query->where('organization_id', $request->organization_id);
        }

        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('period_name', 'like', "%{$search}%")
                  ->orWhereHas('organization', function ($qo) use ($search) {
                      $qo->where('nama', 'like', "%{$search}%");
                  });
            });
        }

        $periods = $query->get();

        return response()->json([
            'status' => 'success',
            'data' => $periods,
        ]);
    }

    /**
     * Get periods of an organization
     */
    public function index(Request $request, $organizationId)
    {
        $organization = Organization::findOrFail($organizationId);

        // Check Tenant isolation - Only Super Admin or Admin Organisasi of THIS organization can view
        if (!auth()->user()->hasRole('Super Admin') && auth()->user()->organization_id != $organization->id) {
            abort(403, 'Anda tidak memiliki akses ke periode organisasi ini.');
        }

        $periods = $organization->periods()->with(['reviewer', 'organization'])->orderBy('end_date', 'desc')->get();
        $currentPeriod = $organization->currentPeriod();

        return response()->json([
            'status' => 'success',
            'data' => $periods,
            'current_period' => $currentPeriod ? $currentPeriod->load(['reviewer', 'organization']) : null
        ]);
    }

    /**
     * Store (Submit Renewal or Initial Setup)
     */
    public function store(Request $request, $organizationId)
    {
        $organization = Organization::findOrFail($organizationId);

        $user = auth()->user();
        if (!$user || (!$user->hasRole('Super Admin') && $user->organization_id != $organization->id)) {
            abort(403, 'Anda tidak diizinkan membuat periode untuk organisasi ini.');
        }

        if (!$user->hasRole('Super Admin') && !$user->can('periods.manage')) {
             abort(403, 'Anda tidak memiliki izin untuk mengelola atau mengajukan perpanjangan periode.');
        }

        $validated = $request->validate([
            'period_name' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'notes' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($organization, $validated, $user, $request) {
            $status = $user->hasRole('Super Admin') ? 'active' : 'pending';
            if ($request->has('status') && in_array($request->status, ['active', 'pending', 'expired', 'rejected'])) {
                if ($user->hasRole('Super Admin')) {
                    $status = $request->status;
                }
            }

            // If active, deactivate any previous active periods for this organization
            if ($status === 'active') {
                OrganizationPeriod::where('organization_id', $organization->id)
                    ->where('status', 'active')
                    ->update(['status' => 'expired']);
            }

            $period = $organization->periods()->create([
                'period_name' => trim($validated['period_name']),
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'status' => $status,
                'notes' => $validated['notes'] ?? null,
                'approved_by' => $status === 'active' ? $user->id : null,
                'approved_at' => $status === 'active' ? now() : null,
            ]);

            if ($status === 'pending') {
                ActivityLogService::log('create', 'organization_periods', 'Mengajukan perpanjangan periode: ' . $period->period_name, clone $period);
                
                // Notify Super Admin
                NotificationService::sendToSuperAdmins(
                    'period_renewal_submitted',
                    'Pengajuan Perpanjangan Periode',
                    "{$organization->nama} mengajukan perpanjangan periode {$period->period_name}.",
                    '/super-admin/periods',
                    ['organization_id' => $organization->id, 'period_id' => $period->id]
                );
            } else {
                ActivityLogService::log('create', 'organization_periods', 'Menyiapkan periode: ' . $period->period_name, clone $period);
            }

            $period->load(['reviewer', 'organization']);

            return response()->json([
                'status' => 'success',
                'message' => $status === 'active' ? 'Periode berhasil ditambahkan dan berstatus aktif.' : 'Pengajuan periode berhasil dikirim.',
                'data' => $period
            ], 201);
        });
    }

    /**
     * Show single period
     */
    public function show($id)
    {
        $period = OrganizationPeriod::with(['organization', 'reviewer'])->findOrFail($id);

        if (!auth()->user()->hasRole('Super Admin') && auth()->user()->organization_id != $period->organization_id) {
            abort(403, 'Anda tidak memiliki akses ke periode ini.');
        }

        return response()->json([
            'status' => 'success',
            'data' => $period
        ]);
    }

    /**
     * Update period (allowed only for Super Admin)
     */
    public function update(Request $request, $id)
    {
        $period = OrganizationPeriod::with(['organization', 'reviewer'])->findOrFail($id);

        $user = auth()->user();
        if (!$user || !$user->hasRole('Super Admin')) {
            abort(403, 'Unauthorized. Super Admin only.');
        }

        $validated = $request->validate([
            'period_name' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'notes' => 'nullable|string',
        ]);

        // Cek overlap terhadap periode aktif lain milik organisasi ini (kecuali diri sendiri)
        $overlap = $period->organization->periods()
            ->where('id', '!=', $period->id)
            ->where('status', 'active')
            ->where(function($q) use ($validated) {
                $q->whereBetween('start_date', [$validated['start_date'], $validated['end_date']])
                  ->orWhereBetween('end_date', [$validated['start_date'], $validated['end_date']])
                  ->orWhere(function($q2) use ($validated) {
                       $q2->where('start_date', '<=', $validated['start_date'])
                          ->where('end_date', '>=', $validated['end_date']);
                  });
            })->exists();

        if ($overlap) {
            return response()->json([
                'message' => 'Tanggal periode bertabrakan dengan periode lain pada organisasi ini.'
            ], 422);
        }

        $oldStartDate = $period->start_date ? Carbon::parse($period->start_date)->toDateString() : null;
        $oldEndDate = $period->end_date ? Carbon::parse($period->end_date)->toDateString() : null;
        $oldPeriodName = $period->period_name;

        $period->update([
            'period_name' => trim($validated['period_name']),
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'notes' => $validated['notes'] ?? null,
        ]);

        ActivityLogService::log(
            'period_update',
            'organization_periods',
            'Super Admin memperbarui periode organisasi',
            $period,
            [
                'organization_id' => $period->organization_id,
                'organization_name' => $period->organization?->nama,
                'period_id' => $period->id,
                'period_name' => $period->period_name,
                'old_start_date' => $oldStartDate,
                'old_end_date' => $oldEndDate,
                'new_start_date' => Carbon::parse($period->start_date)->toDateString(),
                'new_end_date' => Carbon::parse($period->end_date)->toDateString(),
                'old_period_name' => $oldPeriodName,
                'new_period_name' => $period->period_name,
            ]
        );

        if ($period->status === 'active') {
            NotificationService::sendToOrganizationAdmins(
                $period->organization_id,
                'period_updated',
                'Periode Organisasi Diperbarui',
                'Periode kepengurusan organisasi Anda telah diperbarui oleh Super Admin.',
                '/organization/period',
                [
                    'organization_id' => $period->organization_id,
                    'period_id' => $period->id,
                ]
            );
        }

        $period->load(['organization', 'reviewer']);

        return response()->json([
            'status' => 'success',
            'message' => 'Periode berhasil diperbarui.',
            'data' => $period
        ]);
    }

    /**
     * Super Admin Approve (Automatically deactivates any previously active period)
     */
    public function approve($id)
    {
        if (!auth()->user()->hasRole('Super Admin')) {
            abort(403, 'Hanya Super Admin yang dapat melakukan persetujuan.');
        }

        return DB::transaction(function () use ($id) {
            $period = OrganizationPeriod::with('organization.users')->findOrFail($id);

            if ($period->status === 'active') {
                return response()->json(['message' => 'Periode ini sudah aktif.'], 422);
            }

            // Deactivate any currently active periods for this organization
            OrganizationPeriod::where('organization_id', $period->organization_id)
                ->where('id', '!=', $period->id)
                ->where('status', 'active')
                ->update(['status' => 'expired']);

            $period->update([
                'status' => 'active',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);

            ActivityLogService::log('approve', 'organization_periods', 'Persetujuan perpanjangan periode: ' . $period->period_name, clone $period);

            // Notify Admin Organisasi
            $adminUsers = $period->organization->users()->whereHas('roles', function($q) {
                $q->where('name', 'Admin Organisasi');
            })->get();

            foreach ($adminUsers as $admin) {
                NotificationService::send(
                    $admin,
                    'period_renewal_approved',
                    'Periode Disetujui',
                    "Periode {$period->period_name} organisasi {$period->organization->nama} telah disetujui dan berstatus Aktif.",
                    '/organization/period'
                );
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Periode berhasil disetujui dan diaktifkan. Periode aktif sebelumnya otomatis dinonaktifkan.',
                'data' => $period->load(['organization', 'reviewer'])
            ]);
        });
    }

    /**
     * Super Admin Activate Period
     */
    public function activate($id)
    {
        if (!auth()->user()->hasRole('Super Admin')) {
            abort(403, 'Hanya Super Admin yang diizinkan mengaktifkan periode.');
        }

        return DB::transaction(function () use ($id) {
            $period = OrganizationPeriod::with('organization.users')->findOrFail($id);

            // Deactivate any currently active periods for this organization
            OrganizationPeriod::where('organization_id', $period->organization_id)
                ->where('id', '!=', $period->id)
                ->where('status', 'active')
                ->update(['status' => 'expired']);

            $period->update([
                'status' => 'active',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);

            ActivityLogService::log('period_activate', 'organization_periods', 'Super Admin mengaktifkan periode: ' . $period->period_name, clone $period);

            NotificationService::sendToOrganizationAdmins(
                $period->organization_id,
                'period_activated',
                'Periode Diaktifkan',
                "Periode {$period->period_name} organisasi {$period->organization->nama} telah diaktifkan oleh Super Admin.",
                '/organization/period'
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Periode berhasil diaktifkan. Periode aktif sebelumnya otomatis dinonaktifkan.',
                'data' => $period->load(['organization', 'reviewer'])
            ]);
        });
    }

    /**
     * Super Admin Deactivate Period
     */
    public function deactivate($id)
    {
        if (!auth()->user()->hasRole('Super Admin')) {
            abort(403, 'Hanya Super Admin yang diizinkan menonaktifkan periode.');
        }

        return DB::transaction(function () use ($id) {
            $period = OrganizationPeriod::with('organization.users')->findOrFail($id);

            $period->update([
                'status' => 'expired',
            ]);

            ActivityLogService::log('period_deactivate', 'organization_periods', 'Super Admin menonaktifkan periode: ' . $period->period_name, clone $period);

            NotificationService::sendToOrganizationAdmins(
                $period->organization_id,
                'period_deactivated',
                'Periode Dinonaktifkan',
                "Periode {$period->period_name} organisasi {$period->organization->nama} telah dinonaktifkan oleh Super Admin.",
                '/organization/period'
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Periode berhasil dinonaktifkan.',
                'data' => $period->load(['organization', 'reviewer'])
            ]);
        });
    }

    /**
     * Super Admin Reject
     */
    public function reject(Request $request, $id)
    {
        if (!auth()->user()->hasRole('Super Admin')) {
            abort(403, 'Hanya Super Admin yang dapat menolak pengajuan.');
        }

        $reason = is_string($request->reason) ? trim($request->reason) : '';
        $request->merge(['reason' => $reason]);

        $validated = $request->validate([
            'reason' => 'required|string|min:5',
        ], [
            'reason.required' => 'Alasan penolakan wajib diisi.',
            'reason.min' => 'Alasan penolakan minimal 5 karakter.',
        ]);

        $period = OrganizationPeriod::with('organization.users')->findOrFail($id);

        if ($period->status !== 'pending') {
            return response()->json(['message' => 'Hanya periode berstatus pending yang dapat ditolak.'], 422);
        }

        $period->update([
            'status' => 'rejected',
            'rejection_reason' => $validated['reason'],
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        ActivityLogService::log('reject', 'organization_periods', 'Menolak pengajuan periode: ' . $period->period_name, clone $period, ['reason' => $validated['reason']]);

        // Notify Admin Organisasi
        $adminUsers = $period->organization->users()->whereHas('roles', function($q) {
            $q->where('name', 'Admin Organisasi');
        })->get();

        foreach ($adminUsers as $admin) {
            NotificationService::send(
                $admin,
                'period_renewal_rejected',
                'Pengajuan Periode Ditolak',
                "Pengajuan periode {$period->period_name} ditolak. Alasan: {$validated['reason']}.",
                '/organization/period'
            );
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Periode berhasil ditolak.',
            'data' => $period
        ]);
    }
}
