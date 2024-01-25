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

$logLevel = FPP_LOG_WARN;

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

$host = '';
$port = 993;
$username = '';
$password = '';
$mailbox = 'INBOX';

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
    $validSender = -2; // Invalid sender
    $senderIndex = 0;
    $downloadedImages = false;

    if (count($validSenders) > 0) {
        for ($j = 0; $j < count($headers->from); $j++) {
            $sender = $headers->from[$j]->mailbox . '@' . $headers->from[$j]->host;
            foreach ($validSenders as $senderRegex) {
                if (($validSender == -2) && preg_match("/$senderRegex/i", $sender)) {
                    $validSender = $senderIndex;
                    printLog(FPP_LOG_DEBUG, sprintf( "Sender '%s' matches regex '%s'", $sender, $senderRegex));
                }
                $senderIndex++;
            }
        }
    } else {
        // -1 == allow all senders
        $validSender = -1;
    }

    if ($validSender >= -1) {
        $overview = imap_fetch_overview($mbox, $i, 0);
        $message = imap_fetchbody($mbox, $i, 2);
        $structure = imap_fetchstructure($mbox, $i);

        $attachments = array();

        if (isset($structure->parts) && count($structure->parts)) {
            for ($x = 1; $x <= count($structure->parts); $x++) {
                checkPartForAttachments($attachments, $mbox, $i,
                    $structure->parts[$x-1], "$x");
            }
        }

        /* iterate through each attachment and save it */
        foreach($attachments as $attachment)
        {
            $filename = $attachment['filename'];

            $fn = '/dev/null';
            $prefix = Date('Ymd') . '-';
            if (($validSender >= 0) &&
                ($senderFolders[$validSender] != '') &&
                (is_dir($imageDir . '/' . $senderFolders[$validSender]))) {
                $fn = sprintf( "%s/%s/%s%s", $imageDir,
                    $senderFolders[$validSender], $prefix, $filename);
            } else {
                $fn = sprintf( "%s/%s%s", $imageDir, $prefix, $filename);
            }
            printLog( FPP_LOG_INFO, sprintf("Saving File: %s", $fn));
            $fp = fopen($fn, "w+");
            fwrite($fp, $attachment['attachment']);
            fclose($fp);

            chown($fn, 'fpp');
            chgrp($fn, 'fpp');
            chmod($fn, 0644);

            $downloadedImages = true;
        }

        // Delete the email if we saved any images from it
        // if ($downloadedImages)
        //     imap_delete($mbox, $i);
    } else {
        printLog(FPP_LOG_WARN,
            sprintf( "Sender '%s' is not in list of valid senders", $sender));
    }
}

// Expunge deleted emails
imap_expunge($mbox);

imap_close($mbox);

/////////////////////////////////////////////////////////////////////////////

function printLog($level, $string) {
    global $logLevel;

    if ($logLevel >= $level) {
        echo date("Y-m-d H:i:s") . ' ' . $string . "\n";
    }
}

function checkPartForAttachments(&$attachments, $mbox, $idx, $part, $partNum) {
    $attachment = array(
        'filename' => '',
        'attachment' => ''
    );

    if (isset($part->parts) && count($part->parts)) {
        for ($y = 1; $y <= count($part->parts); $y++) {
            checkPartForAttachments($attachments, $mbox, $idx,
                $part->parts[$y-1], $partNum . ".$y");
        }

    } else if($part->ifdparameters) {
        foreach($part->dparameters as $object)
        {
            if(strtolower($object->attribute) == 'filename')
                $attachment['filename'] = $object->value;
        }
    } else if($part->ifparameters) {
        foreach($part->parameters as $object)
        {
            if(strtolower($object->attribute) == 'name')
                $attachment['filename'] = $object->value;
        }
    }

    if (($attachment['filename'] != '') &&
        (preg_match('/\.(jpg|jpeg|png|gif)$/i', $attachment['filename'])))
    {
        $attachment['attachment'] = imap_fetchbody($mbox, $idx, $partNum);

        /* 3 = BASE64 encoding */
        if($part->encoding == 3)
        {
            $attachment['attachment'] =
                base64_decode($attachment['attachment']);
        }
        /* 4 = QUOTED-PRINTABLE encoding */
        elseif($part->encoding == 4)
        {
            $attachment['attachment'] =
                quoted_printable_decode($attachment['attachment']);
        }

        if (isset($attachment['attachment'])) {
            array_push($attachments, $attachment);
        }
    }
}

?>
