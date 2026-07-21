<?php include 'config.php'; ?>
<?php
$msg = '';

// ── ADD EMPLOYEE ──────────────────────────────────────────────────────
if (isset($_POST['add_employee'])) {
    if (!has_role('Admin')) {
        $msg = ['type'=>'danger','text'=>'Access Denied. Only Admin can add employees.'];
    } else {
        $sql  = "INSERT INTO VMS_USERS_EMPLOYEES (USER_ID, FULL_NAME, EMAIL, DEPARTMENT, ROLE, PASSWORD)
                 VALUES (VMS_USER_SEQ.NEXTVAL, :fname, :email, :dept, :role, :pass)";
        $stmt = oci_parse($conn, $sql);
        $pass = '123'; // Default password
        oci_bind_by_name($stmt, ':fname', $_POST['full_name']);
        oci_bind_by_name($stmt, ':email', $_POST['email']);
        oci_bind_by_name($stmt, ':dept',  $_POST['department']);
        oci_bind_by_name($stmt, ':role',  $_POST['role']);
        oci_bind_by_name($stmt, ':pass',  $pass);

        if (oci_execute($stmt, OCI_COMMIT_ON_SUCCESS)) {
            $msg = ['type'=>'success','text'=>'Employee account created. Default password: 123'];
        } else {
            $e = oci_error($stmt);
            $msg = ['type'=>'danger','text'=>$e['message']];
        }
        oci_free_statement($stmt);
    }
}

// ── EDIT EMPLOYEE ─────────────────────────────────────────────────────
if (isset($_POST['edit_employee'])) {
    if (!has_role('Admin')) {
        $msg = ['type'=>'danger','text'=>'Access Denied. Only Admin can edit employees.'];
    } else {
        $sql  = "UPDATE VMS_USERS_EMPLOYEES SET FULL_NAME=:fname, EMAIL=:email, DEPARTMENT=:dept, ROLE=:role WHERE USER_ID=:target_uid";
        $stmt = oci_parse($conn, $sql);
        oci_bind_by_name($stmt, ':target_uid',   $_POST['user_id']);
        oci_bind_by_name($stmt, ':fname', $_POST['full_name']);
        oci_bind_by_name($stmt, ':email', $_POST['email']);
        oci_bind_by_name($stmt, ':dept',  $_POST['department']);
        oci_bind_by_name($stmt, ':role',  $_POST['role']);

        if (oci_execute($stmt, OCI_COMMIT_ON_SUCCESS)) {
            $msg = ['type'=>'success','text'=>'Employee record updated!'];
        } else {
            $e = oci_error($stmt);
            $msg = ['type'=>'danger','text'=>$e['message']];
        }
        oci_free_statement($stmt);
    }
}

// ── DELETE EMPLOYEE ───────────────────────────────────────────────────
if (isset($_GET['delete'])) {
    if (!has_role('Admin')) {
        $msg = ['type'=>'danger','text'=>'Access Denied. Only Admin can delete accounts.'];
    } else {
        $uid  = (int)$_GET['delete'];
        $sql  = "DELETE FROM VMS_USERS_EMPLOYEES WHERE USER_ID=:target_uid";
        $stmt = oci_parse($conn, $sql);
        oci_bind_by_name($stmt, ':target_uid', $uid);

        if (oci_execute($stmt, OCI_COMMIT_ON_SUCCESS)) {
            header("Location: employees.php?deleted=1");
            exit;
        } else {
            $e = oci_error($stmt);
            $msg = ['type'=>'danger','text'=>'Cannot delete — employee has order history.'];
        }
        oci_free_statement($stmt);
    }
}

if (isset($_GET['deleted'])) {
    $msg = ['type'=>'success','text'=>'Employee removed from directory.'];
}

// ── FETCH DATA ───────────────────────────────────────────────────────
$search = $_GET['search'] ?? '';
$where = $search ? "WHERE UPPER(FULL_NAME) LIKE UPPER('%'||:s||'%') OR UPPER(EMAIL) LIKE UPPER('%'||:s||'%')" : "";

