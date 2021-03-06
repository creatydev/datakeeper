<?php

header("Access-Control-Allow-Methods: POST");

include_once './config/database.php';
include_once './utils.php';

session_start();
$loggedInUserId = $_SESSION['userId'];
$database = new Database();
$db = $database->getConnection();
$db->beginTransaction();
try {
    $data = json_decode(file_get_contents('php://input'), true);
    $tableName = htmlspecialchars(strip_tags($data['tableName']));

    if (!doesTableExist($db, $tableName)) {
        header('HTTP/1.0 500 Internal Server Error');
        echo '{"status" : "failed", "message" : "No such table"}';
        return;
    }

    if (!isAdmin($db, $loggedInUserId, $tableName)) {
        header('HTTP/1.0 401 Unauthorized');
        echo 'You are not authorized.';
        return;
    }
    $permissions = json_encode($data['permissions']);
    if (!isPermissionJsonValid($permissions)) {
        header('HTTP/1.0 500 Internal Server Error');
        echo '{"status" : "failed", "message" : "Invalid permissions"}';
        return;
    }
    $userId = null;
    if (array_key_exists('userId', $data)) {
        $userId = $data['userId'];
    }

    $result = null;
    if ($userId == null) {
        $ps = $db->prepare("update tables_info set publicPermissions=:permissions where tableName=:tableName");
        $ps->bindValue(':permissions', $permissions, PDO::PARAM_STR);
        $ps->bindValue(':tableName', $tableName, PDO::PARAM_STR);
        $result = $ps->execute();
    } else {
        $ps = $db->prepare("update guest_permissions set permissions=:permissions where userId=:userId and tableName=:tableName");
        $ps->bindValue(':permissions', $permissions, PDO::PARAM_STR);
        $ps->bindValue(':userId', $userId, PDO::PARAM_INT);
        $ps->bindValue(':tableName', $tableName, PDO::PARAM_STR);
        $result = $ps->execute();
    }
    if ($result) {
        echo '{"status" : "success"}';
    } else {
        echo '{"status" : "failed", "message" : "Problem while updating permissions"}';
    }
		$db->commit();

} catch (Exception $ex) {
	echo 'Error: ' .$ex->getMessage();
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo '{"status" : "error"}';
}

function isPermissionJsonValid($permissionsJson)
{
		$permissions = json_decode($permissionsJson, true);
		foreach ($permissions as $key => $value) {
				if ($key != "read" && $key != "add" && $key != "update" && $key != "delete") {
						return false;
				}
				foreach ($value as $subKey => $subValue) {
						if ($subKey != "allowed" && $subKey != "approval" && $subKey != "loginRequired") {
								return false;
						}
						if (gettype($subValue) != "boolean") {
								return false;
						}
				}
		}
		return true;
}
