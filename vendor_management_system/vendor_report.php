<?php include 'config.php'; ?>
<?php
$active_tab = $_GET['tab'] ?? 'vendor_analytics';

// ── Per-vendor analytics using all Oracle PL/SQL functions ───────────
$vendors = db_fetch_all($conn, "SELECT VENDOR_ID, VENDOR_NAME, EMAIL, CITY, STATUS FROM VMS_VENDORS ORDER BY VENDOR_NAME");
$vendor_data = [];
foreach ($vendors as $v) {
    $vid = $v['VENDOR_ID'];
    $rev = db_fetch_all($conn, "SELECT get_vendor_revenue(:v) AS VAL FROM DUAL", [':v'=>$vid]);
    $qty = db_fetch_all($conn, "SELECT VMS_Get_Vendor_Total_Quantity(:v) AS VAL FROM DUAL", [':v'=>$vid]);
    $rat = db_fetch_all($conn, "SELECT VMS_Get_Vendor_Avg_Rating(:v) AS VAL FROM DUAL", [':v'=>$vid]);
    $con = db_fetch_all($conn, "SELECT VMS_Get_Active_Contracts(:v) AS VAL FROM DUAL", [':v'=>$vid]);
    $vendor_data[] = [
        'VENDOR_ID' => $vid, 'VENDOR_NAME' => $v['VENDOR_NAME'], 'EMAIL' => $v['EMAIL'], 'CITY' => $v['CITY'], 'STATUS' => $v['STATUS'],
        'REVENUE' => (float)($rev[0]['VAL'] ?? 0), 'TOTAL_QTY' => (int)($qty[0]['VAL'] ?? 0),
        'AVG_RATING' => (float)($rat[0]['VAL'] ?? 0), 'ACTIVE_CONTRACTS' => (int)($con[0]['VAL'] ?? 0),
    ];
}
usort($vendor_data, fn($a,$b) => $b['REVENUE'] <=> $a['REVENUE']);

// ── Order-level data ─────────────────────────────────────────────────
$orders = db_fetch_all($conn, "SELECT o.ORDER_ID, o.VENDOR_ID, v.VENDOR_NAME, TO_CHAR(o.ORDER_DATE,'DD-Mon-YYYY') AS ORDER_DATE, o.STATUS, VMS_Get_Order_Total(o.ORDER_ID) AS ORDER_TOTAL FROM VMS_ORDERS o JOIN VMS_VENDORS v ON o.VENDOR_ID=v.VENDOR_ID ORDER BY o.ORDER_DATE DESC");

// ── Stock status ─────────────────────────────────────────────────────
$products = db_fetch_all($conn, "SELECT p.PRODUCT_ID, p.PRODUCT_NAME, p.CATEGORY, VMS_Is_Low_Stock(p.PRODUCT_ID) AS STOCK_STATUS FROM VMS_PRODUCTS p ORDER BY p.PRODUCT_NAME");

// ── Contract discounts ───────────────────────────────────────────────
$contracts = db_fetch_all($conn, "SELECT c.CONTRACT_ID, c.CONTRACT_NUMBER, v.VENDOR_NAME, c.CONTRACT_VALUE, c.DISCOUNT_PERCENTAGE, VMS_Calculate_Discount(c.CONTRACT_ID) AS DISCOUNT_AMT, c.STATUS FROM VMS_VENDORCONTRACTS c JOIN VMS_VENDORS v ON c.VENDOR_ID=v.VENDOR_ID ORDER BY DISCOUNT_AMT DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>VMS — Intelligent Reporting</title>
  <link rel="stylesheet" href="style.css">
  <style>
    .fn-tag { font-family: monospace; font-size: 10px; background: rgba(255,255,255,0.05); padding: 2px 6px; border-radius: 4px; color: var(--accent); }
    .rev-bar { height: 6px; background: var(--accent3); border-radius: 3px; }
    .report-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; }
  </style>
