<?php

require __DIR__ . '/../bootstrap.php';
use function nostrbots\botscripts\setup\test_keys;
use swentel\nostr\Event\Event;
use swentel\nostr\Sign\Sign;
use swentel\nostr\Message\EventMessage;
use swentel\nostr\Relay\Relay;

// make sure you have a key for npub1r0r9c...
test_keys(env: 'NOSTR_BOT_KEY1', npub: 'npub1r0r9c7upagp9s5vmxqkcjymj4mqwqw2g8m029j7pgthr2u2yl5dsn9a3r6');
$private_key = getenv(name: 'NOSTR_BOT_KEY1');

date_default_timezone_set(timezoneId: 'Europe/Berlin');
$date = new DateTimeImmutable();
$date = $date->format('l jS \o\f F Y h:i:s A');

$note = new Event();
$note->setKind(kind: 30023);
$note->setContent(content: 'Market update test.');
$note->setTags(tags: [
    ['d', "finance-update-".strval(value: time())],
    ['title', "Finance Update"],
    ['summary', "The state of the markets on Monday morning at ".$date."."],
    ['t', "economics"],
    ['image', "https://i.nostr.build/GKZXx1cFV5gnsUFH.jpg"]
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
echo "The Monday morning finance bot has run successfully on npub1r0r9c...".PHP_EOL;
return;