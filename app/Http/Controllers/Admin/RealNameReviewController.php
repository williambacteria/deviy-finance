<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Security\SecureUploadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RealNameReviewController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->string('status')->toString();
        $type = $request->string('type')->toString();
        $search = $request->string('q')->trim()->toString();

        $users = User::query()
            ->when($status !== '', fn ($query) => $query->where('real_name_status', $status))
            ->when($type !== '', fn ($query) => $query->where('real_name_type', $type))
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('real_name_company', 'like', "%{$search}%")
                ->orWhere('real_name_credit_code', 'like', "%{$search}%")
                ->orWhere('real_name_legal_name', 'like', "%{$search}%")))
            ->latest('real_name_submitted_at')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $counts = User::query()
            ->selectRaw('real_name_status, count(*) as total')
            ->groupBy('real_name_status')
            ->pluck('total', 'real_name_status');

        return view('admin.real-names.index', compact('users', 'counts', 'status', 'type', 'search'));
    }

    public function edit(User $user): View
    {
        return view('admin.real-names.edit', compact('user'));
    }

    public function update(Request $request, User $user, SecureUploadService $uploads, AuditLogger $audit): RedirectResponse
    {
        $type = (string) $request->input('real_name_type', $user->real_name_type ?: 'personal');
        $data = $request->validate([
            'real_name_type' => ['required', 'in:personal,enterprise'],
            'real_name_status' => ['required', 'in:unverified,pending,verified,rejected'],
            'real_name_reject_reason' => ['nullable', 'string', 'max:255'],
            'real_name' => [$type === 'personal' ? 'required' : 'nullable', 'string', 'max:40'],
            'real_name_id' => [$type === 'personal' ? 'required' : 'nullable', 'string', 'max:18'],
            'real_name_phone' => [$type === 'personal' ? 'required' : 'nullable', 'string', 'max:20'],
            'real_name_company' => [$type === 'enterprise' ? 'required' : 'nullable', 'string', 'max:180'],
            'real_name_credit_code' => [$type === 'enterprise' ? 'required' : 'nullable', 'string', 'max:32'],
            'real_name_legal_name' => [$type === 'enterprise' ? 'required' : 'nullable', 'string', 'max:40'],
            'real_name_legal_phone' => [$type === 'enterprise' ? 'required' : 'nullable', 'string', 'max:20'],
            'real_name_license' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
            'real_name_legal_id' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
        ]);

        $license = $user->real_name_license;
        $legalId = $user->real_name_legal_id;
        if ($request->hasFile('real_name_license')) {
            $license = $uploads->store($request->file('real_name_license'), 'real-name/'.$user->id);
        }
        if ($request->hasFile('real_name_legal_id')) {
            $legalId = $uploads->store($request->file('real_name_legal_id'), 'real-name/'.$user->id);
        }

        $status = $data['real_name_status'];
        $user->update([
            'real_name_type' => $type,
            'real_name_status' => $status,
            'real_name' => $data['real_name'] ?? $user->real_name,
            'real_name_id' => ($data['real_name_id'] ?? null) !== null && ($data['real_name_id'] ?? '') !== '' ? strtoupper((string) $data['real_name_id']) : null,
            'real_name_phone' => $data['real_name_phone'] ?? null,
            'real_name_company' => $data['real_name_company'] ?? null,
            'real_name_credit_code' => ($data['real_name_credit_code'] ?? null) !== null && ($data['real_name_credit_code'] ?? '') !== '' ? strtoupper((string) $data['real_name_credit_code']) : null,
            'real_name_legal_name' => $data['real_name_legal_name'] ?? null,
            'real_name_legal_phone' => $data['real_name_legal_phone'] ?? null,
            'real_name_license' => $type === 'enterprise' ? $license : $user->real_name_license,
            'real_name_legal_id' => $type === 'enterprise' ? $legalId : $user->real_name_legal_id,
            'real_name_submitted_at' => $user->real_name_submitted_at ?? now(),
            'real_name_verified_at' => $status === 'verified' ? ($user->real_name_verified_at ?? now()) : null,
            'real_name_reject_reason' => $status === 'rejected' ? ($data['real_name_reject_reason'] ?? '资料审核未通过') : null,
        ]);

        $audit->record($request->user('admin'), 'customer.real_name_edited', '编辑客户实名资料：'.$user->name, $user);

        return redirect()->route('admin.real-names.index')->with('status', '实名资料已更新。');
    }
}
