<?php include 'config.php'; ?>
<?php
$msg = '';

// ── ADD CONTRACT ─────────────────────────────────────────────────────
if (isset($_POST['add_contract'])) {
    if (!has_role(['Admin', 'Manager'])) {
        $msg = ['type'=>'danger','text'=>'Access Denied. Only Manager or Admin can create contracts.'];
    } else {
        $sql = "INSERT INTO VMS_VENDORCONTRACTS (CONTRACT_ID, VENDOR_ID, CONTRACT_NUMBER, START_DATE, END_DATE, PAYMENT_TERMS, DISCOUNT_PERCENTAGE, CONTRACT_VALUE, STATUS)
                VALUES (VMS_CONTRACT_SEQ.NEXTVAL, :vid, :cnum, TO_DATE(:sdate,'YYYY-MM-DD'), TO_DATE(:edate,'YYYY-MM-DD'), :pterms, :disc, :cval, :status)";
        $stmt = oci_parse($conn, $sql);
        oci_bind_by_name($stmt, ':vid',    $_POST['vendor_id']);
        oci_bind_by_name($stmt, ':cnum',   $_POST['contract_number']);
        oci_bind_by_name($stmt, ':sdate',  $_POST['start_date']);
        oci_bind_by_name($stmt, ':edate',  $_POST['end_date']);
        oci_bind_by_name($stmt, ':pterms', $_POST['payment_terms']);
        oci_bind_by_name($stmt, ':disc',   $_POST['discount_percentage']);
        oci_bind_by_name($stmt, ':cval',   $_POST['contract_value']);
        oci_bind_by_name($stmt, ':status', $_POST['status']);

        if (oci_execute($stmt, OCI_COMMIT_ON_SUCCESS)) {
            $msg = ['type'=>'success','text'=>'Contract registered successfully!'];
        } else {
            $e = oci_error($stmt);
            $msg = ['type'=>'danger','text'=>$e['message']];
        }
        oci_free_statement($stmt);
    }
}

// ── EDIT CONTRACT ────────────────────────────────────────────────────
if (isset($_POST['edit_contract'])) {
    $sql = "UPDATE VMS_VENDORCONTRACTS SET VENDOR_ID=:vid, CONTRACT_NUMBER=:cnum, START_DATE=TO_DATE(:sdate,'YYYY-MM-DD'), END_DATE=TO_DATE(:edate,'YYYY-MM-DD'), PAYMENT_TERMS=:pterms, DISCOUNT_PERCENTAGE=:disc, CONTRACT_VALUE=:cval, STATUS=:status WHERE CONTRACT_ID=:cid";
    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ':cid',    $_POST['contract_id']);
    oci_bind_by_name($stmt, ':vid',    $_POST['vendor_id']);
    oci_bind_by_name($stmt, ':cnum',   $_POST['contract_number']);
    oci_bind_by_name($stmt, ':sdate',  $_POST['start_date']);
    oci_bind_by_name($stmt, ':edate',  $_POST['end_date']);
    oci_bind_by_name($stmt, ':pterms', $_POST['payment_terms']);
    oci_bind_by_name($stmt, ':disc',   $_POST['discount_percentage']);
    oci_bind_by_name($stmt, ':cval',   $_POST['contract_value']);
    oci_bind_by_name($stmt, ':status', $_POST['status']);

    if (oci_execute($stmt, OCI_COMMIT_ON_SUCCESS)) {
        $msg = ['type'=>'success','text'=>'Contract details updated!'];
    } else {
        $e = oci_error($stmt);
        $msg = ['type'=>'danger','text'=>$e['message']];
    }
    oci_free_statement($stmt);
}

// ── DELETE CONTRACT ──────────────────────────────────────────────────
if (isset($_GET['delete'])) {
    if (!has_role('Admin')) {
        $msg = ['type'=>'danger','text'=>'Access Denied. Only Admin can delete contracts.'];
    } else {
        $sql = "DELETE FROM VMS_VENDORCONTRACTS WHERE CONTRACT_ID=:cid";
        $stmt = oci_parse($conn, $sql);
        $cid = (int)$_GET['delete'];
        oci_bind_by_name($stmt, ':cid', $cid);
        if (oci_execute($stmt, OCI_COMMIT_ON_SUCCESS)) {
            header("Location: contracts.php?deleted=1");
            exit;
        }
    }
}
if (isset($_GET['deleted'])) $msg = ['type'=>'success','text'=>'Contract removed from registry.'];

