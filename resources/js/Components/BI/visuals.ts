// Visualisation library (BI spec §2) — every visual maps to a measure-engine "kind" and an ECharts
// option builder. All numbers arrive pre-computed from the server; this file only draws them.

export type Visual = { key: string; name: string; group: string; kind: string; icon: string; defaults?: Record<string, any>; special?: string }

export const PALETTE = ['#6A0DAD', '#00A8B5', '#E4A800', '#E8572A', '#2E7D32', '#8E5CD9', '#1E88E5', '#C2185B', '#6D4C41', '#546E7A']
export const GOOD = '#2E7D32', AMBER = '#E4A800', BAD = '#C62828', MUTED = '#9AA0A6'

export const GROUPS = ['KPIs & targets', 'Bar & column', 'Line & trend', 'Combination', 'Part-to-whole', 'Variance', 'Relationships', 'Distribution', 'Tables', 'Advanced', 'Forecast', 'Text', 'Maps']

const v = (key: string, name: string, group: string, kind: string, icon: string, defaults: Record<string, any> = {}, special?: string): Visual => ({ key, name, group, kind, icon, defaults, special })

export const VISUALS: Visual[] = [
  v('kpi_card', 'KPI card', 'KPIs & targets', 'kpi', 'tabler-number', { measures: ['net_value'] }),
  v('kpi_sparkline', 'KPI with sparkline', 'KPIs & targets', 'kpi', 'tabler-chart-line', { measures: ['net_value', 'growth_pct'] }),
  v('kpi_target', 'KPI with target', 'KPIs & targets', 'kpi', 'tabler-target', { measures: ['target', 'net_value'] }),
  v('multi_kpi', 'Multi-KPI card', 'KPIs & targets', 'kpi', 'tabler-layout-grid', { measures: ['net_value', 'quantity', 'active_customers', 'growth_pct'] }),
  v('gauge', 'Radial gauge', 'KPIs & targets', 'kpi', 'tabler-gauge', { measures: ['achievement_pct'] }),
  v('linear_gauge', 'Linear gauge', 'KPIs & targets', 'kpi', 'tabler-progress', { measures: ['achievement_pct'] }),
  v('bullet', 'Bullet chart', 'KPIs & targets', 'kpi', 'tabler-chart-bar', { measures: ['target', 'net_value'] }),
  v('progress', 'Progress bar', 'KPIs & targets', 'kpi', 'tabler-loader', { measures: ['achievement_pct'] }),
  v('thermometer', 'Thermometer', 'KPIs & targets', 'kpi', 'tabler-temperature', { measures: ['achievement_pct'] }),
  v('traffic_light', 'Traffic light KPI', 'KPIs & targets', 'kpi', 'tabler-traffic-lights', { measures: ['achievement_pct'] }),
  v('variance_card', 'Variance card', 'KPIs & targets', 'kpi', 'tabler-plus-minus', { measures: ['growth_pct', 'last_year'] }),
  v('run_rate_card', 'Target run-rate card', 'KPIs & targets', 'kpi', 'tabler-run', { measures: ['net_value', 'target'] }),
  v('target_table', 'Scorecard (targets)', 'KPIs & targets', 'kpi', 'tabler-list-check', {}, 'targets'),

  v('column_clustered', 'Clustered column', 'Bar & column', 'category', 'tabler-chart-bar', { measures: ['net_value', 'last_year'], dimension: 'category' }),
  v('column_stacked', 'Stacked column', 'Bar & column', 'time', 'tabler-chart-bar', { measures: ['net_value'], series: 'category', grain: 'month' }),
  v('column_100', '100% stacked column', 'Bar & column', 'time', 'tabler-chart-bar', { measures: ['net_value'], series: 'category', grain: 'month' }),
  v('column_simple', 'Simple column', 'Bar & column', 'time', 'tabler-chart-bar', { measures: ['net_value'], grain: 'day' }),
  v('bar_clustered', 'Clustered bar', 'Bar & column', 'category', 'tabler-chart-bar-off', { measures: ['net_value', 'last_year'], dimension: 'customer', top: 10 }),
  v('bar_stacked', 'Stacked bar', 'Bar & column', 'matrix', 'tabler-chart-bar-off', { measures: ['net_value'], dimension: 'customer', series: 'category', top: 10 }),
  v('bar_100', '100% stacked bar', 'Bar & column', 'matrix', 'tabler-chart-bar-off', { measures: ['net_value'], dimension: 'customer', series: 'category', top: 10 }),
  v('bar_ranked', 'Ranked bar (Top N)', 'Bar & column', 'category', 'tabler-sort-descending', { measures: ['net_value'], dimension: 'customer', top: 10 }),
  v('diverging_bar', 'Diverging bar (growth/decline)', 'Bar & column', 'category', 'tabler-arrows-diff', { measures: ['growth_pct'], dimension: 'customer', top: 12 }),
  v('lollipop', 'Lollipop', 'Bar & column', 'category', 'tabler-lollipop', { measures: ['net_value'], dimension: 'customer', top: 12 }),
  v('small_multiples_bar', 'Small multiples (bar)', 'Bar & column', 'matrix', 'tabler-layout-grid', { measures: ['net_value'], dimension: 'region', series: 'category', top: 4 }),

  v('line', 'Line', 'Line & trend', 'time', 'tabler-chart-line', { measures: ['net_value'], grain: 'month' }),
  v('line_multi', 'Multi-series line', 'Line & trend', 'time', 'tabler-chart-line', { measures: ['net_value', 'last_year'], grain: 'month' }),
  v('line_smooth', 'Smooth line', 'Line & trend', 'time', 'tabler-wave-sine', { measures: ['net_value'], grain: 'month' }),
  v('line_step', 'Step line', 'Line & trend', 'time', 'tabler-stairs', { measures: ['net_value'], grain: 'month' }),
  v('area', 'Area', 'Line & trend', 'time', 'tabler-chart-area', { measures: ['net_value'], grain: 'month' }),
  v('area_stacked', 'Stacked area', 'Line & trend', 'time', 'tabler-chart-area-line', { measures: ['net_value'], series: 'category', grain: 'month' }),
  v('area_100', '100% stacked area', 'Line & trend', 'time', 'tabler-chart-area-line', { measures: ['net_value'], series: 'category', grain: 'month' }),
  v('running_total', 'Running total', 'Line & trend', 'time', 'tabler-trending-up', { measures: ['net_value'], grain: 'month' }),
  v('moving_average', 'Moving average', 'Line & trend', 'time', 'tabler-chart-dots', { measures: ['net_value'], grain: 'week' }),
  v('anomaly_line', 'Anomaly-highlighted line', 'Line & trend', 'time', 'tabler-alert-triangle', { measures: ['net_value'], grain: 'day' }),
  v('small_multiples_line', 'Small multiples (line)', 'Line & trend', 'time', 'tabler-layout-grid', { measures: ['net_value'], series: 'region', grain: 'month', top: 4 }),

  v('combo_column_line', 'Column and line', 'Combination', 'time', 'tabler-chart-arcs', { measures: ['net_value', 'target'], grain: 'month' }),
  v('dual_axis', 'Dual axis (value & quantity)', 'Combination', 'time', 'tabler-axis-y', { measures: ['net_value', 'quantity'], grain: 'month' }),
  v('stacked_column_line', 'Stacked column and line', 'Combination', 'time', 'tabler-chart-arcs', { measures: ['net_value'], series: 'category', grain: 'month' }),
  v('area_line', 'Area and line', 'Combination', 'time', 'tabler-chart-area', { measures: ['net_value', 'last_year'], grain: 'month' }),

  v('pie', 'Pie', 'Part-to-whole', 'category', 'tabler-chart-pie', { measures: ['net_value'], dimension: 'category', top: 6, other: true }),
  v('donut', 'Donut', 'Part-to-whole', 'category', 'tabler-chart-donut', { measures: ['net_value'], dimension: 'customer_group', top: 6, other: true }),
  v('treemap', 'Treemap', 'Part-to-whole', 'treemap', 'tabler-layout-board', { measures: ['net_value'], dimension: 'category', series: 'product' }),
  v('sunburst', 'Sunburst', 'Part-to-whole', 'treemap', 'tabler-sun', { measures: ['net_value'], dimension: 'category', series: 'product' }),
  v('rose', 'Nightingale rose', 'Part-to-whole', 'category', 'tabler-flower', { measures: ['net_value'], dimension: 'region' }),

  v('waterfall', 'Waterfall (sales bridge)', 'Variance', 'waterfall', 'tabler-chart-candle', { dimension: 'customer', top: 8 }),
  v('funnel', 'Funnel', 'Variance', 'category', 'tabler-filter', { measures: ['net_value'], dimension: 'customer_group', top: 6 }),

  v('scatter', 'Scatter', 'Relationships', 'scatter', 'tabler-chart-dots-2', { dimension: 'customer' }),
  v('bubble', 'Bubble', 'Relationships', 'scatter', 'tabler-chart-bubble', { dimension: 'customer' }),
  v('quadrant', 'Quadrant (size vs growth)', 'Relationships', 'scatter', 'tabler-grid-dots', { dimension: 'customer' }),
  v('radar', 'Radar', 'Relationships', 'category', 'tabler-chart-radar', { measures: ['net_value', 'quantity', 'active_customers'], dimension: 'region' }),

  v('histogram', 'Histogram', 'Distribution', 'histogram', 'tabler-chart-histogram', { dimension: 'customer' }),
  v('boxplot', 'Box plot', 'Distribution', 'boxplot', 'tabler-box-align-bottom', { dimension: 'customer', series: 'region' }),
  v('pareto', 'Pareto (80/20)', 'Distribution', 'pareto', 'tabler-chart-arrows', { measures: ['net_value'], dimension: 'customer', top: 20 }),
  v('heatmap_matrix', 'Heatmap', 'Distribution', 'matrix', 'tabler-grid-4x4', { measures: ['net_value'], dimension: 'region', series: 'month' }),
  v('calendar_heatmap', 'Calendar heatmap', 'Distribution', 'calendar', 'tabler-calendar', { measures: ['net_value'] }),

  v('table', 'Table', 'Tables', 'table', 'tabler-table', { measures: ['net_value', 'quantity', 'growth_pct'], dimension: 'customer', top: 50 }),
  v('matrix_table', 'Matrix', 'Tables', 'matrix', 'tabler-table-options', { measures: ['net_value'], dimension: 'customer', series: 'month', top: 20 }),
  v('ranking_table', 'Ranking table', 'Tables', 'table', 'tabler-list-numbers', { measures: ['net_value', 'last_year', 'growth_pct'], dimension: 'product', top: 25 }),
  v('variance_table', 'Variance table', 'Tables', 'time', 'tabler-table-alias', { measures: ['net_value', 'target', 'variance', 'achievement_pct'], grain: 'month' }),

  v('sankey', 'Sankey', 'Advanced', 'sankey', 'tabler-arrows-split', { measures: ['net_value'], dimension: 'category', series: 'brand' }),
  v('cohort', 'Cohort retention', 'Advanced', 'cohort', 'tabler-users-group', {}),
  v('decomposition', 'Decomposition (drill table)', 'Advanced', 'decomposition', 'tabler-binary-tree', { measures: ['net_value'], dimension: 'category', top: 10 }),

  v('forecast_line', 'Forecast with range', 'Forecast', 'forecast', 'tabler-crystal-ball', {}),

  v('smart_narrative', 'Smart narrative', 'Text', 'narrative', 'tabler-align-left', {}),
  v('ai_insight', 'AI insight card', 'Text', 'narrative', 'tabler-sparkles', {}, 'ai'),
  v('text_box', 'Text box', 'Text', 'none', 'tabler-typography', {}, 'text'),
  v('data_status', 'Data freshness & imports', 'Text', 'none', 'tabler-database', {}, 'status'),

  v('map_placeholder', 'Maps (need customer locations)', 'Maps', 'category', 'tabler-map', { dimension: 'region' }, 'map'),
]

