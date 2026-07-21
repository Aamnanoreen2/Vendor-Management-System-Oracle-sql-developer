<?php
session_start();
if (isset($_SESSION['vms_user'])) {
    header("Location: index.php"); exit;
}

// ── DB connection (for employee lookup) ─────────────────────────────
$host         = "localhost";
$port         = "1521";
$service_name = "ORCLPDB";
$username     = "vms_user";
$password     = "Aamna22";
$conn_string  = "(DESCRIPTION=(ADDRESS=(PROTOCOL=TCP)(HOST=$host)(PORT=$port))(CONNECT_DATA=(SERVICE_NAME=$service_name)))";
$conn = oci_connect($username, $password, $conn_string, 'AL32UTF8');

$error = '';

// Demo password map: role => password (since DB has no password column)
// In production, add a password_hash column to VMS_Users_Employees
$demo_passwords = [
    'Admin'     => 'admin123',
    'Manager'   => 'manager123',
    'Purchaser' => 'buy123',
    'Viewer'    => 'view123',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $pass     = trim($_POST['password'] ?? '');

    if ($email && $pass && $conn) {
        $stmt = oci_parse($conn,
            "SELECT USER_ID, FULL_NAME, EMAIL, DEPARTMENT, ROLE
             FROM VMS_USERS_EMPLOYEES
             WHERE UPPER(EMAIL) = UPPER(:email)"
        );
        oci_bind_by_name($stmt, ':email', $email);
        oci_execute($stmt);
        $row = oci_fetch_assoc($stmt);

        if ($row) {
            $expectedPass = $demo_passwords[$row['ROLE']] ?? 'vms123';
            if ($pass === $expectedPass) {
                $_SESSION['vms_user'] = [
                    'user_id'    => $row['USER_ID'],
                    'full_name'  => $row['FULL_NAME'],
                    'email'      => $row['EMAIL'],
                    'department' => $row['DEPARTMENT'],
                    'role'       => $row['ROLE'],
                ];
                header("Location: index.php"); exit;
            } else {
                $error = 'Incorrect password for this account.';
            }
        } else {
            $error = 'No employee found with that email address.';
        }
        oci_free_statement($stmt);
    } else {
        $error = 'Please enter both email and password.';
    }
}

