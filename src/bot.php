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

// Check if desired relays are active.
// Remove inactive relays.
// If all relays removed, default to hardcoded relay.
// If that is also inactive, abort.

$webserver = $settings[2];

  if(str_starts_with(haystack: $settings[2], needle: "wss://" or "ws://")){
  
    $result = test_relays( relayUrl: $webserver);

  }else {
  
    $relays = get_relay_list(category: $settings[2]);
  
    foreach($relays as $r){

      $result = test_relays( relayUrl: $r);
      die;

    }
  
  }

$note = new Event();
$note->setKind(kind: 30023);
$note->setContent(content: 'Catholic morning hours test. #biblestr');
$note->setTags(tags: [
    ['d', "catholic-hours-".strval(value: time())],
    ['title', "Liturgy of the Hours"],
    ['summary', "The morning liturgy of the Roman Rite for ".$date],
    ['t', "religion", "Catholicism", "Bible"],
    ['image', "https://i.nostr.build/XxelMbkBMNsRIm5H.jpg"]
  ]);

print_r(value: $note);

$signer = new Sign();
$signer->signEvent(event: $note, private_key: $private_key);

$eventMessage = new EventMessage(event: $note);

$relayUrl = 'wss://thecitadel.nostr1.com';
$relay = new Relay(websocket: $relayUrl);
print_r(value: $eventMessage);

$relay->setMessage(message: $eventMessage);
$result = $relay->send();

print_r(value: $result);

// write completetion notice to log
echo "The catholic morning bot has run successfully on npub1dy6trd...".PHP_EOL;
//print naddr components, naddr, and then njump link to naddr
//nak encode naddr -d <d tag> --author <pubkey-hex> --kind 30023 --relay <relay-url> --relay <other-relay>
return;

// add published_at