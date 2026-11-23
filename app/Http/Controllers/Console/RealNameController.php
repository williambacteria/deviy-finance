<?php

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Services\RealName\PhoneThreeFactorVerifier;
use App\Services\Security\SecureUploadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RealNameController extends Controller
{
    public function show(Request $request): View
    {
        return view('console.real-name', ['user' => $request->user()]);
    }

    public function store(Request $request, PhoneThreeFactorVerifier $verifier, SecureUploadService $uploads): RedirectResponse
    {
        $user = $request->user();
        if (in_array($user->real_name_status, ['verified', 'pending'], true)) {
            return back()->with('status', $user->real_name_status === 'verified' ? '账号已完成实名认证。' : '实名资料正在审核中，请耐心等待。');
        }

        $type = (string) $request->input('real_name_type', 'personal');
        if ($type === 'enterprise') {
            return $this->storeEnterprise($request, $uploads);
        }

        $data = $request->validate([
            'real_name' => ['required', 'string', 'max:40', 'regex:/^[\x{4e00}-\x{9fa5}·]{2,30}$/u'],
            'real_name_id' => ['required', 'string', 'size:18', 'regex:/^\d{17}[\dXx]$/'],
            'real_name_phone' => ['required', 'string', 'regex:/^1[3-9]\d{9}$/'],
        ], [
            'real_name.regex' => '请输入真实姓名，仅支持中文。',
            'real_name_id.size' => '身份证号码必须为 18 位。',
            'real_name_id.regex' => '请输入正确的 18 位身份证号码。',
            'real_name_phone.regex' => '请输入正确的 11 位手机号码。',
        ]);

        $idNumber = strtoupper($data['real_name_id']);
        if (! $this->validChineseId($idNumber)) {
            return back()->withErrors(['real_name_id' => '身份证号码校验失败，请检查后重新输入。'])->withInput();
        }

        $verification = $verifier->verify($data['real_name'], $idNumber, $data['real_name_phone']);
        $status = match ($verification['status']) {
            'verified' => 'verified',
            'mismatch' => 'rejected',
            default => 'pending',
        };

        $user->update([
            'real_name_type' => 'personal',
            'real_name' => $data['real_name'],
            'real_name_id' => $idNumber,
            'real_name_phone' => $data['real_name_phone'],
            'real_name_status' => $status,
            'real_name_submitted_at' => now(),
            'real_name_verified_at' => $status === 'verified' ? now() : null,
            'real_name_reject_reason' => $status === 'rejected' ? $verification['message'] : null,
        ]);

        return back()->with(
            $status === 'rejected' ? 'error' : 'status',
            $status === 'verified' ? '实名认证通过，现在可以购买商品。' : $verification['message'],
        );
    }

    private function storeEnterprise(Request $request, SecureUploadService $uploads): RedirectResponse
    {
        $data = $request->validate([
            'real_name_company' => ['required', 'string', 'max:180'],
            'real_name_credit_code' => ['required', 'string', 'size:18', 'regex:/^[0-9A-Z]{18}$/'],
            'real_name_legal_name' => ['required', 'string', 'max:40', 'regex:/^[\x{4e00}-\x{9fa5}·]{2,30}$/u'],
            'real_name_legal_phone' => ['required', 'string', 'regex:/^1[3-9]\d{9}$/'],
            'real_name_license' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
            'real_name_legal_id' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
        ], [
            'real_name_credit_code.regex' => '请输入 18 位统一社会信用代码。',
            'real_name_legal_name.regex' => '请输入法人真实姓名，仅支持中文。',
            'real_name_legal_phone.regex' => '请输入正确的 11 位法人手机号码。',
        ]);

        $user = $request->user();
        $license = $uploads->store($request->file('real_name_license'), 'real-name/'.$user->id);
        $legalId = $uploads->store($request->file('real_name_legal_id'), 'real-name/'.$user->id);

        $user->update([
            'real_name_type' => 'enterprise',
            'real_name_company' => $data['real_name_company'],
            'real_name_credit_code' => strtoupper($data['real_name_credit_code']),
            'real_name_legal_name' => $data['real_name_legal_name'],
            'real_name_legal_phone' => $data['real_name_legal_phone'],
            'real_name_license' => $license,
            'real_name_legal_id' => $legalId,
            'real_name_status' => 'pending',
            'real_name_submitted_at' => now(),
            'real_name_verified_at' => null,
            'real_name_reject_reason' => null,
        ]);

        return back()->with('status', '企业实名资料已提交，后台审核通过后即可购买商品。');
    }

    private function validChineseId(string $id): bool
    {
        $id = strtoupper($id);
        $weights = [7, 9, 10, 5, 8, 4, 2, 1, 6, 3, 7, 9, 10, 5, 8, 4, 2];
        $checks = ['1', '0', 'X', '9', '8', '7', '6', '5', '4', '3', '2'];
        $sum = 0;
        for ($i = 0; $i < 17; $i++) {
            $sum += (int) $id[$i] * $weights[$i];
        }

        return $checks[$sum % 11] === $id[17];
    }
}
