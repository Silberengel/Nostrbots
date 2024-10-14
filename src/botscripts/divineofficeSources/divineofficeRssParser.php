<?php

// Feed-URL des RSS-Feeds
$url = 'https://divineoffice.org/feed/';
$rssFile = __DIR__ . '/rss.txt';

$rss = new DOMDocument();
$rss->load($url);
$feed = array();

foreach ($rss->getElementsByTagName('item') as $node) {

    $readingDate = $node->getElementsByTagName('title')->item(0)->nodeValue;
    $readingDate = strtok($readingDate, ',').", 2024";
    $readingDate = strtotime($readingDate);
    $readingDate = date("Ymd", $readingDate);

    $readingType = $node->getElementsByTagName('title')->item(0)->nodeValue;
    $readingType = substring_between($readingType, ", ", " for ");

    $item = array (
            'title' => $node->getElementsByTagName('title')->item(0)->nodeValue,
            'readingDate' => $readingDate,
            'readingType' => $readingType,
            'link' => $node->getElementsByTagName('link')->item(0)->nodeValue."?date=".$readingDate
    );
    array_push($feed, $item);
}

$fp = fopen($rssFile, 'w');
fwrite($fp, print_r($feed, TRUE));
fclose($fp);

//?date=20241017

function substring_between($string, $start, $end) {
    $startPos = strpos($string, $start);
    if ($startPos === false) {
        return ""; // Start string not found
    }
    
    $endPos = strpos($string, $end, $startPos + strlen($start));
    if ($endPos === false) {
        return ""; // End string not found
    }
    
    // Calculate the length of the substring to extract
    $length = $endPos - ($startPos + strlen($start));
    
    // Extract and return the substring
    return substr($string, $startPos + strlen($start), $length);
}