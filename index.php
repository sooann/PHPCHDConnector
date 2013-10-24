<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of socketclass
 *
 * @author daniell
 */

include_once('openerpdata.php');

$__server_listening = true;

const ONLINE_PROTOCOL_ID = 5;
const COMMAND_FRAME_ID = 3;

const RESPONSE_OK = 0;
const RESPONSE_BadFrame = 3;
const RESPONSE_CMDFail = 6;

$port = 10000;
$addr = "192.168.1.37";

if (($sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) === false) {
    echo "socket_create() failed: reason: " . socket_strerror(socket_last_error()) . "\n";
}

if (socket_bind($sock, $addr, $port) === false) {
    echo "socket_bind() failed: reason: " . socket_strerror(socket_last_error($sock)) . "\n";
}

if (socket_listen($sock, 5) === false) {
    echo "socket_listen() failed: reason: " . socket_strerror(socket_last_error($sock)) . "\n";
}

socket_getsockname($sock, $addr, $port);
print "Server Listening on $addr:$port\n";
print "waiting for client to connect\n";

$clients = array($sock);

do {

     // create a copy, so $clients doesn't get modified by socket_select()
    $read = $clients;

    // get a list of all the clients that have data to be read from
    // if there are no clients with data, go to next iteration
    if (socket_select($read, $write = NULL, $except = NULL, 0) < 1)
        continue;
    
    // check if there is a client trying to connect
    if (in_array($sock, $read)) {
        // accept the client, and add him to the $clients array
        $clients[] = $newsock = socket_accept($sock);

        // send the client a welcome message
        //socket_write($newsock, "no noobs, but ill make an exception :)\n".
        //"There are ".(count($clients) - 1)." client(s) connected to the server\n");

        socket_getpeername($newsock, $ip);
        echo "New client connected: {$ip}\n";

        // remove the listening socket from the clients-with-data array
        $key = array_search($sock, $read);
        unset($read[$key]);
    }
    
    // loop through all the clients that have data to read from
    foreach ($read as $read_sock) {
        // read until newline or 1024 bytes
        // socket_read while show errors when the client is disconnected, so silence the error messages
        $data = @socket_read($read_sock, 2048);
        
        // check if the client is disconnected
        if ($data === false) {
            // remove client for $clients array
            $key = array_search($read_sock, $clients);
            unset($clients[$key]);
            echo "client disconnected.\n";
            // continue to the next client to read from, if any
            continue;
        }

        HandleResponse($data,$read_sock);   
       
    } // end of reading foreach
       
} while ($__server_listening);
socket_close($sock);

function HandleResponse ($input,$socket) {
    socket_getpeername($socket, $ip);
    echo "servicing client: $ip\n";
    $byte_array = unpack('C*', $input);
    if ($byte_array[1]==ONLINE_PROTOCOL_ID) {
        $datalength = intval($byte_array[3] + $byte_array[2]);
        echo "Online Protocol initiated - Data length received: ".$datalength."\n";
        $command = $byte_array[4];
        if ($command==COMMAND_FRAME_ID) {
            echo "PLU lookup command received\n";
            $value="";
            for ($i=5;$i<(count($byte_array)+1);$i++) {
                $value .= chr($byte_array[$i]);
            }
            echo "Looking up item number: ".$value."\n";
            $data = new OpenERPdata;
            if ($value=="10") {
                echo "PLU $value Item Found\n";
                $pluData = "10,\"PLU 10\",120,0,0,1,1,0,0,0,0";
                $data = BuildResponseFrame(ONLINE_PROTOCOL_ID, RESPONSE_OK, $pluData);
                print "Response : $data\n";
                print "Data Length : ".strlen($data)."\n";
                socket_write($socket, $data, strlen($data));
            } else {
                $res = $data->queryProduct($value);
                if ($res!=null) {
                    echo "PLU $value Item Found\n";
                    $price = $data->queryProductPrice("1", $res[0]["id"], 1);
                    $pluData = "$value,\"".$res[0]["name"]."\",".$price.",0,0,1,1,0,0,0,0";
                    $data = BuildResponseFrame(ONLINE_PROTOCOL_ID, RESPONSE_OK, $pluData);
                    print "Response : $data\n";
                    print "Data Length : ".strlen($data)."\n";
                } else {
                    echo "Item not found\n";
                    $data = BuildResponseFrame(ONLINE_PROTOCOL_ID, RESPONSE_CMDFail);
                    print "Response : $data\n";
                    print "Data Length : ".strlen($data)."\n";
                }
                socket_write($socket, $data, strlen($data));
            }
        }
    }   
}

function BuildResponseFrame ($reponseType, $responseCode, $responseData=null) {
    if ($responseData!=NULL) {
        $length = 1 + strlen($responseData);
    } else {
        $length = 1;
    }
    $dataLength = pack("C*",$reponseType,$length % 256,(int)($length / 256),$responseCode);
    if ($responseData!=NULL) {
        for ($i=0;$i<strlen($responseData);$i++) {
            $dataLength .= pack("C*", ord(substr($responseData, $i, 1)));
        }
    } 
    return $dataLength;
}



?>
