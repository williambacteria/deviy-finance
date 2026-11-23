<?php

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Models\DomainAllowlistRequest;
use App\Services\Networking\DomainAllowlistSubmission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DomainAllowlistController extends Controller
{
    public function index(Request $request): Response
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:253'], 'region' => ['nullable', Rule::in(array_keys(DomainAllowlistRequest::regions()))]]);
        $search = trim((string) ($data['q'] ?? ''));
        $selectedRegion = (string) ($data['region'] ?? '');
        $history = DomainAllowlistRequest::where('user_id', $request->user()->id)
            ->when($selectedRegion !== '', fn ($query) => $query->where('region', $selectedRegion))
            ->when($search !== '', fn ($query) => $query->where(fn ($nested) => $nested->where('domain', 'like', '%'.$search.'%')->orWhere('target_ip', 'like', '%'.$search.'%')))
            ->orderByDesc('updated_at')->orderByDesc('id')->paginate(12)->withQueryString();
        $lastRequest = session('allowlist_request_id') ? DomainAllowlistRequest::where('user_id', $request->user()->id)->find(session('allowlist_request_id')) : null;

        return response()->view('console.domain-allowlist.index', ['regions' => DomainAllowlistRequest::regions(), 'statuses' => DomainAllowlistRequest::statuses(), 'history' => $history, 'lastRequest' => $lastRequest, 'search' => $search, 'selectedRegion' => $selectedRegion])->header('Cache-Control', 'no-store, private');
    }

    public function store(Request $request, DomainAllowlistSubmission $submission): RedirectResponse
    {
        abort_unless($request->user()?->isActive(), 403);
        $data = $request->validate([
            'region' => ['required', Rule::in(array_keys(DomainAllowlistRequest::regions()))],
            'domain' => ['required', 'string', 'max:253'],
            'target_ip' => ['required', 'ipv4'],
            'resubmit' => ['nullable', 'boolean'],
        ], ['target_ip.ipv4' => '请填写服务器的公网 IPv4 地址，不包含端口。']);
        $data['domain'] = strtolower(trim($data['domain']));
        $data['target_ip'] = trim($data['target_ip']);
        if (function_exists('idn_to_ascii')) {
            $data['domain'] = idn_to_ascii($data['domain'], IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46) ?: $data['domain'];
        }
        if (! preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+(?:[a-z]{2,63}|xn--[a-z0-9](?:[a-z0-9-]{0,57}[a-z0-9])?)$/D', $data['domain']) || filter_var($data['domain'], FILTER_VALIDATE_IP)) {
            throw ValidationException::withMessages(['domain' => '请填写主域名，例如 example.com；不要包含协议、路径、端口或通配符。']);
        }
        if (! filter_var($data['target_ip'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_GLOBAL_RANGE)) {
            throw ValidationException::withMessages(['target_ip' => '请填写服务器真实公网 IPv4 地址，不能使用内网或保留地址。']);
        }
        try {
            $record = $submission->submit($request->user(), ['region' => $data['region'], 'domain' => $data['domain'], 'target_ip' => $data['target_ip']], $request->boolean('resubmit'));
        } catch (\Illuminate\Contracts\Cache\LockTimeoutException) {
            return back()->withErrors(['domain' => '这组域名与 IP 正在处理中，请稍候查看记录。'])->withInput($request->only('region', 'domain', 'target_ip'));
        }

        return redirect()->route('console.domain-allowlist.index', ['region' => $data['region']])->with('allowlist_request_id', $record->id);
    }
}
