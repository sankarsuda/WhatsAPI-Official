<?php
set_time_limit(10);
require_once '../src/whatsprot.class.php';
//Change to your time zone
date_default_timezone_set('Europe/Madrid');

// phone number, deviceIdentity, and name.
$options = getopt("d::", array("debug::"));
$debug = (array_key_exists("debug", $options) || array_key_exists("d", $options)) ? true : false;

########## DO NOT COMMIT THIS FILE WITH YOUR CREDENTIALS ###########
///////////////////////CONFIGURATION///////////////////////
//////////////////////////////////////////////////////////
$username = "**your phone number**";                      // Telephone number including the country code without '+' or '00'.
$password = "**server generated whatsapp password**";     // Use registerTool.php or exampleRegister.php to obtain your password
$identity = "myIdentity";                                 // Name of the file where the identity will be stored. In this exaple, a file called myIdentity.dat will be created
$nickname = "**your nickname**";                          // This is the username (or nickname) displayed by WhatsApp clients.
$target = "**contact's phone number**";                   // Destination telephone number including the country code without '+' or '00'.
$debug = false;                                           // Set this to true, to see debug mode.
///////////////////////////////////////////////////////////

function fgets_u($pStdn)
{
    $pArr = array($pStdn);

    if (false === ($num_changed_streams = stream_select($pArr, $write = NULL, $except = NULL, 0))) {
        print("\$ 001 Socket Error : UNABLE TO WATCH STDIN.\n");

        return FALSE;
    } elseif ($num_changed_streams > 0) {
        return trim(fgets($pStdn, 1024));
    }
    return null;
}

//This function only needed to show how eventmanager works.
function onGetProfilePicture($from, $target, $type, $data)
{
    if ($type == "preview") {
        $filename = "preview_" . $target . ".jpg";
    } else {
        $filename = $target . ".jpg";
    }
    $filename = WhatsProt::PICTURES_FOLDER."/" . $filename;
    $fp = @fopen($filename, "w");
    if ($fp) {
        fwrite($fp, $data);
        fclose($fp);
    }

    echo "- Profile picture saved in /".WhatsProt::PICTURES_FOLDER."\n";
}

function onPresenceReceived($username, $from, $type)
{
    $dFrom = str_replace(array("@s.whatsapp.net","@g.us"), "", $from);
    if ($type == "available")
        echo "<$dFrom is online>\n\n";
    else
        echo "<$dFrom is offline>\n\n";
}

echo "[] Logging in as '$nickname' ($username)\n";
//Create the whatsapp object and setup a connection.
$w = new WhatsProt($username, $identity, $nickname, $debug);
$w->connect();

// Now loginWithPassword function sends Nickname and (Available) Presence
$w->loginWithPassword($password);

echo "[*] Connected to WhatsApp\n\n";

//Retrieve large profile picture. Output is in /src/php/pictures/ (you need to bind a function
//to the event onProfilePicture so the script knows what to do.
$w->eventManager()->bind("onGetProfilePicture", "onGetProfilePicture");
$w->sendGetProfilePicture($target, true);

//Print when the user goes online/offline (you need to bind a function to the event onPressence
//so the script knows what to do)
$w->eventManager()->bind("onPresence", "onPresenceReceived");


//update your profile picture
$w->sendSetProfilePicture("demo/venom.jpg");

//send picture
$w->sendMessageImage($target, "demo/x3.jpg");

//send video
//$w->sendMessageVideo($target, 'http://techslides.com/demos/sample-videos/small.mp4');

//send Audio
//$w->sendMessageAudio($target, 'http://www.kozco.com/tech/piano2.wav');

//send Location
//$w->sendLocation($target, '4.948568', '52.352957');

// Implemented out queue messages and auto msgid
$w->sendMessage($target, "Guess the number :)");
$w->sendMessage($target, "Sent from WhatsApi at " . date('H:i'));

while($w->pollMessage());

/**
 * You can create a ProcessNode class (or whatever name you want) that has a process($node) function
 * and pass it through setNewMessageBind, that way everytime the class receives a text message it will run
 * the process function to it.
 */
$pn = new ProcessNode($w, $target);
$w->setNewMessageBind($pn);

echo "\n\nYou can also write and send messages to $target (interactive conversation)\n\n> ";

while (1) {
    $w->pollMessage();
    $msgs = $w->getMessages();
    foreach ($msgs as $m) {
        # process inbound messages
        //print($m->NodeString("") . "\n");
    }
    $line = fgets_u(STDIN);
    if ($line != "") {
        if (strrchr($line, " ")) {
            $command = trim(strstr($line, ' ', TRUE));
        } else {
            $command = $line;
        }
        //available commands in the interactive conversation [/lastseen, /query]
        switch ($command) {
            case "/query":
                $dst = trim(strstr($line, ' ', FALSE));
                echo "[] Interactive conversation with $target:\n";
                break;
            case "/lastseen":
                echo "[] last seen: ";
                $w->sendGetRequestLastSeen($target);
                break;
            default:
                $w->sendMessage($target , $line);
                break;
        }
    }
}

/**
 * Demo class to show how you can process inbound messages
 */
class ProcessNode
{
    protected $wp = false;
    protected $target = false;

    public function __construct($wp, $target)
    {
        $this->wp = $wp;
        $this->target = $target;
    }

    /**
     * @param ProtocolNode $node
     */
    public function process($node)
    {
        // Example of process function, you have to guess a number (psss it's 5)
        // If you guess it right you get a gift
        $text = $node->getChild('body');
        $text = $text->getData();
        if ($text && ($text == "5" || trim($text) == "5")) {
            $this->wp->sendMessageImage($this->target, "https://s3.amazonaws.com/f.cl.ly/items/2F3U0A1K2o051q1q1e1G/baby-nailed-it.jpg");
            $this->wp->sendMessage($this->target, "Congratulations you guessed the right number!");
        }
        elseif (ctype_digit($text)) {
            if ((int)$text != "5")
                $this->wp->sendMessage($this->target, "I'm sorry, try again!");
        }
        $text = $node->getChild('body');
        $text = $text->getData();
        $notify = $node->getAttribute("notify");

        echo "\n- ".$notify.": ".$text."    ".date('H:i')."\n";

    }
}
