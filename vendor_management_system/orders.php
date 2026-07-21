<?php include 'config.php'; ?>
<?php
$msg = '';
$user = $_SESSION['vms_user'];

// ── ADD ORDER ────────────────────────────────────────────────────────
if (isset($_POST['add_order'])) {
    if (!has_role(['Admin', 'Purchaser'])) {
        $msg = ['type'=>'danger','text'=>'Access Denied. Only Admin or Purchaser can create orders.'];
    } else {
        $sql  = "INSERT INTO VMS_ORDERS (ORDER_ID, VENDOR_ID, PLACED_BY_USER_ID, ORDER_DATE, STATUS)
                 VALUES (VMS_ORDER_SEQ.NEXTVAL, :vid, :placed_by, TO_DATE(:odate,'YYYY-MM-DD'), :status)";
        $stmt = oci_parse($conn, $sql);
        oci_bind_by_name($stmt, ':vid',       $_POST['vendor_id']);
        oci_bind_by_name($stmt, ':placed_by', $user['user_id']); 
        oci_bind_by_name($stmt, ':odate',     $_POST['order_date']);
        oci_bind_by_name($stmt, ':status',    $_POST['status']);

        if (oci_execute($stmt, OCI_COMMIT_ON_SUCCESS)) {
            $msg = ['type'=>'success','text'=>'Order placed successfully!'];
        } else {
            $e = oci_error($stmt);
            $msg = ['type'=>'danger','text'=>$e['message']];
        }
        oci_free_statement($stmt);
    }
}

// ── UPDATE STATUS ─────────────────────────────────────────────────────
if (isset($_POST['update_status'])) {
    $sql  = "UPDATE VMS_ORDERS SET STATUS=:status WHERE ORDER_ID=:oid";
    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ':status', $_POST['new_status']);
    oci_bind_by_name($stmt, ':oid',    $_POST['order_id']);

    if (oci_execute($stmt, OCI_COMMIT_ON_SUCCESS)) {
        $msg = ['type'=>'success','text'=>'Order status updated!'];
    } else {
        $e = oci_error($stmt);
        $msg = ['type'=>'danger','text'=>$e['message']];
    }
    oci_free_statement($stmt);
}

// ── DELETE ────────────────────────────────────────────────────────────
if (isset($_GET['delete'])) {
    if (!has_role('Admin')) {
        $msg = ['type'=>'danger','text'=>'Only Admin can delete orders.'];
    } else {
        $sql  = "DELETE FROM VMS_ORDERS WHERE ORDER_ID=:oid";
        $stmt = oci_parse($conn, $sql);
        $oid  = (int)$_GET['delete'];
        oci_bind_by_name($stmt, ':oid', $oid);

        if (oci_execute($stmt, OCI_COMMIT_ON_SUCCESS)) {
            header("Location: orders.php?deleted=1");
            exit;
        } else {
            $e = oci_error($stmt);
            $msg = ['type'=>'danger','text'=>'Cannot delete — dependencies found. '.$e['message']];
        }
        oci_free_statement($stmt);
    }
}

if (isset($_GET['deleted'])) {
    $msg = ['type'=>'success','text'=>'Order removed from system.'];
}

// ── FETCH DATA ───────────────────────────────────────────────────────
$search = $_GET['search'] ?? '';
$where = $search ? "WHERE UPPER(v.VENDOR_NAME) LIKE UPPER('%'||:s||'%') OR UPPER(e.FULL_NAME) LIKE UPPER('%'||:s||'%')" : "";

$sql_orders = "
    SELECT o.ORDER_ID, v.VENDOR_NAME, e.FULL_NAME, e.ROLE AS EMP_ROLE,
           TO_CHAR(o.ORDER_DATE, 'DD-Mon-YYYY') AS ODATE, o.STATUS,
           VMS_Get_Order_Total(o.ORDER_ID) AS TOTAL
    FROM VMS_ORDERS o
    JOIN VMS_VENDORS v ON o.VENDOR_ID = v.VENDOR_ID
    JOIN VMS_USERS_EMPLOYEES e ON o.PLACED_BY_USER_ID = e.USER_ID
    $where
    ORDER BY o.ORDER_ID DESC
";
$stmt = oci_parse($conn, $sql_orders);
if ($search) oci_bind_by_name($stmt, ':s', $search);
oci_execute($stmt);
$orders = [];
while($row = oci_fetch_assoc($stmt)) $orders[] = $row;

$vendors = db_fetch_all($conn, "SELECT VENDOR_ID, VENDOR_NAME FROM VMS_VENDORS WHERE STATUS='Active' ORDER BY VENDOR_NAME");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>VMS — Purchase Orders</title>
  <link rel="stylesheet" href="style.css">
  <style>
    .order-id-badge { display: flex; flex-direction: column; align-items: flex-start; }
    .status-select { font-size: 11px; padding: 4px; background: var(--surface2); border: 1px solid var(--border); color: var(--text); border-radius: 4px; }
  </style>
