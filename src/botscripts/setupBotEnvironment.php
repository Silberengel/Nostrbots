<?php

namespace nostrbots\botscripts\setup;

require_once __DIR__ . '/../bootstrap.php';
use function nostrbots\utilities\check_var_set;
use function nostrbots\utilities\get_key_set;
use function nostrbots\utilities\print_npub;

/**
 * Compares the content of the env variable with the desired npub.
 */
function test_keys(string $env, string $npub): bool{

    $key = getenv($env);

    if(!$botKeySet=check_var_set($env)){
        return FALSE;
    }

    // print out the npub that will be used.
    $keys = get_key_set($key);
    
    if($keys===$npub){
        print_npub($keys);
        echo PHP_EOL;
        return TRUE;
    }

    return FALSE;
}
