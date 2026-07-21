<?php include 'config.php'; ?>
<?php
$msg = '';

// ── ADD VENDOR (Using Stored Procedure + Sequence) ──────────────────
if (isset($_POST['add_vendor'])) {
    $vname  = $_POST['vendor_name'];
    $vemail = $_POST['email'];
    $vphone = $_POST['phone'];
    $vcity  = $_POST['city'];

    // Procedure call: VMS_Add_Vendor(p_name, p_email, p_phone, p_city)
    // The procedure internally uses VMS_VENDOR_SEQ.NEXTVAL
    $sql = "BEGIN VMS_Add_Vendor(:vname, :vemail, :vphone, :vcity); END;";
    $stmt = oci_parse($conn, $sql);

    oci_bind_by_name($stmt, ':vname',  $vname);
    oci_bind_by_name($stmt, ':vemail', $vemail);
    oci_bind_by_name($stmt, ':vphone', $vphone);
    oci_bind_by_name($stmt, ':vcity',  $vcity);

    if (oci_execute($stmt, OCI_COMMIT_ON_SUCCESS)) {
        $msg = ['type'=>'success','text'=>'Vendor added successfully via Stored Procedure!'];
    } else {
        $e = oci_error($stmt);
        $msg = ['type'=>'danger','text'=>$e['message']];
    }
    oci_free_statement($stmt);
}

// ── EDIT VENDOR ──────────────────────────────────────────────────────
if (isset($_POST['edit_vendor'])) {
    $sql  = "UPDATE VMS_VENDORS SET VENDOR_NAME=:vname, EMAIL=:email, PHONE=:phone, CITY=:city, STATUS=:status WHERE VENDOR_ID=:vid";
    $stmt = oci_parse($conn, $sql);

    oci_bind_by_name($stmt, ':vid',    $_POST['vendor_id']);
    oci_bind_by_name($stmt, ':vname',  $_POST['vendor_name']);
    oci_bind_by_name($stmt, ':email',  $_POST['email']);
    oci_bind_by_name($stmt, ':phone',  $_POST['phone']);
    oci_bind_by_name($stmt, ':city',   $_POST['city']);
    oci_bind_by_name($stmt, ':status', $_POST['status']);

    if (oci_execute($stmt, OCI_COMMIT_ON_SUCCESS)) {
        $msg = ['type'=>'success','text'=>'Vendor updated successfully!'];
    } else {
        $e = oci_error($stmt);
        $msg = ['type'=>'danger','text'=>$e['message']];
    }
    oci_free_statement($stmt);
}

// ── DELETE VENDOR ────────────────────────────────────────────────────
if (isset($_GET['delete'])) {
    $sql  = "DELETE FROM VMS_VENDORS WHERE VENDOR_ID=:vid";
    $stmt = oci_parse($conn, $sql);
    $vid  = (int)$_GET['delete'];
    oci_bind_by_name($stmt, ':vid', $vid);

    if (oci_execute($stmt, OCI_COMMIT_ON_SUCCESS)) {
        header("Location: vendors.php?deleted=1");
        exit;
    } else {
        $e = oci_error($stmt);
        $msg = ['type'=>'danger','text'=>$e['message']];
    }
    oci_free_statement($stmt);
}

if (isset($_GET['deleted'])) {
    $msg = ['type'=>'success','text'=>'Vendor removed from directory.'];
}

// ── FETCH DATA ───────────────────────────────────────────────────────
$search = $_GET['search'] ?? '';
$where = $search ? "WHERE UPPER(VENDOR_NAME) LIKE UPPER('%'||:s||'%') OR UPPER(CITY) LIKE UPPER('%'||:s||'%')" : "";

$sql_vendors = "SELECT * FROM VMS_VENDORS $where ORDER BY VENDOR_ID DESC";
$stmt = oci_parse($conn, $sql_vendors);
if ($search) oci_bind_by_name($stmt, ':s', $search);
oci_execute($stmt);
$vendors = [];
while ($row = oci_fetch_assoc($stmt)) $vendors[] = $row;
oci_free_statement($stmt);

