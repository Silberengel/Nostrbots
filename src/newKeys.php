<?php

require_once 'bootstrap.php';
use function nostrbots\utilities\get_key_set;

$keys = get_key_set();
print_r($keys);