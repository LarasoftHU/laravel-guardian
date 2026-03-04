<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Integrity Scan Report</title>
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; line-height: 1.5; color: #333; max-width: 640px; margin: 0 auto; padding: 20px; }
        h1 { font-size: 1.25rem; margin-bottom: 0.5rem; }
        .meta { color: #666; font-size: 0.875rem; margin-bottom: 1.5rem; }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th, td { padding: 0.5rem 0.75rem; text-align: left; border-bottom: 1px solid #eee; }
        th { font-weight: 600; background: #f8f9fa; }
        .status-added { color: #198754; }
        .status-modified { color: #fd7e14; }
        .status-deleted { color: #dc3545; }
        .status-untracked { color: #6f42c1; }
        .status-renamed { color: #0d6efd; }
        .summary { background: #f8f9fa; padding: 1rem; border-radius: 6px; margin-bottom: 1rem; }
        .summary strong { display: inline-block; min-width: 5rem; }
    </style>
</head>
<body>
    <h1>File Integrity Scan Report</h1>
    <div class="meta">Base ref: {{ $report['base_ref'] }} · Scanned at {{ now()->toDateTimeString() }}</div>

    <div class="summary">
        <strong>Total:</strong> {{ $report['summary']['total'] }} changed file(s)<br>
        <strong>Added:</strong> {{ $report['summary']['added'] }} ·
        <strong>Modified:</strong> {{ $report['summary']['modified'] }} ·
        <strong>Deleted:</strong> {{ $report['summary']['deleted'] }} ·
        <strong>Untracked:</strong> {{ $report['summary']['untracked'] ?? 0 }} ·
        <strong>Renamed:</strong> {{ $report['summary']['renamed'] }}
    </div>

    @if(($report['disk_scan']['has_findings'] ?? false))
    <div class="summary" style="background: #fff3cd; margin-top: 0.5rem;">
        <strong>Disk scan alert:</strong><br>
        <strong>Suspicious PHP:</strong> {{ $report['disk_scan']['summary']['suspicious_php_count'] ?? 0 }} file(s) ·
        <strong>Malware patterns:</strong> {{ $report['disk_scan']['summary']['malware_patterns_count'] ?? 0 }} match(es) ·
        <strong>Dangerous extensions:</strong> {{ $report['disk_scan']['summary']['dangerous_files_count'] ?? 0 }} file(s) ·
        <strong>WordPress/CMS-like paths:</strong> {{ $report['disk_scan']['summary']['suspicious_paths_count'] ?? 0 }}
    </div>
    @endif

    @if($report['summary']['total'] > 0)
    <table>
        <thead>
            <tr>
                <th>Status</th>
                <th>File</th>
                <th>To</th>
            </tr>
        </thead>
        <tbody>
            @foreach($report['changed_files']['added'] ?? [] as $file)
            <tr><td class="status-added">Added</td><td colspan="2">{{ $file }}</td></tr>
            @endforeach
            @foreach($report['changed_files']['modified'] ?? [] as $file)
            <tr><td class="status-modified">Modified</td><td colspan="2">{{ $file }}</td></tr>
            @endforeach
            @foreach($report['changed_files']['deleted'] ?? [] as $file)
            <tr><td class="status-deleted">Deleted</td><td colspan="2">{{ $file }}</td></tr>
            @endforeach
            @foreach($report['changed_files']['untracked'] ?? [] as $file)
            <tr><td class="status-untracked">Untracked</td><td colspan="2">{{ $file }}</td></tr>
            @endforeach
            @foreach($report['changed_files']['renamed'] ?? [] as $pair)
            <tr><td class="status-renamed">Renamed</td><td>{{ $pair['from'] }}</td><td>→ {{ $pair['to'] }}</td></tr>
            @endforeach
        </tbody>
    </table>
    @endif

    @if(($report['disk_scan']['has_findings'] ?? false) && !empty($report['disk_scan']['findings']['suspicious_php'] ?? []))
    <h2 style="font-size: 1rem; margin-top: 1.5rem;">Suspicious PHP functions detected</h2>
    <table>
        <thead>
            <tr>
                <th>Disk</th>
                <th>File</th>
                <th>Functions</th>
            </tr>
        </thead>
        <tbody>
            @foreach($report['disk_scan']['findings']['suspicious_php'] as $item)
            <tr><td>{{ $item['disk'] }}</td><td>{{ $item['file'] }}</td><td>{{ implode(', ', $item['functions']) }}</td></tr>
            @endforeach
        </tbody>
    </table>
    @endif

    @if(($report['disk_scan']['has_findings'] ?? false) && !empty($report['disk_scan']['findings']['malware_patterns'] ?? []))
    <h2 style="font-size: 1rem; margin-top: 1.5rem;">Malware patterns detected</h2>
    <table>
        <thead>
            <tr>
                <th>Disk</th>
                <th>File</th>
                <th>Pattern</th>
            </tr>
        </thead>
        <tbody>
            @foreach($report['disk_scan']['findings']['malware_patterns'] as $item)
            <tr><td>{{ $item['disk'] }}</td><td>{{ $item['file'] }}</td><td>{{ $item['pattern'] }}</td></tr>
            @endforeach
        </tbody>
    </table>
    @endif

    @if(($report['disk_scan']['has_findings'] ?? false) && !empty($report['disk_scan']['findings']['suspicious_paths'] ?? []))
    <h2 style="font-size: 1rem; margin-top: 1.5rem;">WordPress/CMS-like paths found</h2>
    <table>
        <thead>
            <tr>
                <th>Location</th>
                <th>File</th>
                <th>Pattern</th>
            </tr>
        </thead>
        <tbody>
            @foreach($report['disk_scan']['findings']['suspicious_paths'] as $item)
            <tr><td>{{ $item['disk'] }}</td><td>{{ $item['file'] }}</td><td>{{ $item['pattern'] }}</td></tr>
            @endforeach
        </tbody>
    </table>
    @endif

    @if(($report['disk_scan']['has_findings'] ?? false) && !empty($report['disk_scan']['findings']['dangerous_files'] ?? []))
    <h2 style="font-size: 1rem; margin-top: 1.5rem;">Dangerous file extensions found</h2>
    <table>
        <thead>
            <tr>
                <th>Disk</th>
                <th>File</th>
                <th>Extension</th>
            </tr>
        </thead>
        <tbody>
            @foreach($report['disk_scan']['findings']['dangerous_files'] as $item)
            <tr><td>{{ $item['disk'] }}</td><td>{{ $item['file'] }}</td><td>{{ $item['extension'] }}</td></tr>
            @endforeach
        </tbody>
    </table>
    @endif

    <p style="margin-top: 1.5rem; font-size: 0.875rem; color: #666;">
        This report was generated by Laravel Guardian. To customize this template, run:<br>
        <code>php artisan vendor:publish --tag="file-integrity-views"</code>
    </p>
</body>
</html>
