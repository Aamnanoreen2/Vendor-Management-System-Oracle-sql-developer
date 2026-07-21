<?php include 'config.php'; ?>
<?php
// ── AJAX TOTAL FETCH HANDLER ─────────────────────────────────────────
if (isset($_GET['get_total'])) {
    header('Content-Type: application/json');
    $rows = db_fetch_all($conn, "SELECT VMS_Get_Order_Total(:o) AS TOTAL FROM DUAL", [':o' => (int)$_GET['get_total']]);
    echo json_encode(['total' => $rows[0]['TOTAL'] ?? 0]);
    exit;
}

$msg = '';

// ── ADD PAYMENT ──────────────────────────────────────────────────────
if (isset($_POST['add_payment'])) {
    if (!has_role(['Admin', 'Finance', 'Manager'])) {
        $msg = ['type'=>'danger','text'=>'Access Denied.'];
    } else {
        $oid = $_POST['order_id'];
        
        // Check if already paid to prevent duplicates
        $check = db_fetch_all($conn, "SELECT COUNT(*) AS CNT FROM VMS_PAYMENTS WHERE ORDER_ID = :o", [':o' => $oid]);
        if ($check[0]['CNT'] > 0) {
            $msg = ['type'=>'warning', 'text'=>"Order #$oid has already been paid. Duplicate prevented."];
        } else {
            $sql  = "INSERT INTO VMS_PAYMENTS (PAYMENT_ID, ORDER_ID, AMOUNT, PAYMENT_DATE)
                     VALUES (VMS_PAYMENT_SEQ.NEXTVAL, :oid, :amt, TO_DATE(:pdate,'YYYY-MM-DD'))";
            $stmt = oci_parse($conn, $sql);
            oci_bind_by_name($stmt, ':oid',   $oid);
            oci_bind_by_name($stmt, ':amt',   $_POST['amount']);
            oci_bind_by_name($stmt, ':pdate', $_POST['payment_date']);

            if (oci_execute($stmt, OCI_COMMIT_ON_SUCCESS)) {
                $msg = ['type'=>'success', 'text'=>'Transaction recorded. Order #'.$oid.' is now settled.'];
            } else {
                $e = oci_error($stmt);
                $msg = ['type'=>'danger', 'text'=>$e['message']];
            }
            oci_free_statement($stmt);
        }
    }
}

// ── DELETE PAYMENT ────────────────────────────────────────────────────
if (isset($_GET['delete'])) {
    if (!has_role('Admin')) {
        $msg = ['type'=>'danger','text'=>'Access Denied. Only Admin can delete transactions.'];
    } else {
        $sql = "DELETE FROM VMS_PAYMENTS WHERE PAYMENT_ID=:pid";
        $stmt = oci_parse($conn, $sql);
        $pid = (int)$_GET['delete'];
        oci_bind_by_name($stmt, ':pid', $pid);
        if (oci_execute($stmt, OCI_COMMIT_ON_SUCCESS)) {
            header("Location: payments.php?deleted=1");
            exit;
        }
    }
}
if (isset($_GET['deleted'])) $msg = ['type'=>'success','text'=>'Transaction removed from ledger.'];

// ── FETCH DATA ───────────────────────────────────────────────────────
$search = $_GET['search'] ?? '';
$where = $search ? "WHERE UPPER(v.VENDOR_NAME) LIKE UPPER('%'||:s||'%') OR UPPER(p.ORDER_ID) LIKE UPPER('%'||:s||'%')" : "";

$sql_p = "SELECT p.*, v.VENDOR_NAME, TO_CHAR(p.PAYMENT_DATE,'DD-Mon-YYYY') as PDATE_F 
          FROM VMS_PAYMENTS p JOIN VMS_ORDERS o ON p.ORDER_ID = o.ORDER_ID JOIN VMS_VENDORS v ON o.VENDOR_ID = v.VENDOR_ID 
          $where ORDER BY p.PAYMENT_DATE DESC";
