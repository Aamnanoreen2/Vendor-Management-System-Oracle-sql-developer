<?php include 'config.php'; ?>
<?php
$msg = '';
$user = $_SESSION['vms_user'];

// ── UPDATE PROFILE ───────────────────────────────────────────────────
if (isset($_POST['update_profile'])) {
    $name = $_POST['full_name'];
    $email = $_POST['email'];
    $uid = $user['user_id'];
    
    $sql = "UPDATE VMS_USERS_EMPLOYEES SET FULL_NAME = :name, EMAIL = :email WHERE USER_ID = :uid";
    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ':name', $name);
    oci_bind_by_name($stmt, ':email', $email);
    oci_bind_by_name($stmt, ':uid', $uid);
    
    if (oci_execute($stmt, OCI_COMMIT_ON_SUCCESS)) {
        $_SESSION['vms_user']['full_name'] = $name;
        $_SESSION['vms_user']['email'] = $email;
        $msg = ['type' => 'success', 'text' => 'Profile updated successfully.'];
    } else {
        $e = oci_error($stmt);
        $msg = ['type' => 'danger', 'text' => $e['message']];
    }
}

// ── CHANGE PASSWORD ──────────────────────────────────────────────────
if (isset($_POST['change_pass'])) {
    $new_p = $_POST['new_password'];
    $uid = $user['user_id'];
    
    $sql = "UPDATE VMS_USERS_EMPLOYEES SET PASSWORD = :p WHERE USER_ID = :uid";
    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ':p', $new_p);
    oci_bind_by_name($stmt, ':uid', $uid);
    
    if (oci_execute($stmt, OCI_COMMIT_ON_SUCCESS)) {
        $msg = ['type' => 'success', 'text' => 'Security credentials updated.'];
    }
}

// ── RAW SQL CONSOLE (DDL/DML/DCL/TCL) ────────────────────────────────
$sql_res = null;
if (isset($_POST['run_sql']) && has_role('Admin')) {
    $raw_sql = trim($_POST['raw_sql'], " ;");
    $stmt = oci_parse($conn, $raw_sql);
    if ($stmt) {
        $success = @oci_execute($stmt, OCI_COMMIT_ON_SUCCESS);
        if ($success) {
            $type = strtoupper(explode(' ', $raw_sql)[0]);
            if ($type === 'SELECT') {
                $sql_res = [];
                while ($r = oci_fetch_assoc($stmt)) $sql_res[] = $r;
            } else {
                $msg = ['type' => 'success', 'text' => "SQL Execution Successful ($type)."];
            }
        } else {
            $e = oci_error($stmt);
            $msg = ['type' => 'danger', 'text' => 'SQL Error: ' . $e['message']];
        }
        oci_free_statement($stmt);
    }
}

// System Info (Mock DDL/Table Meta)
$table_counts = db_fetch_all($conn, "SELECT table_name, num_rows FROM all_tables WHERE owner = 'VMS_USER' AND table_name LIKE 'VMS_%'");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>VMS — System Settings</title>
  <link rel="stylesheet" href="style.css">
  <style>
    .settings-grid { display: grid; grid-template-columns: 1fr 1.5fr; gap: 24px; }
    .meta-item { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid var(--border); font-size: 12px; }
  </style>
