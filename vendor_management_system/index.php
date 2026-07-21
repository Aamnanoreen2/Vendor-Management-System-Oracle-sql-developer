<?php include 'config.php'; ?>
<?php
// ── STAT COUNTS ───────────────────────────────────────────────────────
function get_stat_count($conn, $sql) {
    $r = db_fetch_all($conn, $sql);
    return (int)($r[0]['CNT'] ?? 0);
}

$cnt_vendors     = get_stat_count($conn, "SELECT COUNT(*) CNT FROM VMS_VENDORS");
$cnt_active_vend = get_stat_count($conn, "SELECT COUNT(*) CNT FROM VMS_VENDORS WHERE STATUS='Active'");
$cnt_products    = get_stat_count($conn, "SELECT COUNT(*) CNT FROM VMS_PRODUCTS");
$cnt_orders      = get_stat_count($conn, "SELECT COUNT(*) CNT FROM VMS_ORDERS");
$cnt_pending     = get_stat_count($conn, "SELECT COUNT(*) CNT FROM VMS_ORDERS WHERE STATUS='Pending'");
$cnt_delivered   = get_stat_count($conn, "SELECT COUNT(*) CNT FROM VMS_ORDERS WHERE STATUS='Delivered'");
$cnt_employees   = get_stat_count($conn, "SELECT COUNT(*) CNT FROM VMS_USERS_EMPLOYEES");
$cnt_contracts   = get_stat_count($conn, "SELECT COUNT(*) CNT FROM VMS_VENDORCONTRACTS WHERE STATUS='Active'");
$cnt_expired_con = get_stat_count($conn, "SELECT COUNT(*) CNT FROM VMS_VENDORCONTRACTS WHERE STATUS='Expired'");
$cnt_low_stock   = get_stat_count($conn, "SELECT COUNT(*) CNT FROM VMS_LOW_STOCK_ALERT");

// ── AGGREGATES ────────────────────────────────────────────────────────
$revenue = db_fetch_all($conn, "SELECT NVL(SUM(AMOUNT),0) AS TOTAL FROM VMS_PAYMENTS");
$total_revenue = (float)($revenue[0]['TOTAL'] ?? 0);

