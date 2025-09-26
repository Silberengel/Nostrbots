<?php

/**
 * Daily Office Content Generator
 * 
 * Generates Catholic Daily Office readings for 6am and 6pm UTC
 * Based on the Roman Catholic liturgical calendar
 */

require_once __DIR__ . '/../../src/bootstrap.php';

use Nostrbots\Utils\ErrorHandler;

class DailyOfficeGenerator
{
    private string $botDir;
    private array $config;
    private ErrorHandler $errorHandler;
    
    public function __construct(string $botDir)
    {
        $this->botDir = $botDir;
        $this->config = $this->loadConfig();
        $this->errorHandler = new ErrorHandler(true);
    }
    
    private function loadConfig(): array
    {
        $configFile = $this->botDir . '/config.json';
        if (!file_exists($configFile)) {
            throw new \Exception("Configuration file not found: $configFile");
        }
        
        $config = json_decode(file_get_contents($configFile), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("Invalid JSON in configuration file: " . json_last_error_msg());
        }
        
        return $config;
    }
    
    public function generateContent(): void
    {
        $currentTime = new DateTime('now', new DateTimeZone('UTC'));
        $hour = (int)$currentTime->format('H');
        
        // Determine if this is morning (6am) or evening (6pm)
        $isMorning = $hour === 6;
        $isEvening = $hour === 18;
        
        if (!$isMorning && !$isEvening) {
            throw new \Exception("Daily Office should only run at 6am or 6pm UTC");
        }
        
        $officeType = $isMorning ? 'morning' : 'evening';
        $date = $currentTime->format('Y-m-d');
        $time = $currentTime->format('H:i');
        
        echo "📅 Generating $officeType office for $date at $time UTC\n";
        
        // Get liturgical information
        $liturgicalInfo = $this->getLiturgicalInfo($currentTime);
        
        // Generate the content
        $content = $this->generateOfficeContent($officeType, $liturgicalInfo, $currentTime);
        
        // Save to output directory
        $this->saveContent($content, $date, $time, $officeType);
        
        echo "✓ Daily Office content generated successfully\n";
    }
    
    private function getLiturgicalInfo(DateTime $date): array
    {
        // This is a simplified liturgical calendar
        // In a real implementation, you'd use a proper liturgical calendar library
        
        $year = (int)$date->format('Y');
        $month = (int)$date->format('n');
        $day = (int)$date->format('j');
        
        // Basic liturgical seasons (simplified)
        $seasons = [
            'Advent' => ['start' => [12, 1], 'end' => [12, 24]],
            'Christmas' => ['start' => [12, 25], 'end' => [1, 6]],
            'Ordinary Time' => ['start' => [1, 7], 'end' => [3, 5]], // Before Lent
            'Lent' => ['start' => [3, 6], 'end' => [4, 16]], // Approximate
            'Easter' => ['start' => [4, 17], 'end' => [5, 28]], // Approximate
            'Ordinary Time 2' => ['start' => [5, 29], 'end' => [11, 30]]
        ];
        
        $currentSeason = 'Ordinary Time';
        foreach ($seasons as $season => $dates) {
            if (($month > $dates['start'][0] || ($month === $dates['start'][0] && $day >= $dates['start'][1])) &&
                ($month < $dates['end'][0] || ($month === $dates['end'][0] && $day <= $dates['end'][1]))) {
                $currentSeason = $season;
                break;
            }
        }
        
        return [
            'season' => $currentSeason,
            'date' => $date->format('l, F j, Y'),
            'liturgical_date' => $this->getLiturgicalDate($date),
            'color' => $this->getLiturgicalColor($currentSeason)
        ];
    }
    
    private function getLiturgicalDate(DateTime $date): string
    {
        // Simplified liturgical date calculation
        $dayOfWeek = $date->format('l');
        $month = $date->format('F');
        $day = $date->format('j');
        
        return "$dayOfWeek of the $month $day";
    }
    
    private function getLiturgicalColor(string $season): string
    {
        $colors = [
            'Advent' => 'Purple',
            'Christmas' => 'White',
            'Ordinary Time' => 'Green',
            'Lent' => 'Purple',
            'Easter' => 'White'
        ];
        
        return $colors[$season] ?? 'Green';
        }
    
