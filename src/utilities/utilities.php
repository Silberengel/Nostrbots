<?php

require_once __DIR__ . '/bootstrap.php';
use swentel\nostr\Key\Key;
use swentel\nostr\Subscription\Subscription;
use swentel\nostr\Relay\Relay;
use swentel\nostr\Filter\Filter;
use swentel\nostr\Request\Request;
use swentel\nostr\Message\RequestMessage;



/**
 * Store the command-line arguments for later processing.
 * The arguments must follow the following order: [folder, notifications, relays].
 */
function get_bot_settings(array $args, int $argcount): array{

    // make sure they entered a plausible argument for the botfolder
    if($argcount < 2){
        throw new Exception(
          message: "You must name the folder, inside of /botData, that contains your bot info.");
    }

    if(!is_dir(filename: __DIR__."/../../botData/".$args[1])){
        throw new Exception("That is not a valid botData folder name.");
    }

    $botSettings = [];

    // set the folder as the first argument
    $botSettings[] = $args[1];

    // set the defaults, for the remainder of parameters, if only the folder is supplied.
    if($argcount==2){

        $botSettings[] = "off";
        $botSettings[] = "all";

        return $botSettings;

    }

    // if more than the folder is supplied as an argument

    // set the notifications
    if($argcount >= 3){

        if($args[2]=="note=true"){

            $botSettings[] = "on";
        
        } elseif ($args[2]=="note=false"){

            $botSettings[] = "off";
        
        } else throw new Exception("Please enter a third argument note=true or note=false.");

        // set the relays, where applicable
        if($argcount == 4){

            $botSettings[] = $args[3];

        } else $botSettings[] = "all";
    }

   return $botSettings;

}

/**
 * Test the keys supplied by the article_data.yml file.
 * Returns FALSE, if the private key and the npub don't match.
 */
function test_keys(string $envVariable, string $npub): bool{

    //
    $key = getenv(name: $envVariable);
    $keySet = get_key_set(hexPrivateKey: $key);
    
    if($keySet[0]===$npub) return TRUE;

    return FALSE;
}

/**
 * Tries a relay websocket and returns a PASS/FAIL,
 * depending upon whether the relay returns a filter result.
 */
function test_relays(string $relayUrl): bool {

    // open a subscription to the relay

    $subscription = new Subscription();
    $subscriptionId = $subscription->setId();
    
    // see if you can request related information from the relay

    $filter1 = new Filter();
    $filter1->setKinds(kinds: [1, 3, 5, 7, 1111, 30040, 30041, 30023, 30818]); // You can add multiple kind numbers
    $filter1->setLimit(limit: 1); // Limit to fetch only a maximum of 25 events
    $filters = [$filter1];
    
    $requestMessage = new RequestMessage(subscriptionId: $subscriptionId, filters: $filters);
    
    $relay = new Relay(websocket: $relayUrl);
    $relay->setMessage(message: $requestMessage);
    
    $request = new Request(relay: $relay, message: $requestMessage);

    echo PHP_EOL;
    try{
        $response = $request->send();
    }catch(Exception $e){
        echo $e->getMessage() . PHP_EOL;
        echo "The relay ". $relayUrl. " failed the test, with an exception, and will be removed from the list".PHP_EOL;
        return FALSE;
    }

    //TODO: clean this mess up. Should be able to extract the value from the object property.
    // Need to figure out how to handle AUTH.
    if(str_contains(haystack: json_encode(value: $response), needle: "\"isSuccess\":true")) {
        echo "The relay ". $relayUrl. " passed the test.".PHP_EOL;
        return TRUE;
    }
    echo "The relay ". $relayUrl. " failed the test and will be removed from the list".PHP_EOL;
    return FALSE;
}

/** 
 * Returns list of relays from relays.yml file. 
 * Either all relays (default) or only a specified category.
 * If no relays are found, wss://thecitadel.nostr1.com will be used.
 **/
