<?php
// top_menu.php
// Expected variables:
// - $activePage: 'epostlista' | 'kundlista' | 'manage_users'

if (!isset($activePage)) {
    $activePage = '';
}

function topmenu_active_class($page, $activePage) {
    return $page === $activePage ? 'topmenu-link active' : 'topmenu-link';
}
?>
<div class="topmenu">
    <div class="topmenu-left">
        <a class="<?php echo topmenu_active_class('epostlista', $activePage); ?>" href="index.php">Epostlista</a>
        <a class="<?php echo topmenu_active_class('kundlista', $activePage); ?>" href="kundlista.php">Telefonlista</a>
        <a class="<?php echo topmenu_active_class('manage_users', $activePage); ?>" href="manage_users.php">Manage Users</a>
    </div>
    <div class="topmenu-right">
        <a class="topmenu-link logout" href="logout.php">Logout</a>
    </div>
</div>
