<?php include 'config.php'; ?>
<?php
$msg = '';
$filter_order = $_GET['order_id'] ?? '';

// ── ADD / EDIT LINE ITEM ─────────────────────────────────────────────
if (isset($_POST['save_detail'])) {
    if (!has_role(['Admin', 'Purchaser'])) {
        $msg = ['type'=>'danger','text'=>'Access Denied.'];
    } else {
        $did = $_POST['order_detail_id'] ?? '';
        if ($did) {
            $sql  = "UPDATE VMS_ORDERDETAILS SET PRODUCT_ID=:pid, QUANTITY=:qty WHERE ORDER_DETAIL_ID=:did";
            $stmt = oci_parse($conn, $sql);
            oci_bind_by_name($stmt, ':did', $did);
        } else {
            $sql  = "INSERT INTO VMS_ORDERDETAILS (ORDER_DETAIL_ID, ORDER_ID, PRODUCT_ID, QUANTITY)
                     VALUES (VMS_ORDER_DETAIL_SEQ.NEXTVAL, :oid, :pid, :qty)";
            $stmt = oci_parse($conn, $sql);
            oci_bind_by_name($stmt, ':oid', $_POST['order_id']);
        }
        
        oci_bind_by_name($stmt, ':pid', $_POST['product_id']);
        oci_bind_by_name($stmt, ':qty', $_POST['quantity']);

        if (oci_execute($stmt, OCI_COMMIT_ON_SUCCESS)) {
            $msg = ['type'=>'success','text'=>'Order manifest updated.'];
        } else {
            $e = oci_error($stmt);
            $msg = ['type'=>'danger','text'=>$e['message']];
        }
        oci_free_statement($stmt);
    }
}

// ── REMOVE LINE ITEM ────────────────────────────────────────────────
if (isset($_GET['delete'])) {
    if (!has_role(['Admin', 'Purchaser'])) {
        $msg = ['type'=>'danger','text'=>'Access Denied.'];
    } else {
        $sql  = "DELETE FROM VMS_ORDERDETAILS WHERE ORDER_DETAIL_ID=:did";
        $stmt = oci_parse($conn, $sql);
        $did = (int)$_GET['delete'];
        oci_bind_by_name($stmt, ':did', $did);

        if (oci_execute($stmt, OCI_COMMIT_ON_SUCCESS)) {
            $back = $filter_order ? "order_details.php?order_id=".urlencode($filter_order) : "order_details.php";
            header("Location: $back&deleted=1");
            exit;
        }
    }
}
if (isset($_GET['deleted'])) $msg = ['type'=>'success','text'=>'Line item removed from order.'];

// ── FETCH DATA ───────────────────────────────────────────────────────
$order_info = null;
if ($filter_order) {
    $rows = db_fetch_all($conn, "SELECT o.*, v.VENDOR_NAME FROM VMS_ORDERS o JOIN VMS_VENDORS v ON o.VENDOR_ID = v.VENDOR_ID WHERE o.ORDER_ID = :o", [':o'=>(int)$filter_order]);
    $order_info = $rows[0] ?? null;
}

$sql_d = "SELECT d.*, p.PRODUCT_NAME, p.PRICE, (p.PRICE * d.QUANTITY) AS SUBTOTAL
          FROM VMS_ORDERDETAILS d JOIN VMS_PRODUCTS p ON d.PRODUCT_ID = p.PRODUCT_ID
          " . ($filter_order ? "WHERE d.ORDER_ID = :o" : "") . "
          ORDER BY d.ORDER_ID DESC, d.ORDER_DETAIL_ID ASC";
$stmt = oci_parse($conn, $sql_d);
if ($filter_order) oci_bind_by_name($stmt, ':o', $filter_order);
oci_execute($stmt);
$details = [];
while($r = oci_fetch_assoc($stmt)) $details[] = $r;

$products = db_fetch_all($conn, "SELECT PRODUCT_ID, PRODUCT_NAME, PRICE FROM VMS_PRODUCTS ORDER BY PRODUCT_NAME");
$all_orders = db_fetch_all($conn, "SELECT ORDER_ID FROM VMS_ORDERS ORDER BY ORDER_ID DESC");

