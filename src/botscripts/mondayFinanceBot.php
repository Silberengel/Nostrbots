<?php

require __DIR__ . '/../bootstrap.php';
use function nostrbots\botscripts\setup\test_keys;

// make sure you have a key for npub1r0r9c...
test_keys('NOSTR_BOT_KEY1', 'npub1r0r9c7upagp9s5vmxqkcjymj4mqwqw2g8m029j7pgthr2u2yl5dsn9a3r6');

// test-run the relay list.

// write completetion notice to log
echo "The Monday morning finance bot has run successfully on npub1r0r9c...".PHP_EOL;
return;