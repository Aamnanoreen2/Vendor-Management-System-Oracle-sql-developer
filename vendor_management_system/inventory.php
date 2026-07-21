<?php include 'config.php'; ?>
<?php
$msg = '';


// ── ADD / EDIT RECORD ──────────────────────────────────────────────
if (isset($_POST['save_inventory'])) {
    $iid      = $_POST['inventory_id'] ?? '';
    $pid      = (int)$_POST['product_id'];
    $qty      = (int)$_POST['quantity_in_stock'];
    $reorder  = (int)$_POST['reorder_level'];

    if ($iid) {
        $sql = "UPDATE VMS_INVENTORY SET QUANTITY_IN_STOCK = :qty, REORDER_LEVEL = :reorder, LAST_UPDATED = SYSDATE WHERE INVENTORY_ID = :iid";
        $stmt = oci_parse($conn, $sql);
        oci_bind_by_name($stmt, ':iid', $iid);
    } else {
        $sql = "INSERT INTO VMS_INVENTORY (INVENTORY_ID, PRODUCT_ID, QUANTITY_IN_STOCK, REORDER_LEVEL) VALUES (VMS_INVENTORY_SEQ.NEXTVAL, :pid, :qty, :reorder)";
        $stmt = oci_parse($conn, $sql);
        oci_bind_by_name($stmt, ':pid', $pid);
    }
    
    oci_bind_by_name($stmt, ':qty', $qty);
    oci_bind_by_name($stmt, ':reorder', $reorder);

    if (oci_execute($stmt, OCI_COMMIT_ON_SUCCESS)) {
        $msg = ['type' => 'success', 'text' => 'Inventory record saved.'];
    } else {
        $e = oci_error($stmt);
        $msg = ['type' => 'danger', 'text' => $e['message']];
    }
    oci_free_statement($stmt);
}
// ── DELETE RECORD ───────────────────────────────────────────────────
if (isset($_GET['delete'])) {
    if (!has_role('Admin')) {
        $msg = ['type'=>'danger','text'=>'Access Denied.'];
    } else {
        $did = (int)$_GET['delete'];
        $sql = "DELETE FROM VMS_INVENTORY WHERE INVENTORY_ID = :did";
        $stmt = oci_parse($conn, $sql);
        oci_bind_by_name($stmt, ':did', $did);
        if (oci_execute($stmt, OCI_COMMIT_ON_SUCCESS)) {
            header("Location: inventory.php?deleted=1");
            exit;
        }
    }
}
if (isset($_GET['deleted'])) $msg = ['type'=>'success','text'=>'Inventory record removed.'];