// ── CHART DATA: Orders per Month ─────────────────────────────────────
$chart_orders = db_fetch_all($conn, "
    SELECT TO_CHAR(ORDER_DATE, 'Mon') AS MONTH, COUNT(*) AS CNT
    FROM VMS_ORDERS
    WHERE ORDER_DATE >= ADD_MONTHS(SYSDATE, -6)
    GROUP BY TO_CHAR(ORDER_DATE, 'Mon'), TRUNC(ORDER_DATE, 'MM')
    ORDER BY TRUNC(ORDER_DATE, 'MM')
");

// ── CHART DATA: Vendor Ratings ───────────────────────────────────────
$chart_ratings = db_fetch_all($conn, "
    SELECT RATING, COUNT(*) AS CNT
    FROM VMS_VENDORPERFORMANCE
    GROUP BY RATING
    ORDER BY RATING DESC
");

// ── ALERTS SECTION ────────────────────────────────────────────────────
$alerts = [];
// Low Stock Alerts
$low_stock_list = db_fetch_all($conn, "SELECT PRODUCT_NAME, QUANTITY_IN_STOCK FROM VMS_LOW_STOCK_ALERT FETCH FIRST 3 ROWS ONLY");
foreach ($low_stock_list as $ls) {
    $alerts[] = ['type' => 'danger', 'icon' => '📦', 'text' => "Low stock: <strong>{$ls['PRODUCT_NAME']}</strong> only {$ls['QUANTITY_IN_STOCK']} left."];
}
// Smart Contract Alerts
$contract_alerts = db_fetch_all($conn, "
    SELECT c.CONTRACT_NUMBER, v.VENDOR_NAME, 
           TRUNC(c.END_DATE) - TRUNC(SYSDATE) as DAYS_DIFF
    FROM VMS_VENDORCONTRACTS c
    JOIN VMS_VENDORS v ON c.VENDOR_ID = v.VENDOR_ID
    WHERE (c.END_DATE BETWEEN SYSDATE AND SYSDATE + 15) -- Expiring soon
       OR (c.END_DATE BETWEEN SYSDATE - 2 AND SYSDATE)  -- Just expired
    FETCH FIRST 5 ROWS ONLY
");
foreach ($contract_alerts as $ca) {
    if ($ca['DAYS_DIFF'] < 0) {
        $alerts[] = ['type' => 'danger', 'icon' => '🚨', 'text' => "Contract <strong>{$ca['CONTRACT_NUMBER']}</strong> ({$ca['VENDOR_NAME']}) expired <strong>".abs($ca['DAYS_DIFF'])." days ago</strong>."];
    } elseif ($ca['DAYS_DIFF'] == 0) {
        $alerts[] = ['type' => 'warning', 'icon' => '⚠️', 'text' => "Contract <strong>{$ca['CONTRACT_NUMBER']}</strong> expires <strong>today</strong>!"];
    } else {
        $alerts[] = ['type' => 'warning', 'icon' => '⏳', 'text' => "Contract <strong>{$ca['CONTRACT_NUMBER']}</strong> expires in <strong>{$ca['DAYS_DIFF']} days</strong>."];
    }
}
// Pending Orders
if ($cnt_pending > 0) {
    $alerts[] = ['type' => 'info', 'icon' => '⏳', 'text' => "There are <strong>$cnt_pending</strong> pending orders awaiting processing."];
}

$user = $_SESSION['vms_user'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>VMS — Enterprise Dashboard</title>
  <link rel="stylesheet" href="style.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    .dash-header { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 32px; }
    .welcome-text h1 { font-family: 'Syne', sans-serif; font-weight: 800; font-size: 28px; margin-bottom: 4px; }
    .welcome-text p { color: var(--muted); font-size: 14px; }
    
    .chart-container { background: var(--surface); border: 1px solid var(--border); border-radius: 16px; padding: 24px; }
    .chart-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 24px; margin-bottom: 24px; }
    
    .revenue-value { font-family: 'Syne', sans-serif; font-size: 32px; font-weight: 800; color: var(--accent3); }
  </style>
</head>
<body>
<div class="layout">
  <?php include 'nav.php'; ?>

  <main class="main">
    <div class="dash-header">
      <div class="welcome-text">
        <h1>Welcome back, <?= explode(' ', $user['full_name'])[0] ?>!</h1>
        <p>Here's what's happening in the system today.</p>
      </div>
      <div class="revenue-card" style="text-align: right;">
        <div style="font-size: 10px; color: var(--muted); text-transform: uppercase; letter-spacing: 1px;">Total Spend (PKR)</div>
        <div class="revenue-value">PKR <?= number_format($total_revenue, 0) ?></div>
      </div>
    </div>

    <!-- ALERTS SECTION -->
    <?php if (!empty($alerts)): ?>
    <div class="alerts-container">
      <?php foreach ($alerts as $a): ?>
        <div class="alert-banner <?= $a['type'] ?>">
          <span class="alert-banner-icon"><?= $a['icon'] ?></span>
          <span class="alert-banner-text"><?= $a['text'] ?></span>
        </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- STAT CARDS -->
    <div class="stats-grid" style="margin-bottom: 32px;">
      <div class="stat-card">
        <div class="stat-icon">◈</div>
        <div class="stat-value"><?= $cnt_vendors ?></div>
        <div class="stat-label">Vendors</div>
      </div>
      <div class="stat-card">
        <div class="stat-icon">◉</div>
        <div class="stat-value"><?= $cnt_products ?></div>
        <div class="stat-label">Products</div>
      </div>
      <div class="stat-card">
        <div class="stat-icon">◎</div>
        <div class="stat-value"><?= $cnt_orders ?></div>
        <div class="stat-label">Total Orders</div>
      </div>
      <div class="stat-card">
        <div class="stat-icon">⏳</div>
        <div class="stat-value" style="color: var(--warning);"><?= $cnt_pending ?></div>
        <div class="stat-label">Pending</div>
      </div>
      <div class="stat-card">
        <div class="stat-icon">✅</div>
        <div class="stat-value" style="color: var(--success);"><?= $cnt_delivered ?></div>
        <div class="stat-label">Delivered</div>
      </div>
      <div class="stat-card">
        <div class="stat-icon">📦</div>
        <div class="stat-value" style="color: var(--danger);"><?= $cnt_low_stock ?></div>
        <div class="stat-label">Low Stock</div>
      </div>
      <div class="stat-card">
        <div class="stat-icon">◫</div>
        <div class="stat-value"><?= $cnt_contracts ?></div>
        <div class="stat-label">Contracts</div>
      </div>
      <div class="stat-card">
        <div class="stat-icon">◷</div>
        <div class="stat-value"><?= $cnt_employees ?></div>
        <div class="stat-label">Employees</div>
      </div>
    </div>

    <!-- CHARTS GRID -->
    <div class="chart-grid">
      <div class="chart-container">
        <div class="card-title">Order Trends (Last 6 Months)</div>
        <canvas id="orderChart" height="100"></canvas>
      </div>
      <div class="chart-container">
        <div class="card-title">Vendor Ratings</div>
        <canvas id="ratingChart"></canvas>
      </div>
    </div>

    <div class="dash-grid">
      <!-- RECENT ORDERS -->
      <div class="card">
        <div class="card-title">Recent Orders</div>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>ID</th><th>Vendor</th><th>Ordered By</th><th>Date</th><th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php
              $recent = db_fetch_all($conn, "
                SELECT o.ORDER_ID, v.VENDOR_NAME, e.FULL_NAME, TO_CHAR(o.ORDER_DATE, 'DD-Mon') AS ODATE, o.STATUS
                FROM VMS_ORDERS o
                JOIN VMS_VENDORS v ON o.VENDOR_ID = v.VENDOR_ID
                JOIN VMS_USERS_EMPLOYEES e ON o.PLACED_BY_USER_ID = e.USER_ID
                ORDER BY o.ORDER_DATE DESC
                FETCH FIRST 5 ROWS ONLY
              ");
              foreach ($recent as $r):
                $badge = match($r['STATUS']) { 'Delivered' => 'badge-green', 'Pending' => 'badge-orange', 'Cancelled' => 'badge-red', default => 'badge-purple' };
              ?>
              <tr>
                <td><span class="badge badge-purple">#<?= $r['ORDER_ID'] ?></span></td>
                <td><?= htmlspecialchars($r['VENDOR_NAME']) ?></td>
                <td><?= htmlspecialchars($r['FULL_NAME']) ?></td>
                <td><?= $r['ODATE'] ?></td>
                <td><span class="badge <?= $badge ?>"><?= $r['STATUS'] ?></span></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- INVENTORY STATUS -->
      <div class="card">
        <div class="card-title">Inventory Overview</div>
        <?php
        $inv = db_fetch_all($conn, "
          SELECT p.PRODUCT_NAME, i.QUANTITY_IN_STOCK, i.REORDER_LEVEL
          FROM VMS_INVENTORY i
          JOIN VMS_PRODUCTS p ON i.PRODUCT_ID = p.PRODUCT_ID
          ORDER BY i.QUANTITY_IN_STOCK ASC
          FETCH FIRST 5 ROWS ONLY
        ");
        foreach ($inv as $i):
          $pct = min(100, ($i['QUANTITY_IN_STOCK'] / max(1, $i['REORDER_LEVEL'] * 2)) * 100);
          $color = $i['QUANTITY_IN_STOCK'] <= $i['REORDER_LEVEL'] ? 'var(--danger)' : 'var(--accent3)';
        ?>
        <div style="margin-bottom: 16px;">
          <div style="display: flex; justify-content: space-between; font-size: 12px; margin-bottom: 4px;">
            <span><?= htmlspecialchars($i['PRODUCT_NAME']) ?></span>
            <span><?= $i['QUANTITY_IN_STOCK'] ?> units</span>
          </div>
          <div style="height: 6px; background: var(--surface2); border-radius: 3px; overflow: hidden;">
            <div style="height: 100%; width: <?= $pct ?>%; background: <?= $color ?>;"></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

  </main>
</div>

<script>
// Order Trend Chart
const ctxOrder = document.getElementById('orderChart').getContext('2d');
new Chart(ctxOrder, {
    type: 'line',
    data: {
        labels: <?= json_encode(array_column($chart_orders, 'MONTH')) ?>,
        datasets: [{
            label: 'Orders',
            data: <?= json_encode(array_column($chart_orders, 'CNT')) ?>,
            borderColor: '#6c63ff',
            backgroundColor: 'rgba(108, 99, 255, 0.1)',
            fill: true,
            tension: 0.4,
            borderWidth: 3,
            pointBackgroundColor: '#6c63ff'
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.05)' }, border: { display: false } },
            x: { grid: { display: false }, border: { display: false } }
        }
    }
});

// Rating Doughnut Chart
const ctxRating = document.getElementById('ratingChart').getContext('2d');
new Chart(ctxRating, {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_map(fn($r) => "$r Stars", array_column($chart_ratings, 'RATING'))) ?>,
        datasets: [{
            data: <?= json_encode(array_column($chart_ratings, 'CNT')) ?>,
            backgroundColor: ['#43e97b', '#6c63ff', '#ffa502', '#ff6584', '#ff4757'],
            borderWidth: 0,
            hoverOffset: 10
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { position: 'bottom', labels: { color: '#6b6b80', padding: 20, font: { family: 'DM Mono', size: 10 } } }
        },
        cutout: '70%'
    }
});
</script>

<?php oci_close($conn); ?>
</body>
</html>