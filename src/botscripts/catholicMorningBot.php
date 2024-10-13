<?php

require __DIR__ . '/../bootstrap.php';
use function nostrbots\botscripts\setup\test_keys;
use swentel\nostr\Event\Event;
use swentel\nostr\Sign\Sign;
use swentel\nostr\Message\EventMessage;
use swentel\nostr\Relay\Relay;

// make sure you have a key for npub1dy6trd...
test_keys(env: 'NOSTR_BOT_KEY2', npub: "npub1dy6trdf8pvgt72h2xap9xsu79l0792urjulwktlnfhq4kahgfqgsa8jjcc");
$private_key = getenv(name: 'NOSTR_BOT_KEY2');

date_default_timezone_set(timezoneId: 'Europe/Berlin');
$date = new DateTimeImmutable();
$date = $date->format('l jS \o\f F Y h:i:s A');

$note = new Event();
$note->setKind(kind: 30023);
$note->setContent(content: 'Catholic morning hours test.');
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
return;