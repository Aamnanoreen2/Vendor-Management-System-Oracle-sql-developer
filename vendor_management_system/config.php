<?php
session_start();

// Authentication Guard
if (!isset($_SESSION['vms_user']) && basename($_SERVER['PHP_SELF']) !== 'login.php') {
    header("Location: login.php");
    exit;
}

// Role Helper
function has_role($roles) {
    if (!isset($_SESSION['vms_user'])) return false;
    if (is_array($roles)) {
        return in_array($_SESSION['vms_user']['role'], $roles);
    }
    return $_SESSION['vms_user']['role'] === $roles;
}

// ─────────────────────────────────────────
// Oracle DB Connection — VMS Project (Fixed)
// ─────────────────────────────────────────

$host = "localhost";
$port = "1521";
$service_name = "ORCLPDB";

$username = "vms_user";
$password = "Aamna22";

$conn_string = "(DESCRIPTION=
    (ADDRESS=(PROTOCOL=TCP)(HOST=$host)(PORT=$port))
    (CONNECT_DATA=(SERVICE_NAME=$service_name))
)";

// Create connection
$conn = oci_connect($username, $password, $conn_string, 'AL32UTF8');

// Error handling
if (!$conn) {
    $e = oci_error();

    die("
    <div style='
        font-family:monospace;
        background:#1a0010;
        color:#ff4757;
        padding:20px;
        border-radius:8px;
        margin:20px;
        border:1px solid #ff4757;
    '>
        <strong>⚠ Oracle Connection Failed</strong><br><br>
        " . htmlspecialchars($e['message']) . "
        <br><br>
        <small>
            Check:<br>
            • Oracle is running<br>
            • PDB (ORCLPDB) is OPEN<br>
            • Username/password correct<br>
            • Service name is correct<br>
        </small>
    </div>
    ");
}

// ─────────────────────────────────────────
// Helper: Fetch All Rows (SELECT)
// ─────────────────────────────────────────
function db_fetch_all($conn, $sql, $binds = []) {

    $stmt = oci_parse($conn, $sql);

    if (!$stmt) {
        $e = oci_error($conn);
        die("SQL Parse Error: " . $e['message']);
    }

    foreach ($binds as $key => $value) {
        oci_bind_by_name($stmt, $key, $binds[$key]);
    }

    oci_execute($stmt);

    $rows = [];
    while ($row = oci_fetch_assoc($stmt)) {
        $rows[] = $row;
    }

    oci_free_statement($stmt);

    return $rows;
}

// ─────────────────────────────────────────
// Helper: Execute (INSERT / UPDATE / DELETE)
// ─────────────────────────────────────────
function db_execute($conn, $sql, $binds = []) {

    $stmt = oci_parse($conn, $sql);

    if (!$stmt) {
        $e = oci_error($conn);
        return "Parse Error: " . $e['message'];
    }

    foreach ($binds as $key => $value) {
        oci_bind_by_name($stmt, $key, $binds[$key]);
    }

    $result = oci_execute($stmt, OCI_COMMIT_ON_SUCCESS);

    if (!$result) {
        $e = oci_error($stmt);
        return $e['message'];
    }

    oci_free_statement($stmt);

    return true;
}

// ─────────────────────────────────────────
// Helper: Alert Messages
// ─────────────────────────────────────────
function alert($msg, $type = 'success') {

    $icons = [
        'success' => '✓',
        'danger'  => '✗',
        'warning' => '⚠'
    ];

    $color = match($type) {
        'success' => '#2ecc71',
        'danger'  => '#e74c3c',
        'warning' => '#f39c12',
        default   => '#7f8c8d'
    };

    echo "<div style='
        padding:10px;
        margin:10px 0;
        border-radius:5px;
        border-left:5px solid $color;
        background:#f5f5f5;
    '>
        <strong>{$icons[$type]}</strong> {$msg}
    </div>";
}
?>