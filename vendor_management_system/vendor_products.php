<?php include 'config.php'; ?>
<?php
$msg = '';

// ── ADD / UPDATE LINK ────────────────────────────────────────────────
if (isset($_POST['add_vp'])) {
    if (!has_role(['Admin', 'Manager', 'Purchaser'])) {
        $msg = ['type'=>'danger','text'=>'Access Denied.'];
    } else {
        $sql  = "INSERT INTO VMS_VENDORPRODUCTS (VENDOR_ID, PRODUCT_ID, SUPPLY_PRICE)
                 VALUES (:vid, :pid, :price)";
        $stmt = oci_parse($conn, $sql);
        oci_bind_by_name($stmt, ':vid',   $_POST['vendor_id']);
        oci_bind_by_name($stmt, ':pid',   $_POST['product_id']);
        oci_bind_by_name($stmt, ':price', $_POST['supply_price']);
        
        if (oci_execute($stmt, OCI_COMMIT_ON_SUCCESS)) {
            $msg = ['type'=>'success','text'=>'Supply link established!'];
        } else {
            $e = oci_error($stmt);
            if (strpos($e['message'], 'ORA-00001') !== false) {
                $msg = ['type'=>'danger','text'=>'This vendor already supplies this product. Update existing entry instead.'];
            } else {
                $msg = ['type'=>'danger','text'=>$e['message']];
            }
        }
        oci_free_statement($stmt);
    }
}

if (isset($_POST['edit_vp'])) {
    $sql  = "UPDATE VMS_VENDORPRODUCTS SET SUPPLY_PRICE=:price WHERE VENDOR_ID=:vid AND PRODUCT_ID=:pid";
    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ':price', $_POST['supply_price']);
    oci_bind_by_name($stmt, ':vid',   $_POST['vendor_id']);
    oci_bind_by_name($stmt, ':pid',   $_POST['product_id']);
    if (oci_execute($stmt, OCI_COMMIT_ON_SUCCESS)) {
        $msg = ['type'=>'success','text'=>'Supply price updated!'];
    }
    oci_free_statement($stmt);
}

// ── DELETE ────────────────────────────────────────────────────────────
if (isset($_GET['delete_vid'], $_GET['delete_pid'])) {
    if (!has_role('Admin')) {
        $msg = ['type'=>'danger','text'=>'Access Denied.'];
    } else {
        $sql  = "DELETE FROM VMS_VENDORPRODUCTS WHERE VENDOR_ID=:vid AND PRODUCT_ID=:pid";
        $stmt = oci_parse($conn, $sql);
        oci_bind_by_name($stmt, ':vid', $_GET['delete_vid']);
        oci_bind_by_name($stmt, ':pid', $_GET['delete_pid']);
        if (oci_execute($stmt, OCI_COMMIT_ON_SUCCESS)) {
            header("Location: vendor_products.php?deleted=1"); exit;
        }
    }
}
if (isset($_GET['deleted'])) $msg = ['type'=>'success','text'=>'Supply link removed.'];

// ── FETCH DATA ───────────────────────────────────────────────────────
$search = $_GET['search'] ?? '';
$filter_vendor = $_GET['filter_vendor'] ?? '';
$where = "WHERE 1=1";
if ($search) $where .= " AND (UPPER(p.PRODUCT_NAME) LIKE UPPER('%'||:s||'%') OR UPPER(v.VENDOR_NAME) LIKE UPPER('%'||:s||'%'))";
if ($filter_vendor) $where .= " AND vp.VENDOR_ID = :v";

$sql_all = "SELECT vp.*, v.VENDOR_NAME, p.PRODUCT_NAME, p.CATEGORY, p.PRICE as MARKET_PRICE, 
            ROUND(((p.PRICE - vp.SUPPLY_PRICE)/NULLIF(p.PRICE,0))*100, 1) as MARGIN 
            FROM VMS_VENDORPRODUCTS vp 
            JOIN VMS_VENDORS v ON vp.VENDOR_ID = v.VENDOR_ID 
            JOIN VMS_PRODUCTS p ON vp.PRODUCT_ID = p.PRODUCT_ID 
            $where ORDER BY p.PRODUCT_NAME, vp.SUPPLY_PRICE ASC";
