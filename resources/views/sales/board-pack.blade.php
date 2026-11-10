<!doctype html>
<html><head><meta charset="utf-8"><title>{{ $dashboard }}</title>
<style>
  @page { margin: 26px 34px; }
  body { font-family: DejaVu Sans, sans-serif; color: #2B2B38; }
  .page { page-break-after: always; position: relative; }
  .page:last-child { page-break-after: auto; }
  .band { height: 8px; background: #6A0DAD; margin-bottom: 120px; }
  .brand { color: #6A0DAD; font-weight: bold; font-size: 12px; letter-spacing: 1px; }
  h1 { font-size: 34px; color: #16161F; margin: 8px 0; }
  h2 { font-size: 18px; color: #16161F; margin: 0 0 12px; }
  .muted { color: #77778A; font-size: 12px; }
  table.kpis { width: 100%; border-collapse: separate; border-spacing: 10px; }
  table.kpis td { width: 25%; background: #F7F5FB; border-radius: 10px; padding: 14px; vertical-align: top; }
  .k-title { font-size: 11px; color: #77778A; font-weight: bold; } .k-val { font-size: 22px; color: #16161F; font-weight: bold; margin: 4px 0; } .k-note { font-size: 9px; color: #77778A; }
  .chart { text-align: center; } .chart img { max-width: 100%; max-height: 470px; }
  .foot { position: absolute; bottom: 0; left: 0; right: 0; font-size: 9px; color: #A0A0B0; }
</style></head>
<body>
  <div class="page">
    <div class="band"></div>
    <div class="brand">{{ strtoupper($company) }} · MKHAYA SALES</div>
    <h1>{{ $dashboard }}</h1>
    <div class="muted">{{ $period }} · prepared {{ now()->format('j F Y') }}</div>
  </div>
  @foreach (array_chunk($numbers, 8) as $chunk)
    <div class="page">
      <h2>Headline numbers</h2>
      <table class="kpis">
        @foreach (array_chunk($chunk, 4) as $row)
          <tr>@foreach ($row as $n)
            @php($lines = array_values(array_filter(array_map('trim', explode("\n", $n['text'])))))
            <td><div class="k-title">{{ $n['title'] }}</div><div class="k-val">{{ $lines[0] ?? '' }}</div><div class="k-note">{{ implode(' · ', array_slice($lines, 1, 3)) }}</div></td>
          @endforeach</tr>
        @endforeach
      </table>
      <div class="foot">{{ $dashboard }} · {{ $period }}</div>
    </div>
  @endforeach
  @foreach ($charts as $c)
    <div class="page">
      <h2>{{ $c['title'] }}</h2>
      <div class="chart"><img src="{{ $c['image'] }}" alt=""></div>
      <div class="foot">{{ $dashboard }} · {{ $period }}</div>
    </div>
  @endforeach
</body></html>
