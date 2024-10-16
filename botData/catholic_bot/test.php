<?php

$url = file_get_contents("https://divineoffice.org/ord-mon-np-w2-w4/?date=20241014");

$url = strip_tags($url);

file_put_contents("test.txt", $url);