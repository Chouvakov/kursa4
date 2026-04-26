<?php
require_once 'db_connection.php';
require_once 'functions.php';
session_unset();
session_destroy();
redirect('index.php');
?>