$stmt = oci_parse($conn, $sql_p);
if ($search) oci_bind_by_name($stmt, ':s', $search);
oci_execute($stmt);
$payments = [];
while($r = oci_fetch_assoc($stmt)) $payments[] = $r;

$orders = db_fetch_all($conn, "SELECT o.ORDER_ID, v.VENDOR_NAME FROM VMS_ORDERS o JOIN VMS_VENDORS v ON o.VENDOR_ID = v.VENDOR_ID ORDER BY o.ORDER_ID DESC");

// Total Paid Stats
$stats = db_fetch_all($conn, "SELECT SUM(AMOUNT) as TOTAL, COUNT(*) as COUNT FROM VMS_PAYMENTS");
$total_paid = $stats[0]['TOTAL'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>VMS — Financial Ledger</title>
  <link rel="stylesheet" href="style.css">
  <style>
    .amt-text { font-weight: 700; color: var(--accent3); }
    .trans-card { border-bottom: 1px solid var(--border); padding: 15px 0; display: flex; justify-content: space-between; align-items: center; }
    .trans-card:last-child { border-bottom: none; }
  </style>
</head>
<body>
<div class="layout">
  <?php include 'nav.php'; ?>

  <main class="main">
    <div class="page-header">
      <div>
        <h1 class="page-title">Financial <span>Ledger</span></h1>
        <p class="page-sub">Track accounts payable and disbursement history.</p>
      </div>
    </div>

    <?php if ($msg): ?>
      <div class="alert alert-<?= $msg['type'] ?>"><?= $msg['text'] ?></div>
    <?php endif; ?>

    <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-bottom: 24px;">
      <div class="stat-card">
        <div class="stat-icon">💳</div>
        <div class="stat-value">PKR <?= number_format($total_paid, 0) ?></div>
        <div class="stat-label">Total Disbursed</div>
      </div>
      <div class="stat-card">
        <div class="stat-icon">📝</div>
        <div class="stat-value"><?= $stats[0]['COUNT'] ?? 0 ?></div>
        <div class="stat-label">Transactions Recorded</div>
      </div>
    </div>

    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
      <form method="GET" style="display: flex; gap: 8px;">
        <input type="text" name="search" placeholder="Search order # or vendor..." value="<?= htmlspecialchars($search) ?>" style="width: 250px;">
        <button type="submit" class="btn btn-primary">Search</button>
      </form>
      <button class="btn btn-primary" onclick="openPayModal()">+ Record Payment</button>
    </div>

    <div class="card" style="margin-bottom: 30px; border-left: 4px solid var(--warning);">
      <div class="card-title" style="display: flex; align-items: center; gap: 10px;">
        <span style="font-size: 18px;">⏳</span> Pending Disbursements
      </div>
      <p style="font-size: 12px; color: var(--muted); margin-bottom: 20px;">The following orders have been placed but have no recorded payments in the ledger.</p>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Order #</th><th>Vendor</th><th>Placed By</th><th>Total Owed</th><th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php 
            $pending = db_fetch_all($conn, "
                SELECT o.ORDER_ID, v.VENDOR_NAME, e.FULL_NAME, VMS_Get_Order_Total(o.ORDER_ID) as TOTAL
                FROM VMS_ORDERS o
                JOIN VMS_VENDORS v ON o.VENDOR_ID = v.VENDOR_ID
                JOIN VMS_USERS_EMPLOYEES e ON o.PLACED_BY_USER_ID = e.USER_ID
                WHERE o.ORDER_ID NOT IN (SELECT ORDER_ID FROM VMS_PAYMENTS)
                ORDER BY o.ORDER_ID DESC
            ");
            foreach ($pending as $pen): ?>
            <tr>
              <td><span class="badge badge-orange">#<?= $pen['ORDER_ID'] ?></span></td>
              <td><strong><?= htmlspecialchars($pen['VENDOR_NAME']) ?></strong></td>
              <td style="font-size: 12px;"><?= htmlspecialchars($pen['FULL_NAME']) ?></td>
              <td style="font-weight: 700;">PKR <?= number_format($pen['TOTAL'], 0) ?></td>
              <td>
                <button class="btn btn-sm btn-primary" onclick="openPayModal('<?= $pen['ORDER_ID'] ?>')">Pay Now</button>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($pending)): ?><tr><td colspan="5" style="text-align: center; padding: 20px; color: var(--muted);">All orders have been settled.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="card">
      <div class="card-title">Transaction History</div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Trans ID</th><th>Order Info</th><th>Vendor</th><th>Date</th><th>Amount Paid</th><th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($payments as $p): ?>
            <tr>
              <td><span style="font-family: monospace; color: var(--muted);">TXN-<?= $p['PAYMENT_ID'] ?></span></td>
              <td><span class="badge badge-purple" style="cursor: pointer;" onclick="window.location.href='order_details.php?order_id=<?= $p['ORDER_ID'] ?>'">Order #<?= $p['ORDER_ID'] ?></span></td>
              <td><strong><?= htmlspecialchars($p['VENDOR_NAME']) ?></strong></td>
              <td><?= $p['PDATE_F'] ?></td>
              <td><span class="amt-text">PKR <?= number_format((float)$p['AMOUNT'], 0) ?></span></td>
              <td>
                <?php if (has_role('Admin')): ?>
                  <a href="payments.php?delete=<?= $p['PAYMENT_ID'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Void transaction?')">Void</a>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($payments)): ?><tr><td colspan="6" style="text-align: center; padding: 40px; color: var(--muted);">No transactions found in ledger.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ADD MODAL -->
    <div id="addPayModal" class="modal">
      <div class="modal-content">
        <div class="modal-header">
          <h2 class="card-title" style="margin:0; font-size: 20px;">Record Disbursement</h2>
          <span class="modal-close" onclick="closeModal('addPayModal')">&times;</span>
        </div>
        <form method="POST">
          <div class="form-grid">
            <div class="form-group" style="grid-column: span 2;">
              <label>Select Order</label>
              <select name="order_id" required id="order_sel" onchange="fetchTotal()">
                <option value="">-- Choose Order --</option>
                <?php foreach ($orders as $o): ?><option value="<?= $o['ORDER_ID'] ?>">Order #<?= $o['ORDER_ID'] ?> (<?= htmlspecialchars($o['VENDOR_NAME']) ?>)</option><?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label>Amount (PKR)</label>
              <input type="number" name="amount" id="pay_amt" required value="0">
            </div>
            <div class="form-group">
              <label>Payment Date</label>
              <input type="date" name="payment_date" value="<?= date('Y-m-d') ?>" required>
            </div>
          </div>
          <p style="font-size: 11px; color: var(--muted); margin-top: 16px;">This will record a financial transaction against the selected order.</p>
          <div style="margin-top: 24px; display: flex; justify-content: flex-end; gap: 12px;">
            <button type="button" class="btn" onclick="closeModal('addPayModal')" style="background: var(--surface2); color: var(--text); border: 1px solid var(--border);">Cancel</button>
            <button type="submit" name="add_payment" class="btn btn-primary">Record Transaction</button>
          </div>
        </form>
      </div>
    </div>

  </main>
</div>

<script>
function openModal(id) { 
    document.getElementById(id).classList.add('active'); 
}
function closeModal(id) { 
    document.getElementById(id).classList.remove('active'); 
}

function openPayModal(oid = '') {
    // Reset form first
    document.getElementById('pay_amt').value = '0';
    document.getElementById('order_sel').value = oid;
    
    if (oid) {
        fetchTotal();
    }
    openModal('addPayModal');
}

function fetchTotal() {
    const oid = document.getElementById('order_sel').value;
    const amtInput = document.getElementById('pay_amt');
    if (!oid) return;
    
    amtInput.classList.add('loading');
    fetch('payments.php?get_total=' + oid)
        .then(res => res.json())
        .then(data => {
            amtInput.classList.remove('loading');
            if (data.total) amtInput.value = data.total;
        });
}

window.onclick = function(e) { if(e.target.classList.contains('modal')) e.target.classList.remove('active'); }
</script>

<?php oci_close($conn); ?>
</body>
</html>