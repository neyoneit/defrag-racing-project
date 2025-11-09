<?php

namespace App\External;

use \DOMDocument;
use \DOMXPath;
use Carbon\Carbon;

class Q3DFRecords {
    protected $url = "https://q3df.org/records?page=";
    protected $xpath;

    public function scrape_until($start, $record) {
        $result = [];

        $page = $start;
        $found = false;

        while (! $found) {
            $records = $this->scrape($page);
            $page++;

            $found = $this->check_dates($record, $records) !== -1;

            usleep(500000); // 0.5 second

            $result = array_merge($result, $records);
        }

        return $result;
    }

    public function scrape_through($start, $pages) {
        $result = [];

        for($i = $start; $i <= $start + $pages; $i++) {
            $records = $this->scrape($i);
            $result = array_merge($result, $records);

            usleep(500000); // 0.5 second
        }

        return $result;
    }

    public function scrape($page, $maxRetries = 5, $baseDelay = 60) {
        echo 'Scraping page: ' . $page . PHP_EOL;

        $attempt = 0;
        $response = false;

        while ($attempt < $maxRetries && $response === false) {
            try {
                $response = @file_get_contents($this->url . $page);

                if ($response === false) {
                    throw new \Exception("Failed to fetch page");
                }
            } catch (\Exception $e) {
                $attempt++;

                if ($attempt >= $maxRetries) {
                    throw new \Exception("Failed to fetch page {$page} after {$maxRetries} attempts: " . $e->getMessage());
                }

                // Exponential backoff: 60s, 120s, 240s, 480s
                $delay = $baseDelay * pow(2, $attempt - 1);
                echo "  ⚠️  Connection failed (attempt {$attempt}/{$maxRetries}). Retrying in {$delay} seconds..." . PHP_EOL;
                sleep($delay);
            }
        }

        if ($response === false) {
            return [];
        }

        libxml_use_internal_errors(true);

        $dom = new DOMDocument();
        $dom->loadHTML($response);

        libxml_clear_errors();

        $this->xpath = new DOMXPath($dom);

        $recordsTable = $this->xpath->query('//table[contains(@class, "recordlist")]/tbody')->item(0);

        return $this->getRecords($recordsTable);
    }

    private function check_dates($record, $records) {
        foreach($records as $index => $searchRecord) {
            $searchRecordDate = Carbon::parse($searchRecord['date']);
            $recordDate = Carbon::parse($record->date_set);

            $interval = $searchRecordDate->diff($recordDate);

            if ($interval->days >= 1 && $interval->invert == 0) {
                return $index;
            }
        }

        return -1;
    }

    private function getRecords($recordsTable) {
        $records = [];

        if (!$recordsTable) {
            return $records;
        }

        $recordsParts = $recordsTable->getElementsByTagName('tr');

        foreach($recordsParts as $index => $recordPart) {
            try {
                $records[] = $this->getRecord($recordPart);
            } catch (\Exception $e) {
                // Skip malformed records but log the error
                echo "  ⚠️  Skipping malformed record at index {$index}: " . $e->getMessage() . PHP_EOL;
                continue;
            }
        }

        return $records;
    }

    private function getRecord($recordPart) {
        $parts = $recordPart->getElementsByTagName('td');

        // Verify we have all required elements (at least 6 columns)
        if ($parts->length < 6) {
            throw new \Exception("Invalid record structure: expected at least 6 columns, got {$parts->length}");
        }

        $dateElement = $parts->item(0);
        $playerElement = $parts->item(1);
        $timeElement = $parts->item(2);
        $mapElement = $parts->item(3);
        $physicsElement = $parts->item(5);

        if (!$dateElement || !$playerElement || !$timeElement || !$mapElement || !$physicsElement) {
            throw new \Exception("Invalid record structure: missing required elements");
        }

        $date = $this->parse_date($dateElement->textContent);

        $player = $this->get_player($playerElement);

        $time = $this->parse_time($timeElement->textContent);

        $map = trim($mapElement->textContent);

        $physics = $physicsElement->textContent;
        $physicsParts = explode('-', $physics);

        if (count($physicsParts) < 2) {
            throw new \Exception("Invalid physics format: {$physics}");
        }

        $player['time'] = $time;
        $player['map'] = $map;

        $player['physics'] = $physicsParts[0];
        $player['mode'] = $physicsParts[1];

        $player['date'] = $date->toDateTimeString();

        return $player;
    }

