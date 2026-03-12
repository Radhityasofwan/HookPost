<?php
require_once __DIR__ . '/config.php';
session_unset();
session_destroy();
require_once __DIR__ . '/helpers.php';
redirect_to('login');
