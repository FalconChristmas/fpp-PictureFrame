<?php

$imageDir = '/home/fpp/media/images';
$settings = parse_ini_file('/home/fpp/media/settings');
$pluginSettings = parse_ini_file('/home/fpp/media/config/plugin.fpp-PictureFrame');
$jsonStr = file_get_contents('/home/fpp/media/config/plugin.fpp-PictureFrame.json');
$jsonSettings = json_decode($jsonStr, true);

define("FPP_LOG_ERR",         1);
define("FPP_LOG_WARN",        2);
define("FPP_LOG_INFO",        3);
define("FPP_LOG_DEBUG",       4);
define("FPP_LOG_EXCESSIVE",   5);

$logLevel = FPP_LOG_INFO;

if (isset($settings['LogLevel_Plugin'])) {
    switch ($settings['LogLevel_Plugin']) {
        case 'error':
            $logLevel = FPP_LOG_ERR;
            break;
        case 'warn':
            $logLevel = FPP_LOG_WARN;
            break;
        case 'info':
            $logLevel = FPP_LOG_INFO;
            break;
        case 'debug':
            $logLevel = FPP_LOG_DEBUG;
            break;
        case 'excess':
            $logLevel = FPP_LOG_EXCESSIVE;
            break;
    }
}

function printLog($level, $string) {
    global $logLevel;

    if ($logLevel >= $level) {
        echo date("Y-m-d H:i:s") . ' ' . $string . "\n";
    }
}

$host = '';
$port = 993;
$username = '';
$password = '';
$mailbox = 'INBOX';
$autoDelete = 1;

if (isset($pluginSettings['pfautodelete']))
    $autoDelete = intval($pluginSettings['pfautodelete']);

if (isset($pluginSettings['pfemailserver']))
    $host = $pluginSettings['pfemailserver'];

if (isset($pluginSettings['pfemailuser']))
    $username = $pluginSettings['pfemailuser'];

if (isset($pluginSettings['pfemailpass']))
    $password = $pluginSettings['pfemailpass'];

if (isset($pluginSettings['pfmailbox']))
    $mailbox = $pluginSettings['pfmailbox'];

if (isset($pluginSettings['pfemailport']))
    $port = intval($pluginSettings['pfemailport']);

if (($username == '') ||
    ($password == '') ||
    ($host == '')) {
    printf( "ERROR: One or more of pfemailserver, pfemailuser, pfemailpass is not set\n" );
    exit(0);
}

$server   = '{' . $host . ":993/imap/ssl}";

printLog(FPP_LOG_DEBUG, sprintf( "User           : %s", $username));
printLog(FPP_LOG_DEBUG, sprintf( "Password       : %s", $password));
printLog(FPP_LOG_DEBUG, sprintf( "Host           : %s", $host));
printLog(FPP_LOG_DEBUG, sprintf( "Port           : %d", $port));
printLog(FPP_LOG_DEBUG, sprintf( "Mailbox        : %s", $mailbox));
printLog(FPP_LOG_DEBUG, sprintf( "Server         : %s", $server));

$imageRegex = "\.(jpeg|jpg|png|gif)$";

$validSenders = array();
$senderFolders = array();

if (isset($jsonSettings['senders'])) {
    foreach ($jsonSettings['senders'] as $sender) {
        $email = $sender['email'];
        if(filter_var($email, FILTER_VALIDATE_EMAIL)) {
            printLog(FPP_LOG_DEBUG, sprintf( "Sender Email  : %s", $email));
            printLog(FPP_LOG_DEBUG, sprintf( "       Note   : %s", $sender['note']));
            array_push($validSenders, $email);
            array_push($senderFolders, $sender['folder']);
        } else if(filter_var("blah" . $email, FILTER_VALIDATE_EMAIL)) {
            printLog(FPP_LOG_DEBUG, sprintf( "Sender Domain : %s", $email));
            printLog(FPP_LOG_DEBUG, sprintf( "       Note   : %s", $sender['note']));
            array_push($validSenders, ".*$email");
            array_push($senderFolders, $sender['folder']);
        } else if(filter_var("blah@" . $email, FILTER_VALIDATE_EMAIL)) {
            printLog(FPP_LOG_DEBUG, sprintf( "Sender Domain : %s", $email));
            printLog(FPP_LOG_DEBUG, sprintf( "       Note   : %s", $sender['note']));
            array_push($validSenders, ".*@$email");
            array_push($senderFolders, $sender['folder']);
        }
    }
}

$mbox = imap_open($server . $mailbox, $username, $password);

if (!$mbox) {
    printf( "Error opening connection:\n");
    var_dump(imap_errors());
    exit(0);
}

$info = imap_check($mbox);

