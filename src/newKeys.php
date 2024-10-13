<?php

require_once 'bootstrap.php';
use function nostrbots\utilities\get_new_key_set;

$keys = get_new_key_set();
print_r($keys);