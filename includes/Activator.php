<?php
if (!defined('ABSPATH')) exit;

class MLB_LMS_Activator {
    public static function activate() {
        MLB_LMS_Database::migrate();
        MLB_LMS_Roles::add_roles();
        MLB_LMS_Rewrite::add_rules();
        flush_rewrite_rules();
    }
}