// ── FETCH DATA ───────────────────────────────────────────────────────
$search = $_GET['search'] ?? '';
$filter_status = $_GET['filter_status'] ?? '';
$filter_expiring = isset($_GET['expiring_soon']);
$filter_active = isset($_GET['active_only']);
$min_val = $_GET['min_val'] ?? '';

$where = "WHERE 1=1";
if ($search) $where .= " AND (UPPER(v.VENDOR_NAME) LIKE UPPER('%'||:s||'%') OR UPPER(c.CONTRACT_NUMBER) LIKE UPPER('%'||:s||'%'))";
if ($filter_status) $where .= " AND c.STATUS = :f";
if ($filter_active) $where .= " AND c.STATUS = 'Active' AND c.END_DATE >= SYSDATE";
if ($filter_expiring) $where .= " AND c.END_DATE BETWEEN SYSDATE AND SYSDATE + 30";
if ($min_val) $where .= " AND c.CONTRACT_VALUE >= :min_v";

$sql_c = "SELECT c.*, v.VENDOR_NAME, 
          TO_CHAR(c.START_DATE,'DD-Mon-YYYY') as SDATE_F, 
          TO_CHAR(c.END_DATE,'DD-Mon-YYYY') as EDATE_F, 
          VMS_Calculate_Discount(c.CONTRACT_ID) as DISCOUNT_AMT,
          CASE WHEN c.END_DATE < SYSDATE THEN 'YES' ELSE 'NO' END as IS_AUTO_EXPIRED
          FROM VMS_VENDORCONTRACTS c JOIN VMS_VENDORS v ON c.VENDOR_ID = v.VENDOR_ID 
          $where ORDER BY c.END_DATE ASC";
$stmt = oci_parse($conn, $sql_c);
if ($search) oci_bind_by_name($stmt, ':s', $search);
if ($filter_status) oci_bind_by_name($stmt, ':f', $filter_status);
if ($min_val) oci_bind_by_name($stmt, ':min_v', $min_val);
oci_execute($stmt);
$contracts = [];
while($r = oci_fetch_assoc($stmt)) $contracts[] = $r;

$vendors = db_fetch_all($conn, "SELECT VENDOR_ID, VENDOR_NAME FROM VMS_VENDORS WHERE STATUS='Active' ORDER BY VENDOR_NAME");

