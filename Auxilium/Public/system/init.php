<?php

use Auxilium\DatabaseInteractions\Deegraph\Nodes\User;
use Auxilium\DatabaseInteractions\GraphDatabaseConnection;
use Auxilium\DatabaseInteractions\MariaDB\MariaDBServerConnection;
use Auxilium\DatabaseInteractions\MariaDB\MariaDBTable;
use Auxilium\DatabaseInteractions\MariaDB\SQLQueryBuilderWrapper;
use Auxilium\Enumerators\QueryParamKey;
use Auxilium\Schemas\UserSchema;
use Auxilium\TwigHandling\PageBuilder2;
use Auxilium\Wrappers\QueryParamWrapper;
use Darksparrow\AuxiliumSchemaBuilder\Utilities\URLHandling;
use JetBrains\PhpStorm\NoReturn;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../Configuration/Configuration/Environment.php';

$setup_key = null;


#[NoReturn] function renderCriticalError(string $errorMessage): void
{
    unlink(filename: LOCAL_STORAGE_DIRECTORY . "/setup.lock");
    $errorFile = fopen(LOCAL_STORAGE_DIRECTORY . "/setup.error", "w") or die("Unable to write error file!");
    fwrite($errorFile, $errorMessage);
    fclose($errorFile);
    PageBuilder2::Render(
        template : "Pages/system/init-failure.html.twig",
        variables: []
    );
}


if(isset($_GET["setup_key"]))
{
    if(file_exists(LOCAL_STORAGE_DIRECTORY . "/setup.key"))
    {
        $key_file = fopen(LOCAL_STORAGE_DIRECTORY . "/setup.key", "r") or die("Unable to read keyfile!");
        $file_size = filesize(LOCAL_STORAGE_DIRECTORY . "/setup.key");
        $match_key = fread($key_file, $file_size);
        if(trim($match_key) === trim($_GET["setup_key"]))
        {
            $setup_key = trim($_GET["setup_key"]);
        }
        else
        {
            PageBuilder2::Render(
                template : "Pages/system/init-locked-out.html.twig",
                variables: []
            );
        }
    }
    else
    {
        PageBuilder2::Render(
            template : "Pages/system/init-step-3-done.html.twig",
            variables: []
        );
    }
}

if($setup_key == null)
{
    $setup_key = rtrim(strtr(base64_encode(openssl_random_pseudo_bytes(64)), '+/', '-_'), '=');
    $key_file = fopen(LOCAL_STORAGE_DIRECTORY . "/setup.key", "w") or die("Unable to write keyfile!");
    fwrite($key_file, $setup_key);
    fclose($key_file);
}


$mariaDBConnection = new MariaDBServerConnection();


if(file_exists(LOCAL_STORAGE_DIRECTORY . "/setup.lock"))
{
    if(file_exists(LOCAL_STORAGE_DIRECTORY . "/setup.key"))
    {
        if($setup_key == null)
        {
            PageBuilder2::Render(
                template : "Pages/system/init-locked-out.html.twig",
                variables: []
            );
        }
    }
    else
    {
        PageBuilder2::Render(
            template : "Pages/system/init-step-3-done.html.twig",
            variables: []
        );
    }
}
else
{
    $lock_file = fopen(LOCAL_STORAGE_DIRECTORY . "/setup.lock", "w") or die("Unable to write lockfile!");
    fwrite($lock_file, date("c", time()));
    fclose($lock_file);

    $success = $mariaDBConnection->InitialDatabaseSetup();
    if(!$success)
    {
        renderCriticalError(errorMessage: "Something went wrong with the MariaDB setup... is it online?");
    }

    $initialQueries = [
        // system permissions
        "GRANT READ,WRITE,DELETE WHERE @creator === /", // grant CRUD permissions where the creator is the current node
        "GRANT READ,WRITE,DELETE,ACT WHERE . === /",    // grant all permissions where the current node is the root node
        "GRANT READ ON /*",                             //
        "GRANT READ ON {" . INSTANCE_UUID . "}",        //
        // case permissions
        "GRANT READ,WRITE ON /cases/# ON /cases/#/*",   // grant read and write permissions on the user's cases
        "GRANT READ,WRITE ON /cases/#/todos/#",         // grant read and write (for deletion) permissions on the to do items on a case
        "GRANT READ ON /cases/#/workers/#/name ON /cases/#/workers/#/display_name ON /cases/#/workers/#/preferred_language ON /cases/#/workers/#/contact_email", // grant read permissions for case beneficiaries to see their caseworkers
        // misc permissions
        "GRANT READ ON /messages/#",                    //
        "GRANT READ,WRITE,DELETE ON /assigned_cases/# ON /assigned_cases/#/* ON /assigned_cases/#/messages/# DELEGATABLE", //
    ];
    foreach($initialQueries as $query)
    {
        try
        {
            GraphDatabaseConnection::query(User::get_system_node(), $query);
        }
        catch(Exception $e)
        {
            renderCriticalError(errorMessage: "Deegraph is not reachable... is it online?");
        }
    }
}



