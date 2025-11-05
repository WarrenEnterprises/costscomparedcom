<?php
/**
 * Logout Handler
 */

define('LT_INIT', true);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

start_session();

destroy_session();

redirect(TRACKER_PATH . '/link-tracking.php');