</head>
<body>
<div class="layout">
  <?php include 'nav.php'; ?>

  <main class="main">
    <div class="page-header">
      <div>
        <h1 class="page-title">Operational <span>Intelligence</span></h1>
        <p class="page-sub">Cross-module analytics powered by Oracle PL/SQL Engine.</p>
      </div>
      <div style="display: flex; gap: 10px;">
        <button class="btn btn-sm" style="background: var(--surface2); color: var(--text); border: 1px solid var(--border);" onclick="window.print()">Export Report</button>
      </div>
    </div>

    <div class="tabs">
      <a href="vendor_report.php?tab=vendor_analytics" class="tab-btn <?= $active_tab=='vendor_analytics'?'active':'' ?>">Vendor 360</a>
      <a href="vendor_report.php?tab=order_totals" class="tab-btn <?= $active_tab=='order_totals'?'active':'' ?>">Revenue Logs</a>
      <a href="vendor_report.php?tab=stock_status" class="tab-btn <?= $active_tab=='stock_status'?'active':'' ?>">Inventory Health</a>
      <a href="vendor_report.php?tab=discounts" class="tab-btn <?= $active_tab=='discounts'?'active':'' ?>">Savings Audit</a>
    </div>

    <?php if ($active_tab == 'vendor_analytics'): ?>
    <div class="card">
      <div class="card-title">Commercial Performance <span class="fn-tag">get_vendor_revenue()</span></div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr><th>Vendor</th><th>Status</th><th>Procurement Value</th><th>Items</th><th>Rating</th><th>Contracts</th></tr>
          </thead>
          <tbody>
            <?php 
            $maxRev = max(array_column($vendor_data, 'REVENUE')) ?: 1;
            foreach ($vendor_data as $vd): 
              $w = ($vd['REVENUE'] / $maxRev) * 100;
            ?>
            <tr>
              <td><strong><?= htmlspecialchars($vd['VENDOR_NAME']) ?></strong><div style="font-size: 10px; color: var(--muted);"><?= $vd['CITY'] ?></div></td>
              <td><span class="badge <?= $vd['STATUS']=='Active'?'badge-green':'badge-red' ?>"><?= $vd['STATUS'] ?></span></td>
              <td>
                <div style="font-size: 12px; font-weight: 700; color: var(--accent3);">PKR <?= number_format($vd['REVENUE'], 0) ?></div>
                <div style="width: 100%; height: 4px; background: rgba(255,255,255,0.05); border-radius: 2px; margin-top: 4px;">
                  <div class="rev-bar" style="width: <?= $w ?>%"></div>
                </div>
              </td>
              <td><?= $vd['TOTAL_QTY'] ?> Units</td>
              <td><span style="color: var(--warning);">★ <?= number_format($vd['AVG_RATING'], 1) ?></span></td>
              <td><span class="badge badge-purple"><?= $vd['ACTIVE_CONTRACTS'] ?> Active</span></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <?php elseif ($active_tab == 'order_totals'): ?>
    <div class="card">
      <div class="card-title">Order Fulfillment Totals <span class="fn-tag">VMS_Get_Order_Total()</span></div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr><th>Order #</th><th>Vendor Partner</th><th>Placement Date</th><th>Status</th><th>Calculated Total</th></tr>
          </thead>
          <tbody>
            <?php foreach ($orders as $o): ?>
            <tr>
              <td><span class="badge badge-purple">#<?= $o['ORDER_ID'] ?></span></td>
              <td><strong><?= htmlspecialchars($o['VENDOR_NAME']) ?></strong></td>
              <td><?= $o['ORDER_DATE'] ?></td>
              <td><span class="badge badge-purple"><?= $o['STATUS'] ?></span></td>
              <td><span style="font-weight: 700; color: var(--accent3);">PKR <?= number_format($o['ORDER_TOTAL'], 0) ?></span></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <?php elseif ($active_tab == 'stock_status'): ?>
    <div class="card">
      <div class="card-title">Inventory replenishment Status <span class="fn-tag">VMS_Is_Low_Stock()</span></div>
      <div class="report-grid">
        <?php foreach ($products as $p): 
          $isLow = strpos($p['STOCK_STATUS'], 'Low') !== false;
        ?>
        <div class="card" style="margin:0; background: var(--surface2); border: 1px solid <?= $isLow ? 'var(--danger)' : 'var(--border)' ?>;">
          <h4 style="margin:0;"><?= htmlspecialchars($p['PRODUCT_NAME']) ?></h4>
          <div style="font-size: 11px; color: var(--muted); margin-bottom: 10px;"><?= $p['CATEGORY'] ?></div>
          <span class="badge <?= $isLow ? 'badge-red' : 'badge-green' ?>"><?= $p['STOCK_STATUS'] ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <?php elseif ($active_tab == 'discounts'): ?>
    <div class="card">
      <div class="card-title">Contractual Savings Audit <span class="fn-tag">VMS_Calculate_Discount()</span></div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr><th>Contract</th><th>Vendor</th><th>Value</th><th>Rate</th><th>DB-Calculated Savings</th><th>Status</th></tr>
          </thead>
          <tbody>
            <?php foreach ($contracts as $c): ?>
            <tr>
              <td><strong><?= $c['CONTRACT_NUMBER'] ?></strong></td>
              <td><?= htmlspecialchars($c['VENDOR_NAME']) ?></td>
              <td>PKR <?= number_format($c['CONTRACT_VALUE'], 0) ?></td>
              <td><span class="badge badge-yellow"><?= $c['DISCOUNT_PERCENTAGE'] ?>%</span></td>
              <td><span style="font-weight: 700; color: var(--accent3);">PKR <?= number_format($c['DISCOUNT_AMT'], 0) ?></span></td>
              <td><span class="badge <?= $c['STATUS']=='Active'?'badge-green':'badge-red' ?>"><?= $c['STATUS'] ?></span></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

  </main>
</div>
<?php oci_close($conn); ?>
</body>
</html>