$sql_e = "
    SELECT e.*, COUNT(o.ORDER_ID) AS ORDER_COUNT
    FROM VMS_USERS_EMPLOYEES e
    LEFT JOIN VMS_ORDERS o ON e.USER_ID = o.PLACED_BY_USER_ID
    $where
    GROUP BY e.USER_ID, e.FULL_NAME, e.EMAIL, e.DEPARTMENT, e.ROLE, e.CREATED_DATE, e.PASSWORD
    ORDER BY e.USER_ID DESC
";
$stmt = oci_parse($conn, $sql_e);
if ($search) oci_bind_by_name($stmt, ':s', $search);
oci_execute($stmt);
$employees = [];
while($r = oci_fetch_assoc($stmt)) $employees[] = $r;

// Edit Fetch
$edit = null;
if (isset($_GET['edit'])) {
    $rows = db_fetch_all($conn, "SELECT * FROM VMS_USERS_EMPLOYEES WHERE USER_ID=:u", [':u'=>(int)$_GET['edit']]);
    $edit = $rows[0] ?? null;
}

$role_options = ['Purchaser', 'Manager', 'Admin', 'Viewer', 'Store Keeper'];
$dept_options = ['Procurement', 'Finance', 'IT', 'Operations', 'Logistics', 'Warehouse'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>VMS — Personnel Directory</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="layout">
  <?php include 'nav.php'; ?>

  <main class="main">
    <div class="page-header">
      <div>
        <h1 class="page-title">Personnel <span>Directory</span></h1>
        <p class="page-sub">Manage system access and internal procurement roles.</p>
      </div>
    </div>

    <?php if ($msg): ?>
      <div class="alert alert-<?= $msg['type'] ?>"><?= $msg['text'] ?></div>
    <?php endif; ?>

    <div class="page-actions">
      <form method="GET" style="display: flex; gap: 8px;">
        <input type="text" name="search" placeholder="Search by name or email..." value="<?= htmlspecialchars($search) ?>" style="width: 250px;">
        <button type="submit" class="btn btn-primary">Filter</button>
      </form>
      <?php if (has_role('Admin')): ?>
        <button class="btn btn-primary" onclick="openModal('addEmpModal')">+ Add New Personnel</button>
      <?php endif; ?>
    </div>

    <div class="card">
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>ID</th><th>Personnel Name</th><th>Department</th><th>Assigned Role</th><th>Activity</th><th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($employees as $e): 
              $badge = match($e['ROLE']) { 'Admin' => 'badge-red', 'Manager' => 'badge-purple', 'Purchaser' => 'badge-green', 'Store Keeper' => 'badge-yellow', default => 'badge-purple' };
            ?>
            <tr>
              <td><span class="badge badge-purple">#<?= $e['USER_ID'] ?></span></td>
              <td>
                <div style="font-weight: 700;"><?= htmlspecialchars($e['FULL_NAME']) ?></div>
                <div style="font-size: 11px; color: var(--muted);"><?= htmlspecialchars($e['EMAIL']) ?></div>
              </td>
              <td><?= htmlspecialchars($e['DEPARTMENT'] ?? 'Unassigned') ?></td>
              <td><span class="badge <?= $badge ?>"><?= $e['ROLE'] ?></span></td>
              <td>
                <div style="font-size: 12px; font-weight: 600;"><?= $e['ORDER_COUNT'] ?> Orders</div>
                <div style="font-size: 10px; color: var(--muted);">Joined: <?= $e['CREATED_DATE'] ?></div>
              </td>
              <td>
                <?php if (has_role('Admin')): ?>
                  <a href="employees.php?edit=<?= $e['USER_ID'] ?>" class="btn btn-sm btn-secondary" style="background: var(--surface2); color: var(--text); border: 1px solid var(--border);">Edit</a>
                  <a href="employees.php?delete=<?= $e['USER_ID'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete account?')">Delete</a>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($employees)): ?><tr><td colspan="6" style="text-align: center; padding: 40px; color: var(--muted);">No personnel records found.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ADD MODAL -->
    <div id="addEmpModal" class="modal">
      <div class="modal-content">
        <div class="modal-header">
          <h2 class="card-title" style="margin:0; font-size: 20px;">Onboard Personnel</h2>
          <span class="modal-close" onclick="closeModal('addEmpModal')">&times;</span>
        </div>
        <form method="POST">
          <div class="form-grid">
            <div class="form-group" style="grid-column: span 2;">
              <label>Full Name</label>
              <input type="text" name="full_name" required placeholder="Legal Name">
            </div>
            <div class="form-group" style="grid-column: span 2;">
              <label>Corporate Email</label>
              <input type="email" name="email" required placeholder="name@company.com">
            </div>
            <div class="form-group">
              <label>Department</label>
              <select name="department" required>
                <?php foreach ($dept_options as $d): ?><option value="<?= $d ?>"><?= $d ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label>System Role</label>
              <select name="role" required>
                <?php foreach ($role_options as $r): ?><option value="<?= $r ?>"><?= $r ?></option><?php endforeach; ?>
              </select>
            </div>
          </div>
          <p style="font-size: 11px; color: var(--muted); margin-top: 16px;">Initial password will be set to '123' automatically.</p>
          <div style="margin-top: 24px; display: flex; justify-content: flex-end; gap: 12px;">
            <button type="button" class="btn" onclick="closeModal('addEmpModal')" style="background: var(--surface2); color: var(--text); border: 1px solid var(--border);">Cancel</button>
            <button type="submit" name="add_employee" class="btn btn-primary">Create Account</button>
          </div>
        </form>
      </div>
    </div>

    <!-- EDIT MODAL -->
    <?php if ($edit): ?>
    <div id="editEmpModal" class="modal active">
      <div class="modal-content">
        <div class="modal-header">
          <h2 class="card-title" style="margin:0; font-size: 20px;">Update Profile #<?= $edit['USER_ID'] ?></h2>
          <span class="modal-close" onclick="window.location.href='employees.php'">&times;</span>
        </div>
        <form method="POST">
          <input type="hidden" name="user_id" value="<?= $edit['USER_ID'] ?>">
          <div class="form-grid">
            <div class="form-group" style="grid-column: span 2;">
              <label>Full Name</label>
              <input type="text" name="full_name" required value="<?= htmlspecialchars($edit['FULL_NAME']) ?>">
            </div>
            <div class="form-group" style="grid-column: span 2;">
              <label>Corporate Email</label>
              <input type="email" name="email" required value="<?= htmlspecialchars($edit['EMAIL']) ?>">
            </div>
            <div class="form-group">
              <label>Department</label>
              <select name="department" required>
                <?php foreach ($dept_options as $d): ?>
                  <option value="<?= $d ?>" <?= $edit['DEPARTMENT']==$d?'selected':'' ?>><?= $d ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label>System Role</label>
              <select name="role" required>
                <?php foreach ($role_options as $r): ?>
                  <option value="<?= $r ?>" <?= $edit['ROLE']==$r?'selected':'' ?>><?= $r ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div style="margin-top: 30px; display: flex; justify-content: flex-end; gap: 12px;">
            <button type="button" class="btn" onclick="window.location.href='employees.php'" style="background: var(--surface2); color: var(--text); border: 1px solid var(--border);">Cancel</button>
            <button type="submit" name="edit_employee" class="btn btn-primary">Update Profile</button>
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
window.onclick = function(e) { if(e.target.classList.contains('modal')) { e.target.classList.remove('active'); if(e.target.id === 'editEmpModal') window.location.href='employees.php'; } }
</script>

<?php oci_close($conn); ?>
</body>
</html>