// Edit Fetch
$edit = null;
if (isset($_GET['edit'])) {
    $rows = db_fetch_all($conn, "SELECT c.*, TO_CHAR(c.START_DATE,'YYYY-MM-DD') as SDATE_RAW, TO_CHAR(c.END_DATE,'YYYY-MM-DD') as EDATE_RAW FROM VMS_VENDORCONTRACTS c WHERE CONTRACT_ID=:cid", [':cid'=>(int)$_GET['edit']]);
    $edit = $rows[0] ?? null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>VMS — Contract Registry</title>
  <link rel="stylesheet" href="style.css">
  <style>
    .status-pill { padding: 4px 10px; border-radius: 20px; font-size: 10px; font-weight: 700; text-transform: uppercase; }
    .val-text { font-weight: 700; color: var(--accent3); }
    .date-text { font-size: 11px; color: var(--muted); }
  </style>
</head>
<body>
<div class="layout">
  <?php include 'nav.php'; ?>

  <main class="main">
    <div class="page-header">
      <div>
        <h1 class="page-title">Contract <span>Management</span></h1>
        <p class="page-sub">Legal registry of procurement agreements and commercial terms.</p>
      </div>
    </div>

    <?php if ($msg): ?>
      <div class="alert alert-<?= $msg['type'] ?>"><?= $msg['text'] ?></div>
    <?php endif; ?>

    <div class="card" style="margin-bottom: 24px; padding: 16px;">
      <form method="GET" style="display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end;">
        <div class="form-group" style="margin:0;">
          <label style="font-size: 10px;">Search</label>
          <input type="text" name="search" placeholder="Vendor or #" value="<?= htmlspecialchars($search) ?>" style="width: 200px;">
        </div>
        <div class="form-group" style="margin:0;">
          <label style="font-size: 10px;">Status</label>
          <select name="filter_status" style="width: 120px;">
            <option value="">All</option>
            <option value="Active" <?= $filter_status=='Active'?'selected':'' ?>>Active</option>
            <option value="Expired" <?= $filter_status=='Expired'?'selected':'' ?>>Expired</option>
          </select>
        </div>
        <div class="form-group" style="margin:0;">
          <label style="font-size: 10px;">Min Value</label>
          <input type="number" name="min_val" placeholder="Min PKR" value="<?= htmlspecialchars($min_val) ?>" style="width: 120px;">
        </div>
        <div style="display: flex; gap: 15px; padding-bottom: 10px;">
          <label style="font-size: 12px; cursor: pointer;"><input type="checkbox" name="active_only" <?= $filter_active?'checked':'' ?>> Active Only</label>
          <label style="font-size: 12px; cursor: pointer;"><input type="checkbox" name="expiring_soon" <?= $filter_expiring?'checked':'' ?>> Expiring < 30d</label>
        </div>
        <button type="submit" class="btn btn-primary">Apply Filters</button>
        <div style="margin-bottom: 10px; margin-left: 10px; font-size: 12px; color: var(--muted); font-weight: 700;">
          Showing: <span style="color: var(--accent);"><?= count($contracts) ?></span> Agreements
        </div>
        <button type="button" class="btn" onclick="openModal('addCntModal')" style="margin-left: auto;">+ Register Contract</button>
      </form>
    </div>

    <div class="card">
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>ID</th><th>Contract #</th><th>Vendor</th><th>Period</th><th>Terms & Value</th><th>Discount (DB)</th><th>Status</th><th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($contracts as $c): 
              $isAutoExpired = $c['IS_AUTO_EXPIRED'] == 'YES';
              $status_text = $isAutoExpired ? '🔴 Expired' : $c['STATUS'];
              $badge = match($c['STATUS']) { 'Active' => ($isAutoExpired?'badge-red':'badge-green'), 'Expired' => 'badge-red', 'Terminated' => 'badge-orange', default => 'badge-purple' };
              $final_val = $c['CONTRACT_VALUE'] - $c['DISCOUNT_AMT'];
            ?>
            <tr class="<?= $isAutoExpired ? 'row-expired' : '' ?>">
              <td><span class="badge badge-purple">#<?= $c['CONTRACT_ID'] ?></span></td>
              <td style="font-weight: 700;"><?= htmlspecialchars($c['CONTRACT_NUMBER']) ?></td>
              <td><?= htmlspecialchars($c['VENDOR_NAME']) ?></td>
              <td>
                <div class="date-text">Start: <?= $c['SDATE_F'] ?></div>
                <div class="date-text">End: <span style="<?= $isAutoExpired?'color:var(--danger);font-weight:700;':'' ?>"><?= $c['EDATE_F'] ?></span></div>
              </td>
              <td>
                <div class="val-text">PKR <?= number_format((float)$c['CONTRACT_VALUE'], 0) ?></div>
                <div style="font-size: 10px; color: var(--muted);">Final: PKR <?= number_format($final_val, 0) ?></div>
              </td>
              <td>
                <div style="font-size: 12px; font-weight: 700; color: var(--accent);"><?= $c['DISCOUNT_PERCENTAGE'] ?>% Off</div>
                <div style="font-size: 10px; color: var(--muted);">- PKR <?= number_format((float)$c['DISCOUNT_AMT'], 0) ?></div>
              </td>
              <td><span class="badge <?= $badge ?>"><?= $status_text ?></span></td>
              <td>
                <div style="display: flex; gap: 4px;">
                  <a href="contracts.php?edit=<?= $c['CONTRACT_ID'] ?>" class="btn btn-sm" style="background: var(--surface2); color: var(--text); border: 1px solid var(--border);" title="Edit Details">✏️ Edit</a>
                  <?php if (has_role('Admin')): ?>
                    <a href="contracts.php?delete=<?= $c['CONTRACT_ID'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete contract?')" title="Delete Record">🗑 Delete</a>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($contracts)): ?><tr><td colspan="8" style="text-align: center; padding: 40px; color: var(--muted);">No contracts found.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ADD MODAL -->
    <div id="addCntModal" class="modal">
      <div class="modal-content">
        <div class="modal-header">
          <h2 class="card-title" style="margin:0; font-size: 20px;">Register New Contract</h2>
          <span class="modal-close" onclick="closeModal('addCntModal')">&times;</span>
        </div>
        <form method="POST">
          <div class="form-grid">
            <div class="form-group">
              <label>Vendor</label>
              <select name="vendor_id" required>
                <?php foreach ($vendors as $v): ?><option value="<?= $v['VENDOR_ID'] ?>"><?= htmlspecialchars($v['VENDOR_NAME']) ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label>Contract Number</label>
              <input type="text" name="contract_number" required placeholder="e.g. VMS/2026/001">
            </div>
            <div class="form-group">
              <label>Start Date</label>
              <input type="date" name="start_date" required value="<?= date('Y-m-d') ?>">
            </div>
            <div class="form-group">
              <label>End Date</label>
              <input type="date" name="end_date" required>
            </div>
            <div class="form-group">
              <label>Payment Terms</label>
              <input type="text" name="payment_terms" required placeholder="e.g. Net 30">
            </div>
            <div class="form-group">
              <label>Discount %</label>
              <input type="number" name="discount_percentage" step="0.1" value="0">
            </div>
            <div class="form-group">
              <label>Contract Value (PKR)</label>
              <input type="number" name="contract_value" required value="0">
            </div>
            <div class="form-group">
              <label>Status</label>
              <select name="status">
                <option value="Active">Active</option>
                <option value="Expired">Expired</option>
                <option value="Terminated">Terminated</option>
              </select>
            </div>
          </div>
          <div style="margin-top: 24px; display: flex; justify-content: flex-end; gap: 12px;">
            <button type="button" class="btn" onclick="closeModal('addCntModal')" style="background: var(--surface2); color: var(--text); border: 1px solid var(--border);">Cancel</button>
            <button type="submit" name="add_contract" class="btn btn-primary">Register Agreement</button>
          </div>
        </form>
      </div>
    </div>

    <!-- EDIT MODAL -->
    <?php if ($edit): ?>
    <div id="editCntModal" class="modal active">
      <div class="modal-content">
        <div class="modal-header">
          <h2 class="card-title" style="margin:0; font-size: 20px;">Edit Contract #<?= $edit['CONTRACT_ID'] ?></h2>
          <span class="modal-close" onclick="window.location.href='contracts.php'">&times;</span>
        </div>
        <form method="POST">
          <input type="hidden" name="contract_id" value="<?= $edit['CONTRACT_ID'] ?>">
          <div class="form-grid">
            <div class="form-group">
              <label>Vendor</label>
              <select name="vendor_id" required>
                <?php foreach ($vendors as $v): ?>
                  <option value="<?= $v['VENDOR_ID'] ?>" <?= $edit['VENDOR_ID']==$v['VENDOR_ID']?'selected':'' ?>><?= htmlspecialchars($v['VENDOR_NAME']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label>Contract Number</label>
              <input type="text" name="contract_number" required value="<?= htmlspecialchars($edit['CONTRACT_NUMBER']) ?>">
            </div>
            <div class="form-group">
              <label>Start Date</label>
              <input type="date" name="start_date" required value="<?= $edit['SDATE_RAW'] ?>">
            </div>
            <div class="form-group">
              <label>End Date</label>
              <input type="date" name="end_date" required value="<?= $edit['EDATE_RAW'] ?>">
            </div>
            <div class="form-group">
              <label>Payment Terms</label>
              <input type="text" name="payment_terms" required value="<?= htmlspecialchars($edit['PAYMENT_TERMS']) ?>">
            </div>
            <div class="form-group">
              <label>Discount %</label>
              <input type="number" name="discount_percentage" step="0.1" value="<?= $edit['DISCOUNT_PERCENTAGE'] ?>">
            </div>
            <div class="form-group">
              <label>Contract Value (PKR)</label>
              <input type="number" name="contract_value" required value="<?= $edit['CONTRACT_VALUE'] ?>">
            </div>
            <div class="form-group">
              <label>Status</label>
              <select name="status">
                <option value="Active" <?= $edit['STATUS']=='Active'?'selected':'' ?>>Active</option>
                <option value="Expired" <?= $edit['STATUS']=='Expired'?'selected':'' ?>>Expired</option>
                <option value="Terminated" <?= $edit['STATUS']=='Terminated'?'selected':'' ?>>Terminated</option>
              </select>
            </div>
          </div>
          <div style="margin-top: 24px; display: flex; justify-content: flex-end; gap: 12px;">
            <button type="button" class="btn" onclick="window.location.href='contracts.php'" style="background: var(--surface2); color: var(--text); border: 1px solid var(--border);">Cancel</button>
            <button type="submit" name="edit_contract" class="btn btn-primary">Update Agreement</button>
          </div>
        </form>
      </div>
    </div>
    <?php endif; ?>

  </main>
</div>

<script>
function openModal(id) { document.getElementById(id).classList.add('active'); }
function closeModal(id) { document.getElementById(id).classList.remove('active'); }
window.onclick = function(e) { if(e.target.classList.contains('modal')) { e.target.classList.remove('active'); if(e.target.id === 'editCntModal') window.location.href='contracts.php'; } }
</script>

<?php oci_close($conn); ?>
</body>
</html>