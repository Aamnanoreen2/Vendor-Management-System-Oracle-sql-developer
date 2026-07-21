<?php include 'config.php'; ?>
<?php
$active_tab = $_GET['tab'] ?? 'vendor_summary';
$search = $_GET['search'] ?? '';
$where = $search ? " WHERE UPPER(VENDOR_NAME) LIKE UPPER('%'||:s||'%') OR UPPER(CITY) LIKE UPPER('%'||:s||'%') " : "";
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>VMS — Analytics & Reporting</title>
  <link rel="stylesheet" href="style.css">
  <style>
    .report-tabs { display: flex; gap: 10px; margin-bottom: 24px; overflow-x: auto; padding-bottom: 10px; border-bottom: 1px solid var(--border); }
    .report-tab-btn { padding: 10px 20px; background: var(--surface2); color: var(--text); border: 1px solid var(--border); border-radius: 8px; cursor: pointer; text-decoration: none; white-space: nowrap; transition: 0.2s; font-size: 13px; }
    .report-tab-btn.active { background: var(--accent); color: white; border-color: var(--accent); font-weight: 600; }
    .view-meta { font-size: 11px; color: var(--muted); margin-bottom: 20px; display: flex; align-items: center; gap: 8px; }
    .view-meta code { background: rgba(255,255,255,0.05); padding: 2px 6px; border-radius: 4px; }
    .data-table th { background: var(--surface2); font-weight: 600; text-transform: uppercase; font-size: 10px; letter-spacing: 0.5px; }
  </style>
