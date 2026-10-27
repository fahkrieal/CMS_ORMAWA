<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use App\Services\ActivityLogService;

class UserController extends Controller
{
    /**
     * Tampilkan semua daftar user beserta organisasinya (Scoped by Role & Tenant)
     */
    public function index(Request $request)
    {
        /** @var \App\Models\User $authUser */
        $authUser = $request->user();

        if (!$authUser || (!$authUser->hasRole('Super Admin') && !$authUser->can('users.view'))) {
            abort(403, 'Unauthorized. Anda tidak memiliki hak akses untuk melihat pengguna.');
        }

        $query = User::with(['organization', 'roles']);

        if (!$authUser->hasRole('Super Admin')) {
            $query->where('organization_id', $authUser->organization_id)
                  ->whereDoesntHave('roles', function ($q) {
                      $q->where('name', 'Super Admin');
                  });
        } elseif ($authUser->hasRole('Super Admin')) {
            if ($request->has('organization_id') && $request->organization_id) {
                $query->where('organization_id', $request->organization_id);
            }
            if ($request->boolean('exclude_super_admin')) {
                $query->whereDoesntHave('roles', function ($q) {
                    $q->where('name', 'Super Admin');
                });
            }
        }

        if ($request->has('role') && $request->role) {
            $roleFilter = $request->role;
            $query->whereHas('roles', function ($q) use ($roleFilter) {
                $q->where('name', $roleFilter);
            });
        }

        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->latest()->get()->map(function ($user) {
            $user->role = $user->roles->first()?->name ?? '-';
            return $user;
        });

        return response()->json([
            'status' => 'success',
            'data'   => $users
        ]);
    }

    /**
     * Buat User/Admin Baru & Assign ke Organisasi
     */
    public function store(Request $request)
    {
        /** @var \App\Models\User $authUser */
        $authUser = $request->user();

        if (!$authUser || (!$authUser->hasRole('Super Admin') && !$authUser->can('users.manage'))) {
            abort(403, 'Unauthorized. Anda tidak memiliki hak akses untuk membuat pengguna.');
        }

        $allowedRoles = $authUser->hasRole('Super Admin')
            ? ['Super Admin', 'Admin Organisasi', 'Editor', 'Kontributor']
            : ['Editor', 'Kontributor'];

        $request->validate([
            'name'            => 'required|string|max:255',
            'email'           => 'required|string|email|max:255|unique:users,email',
            'password'        => 'required|string|min:8',
            'role'            => ['required', 'string', Rule::in($allowedRoles)],
            'organization_id' => $authUser->hasRole('Super Admin') ? 'required|exists:organizations,id' : 'nullable',
            'status'          => 'nullable|in:active,inactive',
        ]);

        $organizationId = $authUser->hasRole('Super Admin')
            ? $request->organization_id
            : $authUser->organization_id;

        $user = User::create([
            'name'            => $request->name,
            'email'           => $request->email,
            'password'        => Hash::make($request->password),
            'organization_id' => $organizationId,
            'status'          => $request->status ?? 'active',
        ]);

        // Assign Spatie Role
        $user->assignRole($request->role);

        $user->load(['organization', 'roles']);
        $user->role = $user->roles->first()?->name;

        ActivityLogService::log('create', 'users', 'Membuat akun pengguna: ' . $user->name . ' (' . $user->role . ')', $user);

        return response()->json([
            'status'  => 'success',
            'message' => 'Pengguna ' . $user->role . ' berhasil dibuat!',
            'data'    => $user
        ], 201);
    }

    /**
     * Detail User
     */
    public function show(Request $request, string $id)
    {
        /** @var \App\Models\User $authUser */
        $authUser = $request->user();

        if (!$authUser->hasRole(['Super Admin', 'Admin Organisasi'])) {
            abort(403, 'Unauthorized.');
        }

        $user = User::with(['organization', 'roles'])->findOrFail($id);

        if ($authUser->hasRole('Admin Organisasi') && ($user->organization_id !== $authUser->organization_id || $user->hasRole('Super Admin'))) {
            abort(403, 'Unauthorized. Anda tidak memiliki hak untuk melihat pengguna dari organisasi lain.');
        }

        $user->role = $user->roles->first()?->name ?? '-';

        return response()->json([
            'status' => 'success',
            'data'   => $user
        ]);
    }