// ── FETCH INVENTORY DATA ───────────────────────────────────────────
$inventory = db_fetch_all($conn, "
    SELECT i.*, p.PRODUCT_NAME, p.CATEGORY,
           VMS_Is_Low_Stock(p.PRODUCT_ID) AS STOCK_STATUS
    FROM VMS_INVENTORY i
    JOIN VMS_PRODUCTS p ON i.PRODUCT_ID = p.PRODUCT_ID
    ORDER BY p.PRODUCT_NAME
");

// ── FETCH VENDOR SUGGESTIONS (Lowest price for low stock items) ─────
function get_best_vendor($conn, $pid) {
    $sql = "SELECT v.VENDOR_NAME, vp.SUPPLY_PRICE 
            FROM VMS_VENDORPRODUCTS vp 
            JOIN VMS_VENDORS v ON vp.VENDOR_ID = v.VENDOR_ID 
            WHERE vp.PRODUCT_ID = :pid 
            ORDER BY vp.SUPPLY_PRICE ASC 
            FETCH FIRST 1 ROWS ONLY";
    $rows = db_fetch_all($conn, $sql, [':pid' => $pid]);
    return $rows[0] ?? null;
}

$products = db_fetch_all($conn, "SELECT PRODUCT_ID, PRODUCT_NAME FROM VMS_PRODUCTS ORDER BY PRODUCT_NAME");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>VMS — Inventory Management</title>
  <link rel="stylesheet" href="style.css">
  <style>
    .stock-card { border-left: 4px solid var(--border); transition: transform 0.2s; }
    .stock-card.low { border-left-color: var(--danger); background: rgba(255,71,87,0.02); }
    .suggestion { font-size: 11px; color: var(--accent3); margin-top: 8px; padding-top: 8px; border-top: 1px solid var(--border); }
  </style>
</head>
<body>
<div class="layout">
  <?php include 'nav.php'; ?>

  <main class="main">
    <div class="page-header">
      <div>
        <h1 class="page-title">Inventory <span>Control</span></h1>
        <p class="page-sub">Monitor stock levels and manage restock workflows.</p>
      </div>
    </div>

    <?php if ($msg): ?>
      <div class="alert alert-<?= $msg['type'] ?>"><?= $msg['text'] ?></div>
    <?php endif; ?>

    <div style="display: flex; justify-content: flex-end; margin-bottom: 24px;">
      <button class="btn btn-primary" onclick="openModal('addInvModal')">+ Add Product to Inventory</button>
    </div>

    <!-- INVENTORY GRID -->
    <div class="stats-grid" style="grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 24px;">
      <?php foreach ($inventory as $i): 
        $isLow = strpos($i['STOCK_STATUS'], 'YES') !== false;
        $pct = min(100, ($i['QUANTITY_IN_STOCK'] / max(1, $i['REORDER_LEVEL'] * 2)) * 100);
        $suggestion = $isLow ? get_best_vendor($conn, $i['PRODUCT_ID']) : null;
      ?>
      <div class="card stock-card <?= $isLow ? 'low' : '' ?>">
        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
          <div>
            <div style="font-size: 10px; color: var(--muted); text-transform: uppercase;"><?= $i['CATEGORY'] ?></div>
            <h3 style="margin: 4px 0 12px; font-size: 18px;"><?= htmlspecialchars($i['PRODUCT_NAME']) ?></h3>
          </div>
          <span class="badge <?= $isLow ? 'badge-red' : 'badge-green' ?>">
            <?= $isLow ? 'Low Stock' : 'In Stock' ?>
          </span>
        </div>

        <div style="margin-bottom: 16px;">
          <div style="display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 6px;">
            <span>Current Level: <strong><?= $i['QUANTITY_IN_STOCK'] ?></strong></span>
            <span style="color: var(--muted);">Reorder at: <?= $i['REORDER_LEVEL'] ?></span>
          </div>
          <div style="height: 8px; background: var(--surface2); border-radius: 4px; overflow: hidden;">
            <div style="height: 100%; width: <?= $pct ?>%; background: <?= $isLow ? 'var(--danger)' : 'var(--accent3)' ?>;"></div>
          </div>
        </div>

        <div style="display: flex; gap: 8px;">
          <button class="btn btn-sm" style="flex: 1; background: var(--surface2); color: var(--text); border: 1px solid var(--border);"
                  onclick="openEditModal(<?= $i['INVENTORY_ID'] ?>, <?= $i['PRODUCT_ID'] ?>, <?= $i['QUANTITY_IN_STOCK'] ?>, <?= $i['REORDER_LEVEL'] ?>)">Settings</button>
          <?php if (has_role('Admin')): ?>
          <a href="inventory.php?delete=<?= $i['INVENTORY_ID'] ?>" class="btn btn-sm btn-danger" style="flex: 0.3; padding: 4px;" onclick="return confirm('Remove this product from inventory tracking?')">🗑</a>
          <?php endif; ?>
        </div>

        <?php if ($suggestion): ?>
        <div class="suggestion">
          💡 Suggestion: Order from <strong><?= htmlspecialchars($suggestion['VENDOR_NAME']) ?></strong> @ PKR <?= number_format($suggestion['SUPPLY_PRICE']) ?>
        </div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- ADD MODAL -->
    <div id="addInvModal" class="modal">
      <div class="modal-content">
        <div class="modal-header">
          <h2 class="card-title" style="margin:0;">Initialize Inventory</h2>
          <span class="modal-close" onclick="closeModal('addInvModal')">&times;</span>
        </div>
        <form method="POST">
          <div class="form-grid">
            <div class="form-group" style="grid-column: span 2;">
              <label>Product</label>
              <select name="product_id" required>
                <?php foreach ($products as $p): ?>
                <option value="<?= $p['PRODUCT_ID'] ?>"><?= htmlspecialchars($p['PRODUCT_NAME']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label>Initial Quantity</label>
              <input type="number" name="quantity_in_stock" required value="0">
            </div>
            <div class="form-group">
              <label>Reorder Threshold</label>
              <input type="number" name="reorder_level" required value="10">
            </div>
          </div>
          <div style="margin-top: 24px; display: flex; justify-content: flex-end; gap: 12px;">
            <button type="submit" name="save_inventory" class="btn btn-primary">Initialize Stock</button>
          </div>
        </form>
      </div>
    </div>


    <!-- EDIT SETTINGS MODAL -->
    <div id="editInvModal" class="modal">
      <div class="modal-content">
        <div class="modal-header">
          <h2 class="card-title" style="margin:0;">Inventory Settings</h2>
          <span class="modal-close" onclick="closeModal('editInvModal')">&times;</span>
        </div>
        <form method="POST">
          <input type="hidden" name="inventory_id" id="edit_iid">
          <input type="hidden" name="product_id" id="edit_pid">
          <div class="form-grid">
            <div class="form-group">
              <label>Current Quantity</label>
              <input type="number" name="quantity_in_stock" id="edit_qty" required>
            </div>
            <div class="form-group">
              <label>Reorder Threshold</label>
              <input type="number" name="reorder_level" id="edit_reorder" required>
            </div>
          </div>
          <div style="margin-top: 24px; display: flex; justify-content: flex-end; gap: 12px;">
            <button type="submit" name="save_inventory" class="btn btn-primary">Update Settings</button>
          </div>
        </form>
      </div>
    </div>

  </main>
</div>

<script>
function openModal(id) { document.getElementById(id).classList.add('active'); }
function closeModal(id) { document.getElementById(id).classList.remove('active'); }


function openEditModal(iid, pid, qty, reorder) {
    document.getElementById('edit_iid').value = iid;
    document.getElementById('edit_pid').value = pid;
    document.getElementById('edit_qty').value = qty;
    document.getElementById('edit_reorder').value = reorder;
    openModal('editInvModal');
}

window.onclick = function(e) { if(e.target.classList.contains('modal')) e.target.classList.remove('active'); }
</script>
</body>
</html>