</head>
<body>
<div class="layout">
  <?php include 'nav.php'; ?>

  <main class="main">
    <div class="page-header">
      <div>
        <h1 class="page-title">System <span>Settings</span></h1>
        <p class="page-sub">Manage your profile and system environment.</p>
      </div>
    </div>

    <?php if ($msg): ?>
      <div class="alert alert-<?= $msg['type'] ?>"><?= $msg['text'] ?></div>
    <?php endif; ?>

    <div class="settings-grid">
      <div>
        <div class="card">
          <div class="card-title">User Profile</div>
          <form method="POST">
            <div class="form-group" style="margin-bottom: 16px;">
              <label>Full Name</label>
              <input type="text" name="full_name" value="<?= htmlspecialchars($user['full_name']) ?>" required>
            </div>
            <div class="form-group" style="margin-bottom: 16px;">
              <label>Email Address</label>
              <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
            </div>
            <div class="form-group" style="margin-bottom: 20px;">
              <label>Department / Role</label>
              <input type="text" value="<?= $user['role'] ?>" disabled style="opacity: 0.6;">
            </div>
            <button type="submit" name="update_profile" class="btn btn-primary" style="width: 100%;">Save Profile</button>
          </form>
        </div>

        <div class="card">
          <div class="card-title">Security</div>
          <form method="POST">
            <div class="form-group" style="margin-bottom: 16px;">
              <label>New Password</label>
              <input type="password" name="new_password" placeholder="••••••••" required>
            </div>
            <button type="submit" name="change_pass" class="btn btn-sm" style="width: 100%; background: var(--surface2); color: var(--text); border: 1px solid var(--border);">Update Password</button>
          </form>
        </div>
      </div>

      <div>
        <div class="card">
          <div class="card-title">Database Management (DDL/DML Status)</div>
          <p style="font-size: 12px; color: var(--muted); margin-bottom: 20px;">
            The VMS system is connected to the <strong>ORCLPDB</strong> schema. All management modules utilize DML (Insert/Update/Delete) via sequences and procedures.
          </p>
          
          <div style="margin-bottom: 24px;">
            <div style="font-size: 11px; font-weight: 700; color: var(--accent); margin-bottom: 10px;">MANAGED TABLES</div>
            <?php foreach ($table_counts as $t): ?>
            <div class="meta-item">
              <span style="font-family: monospace;"><?= $t['TABLE_NAME'] ?></span>
              <span class="badge badge-purple"><?= number_format($t['NUM_ROWS'] ?? 0) ?> rows</span>
            </div>
            <?php endforeach; ?>
          </div>

          <div class="sql-block">
            <span class="cm">-- System health check</span>
            <span class="kw">SELECT</span> * <span class="kw">FROM</span> VMS_ORDERS <span class="kw">WHERE</span> status = <span class="str">'Pending'</span>;
          </div>
        </div>

        <div class="card" style="background: rgba(255, 71, 87, 0.05); border-color: rgba(255, 71, 87, 0.2);">
          <div class="card-title" style="color: var(--danger);">Danger Zone</div>
          <p style="font-size: 12px; color: var(--muted); margin-bottom: 16px;">Actions here affect the core system data definition.</p>
          <button class="btn btn-danger btn-sm" onclick="alert('Database maintenance mode is currently locked for your protection.')">Rebuild Sequences</button>
        </div>

        <div class="card" style="margin-top: 24px; border: 1px solid var(--accent);">
          <div class="card-title" style="color: var(--accent);">Advanced SQL Console</div>
          <p style="font-size: 11px; color: var(--muted); margin-bottom: 12px;">Run DDL, DML, TCL, or DCL commands directly against the database (Admin Only).</p>
          <form method="POST">
            <textarea name="raw_sql" style="width: 100%; height: 100px; background: #000; color: #43e97b; font-family: 'DM Mono', monospace; font-size: 12px; padding: 12px; border: 1px solid var(--border); border-radius: 8px; outline: none; margin-bottom: 12px;" placeholder="SELECT * FROM VMS_INVENTORY;"><?= htmlspecialchars($_POST['raw_sql'] ?? '') ?></textarea>
            <button type="submit" name="run_sql" class="btn btn-primary btn-sm" style="width: 100%;">Execute Command</button>
          </form>

          <?php if ($sql_res): ?>
          <div class="table-wrap" style="margin-top: 20px; max-height: 300px; overflow: auto; border: 1px solid var(--border);">
            <table style="font-size: 11px;">
              <thead>
                <tr>
                  <?php foreach (array_keys($sql_res[0]) as $col): ?><th><?= $col ?></th><?php endforeach; ?>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($sql_res as $row): ?>
                <tr><?php foreach ($row as $val): ?><td><?= htmlspecialchars($val) ?></td><?php endforeach; ?></tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

  </main>
</div>
</body>
</html>
