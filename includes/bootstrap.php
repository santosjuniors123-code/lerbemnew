<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/functions.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/layout.php';

lb_session_start();

if (!defined('LB_NO_INSTALL_CHECK') && !lb_installed()) {
    redirect(lb_url('install.php'));
}
