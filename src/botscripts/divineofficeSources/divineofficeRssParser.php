<?php

// Feed-URL des RSS-Feeds
$url = 'https://divineoffice.org/feed/';
$rssFile = __DIR__ . '/rss.md';

$rss = new DOMDocument();
$rss->load($url);
$feed = array();

foreach ($rss->getElementsByTagName('item') as $node) {

    $readingDate = $node->getElementsByTagName('title')->item(0)->nodeValue;
    $readingDate = strtok($readingDate, ',').", 2024";
    $readingDate = strtotime($readingDate);
    $readingDate = date("Ymd", $readingDate);
    
    $content = $node->getElementsByTagName('encoded')->item(0)->nodeValue;
    $content = trim($content);
    $content = str_replace("<p>[", "", $content);
    $content = str_replace("]</p>", "\n", $content);
    $content = str_replace("&#8220;", "“", $content);
    $content = str_replace("&#8221;", "”", $content);
    $content = str_replace("&#8230;", "…", $content);
    $content = str_replace("&#8212;", "——", $content);
    $content = str_replace("&#8211;", "–", $content);
    $content = str_replace("&#119070;", "MUSIC CREDITS: ", $content);
    
    // change formatting to markdown
    $content = str_replace("Canticle – ", "Canticle\n", $content);
    $content = str_replace("Refrain:", "*Refrain:*", $content);
    $content = str_replace("<span style=\"color: #000000;\"><em> ", "*", $content);
    $content = str_replace("</em> (", "* (", $content);
    $content = str_replace("<span style=\"color: #ff0000;\">——</span>", "——", $content);
    $content = str_replace("<span style=\"color: #ff0000;\">", "\n## ", $content);
    $content = str_replace("</span> ", "\n", $content);
    $content = str_replace("</span>", "\n", $content);
    $content = str_replace("</p>", "\n", $content);
    $content = str_replace("&bull;", "•", $content);
    $content = str_replace("&#8217;", "’", $content);
    $content = str_replace("&#8216;", "‘", $content);
    $content = strip_tags($content);

    $item = array (
            'title' => $node->getElementsByTagName('title')->item(0)->nodeValue,
            'readingDate' => $readingDate,
            'link' => $node->getElementsByTagName('link')->item(0)->nodeValue."?date=".$readingDate,
            'content' => $content
    );
    array_push($feed, $item);
}

foreach ($feed as $f){
    if (($key = array_search("Array", $f)) !== false) {
        unset($f[$key]);
    }
}

$fp = fopen($rssFile, 'w');
fwrite($fp, print_r($feed, TRUE));
fclose($fp);

//?date=20241017