<?php
namespace auxilium;

require_once CREDENTIALS_FILE_LOCATION;

class GraphDatabaseConnection {
    public static function get_instance_node() {
        return new Node(INSTANCE_UUID);
    }

    public static function raw_request(?User $actor, string $path, string $method = "GET", string $body_data = null) {
        if ($actor == null) {
            $actor = Session::get_current()->getUser();
        }
    
        $method = strtoupper($method);
        $cmps = explode("/", $path);
        foreach ($cmps as &$cmp) {
            $cmp = urlencode($cmp);
        }
        $url = "https://".INSTANCE_CREDENTIAL_DDS_HOST.implode("/",$cmps);
        $curl_handle = curl_init();
        curl_setopt($curl_handle, CURLOPT_URL, $url);
        if ($method == "POST") {
            curl_setopt($curl_handle, CURLOPT_POST, 1);
        }
        curl_setopt($curl_handle, CURLOPT_PORT, INSTANCE_CREDENTIAL_DDS_PORT);
        if ($actor == null) {
            throw new DatabaseConnectionException("Client's user account is invalid");
        }
        curl_setopt($curl_handle, CURLOPT_HTTPHEADER, ["Content-Type: text/plain", "Authorization: Bearer ".INSTANCE_CREDENTIAL_DDS_TOKEN, "X-Auxilium-Actor: ".$actor->getId()]);
        if ($method == "POST" || $method == "PUT") {
            if ($body_data != null) {
                curl_setopt($curl_handle, CURLOPT_POSTFIELDS, $body_data);
            }
        }
        curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, true);
        $server_output = curl_exec($curl_handle); // Send the message
        
        if ($server_output === false) {
            throw new DatabaseConnectionException("Deegraph server did not respond to Auxilium query");
        }
        
        if (strpos($server_output, "{") === 0) {
            $server_output = json_decode($server_output, true);
        }
        
        if (curl_getinfo($curl_handle, CURLINFO_RESPONSE_CODE) >= 500 && curl_getinfo($curl_handle, CURLINFO_RESPONSE_CODE) < 600) {
            throw new DeegraphException("Deegraph server responded with an internal error code", 0, null, isset($server_output["@trace"]) ? $server_output["@trace"] : null);
            //throw new DatabaseConnectionException("Client's user account is invalid");
        }
        
        curl_close($curl_handle);
        
        return $server_output;
    }

    public static function query(?User $actor, string $query, array $fvars = []) {
        if ($actor == null) {
            $actor = Session::get_current()->getUser();
        }
    
        $lookup_table = [];
        
        $fvars = [
            "passed" => $fvars
        ];
        $fvars["static"] = [
            "case" => "\"https://schemas.auxiliumsoftware.co.uk/v1/case.json\"",
            "user" => "\"https://schemas.auxiliumsoftware.co.uk/v1/user.json\"",
            "organisation" => "\"https://schemas.auxiliumsoftware.co.uk/v1/organisation.json\"",
            "document" => "\"https://schemas.auxiliumsoftware.co.uk/v1/document.json\"",
            "message" => "\"https://schemas.auxiliumsoftware.co.uk/v1/message.json\""
        ];
        
        foreach ($fvars as $var_type => &$vars) {
            foreach ($vars as $key => &$var) {
                $var_id = null;
                while ($var_id == null) {
                    $var_id = bin2hex(openssl_random_pseudo_bytes(32));
                    if (strpos($query, $var_id) !== false) {
                        $var_id = null;
                    }
                }
                $var_id = "###".$var_id."###";
                $query = str_replace("\$".$key, $var_id, $query);
                switch ($var_type) {
                    case "static":
                        $lookup_table[$var_id] = $var;
                        break;
                    default:
                        $lookup_table[$var_id] = $var;
                        break;
                }
            }
        }
        
        $query_buffer = "";
        $data_buffer = "";
        $backtick_switch = false;
        $escape_char = false;
        
        foreach (mb_str_split($query) as $char) {
            if ($char == "`") {
                if ($escape_char) {
                    $escape_char = false;
                    $query_buffer = $query_buffer."`";
                } else {
                    if ($backtick_switch) {
                        $backtick_switch = false;
                        $query_buffer = $query_buffer."\"data:text/plain,".urlencode($data_buffer)."\"";
                    } else {
                        $backtick_switch = true;
                        $data_buffer = "";
                    }
                }
            } elseif ($char == "\\") {
                $escape_char = true;
            } else {
                if ($escape_char) {
                    $escape_char = false;
                    if ($backtick_switch) {
                        $data_buffer = $data_buffer."\\";
                    } else {
                        $query_buffer = $query_buffer."\\";
                    }
                }
                if ($backtick_switch) {
                    $data_buffer = $data_buffer.$char;
                } else {
                    $query_buffer = $query_buffer.$char;
                }
            }
        }
        
        $query = $query_buffer;

        foreach($lookup_table as $key => &$var) {
            $query = str_replace($key, $var, $query);
        }
        
        //echo $query;
        $ret_val = GraphDatabaseConnection::raw_request($actor, "/api/v1/@query", "POST", $query);
        if (is_array($ret_val)) {
            $ret_val["@generated_query"] = $query;
        }
        return $ret_val;
    }
    
    public static function node_from_path(string $path, User $actor = null) {
        if ($actor == null) {
            $actor = Session::get_current()->getUser();
        }
        
        $path_cmps = explode("/", $path);
        foreach ($path_cmps as &$path_cmp) {
            if (strpos($path_cmp, "~") === 0) {
                $path_cmp = "{".substr($path_cmp, 1)."}";
            }
        }
        $path = implode("/", $path_cmps);
        
        $result = GraphDatabaseConnection::raw_request($actor, "/api/v1/@resolve_path", "POST", $path);
        if (is_array($result)) {
            if (isset($result["@id"])) {
                return Node::from_id($result["@id"]);
            }
        }
        return null;
    }

    public static function get_raw_node_info(?User $actor, string $uuid) {
        if ($actor == null) {
            $actor = Session::get_current()->getUser();
        }

        if (!preg_match("/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/", $uuid)) {
            throw new DeegraphException("Invalid UUID", 0, null, null);
        }

        $server_response = GraphDatabaseConnection::raw_request($actor, "/api/v1/{" . $uuid . "}", "GET");

        if (is_array($server_response)) {
            return $server_response;
        }
    }
    
    public static function new_node_raw(string $data_url = null, string $schema = null, User $creator = null) {
        if ($creator == null) {
            $creator = Session::get_current()->getUser();
        }
        
        $body = [
            "@data" => $data_url,
        ];
        
        if ($schema != null) {
            $body["@schema"] = $schema;
        }
        
        $server_response = GraphDatabaseConnection::raw_request($creator, "/api/v1/@new", "PUT", json_encode($body));
        
        if (is_array($server_response)) {
            return new Node($server_response["@id"]);
        }
    }
    
    public static function new_node($data = null, string $media_type = null, string $schema = null, User $creator = null) {
        $data_url = null;
        if ($data != null && $media_type != null) {
            $media_type = mb_strtolower($media_type);
            switch ($media_type) {
                case "text/plain":
                case "application/json":
                    $data_url = "data:".$media_type.",".urlencode($data);
                    break;
                default:
                    $data_url = "data:".$media_type.";base64,".base64_encode($data);
                    break;
            }
        }
        return GraphDatabaseConnection::new_node_raw($data_url, $schema, $creator);
    }
}
    
?>