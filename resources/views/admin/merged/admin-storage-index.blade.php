
    <div class="page-head">
        <div><h1>附件存储</h1><p>工单附件按磁盘统计，用于容量规划与清理决策。</p></div>
        <a class="btn btn-ghost" href="{{ route('admin.audit.index', ['action' => 'ticket.attachment_downloaded']) }}">下载审计</a>
    </div>

    <div class="stat-grid">
        <div class="stat-card"><div class="stat-top"><span class="stat-label">附件总数</span></div><div class="stat-value">{{ $totalFiles }}</div><div class="stat-foot">ticket_attachments</div></div>
        <div class="stat-card"><div class="stat-top"><span class="stat-label">占用空间</span></div><div class="stat-value">{{ number_format($totalBytes / 1024 / 1024, 2) }} MB</div><div class="stat-foot">数据库记录容量</div></div>
        <div class="stat-card"><div class="stat-top"><span class="stat-label">单工单配额</span></div><div class="stat-value">{{ number_format($quota / 1024 / 1024, 0) }} MB</div><div class="stat-foot">DEVIY_TICKET_ATTACHMENT_QUOTA</div></div>
        <div class="stat-card"><div class="stat-top"><span class="stat-label">清理策略</span></div><div class="stat-value">90 天</div><div class="stat-foot">关闭工单 · 每天 04:30</div></div>
    </div>

    <div class="panel mt-4">
        <div class="panel-head"><h2>按磁盘统计</h2><span class="meta">仅统计数据库记录，不含桶内孤儿对象</span></div>
        <div class="panel-body tight">
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>磁盘</th><th>附件数</th><th>占用</th><th>占比</th></tr></thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr>
                                <td><span class="chip">{{ $row->disk }}</span></td>
                                <td>{{ $row->files }}</td>
                                <td>{{ number_format($row->bytes / 1024 / 1024, 2) }} MB</td>
                                <td>{{ $totalBytes > 0 ? number_format($row->bytes / $totalBytes * 100, 1) : '0.0' }}%</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="table-empty">暂无附件记录。</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="panel mt-4">
        <div class="panel-head"><h2>附件占用 TOP 5</h2><span class="meta">按客户累计</span></div>
        <div class="panel-body tight">
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>客户</th><th>邮箱</th><th>附件数</th><th>占用</th></tr></thead>
                    <tbody>
                        @forelse ($topUsers as $topUser)
                            <tr>
                                <td>{{ $topUser->name }}</td>
                                <td class="small muted">{{ $topUser->email }}</td>
                                <td>{{ $topUser->files }}</td>
                                <td>{{ number_format($topUser->bytes / 1024 / 1024, 2) }} MB</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="table-empty">暂无客户附件。</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

