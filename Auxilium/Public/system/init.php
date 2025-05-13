<?php

use Auxilium\Auxilium\InitHelpers;
use Auxilium\DatabaseInteractions\Deegraph\Nodes\User;
use Auxilium\DatabaseInteractions\GraphDatabaseConnection;
use Auxilium\Helpers\ConfigurationManagement\CredentialManagement;
use Auxilium\Helpers\ConfigurationManagement\EnvironmentManagement;
use Auxilium\TwigHandling\PageBuilder2;
use Auxilium\Utilities\NavigationUtilities;
use Auxilium\Utilities\Security;
use Darksparrow\DeegraphInteractions\Core\DeegraphServer;
use Darksparrow\DeegraphInteractions\DataStructures\UUID;
use Darksparrow\DeegraphInteractions\Exceptions\InvalidUUIDFormatException;
use JetBrains\PhpStorm\NoReturn;

require_once __DIR__ . '/../../vendor/autoload.php';



$setup_key = InitHelpers::HandleSetupKey();

if(!file_exists(LOCAL_STORAGE_DIRECTORY . "/setup.vars"))
{
    file_put_contents(LOCAL_STORAGE_DIRECTORY . "/setup.vars", "{}");
}
foreach($_POST as $key => $value)
{
    InitHelpers::AddVariable($key, $value);
}
$variables = InitHelpers::GetVariables();




switch($_GET['page'])
{
    case "0":
        PageBuilder2::Render(
            template : "Pages/system/~InitSteps/00.Welcome.html.twig",
            variables: [
                "Variables"=>$variables,
                "SetupKey"=>$setup_key,
            ]
        );
    case "1":
        PageBuilder2::Render(
            template : "Pages/system/~InitSteps/01.InstanceDetails.html.twig",
            variables: [
                "Variables"=>$variables,
                "SetupKey"=>$setup_key,
            ]
        );
    case "2":
        PageBuilder2::Render(
            template : "Pages/system/~InitSteps/02.MariaDB.html.twig",
            variables: [
                "Variables"=>$variables,
                "SetupKey"=>$setup_key,
            ]
        );
    case "2.1":
        $hostName = $variables['mariadb-host'];
        $port = $variables['mariadb-port'];
        $username = $variables['mariadb-username'];
        $password = $variables['mariadb-password'];
        $database = $variables['mariadb-database'];

        try {
            $db = new PDO(
                dsn     : "mysql:host=$hostName;port=$port;dbname=$database",
                username: $username,
                password: $password,
            );
            InitHelpers::AddVariable("error", null);
            NavigationUtilities::Redirect(
                target: "/system/init?page=3&setup_key=$setup_key",
            );
        }
        catch (PDOException $e) {
            InitHelpers::AddVariable("error", $e->getMessage());
            NavigationUtilities::Redirect(
                target: "/system/init?page=2&setup_key=$setup_key",
            );
        }
    case "3":
        PageBuilder2::Render(
            template : "Pages/system/~InitSteps/03.Deegraph.html.twig",
            variables: [
                "Variables"=>$variables,
                "SetupKey"=>$setup_key,
            ]
        );
    case "3.1":
        $temp = new DeegraphServer(
            token               : $variables['deegraph-token'],
            server              : $variables['deegraph-host'],
            port                : $variables['deegraph-port'],
            allowSelfSignedCerts: $variables['deegraph-allowSelfSignedCerts'],
        );
        try {
            $actorID = new UUID($variables['deegraph-loginNode']);
        }
        catch(InvalidUUIDFormatException $e) {
            InitHelpers::AddVariable("error", $e->getMessage());
            NavigationUtilities::Redirect(
                target: "/system/init?page=3&setup_key=$setup_key",
            );
        }
        try {
            $temp->ServerInfo(actorID: $actorID);
            InitHelpers::AddVariable("error", null);
            NavigationUtilities::Redirect(
                target: "/system/init?page=4&setup_key=$setup_key",
            );
        }
        catch (Exception $e) {
            InitHelpers::AddVariable("error", $e->getMessage());
            NavigationUtilities::Redirect(
                target: "/system/init?page=3&setup_key=$setup_key",
            );
        }
    case "4":
        PageBuilder2::Render(
            template : "Pages/system/~InitSteps/04.RootAccount.html.twig",
            variables: [
                "Variables"=>$variables,
                "SetupKey"=>$setup_key,
            ]
        );
    case "5":
        PageBuilder2::Render(
            template : "Pages/system/~InitSteps/05.Summary.html.twig",
            variables: [
                "Variables"=>$variables,
                "SetupKey"=>$setup_key,
            ]
        );
    case "5.1":
        if(!(array_key_exists(key: 'setupComplete-mariadb', array: $variables) && $variables['setupComplete-mariadb'] === true))
        {
            $hostName2 = $variables['mariadb-host'];
            $port2 = $variables['mariadb-port'];
            $username2 = $variables['mariadb-username'];
            $password2 = $variables['mariadb-password'];
            $database2 = $variables['mariadb-database'];
            $db = new PDO(
                dsn     : "mysql:host=$hostName2;port=$port2;dbname=$database2",
                username: $username2,
                password: $password2,
            );
            $filePath = __DIR__ . "/../../Public/Static/Misc/MariaDBSchema.sql";
            $schema = file_get_contents($filePath);
            $result = $db->exec(statement: $schema);

            InitHelpers::AddVariable("setupComplete-mariadb", true);
        }

        if(!(array_key_exists(key: 'setupComplete-deegraph', array: $variables) && $variables['setupComplete-deegraph'] === true))
        {
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
                    GraphDatabaseConnection::query(
                        new User($variables['deegraph-loginNode']),
                        $query
                    );
                }
                catch(Exception $e)
                {
                    renderCriticalError(errorMessage: "Deegraph is not reachable... is it online?");
                }
            }

            InitHelpers::AddVariable("setupComplete-deegraph", true);
        }

        if(!(array_key_exists(key: 'setupComplete-rootUser', array: $variables) && $variables['setupComplete-rootUser'] === true))
        {
            InitHelpers::CreateRootAccount($variables);
            InitHelpers::AddVariable("setupComplete-rootUser", true);
        }

        $creds = new CredentialManagement(newInstance: true, newVariables: $variables);
        $creds->Write();

        $envs = new EnvironmentManagement(newInstance: true, newVariables: $variables);
        $envs->Write();


        NavigationUtilities::Redirect(
            target: "/system/init?page=6&setup_key=$setup_key",
        );
    case "6":
        unlink(LOCAL_STORAGE_DIRECTORY . "/setup.key");
        unlink(LOCAL_STORAGE_DIRECTORY . "/setup.vars");
        PageBuilder2::Render(
            template : "Pages/system/~InitSteps/06.Completed.html.twig",
            variables: [
                "Variables"=>$variables,
                "SetupKey"=>$setup_key,
            ]
        );

}
