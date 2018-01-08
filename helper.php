<?php

require_once 'vendor/predis/predis/autoload.php';
Predis\Autoloader::register();

Global $db;
$db = new mysqli("localhost", "ilgarsh", "Ilgar", "mymessenger");

Global $redis;
$redis = new Predis\Client();

function check_authorization() {
    if (!isset($_SESSION['user'])) {
        return false;
    } else {
        return true;
    }
}

function build_unauthorized_access() {
    $resp->success = false;
    $resp->error = 'unauthorized access';
    return json_encode($resp);
}