    private function generateOfficeContent(string $officeType, array $liturgicalInfo, DateTime $date): string
    {
        $title = $officeType === 'morning' ? 'Morning Prayer' : 'Evening Prayer';
        $time = $officeType === 'morning' ? '6:00 AM' : '6:00 PM';
        
        $content = "= $title - {$liturgicalInfo['date']}\n";
        $content .= "author: Daily Office Bot\n";
        $content .= "version: 1.0\n";
        $content .= "relays: daily-office-relays\n";
        $content .= "auto_update: true\n";
        $content .= "summary: Catholic Daily Office - $title for {$liturgicalInfo['date']}\n";
        $content .= "type: prayer\n";
        $content .= "liturgical_season: {$liturgicalInfo['season']}\n";
        $content .= "liturgical_color: {$liturgicalInfo['color']}\n";
        $content .= "prayer_time: $time UTC\n\n";
        
        $content .= "**{$liturgicalInfo['liturgical_date']}**\n";
        $content .= "*Liturgical Season: {$liturgicalInfo['season']}*\n";
        $content .= "*Liturgical Color: {$liturgicalInfo['color']}*\n\n";
        
        if ($officeType === 'morning') {
            $content .= $this->generateMorningOffice($liturgicalInfo);
        } else {
            $content .= $this->generateEveningOffice($liturgicalInfo);
        }
        
        return $content;
    }
    
    private function generateMorningOffice(array $liturgicalInfo): string
    {
        $content = "== Opening Prayer\n\n";
        $content .= "O God, come to my assistance.\n";
        $content .= "O Lord, make haste to help me.\n\n";
        
        $content .= "Glory to the Father, and to the Son, and to the Holy Spirit,\n";
        $content .= "as it was in the beginning, is now, and will be for ever. Amen.\n\n";
        
        $content .= "== Psalm of the Day\n\n";
        $content .= $this->getDailyPsalm() . "\n\n";
        
        $content .= "== Scripture Reading\n\n";
        $content .= $this->getDailyReading() . "\n\n";
        
        $content .= "== Intercessions\n\n";
        $content .= "Let us pray to the Lord, who is our light and our salvation:\n\n";
        $content .= "- For the Church, that she may be a beacon of hope in the world\n";
        $content .= "- For all who are suffering, that they may find comfort and strength\n";
        $content .= "- For our families and communities, that we may grow in love and unity\n";
        $content .= "- For peace in our world, that all conflicts may be resolved through justice\n\n";
        
        $content .= "== Closing Prayer\n\n";
        $content .= "Almighty and eternal God,\n";
        $content .= "you have brought us safely to this new day.\n";
        $content .= "Preserve us now by your mighty power,\n";
        $content .= "that we may not fall into sin,\n";
        $content .= "nor be overcome by adversity;\n";
        $content .= "and in all we do,\n";
        $content .= "direct us to the fulfilling of your purpose;\n";
        $content .= "through Jesus Christ our Lord. Amen.\n\n";
        
        $content .= "== Blessing\n\n";
        $content .= "May the Lord bless us, protect us from all evil,\n";
        $content .= "and bring us to everlasting life. Amen.\n";
        
        return $content;
    }
    
    private function generateEveningOffice(array $liturgicalInfo): string
    {
        $content = "== Opening Prayer\n\n";
        $content .= "O God, come to my assistance.\n";
        $content .= "O Lord, make haste to help me.\n\n";
        
        $content .= "Glory to the Father, and to the Son, and to the Holy Spirit,\n";
        $content .= "as it was in the beginning, is now, and will be for ever. Amen.\n\n";
        
        $content .= "== Psalm of Thanksgiving\n\n";
        $content .= $this->getEveningPsalm() . "\n\n";
        
        $content .= "== Scripture Reading\n\n";
        $content .= $this->getEveningReading() . "\n\n";
        
        $content .= "== Magnificat\n\n";
        $content .= "My soul proclaims the greatness of the Lord,\n";
        $content .= "my spirit rejoices in God my Savior,\n";
        $content .= "for he has looked with favor on his lowly servant.\n\n";
        $content .= "From this day all generations will call me blessed:\n";
        $content .= "the Almighty has done great things for me,\n";
        $content .= "and holy is his Name.\n\n";
        $content .= "He has mercy on those who fear him\n";
        $content .= "in every generation.\n";
        $content .= "He has shown the strength of his arm,\n";
        $content .= "he has scattered the proud in their conceit.\n\n";
        $content .= "He has cast down the mighty from their thrones,\n";
        $content .= "and has lifted up the lowly.\n";
        $content .= "He has filled the hungry with good things,\n";
        $content .= "and the rich he has sent away empty.\n\n";
        $content .= "He has come to the help of his servant Israel\n";
        $content .= "for he has remembered his promise of mercy,\n";
        $content .= "the promise he made to our fathers,\n";
        $content .= "to Abraham and his children for ever.\n\n";
        $content .= "Glory to the Father, and to the Son, and to the Holy Spirit,\n";
        $content .= "as it was in the beginning, is now, and will be for ever. Amen.\n\n";
        
        $content .= "== Intercessions\n\n";
        $content .= "Let us pray to the Lord, who is our light and our salvation:\n\n";
        $content .= "- For all who have died today, that they may rest in peace\n";
        $content .= "- For our families and loved ones, that they may be protected through the night\n";
        $content .= "- For those who work through the night, that they may be safe and blessed\n";
        $content .= "- For the Church throughout the world, that she may be a sign of hope\n\n";
        
        $content .= "== Closing Prayer\n\n";
        $content .= "Lord, as we prepare for rest,\n";
        $content .= "we thank you for the blessings of this day.\n";
        $content .= "Watch over us through the night,\n";
        $content .= "and grant us peaceful sleep.\n";
        $content .= "May we wake refreshed and ready\n";
        $content .= "to serve you in the new day;\n";
        $content .= "through Jesus Christ our Lord. Amen.\n\n";
        
        $content .= "== Blessing\n\n";
        $content .= "May the Lord grant us a peaceful night\n";
        $content .= "and a perfect end. Amen.\n";
        
        return $content;
    }
    
