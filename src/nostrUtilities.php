<?php

namespace nostrbots\utilities;

require_once 'bootstrap.php';
use swentel\nostr\Key\Key;

/** Returns list of relays from relays.yml file. 
 *  Either all relays (default) or only a specified category.
 **/
function get_relay_list(string $category="all"): array|bool {

    // read in the relays 
    $relays = yaml_parse_file("relays.yml");
    if ($relays === FALSE) return FALSE;
    if (!is_array($relays)) return FALSE;

    // read in bool to determine whether to use the relay list (default) or whether to search all relays
    if($category !=="all" && array_key_exists($category, $relays)){
            $relays = $relays[$category];
            return $relays;
    }

    if($category !=="all" && !array_key_exists($category, $relays)){
        echo "The relay category you entered does not exist. Defaulting to full list.".PHP_EOL;
    }

    echo "You have requested to use all of your relays.".PHP_EOL;
    $relays = array_reduce($relays, 'array_merge', array());
    if (is_null($relays)) return FALSE;
    return $relays;

}

/** Check if an environment variable has been set. 
 *  Return TRUE if it has been set and is not an empty string. 
**/
function check_var_set(string $envVariable): bool {

    if (getenv($envVariable) === FALSE){
        echo "The ".$envVariable." environment variable has not been set.".PHP_EOL;
        return FALSE;
    }
    if (getenv($envVariable) === ""){
        echo "The ".$envVariable." environment variable is set as an empty string.".PHP_EOL;
        return FALSE;
    }
    echo "The ".$envVariable." environment variable has been set.".PHP_EOL;
    return TRUE;

}
/** Returns a keyset (hex private/public, Bech32 private/public) for a private hex key.
 * If no key is provided, the function generates one.
 * Returns FALSE, if a nsec is provided. 
**/ 
function get_key_set(string $privateKey="new"): array|bool {

    If(str_starts_with($privateKey, 'nsec')){
        echo "Please only submit hex private keys.";
        return FALSE;
    } 

    $hexPrivateKey = new Key();

    If ($privateKey==="new"){
        // generate a new private key in hex format
        $hexPrivateKey = $hexPrivateKey->generatePrivateKey();
    } else $hexPrivateKey = $privateKey;

    // get your public key in hex format from the hex private key
    $hexPublicKey = new Key();
    $hexPublicKey = $hexPublicKey->getPublicKey($hexPrivateKey);

    // get your private key in Bech32 format from the hex private key
    $bechPrivateKey = new Key();
    $bechPrivateKey = $bechPrivateKey->convertPrivateKeyToBech32($hexPrivateKey);

    // get your public key in Bech32 format from the hex public key
    $bechPublicKey = new Key();
    $bechPublicKey = $bechPublicKey->convertPublicKeyToBech32($hexPublicKey);

    $keySet = [
        "hexPrivateKey" => $hexPrivateKey,
        "hexPublicKey" => $hexPublicKey,
        "bechPrivateKey" => $bechPrivateKey,
        "bechPublicKey" => $bechPublicKey,
    ];

    return $keySet;

}