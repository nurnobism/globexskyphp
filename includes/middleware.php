<?php
/**
 * Bootstrap application — load config + includes
 * Include this at the top of every page
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/mail.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

startSession();