    private function getDailyPsalm(): string
    {
        $psalms = [
            "Psalm 95: Come, let us sing to the Lord; let us shout for joy to the Rock of our salvation.",
            "Psalm 100: Make a joyful noise to the Lord, all the earth. Worship the Lord with gladness.",
            "Psalm 63: O God, you are my God, I seek you, my soul thirsts for you.",
            "Psalm 23: The Lord is my shepherd, I shall not want.",
            "Psalm 46: God is our refuge and strength, a very present help in trouble."
        ];
        
        return $psalms[array_rand($psalms)];
    }
    
    private function getEveningPsalm(): string
    {
        $psalms = [
            "Psalm 141: Let my prayer be counted as incense before you, and the lifting up of my hands as an evening sacrifice.",
            "Psalm 134: Come, bless the Lord, all you servants of the Lord, who stand by night in the house of the Lord.",
            "Psalm 4: Answer me when I call, O God of my right! You gave me room when I was in distress.",
            "Psalm 91: You who live in the shelter of the Most High, who abide in the shadow of the Almighty."
        ];
        
        return $psalms[array_rand($psalms)];
    }
    
    private function getDailyReading(): string
    {
        $readings = [
            "Matthew 5:14-16: 'You are the light of the world. A city built on a hill cannot be hid.'",
            "John 8:12: 'I am the light of the world. Whoever follows me will never walk in darkness but will have the light of life.'",
            "Psalm 27:1: 'The Lord is my light and my salvation; whom shall I fear? The Lord is the stronghold of my life; of whom shall I be afraid?'",
            "Isaiah 60:1: 'Arise, shine; for your light has come, and the glory of the Lord has risen upon you.'"
        ];
        
        return $readings[array_rand($readings)];
    }
    
    private function getEveningReading(): string
    {
        $readings = [
            "Luke 24:29: 'Stay with us, because it is almost evening and the day is now nearly over.'",
            "Psalm 4:8: 'I will both lie down and sleep in peace; for you alone, O Lord, make me lie down in safety.'",
            "Mark 4:35-41: 'And he said to them, \"Why are you afraid? Have you still no faith?\"'",
            "Psalm 91:1-2: 'You who live in the shelter of the Most High, who abide in the shadow of the Almighty, will say to the Lord, \"My refuge and my fortress; my God, in whom I trust.\"'"
        ];
        
        return $readings[array_rand($readings)];
    }
    
    private function saveContent(string $content, string $date, string $time, string $officeType): void
    {
        $outputDir = $this->botDir . '/output';
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }
        
        $filename = "daily-office-{$date}-{$time}-{$officeType}.adoc";
        $filepath = $outputDir . '/' . $filename;
        
        if (file_put_contents($filepath, $content) === false) {
            throw new \Exception("Failed to save content to: $filepath");
        }
        
        echo "📄 Content saved to: $filename\n";
    }
}

// Main execution
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    try {
        $botDir = $argv[1] ?? __DIR__;
        $generator = new DailyOfficeGenerator($botDir);
        $generator->generateContent();
    } catch (Exception $e) {
        echo "✗ Error: " . $e->getMessage() . "\n";
        exit(1);
    }
}
