<!doctype html>
<html><head><meta charset="utf-8"><title>{{ $title }}</title>
<style>
  body { font-family: DejaVu Sans, sans-serif; font-size: 9.5px; color: #2B2B38; }
  h1 { font-size: 17px; margin: 0; color: #16161F; } .meta { color: #77778A; margin: 2px 0 14px; }
  .brand { color: #6A0DAD; font-weight: bold; font-size: 9px; letter-spacing: .5px; }
  .kpis td { width: 25%; border: 1px solid #EEEEF3; padding: 8px 10px; vertical-align: top; }
  .kpis small { color: #77778A; display: block; } .kpis b { font-size: 14px; color: #16161F; }
  h2 { font-size: 11.5px; color: #16161F; margin: 16px 0 6px; }
  table.t { width: 100%; border-collapse: collapse; } table.t th { background: #FBFBFD; color: #77778A; text-align: left; padding: 4px; border-bottom: 1px solid #EEEEF3; }
  table.t td { padding: 3px 4px; border-bottom: 1px solid #F4F4F7; } .n { text-align: right; } .up { color: #2E7D32; } .down { color: #C62828; }
  ul { margin: 0; padding-left: 14px; } li { margin-bottom: 3px; } .foot { margin-top: 12px; color: #A0A0B0; font-size: 8px; }
</style></head>
<body>
  @php($r = fn ($v) => $v === null ? '—' : 'R ' . number_format((float) $v, 0, '.', ' '))
  <div class="brand">{{ strtoupper($company ?? '') }} · MKHAYA SALES</div>
  <h1>{{ $title }}</h1>
  <div class="meta">{{ $period }} · generated {{ now()->format('Y-m-d H:i') }}</div>
  <table class="kpis" cellspacing="6" width="100%"><tr>
    <td><small>Net sales</small><b>{{ $r($kpi['net_value'] ?? 0) }}</b></td>
    <td><small>Same period last year</small><b>{{ $r($kpi['last_year'] ?? 0) }}</b></td>
    <td><small>Growth</small><b class="{{ ($kpi['growth_pct'] ?? 0) >= 0 ? 'up' : 'down' }}">{{ isset($kpi['growth_pct']) ? $kpi['growth_pct'] . '%' : '—' }}</b></td>
    <td><small>Target achievement</small><b>{{ isset($kpi['achievement_pct']) ? $kpi['achievement_pct'] . '%' : 'no target' }}</b></td>
  </tr></table>
  <h2>Summary</h2>
  <ul>@foreach ($lines as $l)<li>{{ $l }}</li>@endforeach</ul>
  @foreach ($tables as $t)
    <h2>{{ $t['title'] }}</h2>
    <table class="t"><thead><tr><th>Name</th><th class="n">Net sales</th><th class="n">Last year</th><th class="n">Growth</th></tr></thead><tbody>
      @foreach ($t['rows'] as $row)
        <tr><td>{{ $row[0] }}</td><td class="n">{{ $r($row[1]) }}</td><td class="n">{{ $r($row[2]) }}</td><td class="n {{ ($row[3] ?? 0) >= 0 ? 'up' : 'down' }}">{{ $row[3] === null ? '—' : $row[3] . '%' }}</td></tr>
      @endforeach
    </tbody></table>
  @endforeach
  <div class="foot">Mkhaya Sales · figures from live sales records; open the dashboard for every visual.</div>
</body></html>