export const visual = (key: string) => VISUALS.find(x => x.key === key) ?? VISUALS[0]

// ── formatting ───────────────────────────────────────────────────────────────────────────────

export function fmt(v: any, format = 'currency', compact = true): string {
  if (v === null || v === undefined || Number.isNaN(Number(v))) return '—'
  const n = Number(v)
  if (format === 'percent') return `${n > 0 ? '' : ''}${n.toFixed(1)}%`
  const abs = Math.abs(n)
  const short = compact && abs >= 1e6 ? (n / 1e6).toFixed(abs >= 1e8 ? 0 : 1) + 'm' : compact && abs >= 1e4 ? (n / 1e3).toFixed(0) + 'k' : n.toLocaleString('en-ZA', { maximumFractionDigits: format === 'currency' ? 0 : 1 })
  return format === 'currency' ? `R ${short}` : short
}

// ── option builders ──────────────────────────────────────────────────────────────────────────

const base = (extra: any = {}) => ({
  color: PALETTE,
  textStyle: { fontFamily: 'Nunito Sans, Inter, sans-serif' },
  grid: { left: 8, right: 16, top: 36, bottom: 8, containLabel: true },
  tooltip: { trigger: 'axis', confine: true, backgroundColor: '#fff', borderColor: '#ECECF1', borderWidth: 1, textStyle: { color: '#1F1F29', fontSize: 12 }, extraCssText: 'box-shadow: 0 8px 24px rgba(17,17,26,.08); border-radius: 10px;' },
  legend: { top: 0, right: 0, icon: 'circle', itemWidth: 8, itemHeight: 8, textStyle: { color: '#6B6B7B', fontSize: 11 } },
  ...extra,
})
const axisLabel = (format: string) => ({ color: '#8A8A99', fontSize: 11, formatter: (val: number) => fmt(val, format) })
const catAxis = (data: string[], extra: any = {}) => ({ type: 'category', data, axisLine: { lineStyle: { color: '#E6E6EE' } }, axisTick: { show: false }, ...extra, axisLabel: { color: '#5B5B6E', fontSize: 11, hideOverlap: true, ...(extra.axisLabel ?? {}) } })
const valAxis = (format: string, extra: any = {}) => ({ type: 'value', splitLine: { lineStyle: { color: '#F1F1F5' } }, axisLabel: axisLabel(format), ...extra })
const tip = (format: string) => ({ valueFormatter: (val: number) => fmt(val, format, false) })