// Edit Fetch
$edit = null;
if (isset($_GET['edit'])) {
    $rows = db_fetch_all($conn, "SELECT * FROM VMS_VENDORS WHERE VENDOR_ID=:v", [':v'=>(int)$_GET['edit']]);
    $edit = $rows[0] ?? null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>VMS — Vendors Directory</title>
  <link rel="stylesheet" href="style.css">
  <style>
    .search-box { display: flex; gap: 10px; }
    .search-box input { min-width: 300px; }
  </style>
</head>
<body>
<div class="layout">
  <?php include 'nav.php'; ?>

  <main class="main">
    <div class="page-header">
      <div>
        <h1 class="page-title">Vendors <span>Directory</span></h1>
        <p class="page-sub">Centralized management of all registered business partners.</p>
      </div>
    </div>

    <?php if ($msg): ?>
      <div class="alert alert-<?= $msg['type'] ?>"><?= $msg['text'] ?></div>
    <?php endif; ?>

    <div class="page-actions">
      <div class="search-box">
        <form method="GET" action="vendors.php" style="display: flex; gap: 8px;">
          <input type="text" name="search" placeholder="Search by name or city..." value="<?= htmlspecialchars($search) ?>">
          <button type="submit" class="btn btn-primary" style="padding: 10px 20px;">Search</button>
          <?php if ($search): ?><a href="vendors.php" class="btn btn-secondary" style="background: var(--surface2); color: var(--text); padding: 10px 15px; border: 1px solid var(--border);">Clear</a><?php endif; ?>
        </form>
      </div>
      <button class="btn btn-primary" onclick="openModal('addModal')">+ Register New Vendor</button>
    </div>

    <!-- VENDORS TABLE -->
    <div class="card">
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th style="width: 80px;">ID</th>
              <th style="width: 250px;">Vendor Name</th>
              <th>Contact Info</th>
              <th>Location</th>
              <th style="width: 120px;">Status</th>
              <th style="width: 180px;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($vendors as $v):
              $badge = match($v['STATUS']) { 'Active' => 'badge-green', 'Expired' => 'badge-red', 'Inactive' => 'badge-yellow', default => 'badge-purple' };
            ?>
            <tr class="<?= $v['STATUS'] === 'Expired' ? 'row-expired' : '' ?>">
              <td><span class="badge badge-purple">#<?= $v['VENDOR_ID'] ?></span></td>
              <td><strong><?= htmlspecialchars($v['VENDOR_NAME']) ?></strong></td>
              <td>
                <div style="font-size: 13px;"><?= htmlspecialchars($v['EMAIL']) ?></div>
                <div style="font-size: 11px; color: var(--muted);"><?= htmlspecialchars($v['PHONE']) ?></div>
              </td>
              <td><?= htmlspecialchars($v['CITY']) ?></td>
              <td><span class="badge <?= $badge ?>"><?= $v['STATUS'] ?></span></td>
              <td>
                <a href="vendors.php?edit=<?= $v['VENDOR_ID'] ?>" class="btn btn-sm" style="background: var(--surface2); color: var(--text); border: 1px solid var(--border);">Edit</a>
                <a href="vendors.php?delete=<?= $v['VENDOR_ID'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete vendor?')">Delete</a>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($vendors)): ?><tr><td colspan="6" style="text-align: center; padding: 40px; color: var(--muted);">No vendors found.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ADD VENDOR MODAL -->
    <div id="addModal" class="modal">
      <div class="modal-content">
        <div class="modal-header">
          <h2 class="card-title" style="margin: 0; font-size: 20px;">Register New Vendor</h2>
          <span class="modal-close" onclick="closeModal('addModal')">&times;</span>
        </div>
        <form method="POST" action="vendors.php">
          <div class="form-grid">
            <div class="form-group" style="grid-column: span 2;">
              <label>Vendor Name</label>
              <input type="text" name="vendor_name" required placeholder="Legal business name">
            </div>
            <div class="form-group">
              <label>Email Address</label>
              <input type="email" name="email" required placeholder="contact@vendor.com">
            </div>
            <div class="form-group">
              <label>Phone Number</label>
              <input type="text" name="phone" required placeholder="+92 300 1234567">
            </div>
            <div class="form-group">
              <label>City</label>
              <input type="text" name="city" required placeholder="e.g. Lahore">
            </div>
          </div>
          <div style="margin-top: 30px; display: flex; justify-content: flex-end; gap: 12px;">
            <button type="button" class="btn" onclick="closeModal('addModal')" style="background: var(--surface2); color: var(--text); border: 1px solid var(--border);">Cancel</button>
            <button type="submit" name="add_vendor" class="btn btn-primary">Add Vendor</button>
          </div>
        </form>
      </div>
    </div>

    <!-- EDIT VENDOR MODAL (Triggered by PHP if $edit is set) -->
    <?php if ($edit): ?>
    <div id="editModal" class="modal active">
      <div class="modal-content">
        <div class="modal-header">
          <h2 class="card-title" style="margin: 0; font-size: 20px;">Edit Vendor #<?= $edit['VENDOR_ID'] ?></h2>
          <span class="modal-close" onclick="window.location.href='vendors.php'">&times;</span>
        </div>
        <form method="POST" action="vendors.php">
          <input type="hidden" name="vendor_id" value="<?= $edit['VENDOR_ID'] ?>">
          <div class="form-grid">
            <div class="form-group" style="grid-column: span 2;">
              <label>Vendor Name</label>
              <input type="text" name="vendor_name" required value="<?= htmlspecialchars($edit['VENDOR_NAME']) ?>">
            </div>
            <div class="form-group">
              <label>Email Address</label>
              <input type="email" name="email" required value="<?= htmlspecialchars($edit['EMAIL']) ?>">
            </div>
            <div class="form-group">
              <label>Phone Number</label>
              <input type="text" name="phone" required value="<?= htmlspecialchars($edit['PHONE']) ?>">
            </div>
            <div class="form-group">
              <label>City</label>
              <input type="text" name="city" required value="<?= htmlspecialchars($edit['CITY']) ?>">
            </div>
            <div class="form-group">
              <label>Status</label>
              <select name="status" required>
                <option value="Active" <?= $edit['STATUS']=='Active'?'selected':'' ?>>Active</option>
                <option value="Inactive" <?= $edit['STATUS']=='Inactive'?'selected':'' ?>>Inactive</option>
                <option value="Expired" <?= $edit['STATUS']=='Expired'?'selected':'' ?>>Expired</option>
              </select>
            </div>
          </div>
          <div style="margin-top: 30px; display: flex; justify-content: flex-end; gap: 12px;">
            <button type="button" class="btn" onclick="window.location.href='vendors.php'" style="background: var(--surface2); color: var(--text); border: 1px solid var(--border);">Cancel</button>
            <button type="submit" name="edit_vendor" class="btn btn-primary">Update Vendor</button>
          </div>
        </form>
      </div>
    </div>
    <?php endif; ?>

  </main>
</div>

<script>
function openModal(id) {
  document.getElementById(id).classList.add('active');
}
function closeModal(id) {
  document.getElementById(id).classList.remove('active');
}
// Close modal on click outside
window.onclick = function(event) {
  if (event.target.classList.contains('modal')) {
    event.target.classList.remove('active');
    if (event.target.id === 'editModal') window.location.href = 'vendors.php';
  }
}
</script>

<?php oci_close($conn); ?>
</body>
</html>