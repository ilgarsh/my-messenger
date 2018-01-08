	<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once 'helper.php';

$klein = new \Klein\Klein();

//Колбеки для API чатов
$get_chats = function() {
    Global $db;
    if (check_authorization()) {
        return build_unauthorized_access();
    }
    $username = $_SESSION['user'];
    $sql = "SELECT chat_id FROM chats WHERE username = '$username'";
    $result = $db -> query($sql);
    $resultArray = $result->fetch_all(MYSQLI_NUM);
    $chats = array();
    foreach ($resultArray as $chat_id) {
        $chats []= (int)$chat_id[0];
    }
    $resp->success = true;
    $resp->chats = $chats;
    $resp->error = null;
    return json_encode($resp);
};

$create_chat = function () {
    Global $db;
    if (check_authorization()) {
        return build_unauthorized_access();
    }
    if (!isset($_POST['users']) || empty($_POST['users'])) {
        $resp->success = false;
        $resp->chat_id = null;
        $resp->error = 'empty users';
        return json_encode($resp);
    }
    $current_user = $_SESSION['user'];
    $users = json_decode($_POST['users']);
    $sql_max_chatid = "SELECT max(chat_id) FROM chats";
    $result = $db->query($sql_max_chatid);
    $max_chatid = $result->fetch_row()[0];
    $max_chatid++;
    $stmt = $db->prepare("INSERT INTO chats (chat_id, username) VALUES (?, ?)");
    $stmt->bind_param('is', $max_chatid, $current_user);
    $stmt->execute();
    foreach ($users as $user_id) {
        $stmt->bind_param('is', $max_chatid, $user_id);
        $stmt->execute();
    }
    $stmt->close();
    $resp->success = true;
    $resp->chat_id = $max_chatid;
    $resp->error = null;
    return json_encode($resp);
};

$get_chat_users = function ($request) {
    $_SESSION['user'] = 'user1';
    Global $db;
    if (check_authorization()) {
        return build_unauthorized_access();
    }
    $chat_id = $request->id;
    $username = $_SESSION['user'];
    $rows = $db->query("SELECT * FROM chats WHERE chat_id = $chat_id AND username = '$username'");
    $num_rows = mysqli_num_rows($rows);
    if ($num_rows < 1) {
        $resp->success = false;
        $resp->error = 'there is no such chat or an attempt to delete improper chat';
        return json_encode($resp);
    }
    $result = $db->query("SELECT username FROM chats WHERE chat_id='$chat_id'");
    $resultArray = $result->fetch_all(MYSQLI_NUM);
    $users = array();
    foreach ($resultArray as $username) {
        $users []= $username[0];
    }
    $resp->success = true;
    $resp->users = $users;
    $resp->error = null;
    return json_encode($resp);
};

$get_chat = function ($request) {
    Global $redis;
    if (check_authorization()) {
        return build_unauthorized_access();
    }
    $chat_id = $request->id;
    $n_last_messages = 10;
    if (isset($_GET['numb'])) {
        $n_last_messages = $_GET['numb'];
    }
    $len = $redis->llen("$chat_id");
    $messages = $redis->lrange("$chat_id", $len - $n_last_messages, $len);
    return $messages;
};

$delete_chat = function ($request) {
    Global $redis;
    Global $db;
    if (check_authorization()) {
        return build_unauthorized_access();
    }
    $chat_id = $request->id;
    $username = $_SESSION['user'];
    $rows = $db->query("SELECT * FROM chats WHERE chat_id = $chat_id AND username = '$username'");
    $num_rows = mysqli_num_rows($rows);
    $delete_sql = "DELETE FROM chats WHERE chat_id = $chat_id AND username = '$username'";
    if ($num_rows < 1) {
        $resp->success = false;
        $resp->error = 'there is no such chat or an attempt to delete improper chat';
        return json_encode($resp);
    } else if ($db->query($delete_sql) == TRUE) {
        $db->query("DELETE FROM chats WHERE chat_id = $chat_id");
        $redis->del(["$chat_id"]);
        $resp->success = true;
        $resp->error = null;
        return json_encode($resp);
    } else {
        $resp->success = false;
        $resp->error = 'rows were not deleted';
        return json_encode($resp);
    }
};

$create_message = function ($request) {
    Global $redis;
    if (check_authorization()) {
        return build_unauthorized_access();
    }
    $_POST['message'] = "hello";
    if (!isset($_POST['message']) || empty($_POST['message'])) {
        $resp->success = false;
        $resp->error = 'empty message';
        return json_encode($resp);
    }
    $obj->username = $_SESSION['user'];
    $obj->message = $_POST['message'];
    $chat_id = $request->id;
    $redis->rpush("$chat_id", [json_encode($obj)]);

    $resp->success = true;
    $resp->error = null;
    return json_encode($resp);
};

//Колбеки для API авторизации
$registration_user = function () {
    Global $db;
    if (!isset($_POST['username'])
            || !isset($_POST['password'])) {
        $resp->success = false;
        $resp->error = 'not valid data';
        return json_encode($resp);
    }
    $username = $_POST['username'];
    $password = $_POST['password'];
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $insert = "INSERT INTO users VALUES($username, $hashed_password)";
    if ($db->query($insert) == TRUE) {
        $resp->success = true;
        $resp->error = null;
        return json_encode($resp);
    } else {
        $resp->success = false;
        $resp->error = "the row was not added in db";
        return json_encode($resp);
    }
};

$login = function () {
    Global $db;
    if (!isset($_POST['username'])
        || !isset($_POST['password'])) {
        $resp->success = false;
        $resp->error = 'not valid data in post\'s headers';
        return json_encode($resp);
    }
    $username = $_POST['username'];
    $password = $_POST['password'];
    $result = $db->query("SELECT password FROM users WHERE username = '$username'");
    $password_from_db = $result->fetch_row()[0];
    if (password_verify($password, $password_from_db)) {
        $_SESSION['user'] = $username;
        $resp->success = true;
        $resp->error = null;
        return json_encode($resp);
    } else {
        $resp->success = false;
        $resp->error = 'incorrect password or username';
        return json_encode($resp);
    }
};

$logout = function () {
    $_SESSION['user'] = null;
};

//API для чатов
$klein->respond('GET', '/chats', $get_chats); //получить список чатов для текущего пользователя
$klein->respond('GET', '/chats/[i:id]/users', $get_chat_users); //получать список пользователей чата по id
$klein->respond('GET', '/chats/[i:id]/', $get_chat); //получать список сообщений чата по id по параметру numb
$klein->respond('POST', '/chats', $create_chat); //добавить чат
$klein->respond("POST", "/chats/[i:id]/message", $create_message); //создать сообщение в редис
$klein->respond('DELETE', '/chats/[i:id]', $delete_chat); //удалить чат по id

//API для авторизации
$klein->respond('POST', '/registration', $registration_user);
$klein->respond('POST', '/login', $login);
$klein->respond('GET', '/logout', $logout);


$klein->dispatch();
