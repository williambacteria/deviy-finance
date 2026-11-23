<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\QuarantineFile;
use App\Services\AuditLogger;
use App\Services\Security\QuarantineService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QuarantineController extends Controller
{
    public function index(): View
    {
        $files = QuarantineFile::query()
            ->with('user')
            ->latest()
            ->paginate(25);

        return view('admin.quarantine.index', compact('files'));
    }

    public function destroy(Request $request, QuarantineFile $file, QuarantineService $quarantine, AuditLogger $audit): RedirectResponse
    {
        $quarantine->delete($file);
        $audit->record($request->user('admin'), 'quarantine.deleted', '删除隔离文件：'.$file->original_name, $file);

        return back()->with('status', '隔离文件已删除。');
    }
}