printLog(FPP_LOG_DEBUG, sprintf("Date most recent message : %s", $info->Date));
printLog(FPP_LOG_DEBUG, sprintf("Connection type          : %s", $info->Driver));
printLog(FPP_LOG_DEBUG, sprintf("Name of the mailbox      : %s", $info->Mailbox));
printLog(FPP_LOG_DEBUG, sprintf("Number of messages       : %s", $info->Nmsgs));
printLog(FPP_LOG_DEBUG, sprintf("Number of recent messages: %s", $info->Recent));

for ($i = 1; $i <= $info->Nmsgs; $i++) {
    printLog(FPP_LOG_INFO, sprintf( "Checking email #%d", $i));
    $headers = imap_headerinfo($mbox, $i);

    $sender = '';
    $validSender = -2;
    $senderIndex = 0;
    $downloadedImages = 0;

    if (count($validSenders) > 0) {
        for ($j = 0; $j < count($headers->from); $j++) {
            $sender = $headers->from[$j]->mailbox . '@' . $headers->from[$j]->host;
            foreach ($validSenders as $senderRegex) {
                if (preg_match("/$senderRegex/", $sender)) {
                    $validSender = $senderIndex;
                    printLog(FPP_LOG_DEBUG, sprintf( "Sender '%s' matches regex '%s'", $sender, $senderRegex));
                }
            }
        }
    } else {
        $validSender = -1;
    }

    if ($validSender >= -1) {
        $overview = imap_fetch_overview($mbox, $i, 0);
        $message = imap_fetchbody($mbox, $i, 2);
        $structure = imap_fetchstructure($mbox, $i);

        $attachments = array();

        // This code is from https://stackoverflow.com/questions/2649579/downloading-attachments-to-directory-with-imap-in-php-randomly-works
        if (isset($structure->parts) && count($structure->parts)) {
            for ($x = 0; $x < count($structure->parts); $x++) {
                $attachments[$x] = array(
                    'is_attachment' => false,
                    'filename' => '',
                    'name' => '',
                    'attachment' => ''
                );

                if($structure->parts[$x]->ifdparameters) 
                {
                    foreach($structure->parts[$x]->dparameters as $object) 
                    {
                        if(strtolower($object->attribute) == 'filename') 
                        {
                            $attachments[$x]['is_attachment'] = true;
                            $attachments[$x]['filename'] = $object->value;
                        }
                    }
                }

                if($structure->parts[$x]->ifparameters) 
                {
                    foreach($structure->parts[$x]->parameters as $object) 
                    {
                        if(strtolower($object->attribute) == 'name') 
                        {
                            $attachments[$x]['is_attachment'] = true;
                            $attachments[$x]['name'] = $object->value;
                        }
                    }
                }

                if($attachments[$x]['is_attachment']) 
                {
                    $attachments[$x]['attachment'] = imap_fetchbody($mbox, $i, $x+1);

                    /* 3 = BASE64 encoding */
                    if($structure->parts[$x]->encoding == 3) 
                    { 
                        $attachments[$x]['attachment'] = base64_decode($attachments[$x]['attachment']);
                    }
                    /* 4 = QUOTED-PRINTABLE encoding */
                    elseif($structure->parts[$x]->encoding == 4) 
                    { 
                        $attachments[$x]['attachment'] = quoted_printable_decode($attachments[$x]['attachment']);
                    }
                }
            }
        }

        /* iterate through each attachment and save it */
        foreach($attachments as $attachment)
        {
            if($attachment['is_attachment'] == 1)
            {
                $filename = $attachment['name'];

                if(empty($filename)) $filename = $attachment['filename'];

                if (preg_match("/$imageRegex/i", $filename)) {
                    $fn = '/dev/null';
                    if (($validSender >= 0) &&
                        ($senderFolders[$validSender] != '') &&
                        (is_dir($imageDir . '/' . $senderFolders[$validSender]))) {
                        $fn = sprintf( "%s/%s/%s-%s", $imageDir, $senderFolders[$validSender], Date('Ymd'), $filename);
                    } else {
                        $fn = sprintf( "%s/%s-%s", $imageDir, Date('Ymd'), $filename);
                    }
                    printLog( FPP_LOG_INFO, sprintf("Saving File: %s", $fn));
                    $fp = fopen($fn, "w+");
                    fwrite($fp, $attachment['attachment']);
                    fclose($fp);

                    chown($fn, 'fpp');
                    chgrp($fn, 'fpp');
                    chmod($fn, 0644);

                    $downloadedImages = 1;
                } else {
                    printLog( FPP_LOG_INFO, sprintf("Skipping non-image file: %s", $filename));
                }
            }
        }
    } else {
        printLog(FPP_LOG_WARN, sprintf( "Sender '%s' is not in list of valid senders", $sender));
    }

    if ($autoDelete && $downloadedImages) {
        // Delete the email
        imap_delete($mbox, $i);
    }
}

// Expunge deleted emails
imap_expunge($mbox);

imap_close($mbox);

?>
