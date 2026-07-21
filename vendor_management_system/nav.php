<?php
$current = basename($_SERVER['PHP_SELF'] ?? 'index.php');
$user = $_SESSION['vms_user'] ?? ['full_name' => 'Guest', 'role' => 'Viewer'];
?>

<!-- TOP NAVBAR -->
<div class="top-navbar">
  <div class="nav-search">
    <span class="search-icon">🔍</span>
    <input type="text" placeholder="Search orders, vendors, or products...">
  </div>

  <div class="nav-user">
    <div class="notif-bell" title="Notifications">
      🔔
      <div class="notif-dot"></div>
    </div>

    <div class="user-profile">
      <div class="user-info">
        <span class="user-name"><?= htmlspecialchars($user['full_name']) ?></span>
        <span class="user-role"><?= htmlspecialchars($user['role']) ?></span>
      </div>
      <div class="user-avatar" style="width: 40px; height: 40px; background: var(--accent); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-family: 'Syne', sans-serif; font-weight: 800; font-size: 18px; color: white;">
        <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
      </div>
      <a href="logout.php" title="Logout" style="margin-left: 8px; color: var(--danger); text-decoration: none; display: flex; align-items: center; justify-content: center; width: 36px; height: 36px; border-radius: 8px; background: rgba(255,71,87,0.1); border: 1px solid rgba(255,71,87,0.2); transition: 0.2s;" onmouseover="this.style.background='rgba(255,71,87,0.2)'" onmouseout="this.style.background='rgba(255,71,87,0.1)'">
        ⏻
      </a>
    </div>
  </div>
</div>

<!-- SIDEBAR -->
<div class="sidebar">
  <div class="sidebar-brand">
    <div class="logo-text">VMS</div>
    <div class="logo-sub">Enterprise Edition</div>
  </div>

  <div class="sidebar-scroll" style="flex: 1; overflow-y: auto; scroll-behavior: smooth; padding-right: 8px;">
  <div class="nav-section">
    <div class="nav-label">Main</div>
    <a href="index.php" class="nav-link <?= $current === 'index.php' ? 'active' : '' ?>">
      <span class="icon">⬡</span> Dashboard
    </a>
  </div>

  <div class="nav-section">
    <div class="nav-label">Management</div>
    <a href="vendors.php" class="nav-link <?= $current === 'vendors.php' ? 'active' : '' ?>">
      <span class="icon">◈</span> Vendors
    </a>
    <a href="products.php" class="nav-link <?= $current === 'products.php' ? 'active' : '' ?>">
      <span class="icon">◉</span> Products
    </a>
    <a href="vendor_products.php" class="nav-link <?= $current === 'vendor_products.php' ? 'active' : '' ?>">
      <span class="icon">◬</span> Supply Matrix
    </a>
    <a href="orders.php" class="nav-link <?= $current === 'orders.php' ? 'active' : '' ?>">
      <span class="icon">◎</span> Orders
    </a>
    <a href="payments.php" class="nav-link <?= $current === 'payments.php' ? 'active' : '' ?>">
      <span class="icon">💳</span> Payments
    </a>
    <a href="inventory.php" class="nav-link <?= $current === 'inventory.php' ? 'active' : '' ?>">
      <span class="icon">📦</span> Inventory
    </a>
    <a href="contracts.php" class="nav-link <?= $current === 'contracts.php' ? 'active' : '' ?>">
      <span class="icon">◫</span> Contracts
    </a>
  </div>

  <div class="nav-section">
    <div class="nav-label">Governance</div>
    <a href="performance.php" class="nav-link <?= $current === 'performance.php' ? 'active' : '' ?>">
      <span class="icon">★</span> Performance
    </a>
    <?php if (has_role('Admin')): ?>
    <a href="employees.php" class="nav-link <?= $current === 'employees.php' ? 'active' : '' ?>">
      <span class="icon">◷</span> Employees
    </a>
    <?php endif; ?>
  </div>

  <div class="nav-section">
    <div class="nav-label">Intelligence</div>
    <a href="views.php" class="nav-link <?= $current === 'views.php' ? 'active' : '' ?>">
      <span class="icon">◭</span> DB Views
    </a>
    <a href="reports.php" class="nav-link <?= $current === 'reports.php' ? 'active' : '' ?>">
      <span class="icon">📊</span> Analytics
    </a>
  </div>
  </div>

  <div class="nav-section" style="margin-top: auto;">
    <a href="settings.php" class="nav-link <?= $current === 'settings.php' ? 'active' : '' ?>">
      <span class="icon">⚙</span> Settings
    </a>
    <a href="logout.php" class="nav-link" style="color: var(--danger);">
      <span class="icon">⏻</span> Logout
    </a>
  </div>
</div>