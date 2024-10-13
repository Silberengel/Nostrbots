<?php

require_once __DIR__ . '/../bootstrap.php';
use function nostrbots\botscripts\setup\test_keys;

// make sure you have a key for npub194xnj...
test_keys('NOSTR_BOT_KEY3', "npub194xnj5fu66xkx259v0fv8626cves8aewdl7d8jd4v02s4hm99a8qzw9m2d");

// test-run the relay list.

// write completetion notice to log
file_put_contents("log.txt", 
     'The katholisch morning bot has run successfully on npub194xnj...'.PHP_EOL, 
     FILE_APPEND);

return;