</head>
<body>
<div class="layout">
  <?php include 'nav.php'; ?>

  <main class="main">
    <div class="page-header">
      <div>
        <h1 class="page-title">Purchase <span>Orders</span></h1>
        <p class="page-sub">Track procurement workflows and order fulfillment status.</p>
      </div>
    </div>

    <?php if ($msg): ?>
      <div class="alert alert-<?= $msg['type'] ?>"><?= $msg['text'] ?></div>
    <?php endif; ?>

    <div class="page-actions">
      <form method="GET" style="display: flex; gap: 8px;">
        <input type="text" name="search" placeholder="Search vendor or employee..." value="<?= htmlspecialchars($search) ?>" style="min-width: 300px;">
        <button type="submit" class="btn btn-primary">Filter</button>
      </form>
      <?php if (has_role(['Admin', 'Purchaser'])): ?>
        <button class="btn btn-primary" onclick="openModal('addOrderModal')">+ Create New Order</button>
      <?php endif; ?>
    </div>

    <div class="card">
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>ID & Details</th><th>Vendor</th><th>Placed By</th><th>Date</th><th>Total Amount</th><th>Status</th><th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($orders as $o): 
              $badge = match($o['STATUS']) { 'Delivered' => 'badge-green', 'Pending' => 'badge-orange', 'Cancelled' => 'badge-red', 'Approved' => 'badge-purple', default => 'badge-purple' };
            ?>
            <tr>
              <td>
                <div class="order-id-badge">
                  <span class="badge badge-purple" style="cursor: pointer;" onclick="window.location.href='order_details.php?order_id=<?= $o['ORDER_ID'] ?>'">#<?= $o['ORDER_ID'] ?></span>
                  <a href="order_details.php?order_id=<?= $o['ORDER_ID'] ?>" style="font-size: 10px; margin-top: 4px; color: var(--accent);">View Details →</a>
                </div>
              </td>
              <td><strong><?= htmlspecialchars($o['VENDOR_NAME']) ?></strong></td>
              <td>
                <div style="font-size: 13px;"><?= htmlspecialchars($o['FULL_NAME']) ?></div>
                <div style="font-size: 10px; color: var(--muted); text-transform: uppercase;"><?= $o['EMP_ROLE'] ?></div>
              </td>
              <td style="font-size: 12px;"><?= $o['ODATE'] ?></td>
              <td style="font-weight: 700; color: var(--accent3);">PKR <?= number_format((float)$o['TOTAL'], 0) ?></td>
              <td>
                <form method="POST" style="display: flex; gap: 6px; align-items: center;">
                  <input type="hidden" name="order_id" value="<?= $o['ORDER_ID'] ?>">
                  <select name="new_status" class="status-select">
                    <option value="Pending" <?= $o['STATUS']=='Pending'?'selected':'' ?>>Pending</option>
                    <option value="Approved" <?= $o['STATUS']=='Approved'?'selected':'' ?>>Approved</option>
                    <option value="Delivered" <?= $o['STATUS']=='Delivered'?'selected':'' ?>>Delivered</option>
                    <option value="Cancelled" <?= $o['STATUS']=='Cancelled'?'selected':'' ?>>Cancelled</option>
                  </select>
                  <button type="submit" name="update_status" class="btn btn-sm" style="padding: 4px 8px; font-size: 10px;">Update</button>
                </form>
              </td>
              <td>
                <?php if (has_role('Admin')): ?>
                  <a href="orders.php?delete=<?= $o['ORDER_ID'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete order?')">Delete</a>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($orders)): ?><tr><td colspan="7" style="text-align: center; padding: 40px; color: var(--muted);">No orders found.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ADD ORDER MODAL -->
    <div id="addOrderModal" class="modal">
      <div class="modal-content">
        <div class="modal-header">
          <h2 class="card-title" style="margin:0; font-size: 20px;">Initialize Purchase Order</h2>
          <span class="modal-close" onclick="closeModal('addOrderModal')">&times;</span>
        </div>
        <form method="POST">
          <div class="form-grid">
            <div class="form-group" style="grid-column: span 2;">
              <label>Vendor</label>
              <select name="vendor_id" required>
                <option value="">— Select Vendor —</option>
                <?php foreach ($vendors as $v): ?>
                  <option value="<?= $v['VENDOR_ID'] ?>"><?= htmlspecialchars($v['VENDOR_NAME']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label>Order Date</label>
              <input type="date" name="order_date" required value="<?= date('Y-m-d') ?>">
            </div>
            <div class="form-group">
              <label>Initial Status</label>
              <select name="status">
                <option value="Pending">Pending</option>
                <option value="Approved">Approved</option>
              </select>
            </div>
          </div>
          <p style="font-size: 11px; color: var(--muted); margin-top: 16px;">Note: You can add items to this order from the Details page after creation.</p>
          <div style="margin-top: 24px; display: flex; justify-content: flex-end; gap: 12px;">
            <button type="button" class="btn" onclick="closeModal('addOrderModal')" style="background: var(--surface2); color: var(--text); border: 1px solid var(--border);">Cancel</button>
            <button type="submit" name="add_order" class="btn btn-primary">Create Order Header</button>
          </div>
        </form>
      </div>
    </div>

  </main>
</div>

<script>
function openModal(id) { document.getElementById(id).classList.add('active'); }
function closeModal(id) { document.getElementById(id).classList.remove('active'); }
window.onclick = function(e) { if(e.target.classList.contains('modal')) e.target.classList.remove('active'); }
</script>

<?php oci_close($conn); ?>
</body>
</html>