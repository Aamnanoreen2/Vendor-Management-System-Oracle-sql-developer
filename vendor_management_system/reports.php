<?php include 'config.php'; ?>
<?php
// ── DATA FOR CONTRACT STATUS PIE CHART ───────────────────────────────
$chart_status = db_fetch_all($conn, "
    SELECT STATUS, COUNT(*) AS CNT 
    FROM VMS_VENDORCONTRACTS 
    GROUP BY STATUS
");

// ── DATA FOR VENDOR CONTRACT VALUE BAR CHART ──────────────────────────
$chart_vendor_value = db_fetch_all($conn, "
    SELECT v.VENDOR_NAME, SUM(c.CONTRACT_VALUE) AS TOTAL_VALUE
    FROM VMS_VENDORCONTRACTS c
    JOIN VMS_VENDORS v ON c.VENDOR_ID = v.VENDOR_ID
    GROUP BY v.VENDOR_NAME
    ORDER BY TOTAL_VALUE DESC
    FETCH FIRST 8 ROWS ONLY
");

// ── AGGREGATE STATS ───────────────────────────────────────────────────
$total_val = db_fetch_all($conn, "SELECT SUM(CONTRACT_VALUE) AS TOTAL FROM VMS_VENDORCONTRACTS");
$avg_disc  = db_fetch_all($conn, "SELECT AVG(DISCOUNT_PERCENTAGE) AS AVG_D FROM VMS_VENDORCONTRACTS");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>VMS — Enterprise Analytics</title>
  <link rel="stylesheet" href="style.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    .reports-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 32px; }
    .chart-box { background: var(--surface); border: 1px solid var(--border); border-radius: 16px; padding: 24px; min-height: 350px; }
    .metric-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 24px; }
    .metric-card { background: var(--surface2); padding: 20px; border-radius: 12px; border: 1px solid var(--border); }
    .metric-val { font-size: 20px; font-weight: 800; color: var(--accent3); margin-top: 5px; }
    .metric-label { font-size: 10px; text-transform: uppercase; color: var(--muted); letter-spacing: 1px; }
  </style>
</head>
<body>
<div class="layout">
  <?php include 'nav.php'; ?>

  <main class="main">
    <div class="page-header">
      <div>
        <h1 class="page-title">Enterprise <span>Analytics</span></h1>
        <p class="page-sub">Visual intelligence for contracts, vendor value, and commercial performance.</p>
      </div>
    </div>

    <div class="metric-grid">
      <div class="metric-card">
        <div class="metric-label">Total Portfolio Value</div>
        <div class="metric-val">PKR <?= number_format($total_val[0]['TOTAL'] ?? 0, 0) ?></div>
      </div>
      <div class="metric-card">
        <div class="metric-label">Avg. Negotiated Discount</div>
        <div class="metric-val"><?= number_format($avg_disc[0]['AVG_D'] ?? 0, 1) ?>%</div>
      </div>
      <div class="metric-card">
        <div class="metric-label">Active Agreements</div>
        <div class="metric-val"><?= count(array_filter($chart_status, fn($s) => $s['STATUS'] == 'Active')) ?></div>
      </div>
      <div class="metric-card">
        <div class="metric-label">Expiring Policy</div>
        <div class="metric-val" style="color: var(--warning);">30 Days</div>
      </div>
    </div>

    <div class="reports-grid">
      <div class="chart-box">
        <div class="card-title">Contracts by Status</div>
        <div style="height: 280px; display: flex; justify-content: center;">
          <canvas id="statusPieChart"></canvas>
        </div>
      </div>
      <div class="chart-box">
        <div class="card-title">Top 8 Vendors by Portfolio Value</div>
        <canvas id="vendorBarChart"></canvas>
      </div>
    </div>

    <div class="card">
      <div class="card-title">Detailed Commercial Summary</div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Vendor Name</th><th>Portfolio Value</th><th>Market Share</th>
            </tr>
          </thead>
          <tbody>
            <?php 
            $grand_total = $total_val[0]['TOTAL'] ?: 1;
            foreach ($chart_vendor_value as $vv): 
              $share = ($vv['TOTAL_VALUE'] / $grand_total) * 100;
            ?>
            <tr>
              <td><strong><?= htmlspecialchars($vv['VENDOR_NAME']) ?></strong></td>
              <td class="val-text">PKR <?= number_format($vv['TOTAL_VALUE'], 0) ?></td>
              <td>
                <div style="display: flex; align-items: center; gap: 10px;">
                  <div style="flex: 1; height: 6px; background: var(--surface2); border-radius: 3px; overflow: hidden;">
                    <div style="height: 100%; width: <?= $share ?>%; background: var(--accent);"></div>
                  </div>
                  <span style="font-size: 11px; font-weight: 700;"><?= number_format($share, 1) ?>%</span>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

  </main>
</div>

<script>
// Contracts by Status Pie Chart
const ctxPie = document.getElementById('statusPieChart').getContext('2d');
new Chart(ctxPie, {
    type: 'pie',
    data: {
        labels: <?= json_encode(array_column($chart_status, 'STATUS')) ?>,
        datasets: [{
            data: <?= json_encode(array_column($chart_status, 'CNT')) ?>,
            backgroundColor: ['#43e97b', '#ff4757', '#ffa502', '#6c63ff'],
            borderWidth: 2,
            borderColor: '#1a1a2e'
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { position: 'bottom', labels: { color: '#6b6b80', font: { family: 'DM Mono', size: 11 } } }
        }
    }
});

// Vendor Value Bar Chart
const ctxBar = document.getElementById('vendorBarChart').getContext('2d');
new Chart(ctxBar, {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($chart_vendor_value, 'VENDOR_NAME')) ?>,
        datasets: [{
            label: 'Total Value (PKR)',
            data: <?= json_encode(array_column($chart_vendor_value, 'TOTAL_VALUE')) ?>,
            backgroundColor: 'rgba(108, 99, 255, 0.6)',
            borderColor: '#6c63ff',
            borderWidth: 2,
            borderRadius: 5
        }]
    },
    options: {
        responsive: true,
        indexAxis: 'y',
        plugins: { legend: { display: false } },
        scales: {
            x: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#6b6b80' } },
            y: { ticks: { color: '#6b6b80' } }
        }
    }
});
</script>
</body>
</html>