// Fetch employees for the "quick login" helper
$employees = [];
if ($conn) {
    $stmt = oci_parse($conn, "SELECT FULL_NAME, EMAIL, ROLE FROM VMS_USERS_EMPLOYEES ORDER BY FULL_NAME");
    oci_execute($stmt);
    while ($r = oci_fetch_assoc($stmt)) $employees[] = $r;
    oci_free_statement($stmt);
    oci_close($conn);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>VMS — Login</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Syne:wght@400;700;800&family=DM+Mono:wght@400;500&display=swap">
  <style>
    :root {
      --bg:#0a0a0f; --surface:#111118; --surface2:#1a1a24;
      --border:#2a2a3a; --accent:#6c63ff; --accent2:#ff6584;
      --accent3:#43e97b; --text:#e8e8f0; --muted:#6b6b80;
      --danger:#ff4757; --warning:#ffa502; --success:#2ed573;
    }
    * { margin:0; padding:0; box-sizing:border-box; }
    body {
      font-family:'DM Mono',monospace;
      background:var(--bg);
      color:var(--text);
      min-height:100vh;
      display:flex;
      align-items:center;
      justify-content:center;
      background-image:
        radial-gradient(ellipse at 20% 50%,rgba(108,99,255,.09) 0%,transparent 60%),
        radial-gradient(ellipse at 80% 20%,rgba(255,101,132,.06) 0%,transparent 50%);
    }
    .login-wrap {
      width:100%;
      max-width:460px;
      padding:20px;
    }
    .brand {
      text-align:center;
      margin-bottom:36px;
    }
    .brand-logo {
      font-family:'Syne',sans-serif;
      font-size:42px;
      font-weight:800;
      background:linear-gradient(135deg,var(--accent),var(--accent2));
      -webkit-background-clip:text;
      -webkit-text-fill-color:transparent;
      line-height:1;
    }
    .brand-sub {
      font-size:11px;
      color:var(--muted);
      letter-spacing:3px;
      text-transform:uppercase;
      margin-top:6px;
    }
    .card {
      background:var(--surface);
      border:1px solid var(--border);
      border-radius:16px;
      padding:36px;
    }
    .card-title {
      font-family:'Syne',sans-serif;
      font-size:20px;
      font-weight:700;
      margin-bottom:6px;
    }
    .card-sub {
      font-size:12px;
      color:var(--muted);
      margin-bottom:28px;
    }
    .form-group { margin-bottom:18px; }
    label {
      display:block;
      font-size:10px;
      color:var(--muted);
      letter-spacing:2px;
      text-transform:uppercase;
      margin-bottom:6px;
    }
    input {
      width:100%;
      background:var(--surface2);
      border:1px solid var(--border);
      border-radius:8px;
      padding:12px 14px;
      color:var(--text);
      font-family:'DM Mono',monospace;
      font-size:13px;
      transition:.2s ease;
    }
    input:focus { outline:none; border-color:var(--accent); box-shadow:0 0 0 3px rgba(108,99,255,.1); }
    .btn-login {
      width:100%;
      background:var(--accent);
      color:white;
      border:none;
      border-radius:8px;
      padding:13px;
      font-family:'Syne',sans-serif;
      font-size:14px;
      font-weight:700;
      cursor:pointer;
      transition:.2s ease;
      margin-top:8px;
    }
    .btn-login:hover { background:#5a52e0; transform:translateY(-1px); }
    .alert-error {
      background:rgba(255,71,87,.1);
      border:1px solid rgba(255,71,87,.3);
      color:var(--danger);
      border-radius:8px;
      padding:11px 14px;
      font-size:12px;
      margin-bottom:20px;
    }
    /* Quick login helper */
    .quick-section {
      margin-top:28px;
      padding-top:24px;
      border-top:1px solid var(--border);
    }
    .quick-title {
      font-size:10px;
      color:var(--muted);
      letter-spacing:2px;
      text-transform:uppercase;
      margin-bottom:12px;
    }
    .quick-cards {
      display:grid;
      grid-template-columns:1fr 1fr;
      gap:8px;
    }
    .quick-card {
      background:var(--surface2);
      border:1px solid var(--border);
      border-radius:8px;
      padding:10px 12px;
      cursor:pointer;
      transition:.2s ease;
    }
    .quick-card:hover { border-color:var(--accent); }
    .quick-card-name { font-size:12px; font-weight:600; }
    .quick-card-role {
      font-size:10px;
      margin-top:2px;
    }
    .role-admin     { color:var(--danger); }
    .role-manager   { color:var(--accent); }
    .role-purchaser { color:var(--accent3); }
    .role-viewer    { color:var(--muted); }

    .pw-hint {
      margin-top:20px;
      background:rgba(108,99,255,.07);
      border:1px solid rgba(108,99,255,.2);
      border-radius:8px;
      padding:12px;
      font-size:11px;
      color:var(--muted);
    }
    .pw-hint strong { color:var(--accent); }
    .pw-row { display:flex; justify-content:space-between; padding:3px 0; }
  </style>
</head>
<body>
<div class="login-wrap">
  <div class="brand">
    <div class="brand-logo">VMS</div>
    <div class="brand-sub">Vendor Management System</div>
  </div>

  <div class="card">
    <div class="card-title">Sign In</div>
    <div class="card-sub">Enter your employee credentials to continue</div>

    <?php if ($error): ?>
      <div class="alert-error">✗ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="login.php">
      <div class="form-group">
        <label>Employee Email</label>
        <input type="email" name="email" id="email-input" required
               placeholder="e.g. aamna@example.com"
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label>Password</label>
        <input type="password" name="password" id="pass-input" required
               placeholder="Enter your password">
      </div>
      <button type="submit" class="btn-login">→ Sign In</button>
    </form>

    <!-- Quick Login Cards (Demo Helper) -->
    <?php if (!empty($employees)): ?>
    <div class="quick-section">
      <div class="quick-title">⚡ Quick Demo Login — click to auto-fill</div>
      <div class="quick-cards">
        <?php foreach (array_slice($employees, 0, 4) as $emp):
          $roleClass = 'role-'.strtolower($emp['ROLE']);
          $demoPass  = match($emp['ROLE']) {
            'Admin'     => 'admin123',
            'Manager'   => 'manager123',
            'Purchaser' => 'buy123',
            default     => 'view123'
          };
        ?>
          <div class="quick-card"
               onclick="document.getElementById('email-input').value='<?= htmlspecialchars($emp['EMAIL']) ?>';
                        document.getElementById('pass-input').value='<?= $demoPass ?>';">
            <div class="quick-card-name"><?= htmlspecialchars($emp['FULL_NAME']) ?></div>
            <div class="quick-card-role <?= $roleClass ?>"><?= htmlspecialchars($emp['ROLE']) ?></div>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="pw-hint">
        <div class="pw-row"><span>Admin</span><strong>admin123</strong></div>
        <div class="pw-row"><span>Manager</span><strong>manager123</strong></div>
        <div class="pw-row"><span>Purchaser</span><strong>buy123</strong></div>
        <div class="pw-row"><span>Viewer</span><strong>view123</strong></div>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
