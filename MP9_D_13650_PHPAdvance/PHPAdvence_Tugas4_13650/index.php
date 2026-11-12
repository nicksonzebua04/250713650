<?php
    session_start();

    if (isset($_GET['logout'])) {
        session_unset();
        session_destroy();
        header('Location: index.php');
        exit;
    }

    $error = '';

    if ($_SERVER['REQUEST_METHOD'])