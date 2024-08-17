# Node relationships

The message must be stored in a node at $sender/messages/# and $recipient\[*]/messages/#
If a user removes a message from these nodes, the user SHOULD lose access to the message
The user SHOULD be allowed to "delete" messages using this method

The node must have the following properties on creation:
$message/sender = $sender
$message/recipients/# = $recipient\[*]



# Node data

The mime type should be message/rfc822

The message itself must conform to RFC 2822 and the following criteria

Addresses should be in the format 
```"auxiliuminbox+" <node_uuid> "@" <dds_fqdn>```
Example:
```auxiliuminbox+dd89f43a-a256-4e13-908d-d2ff6a9e7908@auth-dev.indent.one```
UUIDs MUST be in lowercase to conform to the standard

Messages adhering to this standard MUST include the header:
```X-Auxilium-Message-Version: 2.0```


