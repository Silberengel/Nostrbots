<?php

require __DIR__ . '/utilities/bootstrap.php';
use swentel\nostr\Event\Event;
use swentel\nostr\Sign\Sign;
use swentel\nostr\Message\EventMessage;
use swentel\nostr\Relay\Relay;


// Setup the system.

echo PHP_EOL.PHP_EOL;
echo "Welcome to Nostrbots!";
echo PHP_EOL.PHP_EOL;

// Check arguments from the command line.

$settings = get_bot_settings(args: $argv, argcount: $argc);
  echo "You have entered the following settings: ".PHP_EOL;
  echo "Bot folder is: ".$settings[0].PHP_EOL;
  echo "Notifications are: ".$settings[1].PHP_EOL;
  echo "The relays chosen are: ".$settings[2].PHP_EOL;
  echo PHP_EOL.PHP_EOL;

// Ensure that they have at least one active relay, that they have access to.

echo "You have requested the following relays be used: ".PHP_EOL;
  $relays = get_active_relays(websocket: $settings[2]);
  if(empty($relays)){
    $relays = get_hardcoded_relay();
    $result = test_relays( relayUrl: $relays[0]);
    if($result === FALSE){
      echo PHP_EOL."All relays failed the test. Aborting.".PHP_EOL.PHP_EOL;
      exit(125);
    }
  } 

  echo PHP_EOL."The relays that will be used are: ".PHP_EOL;
  print_r(value: $relays);
  echo PHP_EOL;

// get article data from the yaml file

$articleData = yaml_parse_file(__DIR__ . "/../botData/".$settings[0]."/article_data.yml");
    if ((!is_array(value: $articleData)) || ($articleData === FALSE)){
        echo "The article_data.yml file in the ".$settings[0]." folder could not be read. Aborting.".PHP_EOL;
        exit(125);
      }

echo "You have requested the following article data: ".PHP_EOL;
print_r(value: $articleData);
echo PHP_EOL;

$botName = $articleData['bot-name'];
$envVar = $articleData['npub']['environment-variable-name'];
$npubName = $articleData['npub']['npub'];
$tags= $articleData['tags'];
print_r(value: $tags);
// add d-tag to tags, and reformat for the note
$noteTitle = $tags[0][1];
$dTag = strtolower(string: str_replace(search: " ", replace: "-", subject: $noteTitle."-".(string)time()));
$dTags = ["d", $dTag];
$tags[] = $dTags;

echo "The full tags are: ".PHP_EOL;
print_r(value: $tags);
echo PHP_EOL;

// get article content from the markdown file

$articleContent = file_get_contents(filename: __DIR__ . "/../botData/".$settings[0]."/article_content.md");
    if (!$articleContent){
      echo "The article_content.md file in the ".$settings[0]." folder could not be read. Aborting.".PHP_EOL;
      exit(125);
    }

echo "You have requested the following article content: ".PHP_EOL;
print_r(value: $articleContent);
echo PHP_EOL.PHP_EOL;

$kind = 30023;
$note = new Event();
$note->setKind(kind: $kind);
$note->setContent(content: $articleContent);
$note->setTags(tags: $tags);

$checkResult = check_var_set(envVariable: $envVar);
if(!$checkResult) exit(125);

$keyTest = test_keys(envVariable: $envVar, npub: $npubName);
if(!$keyTest) exit(125);

$private_key = getenv(name: $envVar);
$keySet = get_key_set(hexPrivateKey: $private_key);
$bechPublicKey = $keySet['bechPublicKey'];
$hexPublicKey = $keySet['hexPublicKey'];
echo "You are using the following npub: ".$bechPublicKey.PHP_EOL.PHP_EOL;

$signer = new Sign();
$signer->signEvent(event: $note, private_key: $private_key);

$eventMessage = new EventMessage(event: $note);
var_dump($eventMessage);

// TODO: handle relay array with AUTH
$relayUrl = 'wss://thecitadel.nostr1.com';
$relay = new Relay(websocket: $relayUrl);

$relay->setMessage(message: $eventMessage);
$result = $relay->send();

// write completion notice to log
echo "The <".$botName."> bot has published the event with ID " . $note->getid() .PHP_EOL.PHP_EOL;

// create link to website, to view the note

$dTag = $dTags[1];
$naddr = shell_exec(command: 'nak encode naddr -d '. $dTag . ' --author '.$hexPublicKey.' --kind '.(string)$kind.' --relay '.$relays[0]);
echo "The message can be viewed at https://njump.me/".$naddr;

$notification = $settings[1];
// send a notification, where applicable
if($notification==='on'){

  $notificationKind = 1111;
  $notification = new Event();
  $notification->setKind(kind: $notificationKind);
  $notification->setTags(tags: [
    ["A", $naddr, $relays[0]], 
    ["K", $notificationKind], 
    ["a", $naddr, $relays[0]],
    ["k", $notificationKind]
  ]);
  $notification->setContent(content: 
    "A new article has been posted:\nnostr:".$naddr);

  $signer = new Sign();
  $signer->signEvent(event: $notification, private_key: $private_key);

  $eventMessage = new EventMessage(event: $notification);
  $relay = new Relay(websocket: $relayUrl);

  $relay->setMessage(message: $eventMessage);
  $result = $relay->send();

  echo PHP_EOL."The <".$botName."> bot has published the notification with ID " . $notification->getid() .PHP_EOL.PHP_EOL;
}

echo "Finished.".PHP_EOL.PHP_EOL;

exit(0);