    /**
     * Update data User
     */
    public function update(Request $request, string $id)
    {
        /** @var \App\Models\User $authUser */
        $authUser = $request->user();

        if (!$authUser || (!$authUser->hasRole('Super Admin') && !$authUser->can('users.manage'))) {
            abort(403, 'Unauthorized. Anda tidak memiliki hak akses untuk mengedit pengguna.');
        }

        $targetUser = User::with('roles')->findOrFail($id);

        // 1. Tenant Isolation & Protection Checks
        if ($authUser->hasRole('Admin Organisasi')) {
            // Must belong to the exact same organization
            if ((int)$targetUser->organization_id !== (int)$authUser->organization_id) {
                abort(403, 'Unauthorized. Anda tidak dapat mengedit pengguna di luar organisasi Anda.');
            }

            // Cannot edit Super Admin accounts under any circumstance
            if ($targetUser->hasRole('Super Admin')) {
                abort(403, 'Unauthorized. Anda tidak dapat mengedit akun Super Admin.');
            }

            // Cannot edit OTHER Admin Organisasi accounts in the organization
            if ($targetUser->hasRole('Admin Organisasi') && $targetUser->id !== $authUser->id) {
                abort(403, 'Unauthorized. Anda tidak dapat mengedit akun Admin Organisasi lain.');
            }
        }

        $targetIsAdminOrg = $targetUser->hasRole('Admin Organisasi');
        $targetIsSuperAdmin = $targetUser->hasRole('Super Admin');

        // 2. Role Mutation Authorization: Admin Organisasi cannot change the role of an Admin Organisasi (including self)
        if ($authUser->hasRole('Admin Organisasi') && $targetIsAdminOrg && $request->has('role')) {
            if ($request->role !== 'Admin Organisasi') {
                abort(403, 'Unauthorized. Peran Admin Organisasi tidak dapat diubah.');
            }
        }

        // Allowed roles for assignment:
        // Super Admin can assign: ['Super Admin', 'Admin Organisasi', 'Editor', 'Kontributor']
        // Admin Organisasi can assign: ['Editor', 'Kontributor'] (only on Editor/Kontributor targets)
        $allowedRoles = $authUser->hasRole('Super Admin')
            ? ['Super Admin', 'Admin Organisasi', 'Editor', 'Kontributor']
            : ['Editor', 'Kontributor', 'Admin Organisasi'];

        $request->validate([
            'name'            => 'sometimes|required|string|max:255',
            'email'           => ['sometimes', 'required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($targetUser->id)],
            'password'        => 'nullable|string|min:8',
            'role'            => ['sometimes', 'required', 'string', Rule::in($allowedRoles)],
            'organization_id' => $authUser->hasRole('Super Admin') ? 'sometimes|nullable|exists:organizations,id' : 'nullable',
            'status'          => 'sometimes|required|in:active,inactive',
        ]);

        // 3. Build safe update payload
        $userData = [];
        if ($request->has('name')) {
            $userData['name'] = trim($request->name);
        }
        if ($request->has('email')) {
            $userData['email'] = trim($request->email);
        }
        if ($request->has('status')) {
            if ($request->status === 'inactive') {
                if ($targetUser->id === $authUser->id) {
                    return response()->json(['message' => 'Anda tidak dapat menonaktifkan akun Anda sendiri.'], 422);
                }
                if ($targetUser->hasRole('Super Admin')) {
                    $otherActiveSuperAdmins = User::whereHas('roles', function ($q) {
                        $q->where('name', 'Super Admin');
                    })->where('status', 'active')->where('id', '!=', $targetUser->id)->count();

                    if ($otherActiveSuperAdmins === 0) {
                        return response()->json(['message' => 'Tidak dapat menonaktifkan satu-satunya Super Admin yang masih aktif.'], 422);
                    }
                }
            }
            $userData['status'] = $request->status;
        }

        // organization_id can only ever be updated by Super Admin
        if ($authUser->hasRole('Super Admin') && $request->has('organization_id')) {
            $userData['organization_id'] = $request->organization_id;
        }

        if ($request->filled('password')) {
            $userData['password'] = Hash::make($request->password);
        }

        $oldStatus = $targetUser->status;

        if (!empty($userData)) {
            $targetUser->update($userData);

            if ((isset($userData['status']) && $userData['status'] === 'inactive') || isset($userData['password'])) {
                $targetUser->tokens()->delete();
            }
        }

        // 4. Role Synchronization:
        // Only synchronize role if explicitly present AND authorized
        if ($request->has('role') && !empty($request->role)) {
            if ($authUser->hasRole('Super Admin')) {
                $targetUser->syncRoles([$request->role]);
            } elseif ($authUser->hasRole('Admin Organisasi')) {
                // Admin Organisasi can ONLY change roles between Editor and Kontributor on non-Admin-Org targets
                if (!$targetIsAdminOrg && !$targetIsSuperAdmin) {
                    if (in_array($request->role, ['Editor', 'Kontributor'], true)) {
                        $targetUser->syncRoles([$request->role]);
                    }
                }
            }
        }

        // 5. Notification on status change
        if (isset($userData['status']) && $userData['status'] !== $oldStatus) {
            $statusTitle = $userData['status'] === 'active' ? 'Akun Diaktifkan' : 'Akun Dinonaktifkan';
            $statusMessage = $userData['status'] === 'active' ? 'Akun Anda telah diaktifkan kembali.' : 'Akun Anda telah dinonaktifkan.';
            
            \App\Services\NotificationService::send(
                $targetUser,
                'user_status_changed',
                $statusTitle,
                $statusMessage,
                null
            );
        }

        $targetUser->load(['organization', 'roles']);
        $targetUser->role = $targetUser->roles->first()?->name ?? '-';

        ActivityLogService::log('update', 'users', 'Memperbarui akun pengguna: ' . $targetUser->name, $targetUser);

        return response()->json([
            'status'  => 'success',
            'message' => 'Data pengguna berhasil diperbarui!',
            'data'    => $targetUser
        ]);
    }

    /**
     * Hapus User
     */
    public function destroy(Request $request, string $id)
    {
        /** @var \App\Models\User $authUser */
        $authUser = $request->user();

        if (!$authUser || (!$authUser->hasRole('Super Admin') && !$authUser->can('users.manage'))) {
            abort(403, 'Unauthorized. Anda tidak memiliki hak akses untuk menghapus pengguna.');
        }

        $targetUser = User::with('roles')->findOrFail($id);

        if (!$authUser->hasRole('Super Admin')) {
            if ((int)$targetUser->organization_id !== (int)$authUser->organization_id || $targetUser->hasRole('Super Admin') || $targetUser->hasRole('Admin Organisasi') || $targetUser->id === $authUser->id) {
                abort(403, 'Unauthorized. Anda hanya dapat menghapus Editor atau Kontributor di organisasi Anda.');
            }
        }

        $targetUser->delete();

        ActivityLogService::log('delete', 'users', 'Menghapus pengguna: ' . $targetUser->name, clone $targetUser);

        return response()->json([
            'status'  => 'success',
            'message' => 'Pengguna berhasil dihapus!'
        ]);
    }

    /**
     * Activate User (Super Admin & Admin Organisasi)
     */
    public function activate(Request $request, string $id)
    {
        /** @var \App\Models\User $authUser */
        $authUser = $request->user();

        if (!$authUser || (!$authUser->hasRole('Super Admin') && !$authUser->can('users.manage'))) {
            abort(403, 'Unauthorized.');
        }

        $targetUser = User::with('roles')->findOrFail($id);

        if (!$authUser->hasRole('Super Admin')) {
            if ((int)$targetUser->organization_id !== (int)$authUser->organization_id || $targetUser->hasRole('Super Admin')) {
                abort(403, 'Unauthorized.');
            }
        }

        $targetUser->update(['status' => 'active']);

        ActivityLogService::log('activate', 'users', 'Mengaktifkan akun pengguna: ' . $targetUser->name, clone $targetUser);

        return response()->json([
            'status'  => 'success',
            'message' => 'Akun pengguna berhasil diaktifkan.',
            'data'    => $targetUser
        ]);
    }

    /**
     * Deactivate User (Super Admin & Admin Organisasi)
     */
    public function deactivate(Request $request, string $id)
    {
        /** @var \App\Models\User $authUser */
        $authUser = $request->user();

        if (!$authUser || (!$authUser->hasRole('Super Admin') && !$authUser->can('users.manage'))) {
            abort(403, 'Unauthorized.');
        }

        $targetUser = User::with('roles')->findOrFail($id);

        if ($authUser->id === (int)$targetUser->id) {
            return response()->json(['message' => 'Anda tidak dapat menonaktifkan akun Anda sendiri.'], 422);
        }

        if ($targetUser->hasRole('Super Admin')) {
            $otherActiveSuperAdmins = User::whereHas('roles', function ($q) {
                $q->where('name', 'Super Admin');
            })->where('status', 'active')->where('id', '!=', $targetUser->id)->count();

            if ($otherActiveSuperAdmins === 0) {
                return response()->json(['message' => 'Tidak dapat menonaktifkan satu-satunya Super Admin yang masih aktif.'], 422);
            }
        }

        if (!$authUser->hasRole('Super Admin')) {
            if ((int)$targetUser->organization_id !== (int)$authUser->organization_id || $targetUser->hasRole('Super Admin')) {
                abort(403, 'Unauthorized.');
            }
        }

        $targetUser->update(['status' => 'inactive']);

        // Revoke all tokens for the deactivated user
        $targetUser->tokens()->delete();

        ActivityLogService::log('deactivate', 'users', 'Menonaktifkan akun pengguna: ' . $targetUser->name, $targetUser, [
            'target_user_id' => $targetUser->id,
            'target_email' => $targetUser->email,
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Akun pengguna berhasil dinonaktifkan.',
            'data'    => $targetUser
        ]);
    }

    /**
     * Reset Password User
     */
    public function resetPassword(Request $request, string $id)
    {
        /** @var \App\Models\User $authUser */
        $authUser = $request->user();

        if (!$authUser || (!$authUser->hasRole('Super Admin') && !$authUser->can('users.manage'))) {
            abort(403, 'Unauthorized.');
        }

        $request->validate([
            'password' => 'required|string|min:8',
        ], [
            'password.required' => 'Password baru wajib diisi.',
            'password.min' => 'Password minimal 8 karakter.',
        ]);

        $targetUser = User::with('roles')->findOrFail($id);

        if ($authUser->hasRole('Admin Organisasi')) {
            // Cannot reset user of another organization
            if ((int)$targetUser->organization_id !== (int)$authUser->organization_id) {
                abort(403, 'Unauthorized. Anda tidak dapat mereset password pengguna di luar organisasi Anda.');
            }
            // Cannot reset Super Admin
            if ($targetUser->hasRole('Super Admin')) {
                abort(403, 'Unauthorized. Anda tidak dapat mereset password Super Admin.');
            }
            // Cannot reset other Admin Organisasi
            if ($targetUser->hasRole('Admin Organisasi') && $targetUser->id !== $authUser->id) {
                abort(403, 'Unauthorized. Anda tidak dapat mereset password Admin Organisasi lain.');
            }
        }

        $targetUser->update([
            'password' => Hash::make($request->password),
        ]);

        // Revoke all tokens so old sessions cannot persist after admin password reset
        $targetUser->tokens()->delete();

        ActivityLogService::log('reset_password', 'users', 'Mereset password pengguna: ' . $targetUser->name, $targetUser, [
            'target_user_id' => $targetUser->id,
            'target_email' => $targetUser->email,
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Password pengguna berhasil direset!'
        ]);
    }
}