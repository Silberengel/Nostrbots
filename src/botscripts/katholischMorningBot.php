<?php

require __DIR__ . '/../bootstrap.php';
use function nostrbots\botscripts\setup\test_keys;
use swentel\nostr\Event\Event;
use swentel\nostr\Sign\Sign;
use swentel\nostr\Message\EventMessage;
use swentel\nostr\Relay\Relay;

// make sure you have a key for npub194xnj...
test_keys(env: 'NOSTR_BOT_KEY3', npub: "npub194xnj5fu66xkx259v0fv8626cves8aewdl7d8jd4v02s4hm99a8qzw9m2d");
$private_key = getenv(name: 'NOSTR_BOT_KEY3');

date_default_timezone_set(timezoneId: 'Europe/Berlin');
$date = new DateTimeImmutable();
$date = $date->format('d.m.Y H:m');

$note = new Event();
$note->setKind(kind: 30023);
$note->setContent(content: 'Katholisches Stundengebet Test. #biblestr');
$note->setTags(tags: [
    ['d', "katholisches-stundengebet-".strval(value: time())],
    ['title', "Stundengebet der katholischen Kirche"],
    ['summary', "Morgendliche Liturgie des römischen Ritus für ".$date."."],
    ['t', "Religion", "katholisch", "Bibel"],
    ['image', "https:S//i.nostr.build/hE82Q7iisbNGKQP2.png"]
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
echo "The katholisch morning bot has run successfully on npub194xnj...".PHP_EOL;
return;