$stmt = oci_parse($conn, $sql_all);
if ($search) oci_bind_by_name($stmt, ':s', $search);
if ($filter_vendor) oci_bind_by_name($stmt, ':v', $filter_vendor);
oci_execute($stmt);
$records = [];
while($r = oci_fetch_assoc($stmt)) $records[] = $r;

// Best Prices Calculation (Oracle Grouping)
$best_prices = db_fetch_all($conn, "
    SELECT p.PRODUCT_NAME, MIN(vp.SUPPLY_PRICE) as BEST_PRICE, COUNT(vp.VENDOR_ID) as VENDOR_COUNT
    FROM VMS_VENDORPRODUCTS vp
    JOIN VMS_PRODUCTS p ON vp.PRODUCT_ID = p.PRODUCT_ID
    GROUP BY p.PRODUCT_NAME
    ORDER BY p.PRODUCT_NAME
");

$vendors = db_fetch_all($conn, "SELECT VENDOR_ID, VENDOR_NAME FROM VMS_VENDORS WHERE STATUS='Active' ORDER BY VENDOR_NAME");
$products = db_fetch_all($conn, "SELECT PRODUCT_ID, PRODUCT_NAME, PRICE FROM VMS_PRODUCTS ORDER BY PRODUCT_NAME");

// Advanced Intelligence Metrics
$total_matrix_count = count($records);
$multiple_vendors   = db_fetch_all($conn, "SELECT COUNT(*) as CNT FROM (SELECT PRODUCT_ID FROM VMS_VENDORPRODUCTS GROUP BY PRODUCT_ID HAVING COUNT(*) > 1)");
$risk_products     = db_fetch_all($conn, "SELECT COUNT(*) as CNT FROM (SELECT PRODUCT_ID FROM VMS_VENDORPRODUCTS GROUP BY PRODUCT_ID HAVING COUNT(*) = 1)");
$cheapest_vendor   = db_fetch_all($conn, "
    SELECT v.VENDOR_NAME 
    FROM VMS_VENDORPRODUCTS vp 
    JOIN VMS_VENDORS v ON vp.VENDOR_ID = v.VENDOR_ID
    WHERE vp.SUPPLY_PRICE = (SELECT MIN(SUPPLY_PRICE) FROM VMS_VENDORPRODUCTS)
    FETCH FIRST 1 ROWS ONLY
");

$avg_savings = db_fetch_all($conn, "
    SELECT AVG((p.PRICE - vp.SUPPLY_PRICE)/NULLIF(p.PRICE,0)*100) as AVG_S
    FROM VMS_VENDORPRODUCTS vp
    JOIN VMS_PRODUCTS p ON vp.PRODUCT_ID = p.PRODUCT_ID
");

$cat_performance = db_fetch_all($conn, "
    SELECT p.CATEGORY, AVG((p.PRICE - vp.SUPPLY_PRICE)/NULLIF(p.PRICE,0)*100) as SAVINGS
    FROM VMS_VENDORPRODUCTS vp
    JOIN VMS_PRODUCTS p ON vp.PRODUCT_ID = p.PRODUCT_ID
    GROUP BY p.CATEGORY
    ORDER BY SAVINGS DESC
");

// Edit Fetch
$edit = null;
if (isset($_GET['edit_vid'], $_GET['edit_pid'])) {
    $rows = db_fetch_all($conn, "SELECT vp.*, v.VENDOR_NAME, p.PRODUCT_NAME FROM VMS_VENDORPRODUCTS vp JOIN VMS_VENDORS v ON vp.VENDOR_ID=v.VENDOR_ID JOIN VMS_PRODUCTS p ON vp.PRODUCT_ID=p.PRODUCT_ID WHERE vp.VENDOR_ID=:v AND vp.PRODUCT_ID=:p", [':v'=>(int)$_GET['edit_vid'], ':p'=>(int)$_GET['edit_pid']]);
    $edit = $rows[0] ?? null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>VMS — Supply Links</title>
  <link rel="stylesheet" href="style.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    .margin-badge { font-size: 10px; font-weight: 800; padding: 2px 8px; border-radius: 4px; }
    .margin-plus { background: rgba(67, 233, 123, 0.2); color: var(--accent3); }
    .margin-minus { background: rgba(255, 71, 87, 0.2); color: var(--danger); }
    .best-price-card { background: linear-gradient(135deg, var(--surface), var(--surface2)); border-left: 4px solid var(--accent3); }
  </style>
</head>
<body>
<div class="layout">
  <?php include 'nav.php'; ?>

  <main class="main">
    <div class="page-header">
      <div>
        <h1 class="page-title">Supply <span>Matrix</span></h1>
        <p class="page-sub">Comprehensive mapping of vendors to products with price benchmarking.</p>
      </div>
    </div>

    <?php if ($msg): ?>
      <div class="alert alert-<?= $msg['type'] ?>"><?= $msg['text'] ?></div>
    <?php endif; ?>

    <!-- SUMMARY SECTION: Best Prices -->
    <div style="margin-bottom: 32px;">
      <h2 class="card-title" style="color: var(--accent3);">Market Best Rates</h2>
      <div class="stats-grid" style="grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));">
        <?php foreach (array_slice($best_prices, 0, 4) as $bp): ?>
        <div class="stat-card best-price-card">
          <div style="font-size: 10px; text-transform: uppercase; color: var(--muted);"><?= htmlspecialchars($bp['PRODUCT_NAME']) ?></div>
          <div class="stat-value" style="font-size: 20px; color: var(--accent3);">PKR <?= number_format($bp['BEST_PRICE'], 0) ?></div>
          <div style="font-size: 10px; color: var(--muted);">Offered by <?= $bp['VENDOR_COUNT'] ?> vendors</div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="page-actions">
      <form method="GET" style="display: flex; gap: 12px; align-items: center;">
        <input type="text" name="search" placeholder="Search by Vendor or Product..." value="<?= htmlspecialchars($search) ?>" style="width: 300px;">
        <select name="filter_vendor" onchange="this.form.submit()" style="width: 180px;">
          <option value="">— All Vendors —</option>
          <?php foreach ($vendors as $v): ?><option value="<?= $v['VENDOR_ID'] ?>" <?= $filter_vendor==$v['VENDOR_ID']?'selected':'' ?>><?= htmlspecialchars($v['VENDOR_NAME']) ?></option><?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-primary">Benchmark</button>
      </form>
      <button class="btn btn-primary" onclick="openModal('addVpModal')">+ Link New Supply</button>
    </div>

    <div class="card">
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th style="width: 250px;">Vendor Partner</th>
              <th style="width: 250px;">Product SKU</th>
              <th>Category</th>
              <th>Market Price</th>
              <th>Supply Price</th>
              <th>Savings %</th>
              <th style="width: 150px;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($records as $r): 
              $isSaving = (float)$r['MARGIN'] > 0;
            ?>
            <tr>
              <td><strong><?= htmlspecialchars($r['VENDOR_NAME']) ?></strong></td>
              <td><?= htmlspecialchars($r['PRODUCT_NAME']) ?></td>
              <td><span class="badge badge-purple" style="font-size: 9px; text-transform: uppercase;"><?= htmlspecialchars($r['CATEGORY']) ?></span></td>
              <td style="color: var(--muted); font-size: 12px;">PKR <?= number_format((float)$r['MARKET_PRICE'], 0) ?></td>
              <td><span style="font-weight: 700; color: var(--accent3);">PKR <?= number_format((float)$r['SUPPLY_PRICE'], 0) ?></span></td>
              <td><span class="margin-badge <?= $isSaving ? 'margin-plus' : 'margin-minus' ?>"><?= $r['MARGIN'] ?>%</span></td>
              <td>
                <div style="display: flex; gap: 6px;">
                  <a href="vendor_products.php?edit_vid=<?= $r['VENDOR_ID'] ?>&edit_pid=<?= $r['PRODUCT_ID'] ?>" class="btn btn-sm" style="background: var(--surface2); color: var(--text); border: 1px solid var(--border);" title="Edit Price">✏️</a>
                  <?php if (has_role('Admin')): ?>
                    <a href="vendor_products.php?delete_vid=<?= $r['VENDOR_ID'] ?>&delete_pid=<?= $r['PRODUCT_ID'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Unlink this product from this vendor?')" title="Remove Association">🗑</a>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($records)): ?><tr><td colspan="7" style="text-align: center; padding: 60px; color: var(--muted);">No supply matrix data found.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ADD MODAL -->
    <div id="addVpModal" class="modal">
      <div class="modal-content">
        <div class="modal-header">
          <h2 class="card-title" style="margin:0; font-size: 20px;">Register Supply Link</h2>
          <span class="modal-close" onclick="closeModal('addVpModal')">&times;</span>
        </div>
        <form method="POST">
          <div class="form-grid">
            <div class="form-group">
              <label>Vendor Partner</label>
              <select name="vendor_id" required>
                <option value="">— Select Vendor —</option>
                <?php foreach ($vendors as $v): ?><option value="<?= $v['VENDOR_ID'] ?>"><?= htmlspecialchars($v['VENDOR_NAME']) ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label>Product SKU</label>
              <select name="product_id" required>
                <option value="">— Select Product —</option>
                <?php foreach ($products as $p): ?><option value="<?= $p['PRODUCT_ID'] ?>"><?= htmlspecialchars($p['PRODUCT_NAME']) ?> (Market: <?= number_format($p['PRICE'], 0) ?>)</option><?php endforeach; ?>
              </select>
            </div>
            <div class="form-group" style="grid-column: span 2;">
              <label>Agreed Supply Price (PKR)</label>
              <input type="number" step="0.01" name="supply_price" required placeholder="Enter the price agreed with this vendor">
            </div>
          </div>
          <div style="margin-top: 24px; display: flex; justify-content: flex-end; gap: 12px;">
            <button type="button" class="btn" onclick="closeModal('addVpModal')" style="background: var(--surface2); color: var(--text); border: 1px solid var(--border);">Cancel</button>
            <button type="submit" name="add_vp" class="btn btn-primary">Establish Link</button>
          </div>
        </form>
      </div>
    </div>

    <!-- EDIT MODAL -->
    <?php if ($edit): ?>
    <div id="editVpModal" class="modal active">
      <div class="modal-content">
        <div class="modal-header">
          <h2 class="card-title" style="margin:0; font-size: 20px;">Update Negotiated Price</h2>
          <span class="modal-close" onclick="window.location.href='vendor_products.php'">&times;</span>
        </div>
        <form method="POST">
          <input type="hidden" name="vendor_id" value="<?= $edit['VENDOR_ID'] ?>">
          <input type="hidden" name="product_id" value="<?= $edit['PRODUCT_ID'] ?>">
          <div style="margin-bottom: 20px; background: rgba(108, 99, 255, 0.05); padding: 16px; border-radius: 8px; border-left: 3px solid var(--accent);">
            <div style="font-size: 11px; color: var(--muted); text-transform: uppercase;">Agreement For</div>
            <div style="font-size: 16px; font-weight: 700;"><?= htmlspecialchars($edit['VENDOR_NAME']) ?> ➜ <?= htmlspecialchars($edit['PRODUCT_NAME']) ?></div>
          </div>
          <div class="form-group">
            <label>New Supply Price (PKR)</label>
            <input type="number" step="0.01" name="supply_price" value="<?= $edit['SUPPLY_PRICE'] ?>" required style="font-size: 18px; font-weight: 700; color: var(--accent3);">
          </div>
          <div style="margin-top: 24px; display: flex; justify-content: flex-end; gap: 12px;">
            <button type="button" class="btn" onclick="window.location.href='vendor_products.php'" style="background: var(--surface2); color: var(--text); border: 1px solid var(--border);">Cancel</button>
            <button type="submit" name="edit_vp" class="btn btn-primary">Update Agreement</button>
          </div>
        </form>
      </div>
    </div>
    <?php endif; ?>

    <!-- INTELLIGENCE FOOTER -->
    <div class="card" style="margin-top: 40px; border: 1px solid var(--accent); background: rgba(108, 99, 255, 0.03); padding: 32px;">
      <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px;">
        <div>
          <h2 class="card-title" style="color: var(--accent); margin:0;">Supply Chain Intelligence</h2>
          <p style="font-size: 11px; color: var(--muted); margin-top: 5px;">Advanced price variance and risk analytics.</p>
        </div>
        <div class="badge badge-purple">AI Benchmark Enabled</div>
      </div>

      <div class="stats-grid" style="grid-template-columns: repeat(4, 1fr); margin-bottom: 32px;">
        <div style="text-align: center; border-right: 1px solid var(--border);">
          <div style="font-size: 24px; font-weight: 800; color: var(--text);"><?= $total_matrix_count ?></div>
          <div style="font-size: 10px; color: var(--muted); text-transform: uppercase;">Matrix Density</div>
        </div>
        <div style="text-align: center; border-right: 1px solid var(--border);">
          <div style="font-size: 24px; font-weight: 800; color: var(--accent3);"><?= number_format($avg_savings[0]['AVG_S'] ?? 0, 1) ?>%</div>
          <div style="font-size: 10px; color: var(--muted); text-transform: uppercase;">Avg. Portfolio Savings</div>
        </div>
        <div style="text-align: center; border-right: 1px solid var(--border);">
          <div style="font-size: 18px; font-weight: 800; color: var(--accent);"><?= htmlspecialchars($cat_performance[0]['CATEGORY'] ?? 'N/A') ?></div>
          <div style="font-size: 10px; color: var(--muted); text-transform: uppercase;">Most Competitive Category</div>
        </div>
        <div style="text-align: center;">
          <div style="font-size: 24px; font-weight: 800; color: var(--danger);"><?= $risk_products[0]['CNT'] ?? 0 ?></div>
          <div style="font-size: 10px; color: var(--muted); text-transform: uppercase;">Single-Source Dependencies</div>
        </div>
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
        <div style="background: var(--surface2); padding: 20px; border-radius: 12px; border: 1px solid var(--border);">
           <div class="card-title" style="font-size: 11px;">Savings Heatmap by Category</div>
           <div style="height: 200px;"><canvas id="savingsChart"></canvas></div>
        </div>
        <div style="background: var(--surface2); padding: 20px; border-radius: 12px; border: 1px solid var(--border);">
           <div class="card-title" style="font-size: 11px;">Redundancy Risk Distribution</div>
           <div style="height: 200px;"><canvas id="riskChart"></canvas></div>
        </div>
      </div>
    </div>

  </main>
</div>

<script>
function openModal(id) { document.getElementById(id).classList.add('active'); }
function closeModal(id) { document.getElementById(id).classList.remove('active'); }
window.onclick = function(e) { if(e.target.classList.contains('modal')) { e.target.classList.remove('active'); if(e.target.id === 'editVpModal') window.location.href='vendor_products.php'; } }
</script>

<?php oci_close($conn); ?>
</body>
</html>
