@pushOnce('styles', 'service-workspace-style')
    <link rel="stylesheet" href="{{ asset('assets/css/service-workspace.css') }}?v={{ filemtime(public_path('assets/css/service-workspace.css')) }}">
@endPushOnce
