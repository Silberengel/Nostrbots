<?php

require_once __DIR__ . '/../bootstrap.php';
use function nostrbots\botscripts\setup\test_keys;

// make sure you have a key for npub1dy6trd...
test_keys('NOSTR_BOT_KEY2', "npub1dy6trdf8pvgt72h2xap9xsu79l0792urjulwktlnfhq4kahgfqgsa8jjcc");

// test-run the relay list.

// write completetion notice to log
file_put_contents("log.txt", 
     'The catholic evening bot has run successfully on npub1dy6trd...'.PHP_EOL, 
     FILE_APPEND);

return;