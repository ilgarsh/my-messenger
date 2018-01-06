<?php

Global $db;

$db = new mysqli("localhost", "ilgarsh", "Ilgar", "mymessenger");

function check_authorization() {
    if (!isset($_SESSION['user'])) {
        $resp->success = false;
        $resp->error = 'unauthorized access';
        return json_encode($resp);
    }
}

