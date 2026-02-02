<?php
// app/Views/partials/top_menu.php
if (!isset($activePage)) {
    $activePage = '';
}
function topmenu_active_class($page, $activePage)
{
    return $page === $activePage ? 'topmenu-link active' : 'topmenu-link';
}
?>
<div class="topmenu">
    <div class="topmenu-left">
        <a class="<?php echo topmenu_active_class('epostlista', $activePage); ?>"
            href="<?php echo BASE_PATH; ?>/">Epostlista</a>
        <a class="<?php echo topmenu_active_class('kundlista', $activePage); ?>"
            href="<?php echo BASE_PATH; ?>/kundlista">Telefonlista</a>
        <a class="<?php echo topmenu_active_class('admin_phone_list', $activePage); ?>"
            href="<?php echo BASE_PATH; ?>/phone_list">Telefonlista (Admin)</a>
        <a class="<?php echo topmenu_active_class('manage_users', $activePage); ?>"
            href="<?php echo BASE_PATH; ?>/users">Manage Users</a>
    </div>
    <div class="topmenu-right">
        <a class="topmenu-link logout" href="<?php echo BASE_PATH; ?>/logout">Logout</a>
    </div>
</div>