function get_relay_list(string $category): array {

    $relays=[];

    // read in the relays
    $relays = yaml_parse_file(__DIR__ . "/../relays.yml");
    if ((!is_array(value: $relays)) || ($relays === FALSE)){
        echo "The relay list could not be read. Defaulting to the hardcoded relay.".PHP_EOL;
        $relays = get_hardcoded_relay();
        return $relays;
    }

    // if the user requested a particular category of relays

    // if category found
    if($category !=="all" && array_key_exists(key: $category, array: $relays)){
            $relays = $relays[$category];
            return $relays;
    }

    // if category not found, fall-through to full list
    if($category !=="all" && !array_key_exists(key: $category, array: $relays)){
        echo "The relay category you entered does not exist.".PHP_EOL;
    }

    echo "Defaulting to full list of relays.".PHP_EOL;
    $relays = array_reduce(array: $relays, callback: 'array_merge', initial: array());
    
    if (is_null(value: $relays)) {
        echo "The relay list is empty. Defaulting to the hardcoded relay.".PHP_EOL;
        get_hardcoded_relay();
    }
    
    return $relays;

}

/**
 * Fallback function, if the provided relay information is faulty or missing.
 * Returns array containing only thecitadel relay.
 */
function get_hardcoded_relay():array{

    $hardcodedRelay = "wss://thecitadel.nostr1.com";
    
    echo "There were no relays found. Defaulting to ".$hardcodedRelay.PHP_EOL.PHP_EOL;

    $relays[] = $hardcodedRelay;
    
    return $relays;

}

/** 
 *  Check if an environment variable has been set. 
 *  Return TRUE if it has been set and is not an empty string. 
**/
function check_var_set(string $envVariable): bool {

    if (getenv(name: $envVariable) === FALSE){
        echo "The ".$envVariable." environment variable has not been set.".PHP_EOL;
        return FALSE;
    }
    if (getenv(name: $envVariable) === ""){
        echo "The ".$envVariable." environment variable is set as an empty string.".PHP_EOL;
        return FALSE;
    }
    echo "The ".$envVariable." environment variable has been set.".PHP_EOL;
    return TRUE;

}

/**
 * Takes a keyset array and prints the npub inside of it.
 */
function print_npub(array $keyset): void{

    if ($keyset){
    
        $currentNpub = array_pop(array: $keyset);
        print_r(value: $currentNpub);
    
    } else print "There is no npub defined.";

}

/** 
 * 
 *  Returns a full keyset for a private hex key.
 *  Returns FALSE, if a nsec is provided.
 *  If no key is provided, one is generated.
 *  
 *  The keyset is:
 *  [0] hex private key
 *  [1] hex public key
 *  [2] nsec
 *  [3] npub
 * 
**/ 
function get_key_set(string $hexPrivateKey='none'): array|bool {

    If(str_starts_with(haystack: $hexPrivateKey, needle: 'nsec')){
        echo "Please only submit hex private keys.";
        return FALSE;
    } 

    If($hexPrivateKey=="none"){
        $hexPrivateKey = get_new_key();
    }

    // get your public key in hex format from the hex private key
    $hexPublicKey = new Key();
    $hexPublicKey = $hexPublicKey->getPublicKey(private_hex: $hexPrivateKey);

    // get your private key in Bech32 format from the hex private key
    $bechPrivateKey = new Key();
    $bechPrivateKey = $bechPrivateKey->convertPrivateKeyToBech32(key: $hexPrivateKey);

    // get your public key in Bech32 format from the hex public key
    $bechPublicKey = new Key();
    $bechPublicKey = $bechPublicKey->convertPublicKeyToBech32(key: $hexPublicKey);

    $keySet = [
        "hexPrivateKey" => $hexPrivateKey,
        "hexPublicKey" => $hexPublicKey,
        "bechPrivateKey" => $bechPrivateKey,
        "bechPublicKey" => $bechPublicKey,
    ];

    return $keySet;

}

/** 
 * Returns a new hex private key. 
**/ 
function get_new_key(): string {

    $hexPrivateKey = new Key();
    return $hexPrivateKey->generatePrivateKey();

}