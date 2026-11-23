<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>VNC · {{ $service->name }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}?v=20261124-brand1">
    <style>
        html, body { width:100%; height:100%; margin:0; padding:0; overflow:hidden;background:var(--dt-b-020617,#020617); }
        iframe { display:block; width:100vw; height:100vh;border:0;background:var(--dt-b-020617,#020617); }
    </style>
</head>
<body>
    <iframe
        src="{{ $vncUrl }}"
        title="VNC · {{ $service->name }}"
        allow="clipboard-read; clipboard-write; fullscreen"
        allowfullscreen
    ></iframe>
</body>
</html>