export function buildOption(type: string, d: any): any {
  const f = d.formats ?? {}
  const fm = (m: string) => f[m] ?? 'currency'
  const series = d.series ?? []
  const first = series[0]?.measure ?? 'net_value'

  switch (type) {
    case 'column_clustered': case 'column_simple': case 'bar_clustered': case 'bar_ranked': case 'lollipop': {
      const horizontal = type.startsWith('bar') || type === 'lollipop'
      const cats = horizontal ? [...d.categories].reverse() : d.categories
      const s = series.map((x: any, i: number) => ({ name: x.name, type: type === 'lollipop' ? 'scatter' : 'bar', symbolSize: 10, barMaxWidth: 28,
        itemStyle: { borderRadius: horizontal ? [0, 6, 6, 0] : [6, 6, 0, 0], color: i === 1 ? '#D9D2EC' : undefined },
        data: horizontal ? [...x.data].reverse() : x.data, label: { show: type === 'bar_ranked', position: 'right', color: '#6B6B7B', fontSize: 10, formatter: (p: any) => fmt(p.value, fm(x.measure)) } }))
      if (type === 'lollipop') s.push({ type: 'bar', barWidth: 2, data: [...series[0].data].reverse(), itemStyle: { color: '#D9D2EC' }, tooltip: { show: false } })
      return base({ tooltip: { ...base().tooltip, ...tip(fm(first)) }, xAxis: horizontal ? valAxis(fm(first)) : catAxis(cats), yAxis: horizontal ? catAxis(cats, { axisLabel: { width: 130, overflow: 'truncate', color: '#45455A', fontSize: 11 } }) : valAxis(fm(first)), series: s })
    }
    case 'diverging_bar': {
      const cats = [...d.categories].reverse(), data = [...series[0].data].reverse()
      return base({ tooltip: { ...base().tooltip, ...tip(fm(first)) }, xAxis: valAxis(fm(first)), yAxis: catAxis(cats, { axisLabel: { width: 130, overflow: 'truncate', fontSize: 11 } }),
        series: [{ type: 'bar', barMaxWidth: 18, data: data.map((val: number) => ({ value: val, itemStyle: { color: (val ?? 0) >= 0 ? GOOD : BAD, borderRadius: (val ?? 0) >= 0 ? [0, 6, 6, 0] : [6, 0, 0, 6] } })) }] })
    }
    case 'column_stacked': case 'column_100': case 'area_stacked': case 'area_100': case 'stacked_column_line': {
      const pct = type.endsWith('100'), area = type.startsWith('area')
      const totals = d.categories.map((_: any, i: number) => series.reduce((a: number, s: any) => a + (s.data[i] ?? 0), 0))
      const s = series.map((x: any) => ({ name: x.name, type: area ? 'line' : 'bar', stack: 'a', smooth: true, symbol: 'none', areaStyle: area ? { opacity: .85 } : undefined, barMaxWidth: 36,
        data: pct ? x.data.map((val: number, i: number) => totals[i] ? +(val / totals[i] * 100).toFixed(1) : 0) : x.data }))
      if (type === 'stacked_column_line') s.push({ name: 'Total', type: 'line', smooth: true, data: totals, itemStyle: { color: '#1F1F29' }, symbolSize: 6 })
      return base({ tooltip: { ...base().tooltip, ...tip(pct ? 'percent' : fm(first)) }, xAxis: catAxis(d.categories, { boundaryGap: !area }), yAxis: valAxis(pct ? 'percent' : fm(first), pct ? { max: 100 } : {}), series: s })
    }
    case 'line': case 'line_multi': case 'line_smooth': case 'line_step': case 'area': case 'area_line': case 'running_total': case 'moving_average': case 'anomaly_line': case 'small_multiples_line': {
      const s = series.map((x: any, i: number) => {
        let data = x.data
        if (type === 'running_total') { let r = 0; data = data.map((val: number) => (r += val ?? 0)) }
        return { name: x.name, type: 'line', smooth: type !== 'line_step' && type !== 'line', step: type === 'line_step' ? 'middle' : undefined, symbol: 'circle', symbolSize: 5, showSymbol: d.categories.length < 40,
          lineStyle: { width: i === 0 ? 3 : 2, type: x.measure === 'last_year' || x.measure === 'target' ? 'dashed' : 'solid' },
          areaStyle: (type === 'area' || (type === 'area_line' && i === 0)) ? { opacity: .15 } : undefined, data,
          markPoint: type === 'anomaly_line' && i === 0 ? { symbol: 'pin', symbolSize: 34, itemStyle: { color: BAD }, data: (d.anomalies ?? []).map((idx: number) => ({ coord: [idx, data[idx]], value: '!' })) } : undefined }
      })
      if (type === 'moving_average') {
        const src = series[0]?.data ?? []
        s.push({ name: '4-period average', type: 'line', smooth: true, symbol: 'none', lineStyle: { width: 2, color: AMBER }, data: src.map((_: any, i: number) => i < 3 ? null : +(src.slice(i - 3, i + 1).reduce((a: number, b: number) => a + (b ?? 0), 0) / 4).toFixed(2)) } as any)
      }
      return base({ tooltip: { ...base().tooltip, ...tip(fm(first)) }, xAxis: catAxis(d.categories, { boundaryGap: false }), yAxis: valAxis(fm(first)), dataZoom: d.categories.length > 40 ? [{ type: 'inside' }] : [], series: s })
    }
    case 'combo_column_line': case 'dual_axis': {
      const dual = type === 'dual_axis'
      return base({ tooltip: { ...base().tooltip }, xAxis: catAxis(d.categories), yAxis: dual ? [valAxis(fm(series[0]?.measure)), valAxis(fm(series[1]?.measure), { splitLine: { show: false } })] : valAxis(fm(first)),
        series: series.map((x: any, i: number) => ({ name: x.name, type: i === 0 ? 'bar' : 'line', yAxisIndex: dual && i === 1 ? 1 : 0, barMaxWidth: 32, smooth: true, symbolSize: 6,
          itemStyle: { borderRadius: [6, 6, 0, 0] }, lineStyle: { type: x.measure === 'last_year' ? 'dashed' : 'solid', width: 2.5 }, data: x.data, tooltip: tip(fm(x.measure)) })) })
    }
    case 'pie': case 'donut': case 'rose': case 'funnel': {
      const data = d.categories.map((c: string, i: number) => ({ name: c, value: series[0]?.data[i] ?? 0 }))
      if (type === 'funnel') return base({ tooltip: { trigger: 'item', ...tip(fm(first)) }, legend: { show: false }, series: [{ type: 'funnel', sort: 'descending', gap: 2, label: { formatter: '{b}' }, data }] })
      return base({ tooltip: { trigger: 'item', ...tip(fm(first)) }, legend: { type: 'scroll', bottom: 0, top: 'auto', left: 'center' },
        series: [{ type: 'pie', radius: type === 'pie' ? ['0%', '68%'] : ['46%', '72%'], roseType: type === 'rose' ? 'radius' : undefined, center: ['50%', '45%'], itemStyle: { borderColor: '#fff', borderWidth: 2, borderRadius: 6 },
          label: { show: d.categories.length <= 6, formatter: '{d}%', color: '#45455A' }, data }] })
    }
    case 'treemap': case 'sunburst': {
      const data = (d.tree ?? []).map((p: any) => ({ name: p.name, value: p.value, children: p.children }))
      return type === 'treemap'
        ? { color: PALETTE, tooltip: { formatter: (p: any) => `${p.name}<br><b>${fmt(p.value, 'currency', false)}</b>` }, series: [{ type: 'treemap', roam: false, nodeClick: 'zoomToNode', breadcrumb: { bottom: 0 }, top: 4, left: 4, right: 4, bottom: 28, itemStyle: { borderColor: '#fff', borderWidth: 2, gapWidth: 2, borderRadius: 6 }, levels: [{ itemStyle: { gapWidth: 3 } }, { colorSaturation: [0.35, 0.6], itemStyle: { gapWidth: 1 } }], label: { formatter: '{b}' }, upperLabel: { show: true, height: 22, color: '#fff' }, data }] }
        : { color: PALETTE, tooltip: { formatter: (p: any) => `${p.name}<br><b>${fmt(p.value, 'currency', false)}</b>` }, series: [{ type: 'sunburst', radius: ['15%', '90%'], itemStyle: { borderColor: '#fff', borderWidth: 2, borderRadius: 4 }, label: { rotate: 'radial', fontSize: 10, minAngle: 8 }, data }] }
    }
    case 'waterfall': {
      const names = [d.start.name, ...d.steps.map((s: any) => s.name), d.end.name]
      let run = d.start.value
      const helper: number[] = [0], up: any[] = [d.start.value], down: any[] = ['-']
      d.steps.forEach((s: any) => { if (s.value >= 0) { helper.push(run); up.push(s.value); down.push('-') } else { helper.push(run + s.value); up.push('-'); down.push(-s.value) } run += s.value })
      helper.push(0); up.push(d.end.value); down.push('-')
      return base({ legend: { show: false }, tooltip: { trigger: 'axis', formatter: (ps: any[]) => { const i = ps[0].dataIndex; const val = i === 0 ? d.start.value : i === names.length - 1 ? d.end.value : d.steps[i - 1].value; return `${names[i]}<br><b>${fmt(val, 'currency', false)}</b>` } },
        xAxis: catAxis(names, { axisLabel: { interval: 0, rotate: names.length > 6 ? 30 : 0, width: 90, overflow: 'truncate', fontSize: 10 } }), yAxis: valAxis('currency'),
        series: [{ type: 'bar', stack: 'w', itemStyle: { color: 'transparent' }, data: helper },
          { type: 'bar', stack: 'w', barMaxWidth: 40, data: up.map((val, i) => val === '-' ? '-' : { value: val, itemStyle: { color: i === 0 || i === names.length - 1 ? '#6A0DAD' : GOOD, borderRadius: 4 } }) },
          { type: 'bar', stack: 'w', barMaxWidth: 40, itemStyle: { color: BAD, borderRadius: 4 }, data: down }] })
    }
    case 'scatter': case 'bubble': case 'quadrant': {
      const pts = d.points ?? []
      const maxQ = Math.max(1, ...pts.map((p: any) => p.value[2]))
      return base({ legend: { show: false }, grid: { left: 8, right: 24, top: 24, bottom: 8, containLabel: true },
        tooltip: { trigger: 'item', formatter: (p: any) => `<b>${p.data.name}</b><br>Sales ${fmt(p.data.value[0], 'currency', false)}<br>Growth ${fmt(p.data.value[1], 'percent')}<br>Quantity ${fmt(p.data.value[2], 'number', false)}` },
        xAxis: valAxis('currency', { name: 'Sales', nameLocation: 'middle', nameGap: 28, scale: true }), yAxis: valAxis('percent', { name: 'Growth vs last year', scale: true }),
        series: [{ type: 'scatter', data: pts, symbolSize: (val: number[]) => type === 'scatter' ? 9 : 8 + 34 * Math.sqrt(val[2] / maxQ),
          itemStyle: { color: (p: any) => p.data.value[1] >= 0 ? (p.data.value[0] >= d.x_mid ? '#6A0DAD' : '#00A8B5') : (p.data.value[0] >= d.x_mid ? AMBER : BAD), opacity: .75 },
          markLine: type === 'quadrant' ? { silent: true, symbol: 'none', lineStyle: { color: '#C8C8D4', type: 'dashed' }, label: { show: false }, data: [{ xAxis: d.x_mid }, { yAxis: 0 }] } : undefined }] })
    }
    case 'radar': {
      const max = series.map((s: any) => Math.max(1, ...s.data) * 1.1)
      return { color: PALETTE, tooltip: {}, legend: { bottom: 0, icon: 'circle', itemWidth: 8 }, radar: { indicator: series.map((s: any, i: number) => ({ name: s.name, max: max[i] })), radius: '62%', splitArea: { areaStyle: { color: ['#FBFAFE', '#fff'] } } },
        series: [{ type: 'radar', data: d.categories.slice(0, 6).map((c: string, i: number) => ({ name: c, value: series.map((s: any) => s.data[i] ?? 0), areaStyle: { opacity: .12 } })) }] }
    }
    case 'histogram': return base({ legend: { show: false }, tooltip: { trigger: 'axis', formatter: (ps: any[]) => `${fmt(d.bins[ps[0].dataIndex], 'currency', false)} – ${fmt(d.bins[ps[0].dataIndex] + d.width, 'currency', false)}<br><b>${ps[0].value}</b> ${d.entity}s` },
      xAxis: catAxis((d.bins ?? []).map((b: number) => fmt(b, 'currency'))), yAxis: valAxis('number'), series: [{ type: 'bar', barCategoryGap: '4%', data: d.counts, itemStyle: { color: '#8E5CD9', borderRadius: [4, 4, 0, 0] } }] })
    case 'boxplot': return base({ legend: { show: false }, tooltip: { trigger: 'item', formatter: (p: any) => `<b>${p.name}</b><br>max ${fmt(p.data[5], 'currency')}<br>Q3 ${fmt(p.data[4], 'currency')}<br>median ${fmt(p.data[3], 'currency')}<br>Q1 ${fmt(p.data[2], 'currency')}<br>min ${fmt(p.data[1], 'currency')}` },
      xAxis: catAxis(d.groups ?? []), yAxis: valAxis('currency'), series: [{ type: 'boxplot', data: d.boxes, itemStyle: { color: '#EDE4F7', borderColor: '#6A0DAD' } }] })
    case 'pareto': return base({ tooltip: { ...base().tooltip }, xAxis: catAxis(d.categories, { axisLabel: { rotate: 35, width: 90, overflow: 'truncate', fontSize: 10 } }), yAxis: [valAxis('currency'), valAxis('percent', { max: 100, splitLine: { show: false } })],
      series: [{ name: series[0]?.name, type: 'bar', barMaxWidth: 26, data: series[0]?.data, itemStyle: { borderRadius: [5, 5, 0, 0] }, tooltip: tip('currency') },
        { name: 'Cumulative %', type: 'line', yAxisIndex: 1, smooth: true, data: d.cumulative, itemStyle: { color: AMBER }, markLine: { silent: true, symbol: 'none', data: [{ yAxis: 80 }], lineStyle: { color: BAD, type: 'dashed' }, label: { formatter: '80%' } }, tooltip: tip('percent') }] })
    case 'heatmap_matrix': case 'cohort': {
      const pct = type === 'cohort'
      return { tooltip: { position: 'top', formatter: (p: any) => pct ? `${d.rows[p.data[1]]}, month ${p.data[0]}<br><b>${p.data[2]}%</b> still buying (${p.data[3]})` : `${d.rows[p.data[1]]} · ${d.columns[p.data[0]]}<br><b>${fmt(p.data[2], 'currency', false)}</b>` },
        grid: { left: 8, right: 8, top: 8, bottom: 40, containLabel: true }, xAxis: catAxis(d.columns.map(String), { splitArea: { show: true } }), yAxis: catAxis(d.rows, { axisLabel: { width: 120, overflow: 'truncate', fontSize: 11 } }),
        visualMap: { min: 0, max: pct ? 100 : (d.max || 1), calculable: true, orient: 'horizontal', left: 'center', bottom: 0, itemHeight: 120, inRange: { color: ['#F4EEFB', '#B48FE0', '#6A0DAD'] }, textStyle: { fontSize: 10 } },
        series: [{ type: 'heatmap', data: d.cells, label: { show: d.cells.length <= 120, fontSize: 9, formatter: (p: any) => pct ? p.data[2] + '%' : fmt(p.data[2], 'currency') }, itemStyle: { borderColor: '#fff', borderWidth: 2, borderRadius: 4 } }] }
    }
    case 'calendar_heatmap': {
      const pts = (d.categories ?? []).map((c: string, i: number) => [c, series[0]?.data[i] ?? 0])
      const max = Math.max(1, ...pts.map((p: any) => p[1]))
      return { tooltip: { formatter: (p: any) => `${p.data[0]}<br><b>${fmt(p.data[1], 'currency', false)}</b>` }, visualMap: { min: 0, max, show: false, inRange: { color: ['#F4EEFB', '#6A0DAD'] } },
        calendar: { range: [d.period.from, d.period.to], cellSize: ['auto', 14], top: 30, left: 30, right: 10, itemStyle: { borderColor: '#fff', borderWidth: 2 }, splitLine: { show: false }, dayLabel: { fontSize: 9, color: '#8A8A99' }, monthLabel: { fontSize: 10, color: '#8A8A99' }, yearLabel: { show: false } },
        series: [{ type: 'heatmap', coordinateSystem: 'calendar', data: pts }] }
    }
    case 'sankey': {
      const nodes = new Map<string, any>(), links: any[] = []
      ;(d.tree ?? []).forEach((p: any) => { nodes.set('p:' + p.name, { name: 'p:' + p.name }); p.children.forEach((c: any) => { nodes.set('c:' + c.name, { name: 'c:' + c.name }); links.push({ source: 'p:' + p.name, target: 'c:' + c.name, value: c.value }) }) })
      return { color: PALETTE, tooltip: { trigger: 'item', formatter: (p: any) => p.dataType === 'edge' ? `${p.data.source.slice(2)} → ${p.data.target.slice(2)}<br><b>${fmt(p.data.value, 'currency', false)}</b>` : p.name.slice(2) },
        series: [{ type: 'sankey', left: 8, right: 120, data: [...nodes.values()], links, emphasis: { focus: 'adjacency' }, lineStyle: { color: 'gradient', opacity: .35 }, label: { formatter: (p: any) => p.name.slice(2), fontSize: 11 }, nodeGap: 6 }] }
    }
    case 'forecast_line': {
      const hist = (d.history ?? []).slice(-12), fut = (d.months ?? []).slice(1)
      const cats = [...hist.map((h: any) => h.month), ...fut.map((m: any) => m.month)]
      const actual = cats.map(c => hist.find((h: any) => h.month === c)?.value ?? null)
      const fc = cats.map((c, i) => { const m = (d.months ?? []).find((x: any) => x.month === c); return m ? m.forecast : (i === hist.length - 1 ? actual[i] : null) })
      const low = cats.map(c => (d.months ?? []).find((x: any) => x.month === c)?.low ?? null)
      const band = cats.map(c => { const m = (d.months ?? []).find((x: any) => x.month === c); return m ? m.high - m.low : null })
      const tgt = cats.map(c => d.targets?.[c + '-01'] ?? null)
      return base({ legend: { ...base().legend, data: ['Actual', 'Forecast (estimate)', 'Likely range', 'Target'] }, tooltip: { ...base().tooltip, ...tip('currency') }, xAxis: catAxis(cats, { boundaryGap: false }), yAxis: valAxis('currency'),
        series: [{ name: 'Actual', type: 'line', smooth: true, data: actual, lineStyle: { width: 3 }, areaStyle: { opacity: .1 } },
          { name: 'Forecast (estimate)', type: 'line', smooth: true, data: fc, lineStyle: { type: 'dashed', width: 2.5 }, itemStyle: { color: AMBER } },
          { name: 'low', type: 'line', stack: 'band', data: low, lineStyle: { opacity: 0 }, symbol: 'none', tooltip: { show: false }, legendHoverLink: false },
          { name: 'Likely range', type: 'line', stack: 'band', data: band, lineStyle: { opacity: 0 }, symbol: 'none', areaStyle: { color: 'rgba(228,168,0,.15)' }, tooltip: { show: false } },
          { name: 'Target', type: 'line', step: 'middle', data: tgt, lineStyle: { color: GOOD, type: 'dotted', width: 2 }, symbol: 'none' }] })
    }
    case 'small_multiples_bar': case 'bar_stacked': case 'bar_100': {
      const pct = type === 'bar_100'
      const cols = d.columns ?? [], rows = d.rows ?? []
      const val = (ri: number, ci: number) => (d.cells.find((c: any) => c[0] === ci && c[1] === ri) ?? [0, 0, 0])[2]
      const tot = rows.map((_: any, ri: number) => cols.reduce((a: number, _c: any, ci: number) => a + val(ri, ci), 0))
      return base({ tooltip: { ...base().tooltip, ...tip(pct ? 'percent' : 'currency') }, xAxis: valAxis(pct ? 'percent' : 'currency', pct ? { max: 100 } : {}), yAxis: catAxis([...rows].reverse(), { axisLabel: { width: 120, overflow: 'truncate', fontSize: 11 } }),
        series: cols.map((c: string, ci: number) => ({ name: c, type: 'bar', stack: 's', barMaxWidth: 20, data: [...rows].map((_: any, ri: number) => pct ? +(tot[ri] ? val(ri, ci) / tot[ri] * 100 : 0).toFixed(1) : val(ri, ci)).reverse() })) })
    }
  }
  return base({ xAxis: catAxis(d.categories ?? []), yAxis: valAxis(fm(first)), series: series.map((x: any) => ({ name: x.name, type: 'bar', data: x.data })) })
}
