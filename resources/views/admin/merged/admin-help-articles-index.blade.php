<div class="page-head">
    <div>
        <h1>帮助文档</h1>
        <p>维护帮助中心教程与图文指南，支持草稿、定时发布和封面图片。</p>
    </div>
    <a class="btn btn-primary" href="{{ route('admin.help-articles.create') }}"><x-icon name="plus" :size="15" /> 新建文档</a>
</div>

<div class="panel">
    <div class="panel-body tight">
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>文档</th>
                        <th>分类</th>
                        <th>状态</th>
                        <th>阅读</th>
                        <th>更新时间</th>
                        <th class="actions">操作</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($articles as $article)
                        <tr>
                            <td>
                                <span class="strong">{{ $article->title }}</span>
                                <div class="sub">/help/{{ $article->slug }}</div>
                            </td>
                            <td><span class="chip">{{ $article->category?->name ?? '未分类' }}</span></td>
                            <td><x-status :status="$article->is_published ? 'active' : 'pending'" :label="$article->is_published ? '已发布' : '草稿'" /></td>
                            <td class="small muted">{{ number_format($article->views) }}</td>
                            <td class="small muted">{{ $article->updated_at?->format('Y-m-d H:i') }}</td>
                            <td class="actions">
                                <a class="btn btn-ghost btn-sm" href="{{ route('admin.help-articles.edit', $article) }}">编辑</a>
                                <form method="POST" action="{{ route('admin.help-articles.destroy', $article) }}" style="display:inline" data-confirm="确认删除这篇帮助文档？">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-danger btn-sm" type="submit">删除</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="table-empty">还没有帮助文档，创建第一篇教程。</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="panel-footer">{{ $articles->links('partials.pagination') }}</div>
    </div>
</div>
