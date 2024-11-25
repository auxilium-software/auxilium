<?php
require_once "../environment.php";

$setup_key = null;

if (isset($_GET["setup_key"])) {
    if (file_exists(LOCAL_STORAGE_DIRECTORY."setup.key")) {
        $key_file = fopen(LOCAL_STORAGE_DIRECTORY."setup.key", "r") or die("Unable to read keyfile!");
        $file_size = filesize(LOCAL_STORAGE_DIRECTORY."setup.key");
        $match_key = fread($key_file, $file_size);
        if (trim($match_key) == trim($_GET["setup_key"])) {
            $setup_key = trim($_GET["setup_key"]);
        } else {
            $pb = \Auxilium\TwigHandling\PageBuilder::get_instance();
            $pb->setTemplate("Pages/system/init-locked-out");
            $pb->render();
            exit();
        }
    } else {
        $pb = \Auxilium\TwigHandling\PageBuilder::get_instance();
        $pb->setTemplate("Pages/system/init-done");
        $pb->render();
        exit();
    }
}

if (file_exists(LOCAL_STORAGE_DIRECTORY."setup.lock")) {
    if (file_exists(LOCAL_STORAGE_DIRECTORY."setup.key")) {
        if ($setup_key == null) {
            $pb = \Auxilium\TwigHandling\PageBuilder::get_instance();
            $pb->setTemplate("Pages/system/init-locked-out");
            $pb->render();
            exit();
        }
    } else {
        $pb = \Auxilium\TwigHandling\PageBuilder::get_instance();
        $pb->setTemplate("Pages/system/init-done");
        $pb->render();
        exit();
    }
} else {
    $lock_file = fopen(LOCAL_STORAGE_DIRECTORY."setup.lock", "w") or die("Unable to write lockfile!");
    fwrite($lock_file, date("c", time()));
    fclose($lock_file);
    
    $relational_schema = WEB_ROOT_DIRECTORY."system/first-setup/schema.sql";
    $pdo = Auxilium\RelationalDatabaseConnection::get_pdo();
    $pdo->query(file_get_contents($relational_schema));
    
    Auxilium\GraphDatabaseConnection::query(Auxilium\User::get_system_node(), "GRANT READ,WRITE,DELETE WHERE @creator === /");
    Auxilium\GraphDatabaseConnection::query(Auxilium\User::get_system_node(), "GRANT READ,WRITE,DELETE,ACT WHERE . === /");
    Auxilium\GraphDatabaseConnection::query(Auxilium\User::get_system_node(), "GRANT READ ON /*");
    Auxilium\GraphDatabaseConnection::query(Auxilium\User::get_system_node(), "GRANT READ ON {".INSTANCE_UUID."}");
    Auxilium\GraphDatabaseConnection::query(Auxilium\User::get_system_node(), "GRANT READ,WRITE ON /cases/# ON /cases/#/*");
    Auxilium\GraphDatabaseConnection::query(Auxilium\User::get_system_node(), "GRANT READ ON /messages/#");
    Auxilium\GraphDatabaseConnection::query(Auxilium\User::get_system_node(), "GRANT READ,WRITE,DELETE ON /assigned_cases/# ON /assigned_cases/#/* ON /assigned_cases/#/messages/# DELEGATABLE");
}

if ($setup_key == null) {
    $setup_key = rtrim(strtr(base64_encode(openssl_random_pseudo_bytes(64)), '+/', '-_'), '=');
    $key_file = fopen(LOCAL_STORAGE_DIRECTORY."setup.key", "w") or die("Unable to write keyfile!");
    fwrite($key_file, $setup_key);
    fclose($key_file);
}

$pb = \Auxilium\TwigHandling\PageBuilder::get_instance();
$pb->setTemplate("Pages/system/init");
$pb->setVariable("setup_key", $setup_key);
if (isset($_GET["lang"])) {
    $pb->overrideCurrentLanguage($_GET["lang"]);
    $pb->setVariable("lang", $_GET["lang"]);
}
if (isset($_GET["page"])) {
    switch (strtolower($_GET["page"])) {
        case "cmgmt":
            $pb->setTemplate("Pages/system/init-central-management");
            break;
        case "racc":
            $pb->setTemplate("Pages/system/init-root-account");
            if (isset($_POST["name"]) && isset($_POST["email"]) && isset($_POST["password"])) {
                $user_node = Auxilium\GraphDatabaseConnection::new_node(null, null, "https://schemas.auxiliumsoftware.co.uk/v1/user.json", Auxilium\User::get_system_node());
                $user_node = new Auxilium\User($user_node->getId());
                
                $pre_hashed_password = base64_encode(hash("sha256", $_POST["password"], true)); 
                $user_node = Auxilium\GraphDatabaseConnection::new_node(null, null, "https://schemas.auxiliumsoftware.co.uk/v1/user.json", Auxilium\User::get_system_node());
                $user_node = new Auxilium\User($user_node->getId());
        
                $hash_options = [
                    "cost" => 12,
                ];
                $hashed_password = password_hash($pre_hashed_password, PASSWORD_BCRYPT, $hash_options);
                
                $bind_variables = [
                    "user_uuid" => $user_node->getId(),
                    "email_address" => $_POST["email"],
                    "password" => $hashed_password
                ];
                $sql = "INSERT INTO standard_logins (email_address, user_uuid, password) VALUES (:email_address, :user_uuid, :password)";
                $statement = Auxilium\RelationalDatabaseConnection::get_pdo()->prepare($sql);
                $statement->execute($bind_variables);
                
                Auxilium\GraphDatabaseConnection::query(Auxilium\User::get_system_node(), "GRANT READ,WRITE,DELETE,ACT WHERE / === {".$user_node->getId()."}");
                
                $language_prop = Auxilium\GraphDatabaseConnection::new_node(strtoupper($pb->getCurrentLanguage()), "text/plain", null, $user_node);
                $user_node->addProperty("preferred_language", $language_prop, $user_node);
                $full_name_prop = Auxilium\GraphDatabaseConnection::new_node($_POST["name"], "text/plain", null, $user_node);
                $user_node->addProperty("name", $full_name_prop, $user_node);
                $name_prop = Auxilium\GraphDatabaseConnection::new_node(explode(" ", $_POST["name"])[0], "text/plain", null, $user_node);
                $user_node->addProperty("display_name", $name_prop, $user_node);
                $email_name_prop = Auxilium\GraphDatabaseConnection::new_node($_POST["email"], "text/plain", null, $user_node);
                $user_node->addProperty("contact_email", $email_name_prop, $user_node);
                
                
                
                if (unlink(LOCAL_STORAGE_DIRECTORY."setup.key")) {
                    $pb = \Auxilium\TwigHandling\PageBuilder::get_instance();
                    $pb->setTemplate("Pages/system/init-done");
                    $pb->render();
                    exit();
                } else {
                    echo "ERROR DELETING KEY";
                    exit();
                }
            }
            break;
    }
}
$pb->render();
