<!doctype html>
<html><body style="margin:0;background:#F7F7FA;font-family:'Nunito Sans',Arial,sans-serif;color:#2B2B38">
  <table width="100%" cellpadding="0" cellspacing="0" style="padding:28px 12px"><tr><td align="center">
    <table width="560" cellpadding="0" cellspacing="0" style="background:#fff;border:1px solid #EEEEF3;border-radius:18px;padding:28px">
      <tr><td>
        <div style="font-size:12px;font-weight:700;color:#6A0DAD;letter-spacing:.3px">{{ $company }} · MKHAYA SALES</div>
        <h1 style="font-family:Montserrat,Arial,sans-serif;font-size:22px;margin:6px 0 2px;color:#16161F">{{ $title }}</h1>
        <div style="font-size:13px;color:#77778A;margin-bottom:16px">{{ $period }}</div>
        @foreach ($lines as $l)
          <p style="font-size:14px;line-height:1.5;margin:0 0 8px">{{ $l }}</p>
        @endforeach
        <p style="font-size:13px;color:#77778A;margin:18px 0 0">The full report is attached ({{ strtoupper(pathinfo($file['name'], PATHINFO_EXTENSION)) }}).
          You receive this because a schedule was set up in Mkhaya Sales → Reports → Email schedules.</p>
      </td></tr>
    </table>
  </td></tr></table>
</body></html>
