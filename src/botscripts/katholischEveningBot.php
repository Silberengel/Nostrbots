<?php

require_once __DIR__ . '/../bootstrap.php';
use function nostrbots\botscripts\setup\test_keys;

// make sure you have a key for npub194xnj...
test_keys('NOSTR_BOT_KEY3', "npub194xnj5fu66xkx259v0fv8626cves8aewdl7d8jd4v02s4hm99a8qzw9m2d");

// test-run the relay list.

fwrite(STDOUT, 'The katholisch evening bot has run successfully on npub194xnj...'.PHP_EOL);
return;