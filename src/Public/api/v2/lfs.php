<?php

ob_start();

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../environment.php';

$at = Auxilium\APITools::get_instance();
$at->requireLogin();



$file_id = null;
$file_hash = null;
$metadata = false;
$mime_type = null;
$uri_components = explode("/", $_SERVER["REQUEST_URI"]);
$last_uri_component = explode("?", end($uri_components));
$get_params = "";
if (count($last_uri_component) > 1) {
    $get_params = $last_uri_component[1];
}
$uri_components[count($uri_components) - 1] = $last_uri_component[0];

if (count($uri_components) > 4) {
    $spl = explode("+",$uri_components[4]);
    $file_id = strtolower($spl[0]);
    if (count($spl) > 1) {
        $file_hash = $spl[1];
    }
    if (count($spl) > 2) {
        $mime_type = str_replace(":", "/", urldecode($spl[2]));
    }
}

if ($mime_type == null) {
    $mime_type = "application/octet-stream";
}

if (strtolower($get_params) == "metadata") {
    $metadata = true;
}

$lfsobj = new Auxilium\AuxiliumLFSObject("auxlfs://".INSTANCE_CREDENTIAL_DDS_HOST."/".$file_id."+".$file_hash."+".urlencode($mime_type));

if (!$lfsobj->canRead()) {
    $at->setErrorText("Missing read permission");
    $at->output();
    exit();
}

/*

Byteserving code derived from work by Răzvan Valentin Florian avaliable at:
https://github.com/rvflorian/byte-serving-php
Accessed 2022-04-14

MIT License

Copyright (c) 2018 Răzvan Valentin Florian

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.

*/


if (isset($_FILES["file"]["name"])) {
    if ($file_id == null) {
        $at->setResponseCode(400);
        $at->setErrorText("Missing uuid");
        $at->output();
    } else {
        if (preg_match("/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/", $file_id)) {
            if ($lfsobj->exists()) {
                $at->setResponseCode(409);
                $at->setErrorText("You cannot overwrite LFS objects");
                $at->output();
                exit();
            }
            
            if (!$lfsobj->isWriteable()) {
                $at->setResponseCode(403);
                $at->setErrorText("You didn't create the node associated with this object");
                $at->output();
                exit();
            }
        
            move_uploaded_file($_FILES["file"]["tmp_name"], LOCAL_STORAGE_DIRECTORY.$file_id);
            
            $at->setVariable("file_id", $file_id);
            $at->setVariable("file_hash", $file_hash);
            $at->output();
        } else {
            $at->setErrorText("Malformed uuid");
            $at->output();
        }
    }
} else {
    if ($file_id == null) {
        $at->setErrorText("Missing uuid");
        $at->output();
    } else {
        if (preg_match("/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/", $file_id)) {
            //echo "SENDING ".$file_id;
            if (!$lfsobj->exists()) {
                $at->setResponseCode(404);
                $at->setErrorText("LFS file content is missing");
                $at->output();
                exit();
            }
            
            if ($metadata) {
                $at->setVariable("id", $file_id);
                $at->setVariable("hash", $file_hash);
                $at->setVariable("size", filesize(LOCAL_STORAGE_DIRECTORY.$file_id));
                $at->output();
            } else {
                //header("Content-Disposition: filename=\"".$file_name."\"");
                $data_size = filesize(LOCAL_STORAGE_DIRECTORY.$file_id);
                if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_SERVER["HTTP_RANGE"]) && $range = stristr(trim($_SERVER["HTTP_RANGE"]), "bytes=")) {
                
                    $range = substr($range, 6);
                    $ranges = explode(",",$range);

                    if (count($ranges) > 0) {
                        $firsts = [];
                        $lasts = [];
                        $rangecount = 0;
                        
                        foreach ($ranges as $range){
                            $dash = strpos($range, "-");
                            $first = trim(substr($range, 0, $dash));
                            $last = trim(substr($range, $dash + 1));
                            if ($first == "") {
                                $suffix = $last;
                                $last = $data_size-1;
                                $first = $data_size-$suffix;
                                if ($first < 0) $first = 0;
                            } else {
                                if ($last == "" || ($last > ($data_size - 1))) $last = $data_size - 1;
                            }
                            if ($first > $last) {
                                // Unsatisfiable range
                                header("Status: 416 Requested range not satisfiable");
                                header("Content-Range: */$data_size");
                                exit();
                            } else {
                                array_push($firsts, $first);
                                array_push($lasts, $last);
                                $rangecount++;
                            }
                        }
                    
                        header("HTTP/1.1 206 Partial content");
                        header("Accept-Ranges: bytes");
                        if (count($ranges) > 1) {
                            $boundary = bin2hex(openssl_random_pseudo_bytes(32)); // Set a random boundary - we better hope this doesn't show up in the file!
                            $content_length = 0;

                            for ($i = 0; $i < $rangecount; $i++){
                                $content_length += strlen("\r\n--$boundary\r\n");
                                $content_length += strlen("Content-Type: ".$mime_type."\r\n");
                                $content_length += strlen("Content-Range: bytes ".$firsts[$i]."-".$lasts[$i]."/$data_size\r\n\r\n");
                                $content_length += $lasts[$i]-$firsts[$i];          
                            }
                            $content_length += strlen("\r\n--$boundary--\r\n");
                            header("Content-Length: $content_length");
                            header("Content-Type: multipart/x-byteranges; boundary=$boundary");

                            while (ob_get_level()) {
                                ob_end_clean();
                            }
                            flush();
                            $fh = fopen(LOCAL_STORAGE_DIRECTORY.$file_id, 'r');
                            for ($i = 0; $i < $rangecount; $i++){
                                echo "\r\n--$boundary\r\n";
                                echo "Content-Type: ".$mime_type."\r\n";
                                echo "Content-Range: bytes ".$firsts[$i]."-".$lasts[$i]."/$data_size\r\n\r\n";
                                fseek($fh, $firsts[$i], SEEK_SET);
                                echo fread($fh, $lasts[$i] - $firsts[$i]);
                            }
                            fclose($fh);
                            echo "\r\n--$boundary--\r\n";
                        } else {
                            header("Content-Length: ".($lasts[0] - $firsts[0]));
                            header("Content-Range: bytes ".$firsts[0]."-".$lasts[0]."/$data_size");
                            header("Content-Type: ".$mime_type."");  
                            $fh = fopen(LOCAL_STORAGE_DIRECTORY.$file_id, 'r');
                            while (ob_get_level()) {
                                ob_end_clean();
                            }
                            flush();
                            fseek($fh, $firsts[0], SEEK_SET);
                            echo fread($fh, $lasts[0] - $firsts[0]);
                            fclose($fh);
                        }
                    } else {
                        header("Accept-Ranges: bytes");
                        header("Content-Length: $data_size");
                        header("Content-Type: ".$mime_type);
                        while (ob_get_level()) {
                            ob_end_clean();
                        }
                        flush();
                        readfile(LOCAL_STORAGE_DIRECTORY.$file_id);
                    }
                } else {
                    header("Accept-Ranges: bytes");
                    header("Content-Length: $data_size");
                    header("Content-Type: ".$mime_type);
                    while (ob_get_level()) {
                        ob_end_clean();
                    }
                    flush();
                    readfile(LOCAL_STORAGE_DIRECTORY.$file_id);
                }
            }
        } else {
            $at->setErrorText("Malformed uuid");
            $at->output();
        }
    }
}

// intentionally missing PHP closing tag to avoid trailing whitespace issue