    private function parse_date($date) {
        $cleanedDate = str_replace(['th', 'st', 'nd', 'rd'], '', $date);

        $carbonDate = Carbon::createFromFormat('M d, \'y, H:i', $cleanedDate);

        return $carbonDate;
    }

    private function get_player($playerColumn) {
        // Get flag from player column
        $flag = $this->xpath->query('.//img[@class="flag"]', $playerColumn)->item(0);

        if (!$flag) {
            throw new \Exception("Missing flag element in player data");
        }

        $country = explode('.', basename($flag->getAttribute('src')))[0];

        if ($country === 'nocountry') {
            $country = '_404';
        }

        // Get player link from player column
        $a = $this->xpath->query('.//a[@class="userlink"]', $playerColumn)->item(0);

        if (!$a) {
            throw new \Exception("Missing player link element");
        }

        $href = $a->getAttribute('href');
        if (!$href || strpos($href, '?id=') === false) {
            throw new \Exception("Invalid player link format: {$href}");
        }

        $mdd_id = explode('?id=', basename($href))[1];

        // Get name from inside the <a> tag
        $name = $this->xpath->query('.//span[@class="visname"]', $a)->item(0);

        // If name is empty or doesn't exist, use "Unknown"
        $playerName = $name ? $this->get_q3_string($name) : 'Unknown';

        // If name is still empty after processing, use "Unknown"
        if (trim($playerName) === '') {
            $playerName = 'Unknown';
        }

        return [
            'name'      =>  $playerName,
            'country'   =>  strtoupper($country),
            'mdd_id'    =>  intval($mdd_id)
        ];
    }

    private function parse_time($time) {
        $result = 0;

        $parts = explode(':', $time);

        if (count($parts) == 3) {
            list($minutes, $seconds, $millisecond) = array_map('intval', $parts);
            $result = ($minutes * 60000) + ($seconds * 1000) + $millisecond;
        } elseif (count($parts) == 2) {
            list($seconds, $millisecond) = array_map('intval', $parts);
            $result = ($seconds * 1000) + $millisecond;
        } else {
            return 0;
        }

        return $result;
    }

    private function get_q3_string($node) {
        if (!$node) {
            return '';
        }

        $parts = $this->html_to_q3($node);

        $result = '';

        foreach($parts as $part) {
            $result .= $part['style'] . $part['text'];
        }

        return $result;
    }

    private function html_to_q3($node) {
        $result = [];

        if (!$node || !$node->childNodes) {
            return $result;
        }

        foreach($node->childNodes as $child) {
            if ($child->nodeName === '#text') {
                $result[] = [
                    'style' =>  $this->get_q3_color($node->getAttribute('style')),
                    'text'  =>  $child->textContent,
                ];
            } else {
                $result = array_merge($result, $this->html_to_q3($child));
            }
        }

        return $result;
    }

    private function get_q3_color($style) {
        $colors = [
            'yellow'        =>      '^3',
            'red'           =>      '^1',
            '#B5B5B5'       =>      '^9',
            '#4D87AB'       =>      '^4',
            'cyan'          =>      '^5',
            'green'         =>      '^2',
            'purple'        =>      '^6',
            'white'         =>      '^7',
            'rgb(181, 181, 181)' => '^9'
        ];

        $parts = explode('color:', $style);

        $color = trim($parts[1]);

        if (! array_key_exists($color, $colors)) {
            return '^7';
        }

        return $colors[$color];
    }
}