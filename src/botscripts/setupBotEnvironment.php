<?php

require_once __DIR__ . '/../bootstrap.php';
use function nostrbots\utilities\check_var_set;
use function nostrbots\utilities\get_key_set;
use function nostrbots\utilities\print_npub;

function test_keys($env){

    $key = getenv('NOSTR_BOT_KEY1');

    if(!$botKeySet=check_var_set('NOSTR_BOT_KEY1')){
        throw new Exception('The hex private key has not been set as. Aborting.');
    }

    // print out the npub that will be used.
    $keys = get_key_set($key);
    print_npub($keys);
    echo PHP_EOL;

}
