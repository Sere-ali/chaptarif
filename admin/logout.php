<?php
if (is_post() && is_admin()) {
    audit('admin.deconnexion');
    logout_user();
}
redirect('/admin/login');