</head>
<body>
<div class="layout">
  <?php include 'nav.php'; ?>

  <main class="main">
    <div class="page-header">
      <div>
        <h1 class="page-title">Operational <span>Intelligence</span></h1>
        <p class="page-sub">Live reporting and cross-table analytics derived from Oracle Views.</p>
      </div>
      <div style="display: flex; gap: 12px; align-items: center;">
        <form method="GET" style="display: flex; gap: 8px;">
            <input type="hidden" name="tab" value="<?= $active_tab ?>">
            <input type="text" name="search" placeholder="Filter current view..." value="<?= htmlspecialchars($search) ?>" style="font-size: 13px; width: 220px;">
            <button type="submit" class="btn btn-sm btn-primary">Filter</button>
        </form>
        <button class="btn btn-sm" style="background: var(--surface2); border: 1px solid var(--border); color: var(--text);" onclick="window.print()">Export PDF</button>
      </div>
    </div>

    <div class="report-tabs">
      <?php
      $tabs = [
        'vendor_summary'    => 'Vendor 360',
        'order_summary'     => 'Order Log',
        'low_stock'         => 'Shortage Alerts',
        'active_contracts'  => 'Contract Registry',
        'performance'       => 'Rating Analytics',
        'vendor_revenue'    => 'Financial Summary',
        'revenue_detail'    => 'SKU-Level Profit',
        'order_details_view'=> 'Line Items'
      ];
      foreach ($tabs as $key => $label): ?>
        <a href="views.php?tab=<?= $key ?>&search=<?= urlencode($search) ?>" class="report-tab-btn <?= $active_tab === $key ? 'active' : '' ?>">
          <?= $label ?>
        </a>
      <?php endforeach; ?>
    </div>

    <div class="card">
      <?php
      // ───────────────────────────────────────────────────────────────────
      // DYNAMIC QUERY ENGINE BASED ON TAB
      // ───────────────────────────────────────────────────────────────────
      $sql = "";
      $view_name = "";
      $desc = "";

      switch($active_tab) {
        case 'vendor_summary':
            $view_name = "VMS_VENDOR_SUMMARY";
            $desc = "A consolidated view of vendors with total orders, contracts, and average ratings.";
            $sql = "SELECT VENDOR_NAME, CITY, STATUS, TOTAL_ORDERS, TOTAL_CONTRACTS, ROUND(AVERAGE_RATING,2) as RATING FROM VMS_VENDOR_SUMMARY $where ORDER BY TOTAL_ORDERS DESC";
            break;
        case 'order_summary':
            $view_name = "VMS_ORDER_SUMMARY";
            $desc = "Historical order log with placement details and estimated total amounts.";
            $sql = "SELECT ORDER_ID, VENDOR_NAME, PLACED_BY, STATUS, TOTAL_ITEMS, NVL(ESTIMATED_TOTAL_AMOUNT,0) as TOTAL FROM VMS_ORDER_SUMMARY " . ($search ? " WHERE UPPER(VENDOR_NAME) LIKE UPPER('%'||:s||'%') OR UPPER(PLACED_BY) LIKE UPPER('%'||:s||'%') " : "") . " ORDER BY ORDER_ID DESC";
            break;
        case 'low_stock':
            $view_name = "VMS_LOW_STOCK_ALERT";
            $desc = "Critical inventory alert for products reaching or exceeding reorder thresholds.";
            $sql = "SELECT PRODUCT_NAME, CATEGORY, QUANTITY_IN_STOCK as QTY, REORDER_LEVEL, SHORTAGE_QUANTITY as SHORTAGE FROM VMS_LOW_STOCK_ALERT " . ($search ? " WHERE UPPER(PRODUCT_NAME) LIKE UPPER('%'||:s||'%') " : "") . " ORDER BY SHORTAGE DESC";
            break;
        case 'active_contracts':
            $view_name = "VMS_ACTIVE_CONTRACTS";
            $desc = "Registry of currently valid legal agreements and their commercial terms.";
            $sql = "SELECT CONTRACT_NUMBER, VENDOR_NAME, END_DATE, PAYMENT_TERMS, DISCOUNT_PERCENTAGE as DISC, VMS_Calculate_Discount(CONTRACT_ID) as DISCOUNT_AMT FROM VMS_ACTIVE_CONTRACTS " . ($search ? " WHERE UPPER(VENDOR_NAME) LIKE UPPER('%'||:s||'%') " : "") . " ORDER BY END_DATE ASC";
            break;
        case 'performance':
            $view_name = "VMS_VENDOR_PERFORMANCE_VIEW";
            $desc = "Aggregated performance metrics based on historical vendor reviews.";
            $sql = "SELECT VENDOR_NAME, TOTAL_REVIEWS, ROUND(AVG_RATING,2) as AVG_RAT, HIGHEST_RATING as BEST FROM VMS_VENDOR_PERFORMANCE_VIEW " . ($search ? " WHERE UPPER(VENDOR_NAME) LIKE UPPER('%'||:s||'%') " : "") . " ORDER BY AVG_RAT DESC NULLS LAST";
            break;
        case 'vendor_revenue':
            $view_name = "VMS_VENDOR_REVENUE";
            $desc = "Financial overview of total capital allocation per vendor partner.";
            $sql = "SELECT VENDOR_NAME, TOTAL_ORDERS, NVL(TOTAL_REVENUE,0) as REVENUE, NVL(AVERAGE_ORDER_VALUE,0) as AOV FROM VMS_VENDOR_REVENUE $where ORDER BY REVENUE DESC";
            break;
        case 'revenue_detail':
            $view_name = "VMS_VENDOR_REVENUE_DETAIL";
            $desc = "Granular spend analysis breaking down procurement costs by vendor and product SKU.";
            $sql = "SELECT VENDOR_NAME, PRODUCT_NAME, TOTAL_QUANTITY_PURCHASED as QTY, NVL(TOTAL_AMOUNT_SPENT,0) as SPENT FROM VMS_VENDOR_REVENUE_DETAIL " . ($search ? " WHERE UPPER(VENDOR_NAME) LIKE UPPER('%'||:s||'%') OR UPPER(PRODUCT_NAME) LIKE UPPER('%'||:s||'%') " : "") . " ORDER BY SPENT DESC";
            break;
        case 'order_details_view':
            $view_name = "VMS_ORDER_DETAILS_VIEW";
            $desc = "Low-level line item reporting for all historical order transactions.";
            $sql = "SELECT ORDER_ID, VENDOR_NAME, PRODUCT_NAME, QUANTITY, NVL(PRICE,0) as PRICE, NVL(SUBTOTAL,0) as SUBTOTAL FROM VMS_ORDER_DETAILS_VIEW " . ($search ? " WHERE UPPER(VENDOR_NAME) LIKE UPPER('%'||:s||'%') OR UPPER(PRODUCT_NAME) LIKE UPPER('%'||:s||'%') " : "") . " ORDER BY ORDER_ID DESC";
            break;
      }

      $stmt = oci_parse($conn, $sql);
      if ($search) oci_bind_by_name($stmt, ':s', $search);
      oci_execute($stmt);
      $data = [];
      while($r = oci_fetch_assoc($stmt)) $data[] = $r;
      ?>

      <div class="view-meta">
        <span style="font-weight: 700; color: var(--accent);">VIEW:</span> <code><?= $view_name ?></code>
        <span style="color: var(--muted2); font-weight: 300;">|</span>
        <span><?= $desc ?></span>
      </div>

      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <?php if (!empty($data)): foreach (array_keys($data[0]) as $col): ?>
                <th><?= str_replace('_', ' ', $col) ?></th>
              <?php endforeach; endif; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($data as $row): ?>
              <tr>
                <?php foreach ($row as $key => $val): ?>
                  <td>
                    <?php 
                      if (strpos($key, 'REVENUE') !== false || strpos($key, 'TOTAL') !== false || strpos($key, 'SPENT') !== false || strpos($key, 'PRICE') !== false || strpos($key, 'SUBTOTAL') !== false || strpos($key, 'AOV') !== false || strpos($key, 'DISCOUNT_AMT') !== false) {
                        echo "<span style='color: var(--accent3); font-weight: 600;'>PKR " . number_format((float)$val, 0) . "</span>";
                      } else if ($key == 'STATUS') {
                        $b = match($val) { 'Active','Delivered'=>'badge-green', 'Pending','Approved'=>'badge-purple', 'Expired','Cancelled'=>'badge-red', default=>'badge-purple' };
                        echo "<span class='badge $b'>$val</span>";
                      } else if ($key == 'RATING' || $key == 'AVG_RAT' || $key == 'BEST') {
                        echo "<span style='color: var(--warning);'>★ $val</span>";
                      } else {
                        echo htmlspecialchars($val);
                      }
                    ?>
                  </td>
                <?php endforeach; ?>
              </tr>
            <?php endforeach; ?>
            <?php if (empty($data)): ?><tr><td colspan="10" style="text-align: center; padding: 60px; color: var(--muted);">No records match your criteria in the database view.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</div>
<?php oci_close($conn); ?>
</body>
</html>