$total_running = array_sum(array_column($details, 'SUBTOTAL'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>VMS — Order Manifest</title>
  <link rel="stylesheet" href="style.css">
  <style>
    .order-header-card { background: var(--surface2); border: 1px solid var(--border); padding: 20px; border-radius: 12px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; }
    .line-subtotal { font-weight: 700; color: var(--accent3); }
  </style>
</head>
<body>
<div class="layout">
  <?php include 'nav.php'; ?>

  <main class="main">
    <div class="page-header">
      <div>
        <h1 class="page-title">Order <span>Manifest</span></h1>
        <p class="page-sub">Line item management and procurement breakdown.</p>
      </div>
      <a href="orders.php" class="btn btn-sm" style="background: var(--surface2); color: var(--text); border: 1px solid var(--border);">← Back to Orders</a>
    </div>

    <?php if ($msg): ?>
      <div class="alert alert-<?= $msg['type'] ?>"><?= $msg['text'] ?></div>
    <?php endif; ?>

    <?php if ($order_info): ?>
    <div class="order-header-card">
      <div>
        <div style="font-size: 11px; color: var(--muted); text-transform: uppercase; font-weight: 700;">Purchase Order #<?= $order_info['ORDER_ID'] ?></div>
        <h2 style="margin: 4px 0; font-size: 22px;"><?= htmlspecialchars($order_info['VENDOR_NAME']) ?></h2>
        <div style="font-size: 13px;">Status: <span class="badge badge-purple"><?= $order_info['STATUS'] ?></span></div>
      </div>
      <div style="text-align: right;">
        <div style="font-size: 11px; color: var(--muted);">RUNNING TOTAL</div>
        <div style="font-size: 24px; font-weight: 800; color: var(--accent3);">PKR <?= number_format($total_running, 0) ?></div>
        <button class="btn btn-primary btn-sm" style="margin-top: 10px;" onclick="openAddModal()">+ Add Line Item</button>
      </div>
    </div>
    <?php endif; ?>

    <div class="card">
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Detail ID</th><?php if(!$filter_order): ?><th>Order #</th><?php endif; ?><th>Product Description</th><th>Unit Price</th><th>Quantity</th><th>Subtotal</th><th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($details as $d): ?>
            <tr>
              <td><span style="font-family: monospace; color: var(--muted);">LN-<?= $d['ORDER_DETAIL_ID'] ?></span></td>
              <?php if(!$filter_order): ?><td><a href="order_details.php?order_id=<?= $d['ORDER_ID'] ?>" class="badge badge-purple">#<?= $d['ORDER_ID'] ?></a></td><?php endif; ?>
              <td><strong><?= htmlspecialchars($d['PRODUCT_NAME']) ?></strong></td>
              <td>PKR <?= number_format((float)$d['PRICE'], 0) ?></td>
              <td><span class="badge badge-yellow" style="font-size: 11px;"><?= $d['QUANTITY'] ?> Units</span></td>
              <td><span class="line-subtotal">PKR <?= number_format((float)$d['SUBTOTAL'], 0) ?></span></td>
              <td>
                <?php if (has_role(['Admin', 'Purchaser'])): ?>
                  <button class="btn btn-sm" style="background: var(--surface2); color: var(--text); border: 1px solid var(--border);" 
                          onclick="openEditModal(<?= $d['ORDER_DETAIL_ID'] ?>, <?= $d['ORDER_ID'] ?>, <?= $d['PRODUCT_ID'] ?>, <?= $d['QUANTITY'] ?>)">Edit</button>
                  <a href="order_details.php?delete=<?= $d['ORDER_DETAIL_ID'] ?><?= $filter_order ? '&order_id='.$filter_order : '' ?>" class="btn btn-sm btn-danger" onclick="return confirm('Remove item?')">Remove</a>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($details)): ?><tr><td colspan="7" style="text-align: center; padding: 40px; color: var(--muted);">No line items found for this manifest.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ADD / EDIT MODAL -->
    <div id="itemModal" class="modal">
      <div class="modal-content">
        <div class="modal-header">
          <h2 class="card-title" id="modalTitle" style="margin:0; font-size: 20px;">Add Item to Manifest</h2>
          <span class="modal-close" onclick="closeModal('itemModal')">&times;</span>
        </div>
        <form method="POST">
          <input type="hidden" name="order_detail_id" id="edit_did">
          <div class="form-grid">
            <div class="form-group">
              <label>Order ID</label>
              <select name="order_id" id="form_oid" required>
                <?php if ($filter_order): ?>
                  <option value="<?= $filter_order ?>">#<?= $filter_order ?></option>
                <?php else: ?>
                  <?php foreach ($all_orders as $o): ?><option value="<?= $o['ORDER_ID'] ?>">#<?= $o['ORDER_ID'] ?></option><?php endforeach; ?>
                <?php endif; ?>
              </select>
            </div>
            <div class="form-group">
              <label>Product SKU</label>
              <select name="product_id" id="form_pid" required>
                <?php foreach ($products as $p): ?>
                  <option value="<?= $p['PRODUCT_ID'] ?>"><?= htmlspecialchars($p['PRODUCT_NAME']) ?> (PKR <?= number_format($p['PRICE'], 0) ?>)</option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label>Quantity</label>
              <input type="number" name="quantity" id="form_qty" required min="1" value="1">
            </div>
          </div>
          <div style="margin-top: 24px; display: flex; justify-content: flex-end; gap: 12px;">
            <button type="button" class="btn" onclick="closeModal('itemModal')" style="background: var(--surface2); color: var(--text); border: 1px solid var(--border);">Cancel</button>
            <button type="submit" name="save_detail" class="btn btn-primary">Save Line Item</button>
          </div>
        </form>
      </div>
    </div>

  </main>
</div>

<script>
function openModal(id) { document.getElementById(id).classList.add('active'); }
function closeModal(id) { document.getElementById(id).classList.remove('active'); }

function openAddModal() {
    document.getElementById('modalTitle').innerText = "Add Item to Manifest";
    document.getElementById('edit_did').value = "";
    document.getElementById('form_qty').value = "1";
    openModal('itemModal');
}

function openEditModal(did, oid, pid, qty) {
    document.getElementById('modalTitle').innerText = "Edit Line Item";
    document.getElementById('edit_did').value = did;
    document.getElementById('form_oid').value = oid;
    document.getElementById('form_pid').value = pid;
    document.getElementById('form_qty').value = qty;
    openModal('itemModal');
}

window.onclick = function(e) { if(e.target.classList.contains('modal')) e.target.classList.remove('active'); }
</script>

<?php oci_close($conn); ?>
</body>
</html>