PageBuilder2::AddVariable(
    variableName : 'selected_lang',
    variableValue: QueryParamWrapper::Get(
        key            : QueryParamKey::LANGUAGE,
        default        : 'en',
        writeToIfNotSet: true,
    )
);


if(!isset($_GET["page"]))
{
    PageBuilder2::Render(
        template : "Pages/system/init.html.twig",
        variables: [
            "setup_key" => $setup_key,
            "lang" => $_GET["lang"],
        ]
    );
}

switch(strtolower($_GET["page"]))
{
    case "cmgmt":
        PageBuilder2::Render(
            template : "Pages/system/init-step-1-central-management.html.twig",
            variables: [
                "setup_key" => $setup_key,
                "lang" => $_GET["lang"],
            ]
        );
    case "racc":
        if(isset($_POST["name"]) && isset($_POST["email"]) && isset($_POST["password"]))
        {
            $user_node = GraphDatabaseConnection::new_node(
                data      : null,
                media_type: null,
                schema    : URLHandling::GetURLForSchema(UserSchema::class),
                creator   : User::get_system_node()
            );
            $user_node = new User($user_node->getId());

            $pre_hashed_password = base64_encode(hash("sha256", $_POST["password"], true));
            $user_node = GraphDatabaseConnection::new_node(
                data      : null,
                media_type: null,
                schema    : URLHandling::GetURLForSchema(UserSchema::class),
                creator   : User::get_system_node()
            );
            $user_node = new User($user_node->getId());

            $hash_options = [
                "cost" => 12,
            ];
            $hashed_password = password_hash($pre_hashed_password, PASSWORD_BCRYPT, $hash_options);


            $mariaDBConnection->RunInsert(
                queryBuilder: SQLQueryBuilderWrapper::INSERT(MariaDBTable::STANDARD_LOGINS)
                    ->set(col: 'email_address', value: ':__email_address__')
                    ->set(col: 'user_uuid', value: ':__user_uuid__')
                    ->set(col: 'password', value: ':__password__')
                    ->bindValue(name: '__email_address__', value: $_POST["email"])
                    ->bindValue(name: '__user_uuid__', value: $user_node->getId())
                    ->bindValue(name: '__password__', value: $hashed_password)
            );


            GraphDatabaseConnection::query(User::get_system_node(), "GRANT READ,WRITE,DELETE,ACT WHERE / === {" . $user_node->getId() . "}");

            $language_prop = GraphDatabaseConnection::new_node(strtoupper(PageBuilder2::GetVariable(variableName: 'selected_lang')), "text/plain", null, $user_node);
            $user_node->addProperty("preferred_language", $language_prop, $user_node);
            $full_name_prop = GraphDatabaseConnection::new_node($_POST["name"], "text/plain", null, $user_node);
            $user_node->addProperty("name", $full_name_prop, $user_node);
            $name_prop = GraphDatabaseConnection::new_node(explode(" ", $_POST["name"])[0], "text/plain", null, $user_node);
            $user_node->addProperty("display_name", $name_prop, $user_node);
            $email_name_prop = GraphDatabaseConnection::new_node($_POST["email"], "text/plain", null, $user_node);
            $user_node->addProperty("contact_email", $email_name_prop, $user_node);


            if(unlink(LOCAL_STORAGE_DIRECTORY . "/setup.key"))
            {
                PageBuilder2::Render(
                    template : "Pages/system/init-step-3-done.html.twig",
                    variables: [
                        "setup_key" => $setup_key,
                        "lang" => $_GET["lang"],
                    ]
                );
            }
            else
            {
                echo "ERROR DELETING KEY";
                exit();
            }
        }
        PageBuilder2::Render(
            template : "Pages/system/init-step-2-root-account.html.twig",
            variables: [
                "setup_key" => $setup_key,
                "lang" => $_GET["lang"],
            ]
        );
}
