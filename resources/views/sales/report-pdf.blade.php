<!doctype html>
<html><head><meta charset="utf-8"><title>{{ $title }}</title>
<style>
  body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #222; }
  h1 { font-size: 15px; margin: 0; color: #6A0DAD; } .meta { color: #666; margin: 2px 0 10px; }
  table { width: 100%; border-collapse: collapse; } th { background: #f1ecf8; text-align: left; padding: 4px; border-bottom: 1px solid #ccc; }
  td { padding: 3px 4px; border-bottom: 1px solid #eee; } td.n { text-align: right; } .foot { margin-top: 8px; color: #888; font-size: 8px; }
</style></head>
<body>
  <h1>{{ $title }}</h1>
  <div class="meta">{{ $company }} · {{ $period }} · generated {{ now()->format('Y-m-d H:i') }}</div>
  <table>
    <thead><tr>@foreach ($headings as $h)<th>{{ $h }}</th>@endforeach</tr></thead>
    <tbody>
      @foreach ($rows as $r)
        <tr>@foreach ($r as $v)<td class="{{ is_numeric($v) ? 'n' : '' }}">{{ is_float($v) ? number_format($v, 2, '.', ' ') : $v }}</td>@endforeach</tr>
      @endforeach
    </tbody>
  </table>
  <div class="foot">Mkhaya Sales · figures are live sales records only; forecasts are estimates.</div>
</body></html>
