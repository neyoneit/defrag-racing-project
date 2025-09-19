<?php

namespace App\External;

use \DOMDocument;
use \DOMXPath;
use Carbon\Carbon;

class WorldSpawn {
    protected $url = "https://ws.q3df.org/maps/?show=50&page=";
    protected $mapUrl = "https://ws.q3df.org/map/";
    protected $wsUrl = "https://ws.q3df.org";
    protected $xpath;

    public function read_data($url) {
        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
        curl_setopt($ch, CURLOPT_ENCODING, ''); // Let curl handle decompression automatically
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.5',
            'Connection: keep-alive',
            'Upgrade-Insecure-Requests: 1',
        ]);

        $response = curl_exec($ch);

        curl_close($ch);

        if ($response === false) {
            return [];
        }

        libxml_use_internal_errors(true);

        $dom = new DOMDocument();
        $dom->loadHTML($response);

        libxml_clear_errors();

        $xpath = new DOMXPath($dom);

        return $xpath;
    }

    public function scrape_until ($mapname) {
        $page = 0;
        $maps = [];

        while (1) {
            echo 'Scraping page: ' . ($page + 1) . PHP_EOL;
            $xpath = $this->read_data($this->url . $page);

            $mapsTable = $xpath->query("//table[@id='maps_table']")->item(0);

            $result = $this->getMaps($mapsTable, $mapname);

            $maps = array_merge($maps, $result['maps']);

            if ($result['found']) {
                break;
            }

            $page++;

            if ($page > 10) {
                break;
            }
        }

        $maps = array_reverse($maps);

        $data = [];

        foreach($maps as $map) {
            $mapdetails = $this->getMapDetails($map);
            if ($mapdetails === NULL) {
                continue;
            }

            $data[] = $mapdetails;
        }

        return $data;
    }

    public function getMaps($table, $searchmap=NULL) {
        $rows = $table->getElementsByTagName('tr');
        $first = true;

        $maps = [];
        $found = false;

        foreach($rows as $row) {
            if ($first) {
                $first = false;
                continue;
            }

            $parts = $row->getElementsByTagName('td');

            $map = $parts->item(1);

            $a = $map->getElementsByTagName('a')->item(0);

            $url = $a->getAttribute('href');

            $mapname = trim(str_replace('/map/', '', $url));
            $mapname = str_replace('/', '', $mapname);

            if ($searchmap !== NULL && strtolower($searchmap) == strtolower($mapname)) {
                $found = true;
                break;
            }

            $maps[] = $mapname;
        }

        return [
            'found' =>  $found,
            'maps'  =>  $maps
        ];
    }

    public function getMapDetails($map) {
        $result = [];

        $xpath = $this->read_data($this->mapUrl . $map);

        $mapImageElement = $xpath->query("//img[@id='mapdetails_levelshot']")->item(0);

        $mapImage = $this->wsUrl . $mapImageElement->getAttribute('src');

        $detailsTable = $xpath->query("//table[@id='mapdetails_data_table']")->item(0);

        $data = [];

        $rows = $detailsTable->getElementsByTagName('tr');

        foreach($rows as $row) {
            $name = trim($row->getElementsByTagName('td')->item(0)->textContent);

            $data[$name] = $row->getElementsByTagName('td')->item(1);
        }

        if (! $this->shouldGetMap($data)) {
            return NULL;
        }
        
        if (isset($data['Mapname'])) {
            $result['description'] = $data['Mapname']->textContent;
        }
        
        if (isset($data['Author'])) {
            $result['author'] = $data['Author']->textContent;
        }

        $result['name'] = $map;
        $result['author'] = $this->getAuthor($data);
        $result['physics'] = $this->getPhysics($data);
        $result['description'] = $this->getDescription($data);
        $result['gametype'] = $this->getGameType($data);
        $result['date_added'] = $this->getDate($data);
        $result['pk3'] = $this->getPk3($data);
        $result['pk3_size'] = $this->getPk3Size($data);
        $result['mod'] = 'df';
        $result['weapons'] = $this->getWeapons($data);
        $result['items'] = $this->getItems($data);
        $result['functions'] = $this->getFunctions($data);

        $result['map_image'] = $mapImage;

        return $result;
    }

    public function shouldGetMap($data) {
        // Get all maps instead of filtering by defrag only
        return true;

        // Original filtering logic (commented out):
        // if (isset($data['Modification'])) {
        //     $list = $this->getImageList($data['Modification']);
        //     if (in_array('defrag', $list)) {
        //         return true;
        //     }
        // }
        // return $this->getGameType($data) === 'unknown';
    }

    public function getGameType($data) {
        if (isset($data['Game type'])) {
            $gametypes = $data['Game type']->textContent;
            $gametypes = explode(',', $gametypes);
            $gametypes = array_map('trim', $gametypes);

            if (in_array('ctf', $gametypes)) {
                return 'fastcaps';
            }
        }

        if (isset($data['Defrag style'])) {
            $gametypes = $data['Defrag style']->textContent;
            $gametypes = explode(',', $gametypes);
            $gametypes = array_map('trim', $gametypes);

            if (in_array('run', $gametypes)) {
                return 'run';
            }
        }

        if (isset($data['Functions'])) {
            $list = $this->getImageList($data['Functions']);

            if (in_array('t', $list)) {
                return 'run';
            }
        }

        return 'unknown';
    }

    public function getImageList($element) {
        $imgs = $element->getElementsByTagName('img');

        $result = [];

        foreach($imgs as $img) {
            $result[] = strtolower($img->getAttribute('alt'));
        }

        return $result;
    }

    public function getPhysics($data) {
        if (isset($data['Defrag physics'])) {
            $physics = $data['Defrag physics']->textContent;
            $physics = explode(',', $physics);
            $physics = array_map('trim', $physics);

            if (in_array('cpm', $physics) && in_array('vq3', $physics)) {
                return 'all';
            }

            if (in_array('cpm', $physics)) {
                return 'cpm';
            }

            if (in_array('vq3', $physics)) {
                return 'vq3';
            }
        }

        return 'all';
    }

    public function getAuthor($data) {
        if (isset($data['Author'])) {
            return trim($data['Author']->textContent);
        }

        return 'Unknown';
    }

    public function getDescription($data) {
        if (isset($data['Mapname'])) {
            return trim($data['Mapname']->textContent);
        }

        return '';
    }

    public function getDate($data) {
        if (isset($data['Release date'])) {
            return trim($data['Release date']->textContent);
        }

        return '';
    }

    public function getPk3($data) {
        if (isset($data['Pk3 file'])) {
            $pk3 = $data['Pk3 file']->getElementsByTagName('a')->item(0)->textContent;

            return $pk3 . ".pk3";
        }

        return 0;
    }

    public function getPk3Size($data) {
        if (isset($data['File size'])) {
            $size = trim($data['File size']->textContent);
            $size = trim(str_replace('MB', '', $size));

            $float = floatval($size) * 1024 * 1024;

            return intval(ceil($float));
        }

        return 0;
    }

    public function getWeapons($data) {
        if (isset($data['Weapons'])) {
            $list = $this->getImageList($data['Weapons']);

            return implode(',', $list);
        }

        return '';
    }

    public function getItems($data) {
        $items = [
            'smallh'    =>  'smallhealth',
            'largeh'    =>  'bighealth'
        ];

        if (isset($data['Items'])) {
            $list = $this->getImageList($data['Items']);

            $list = array_map(function ($item) use ($items) {
                if (isset($items[$item])) {
                    return $items[$item];
                }

                return $item;
            }, $list);

            return implode(',', $list);
        }

        return '';
    }

    public function getFunctions($data) {
        $functions = [
            'ice'   =>  'slick',
            'w'     =>  'water',
            't'     =>  'timer',
            'push'  =>  'jumppad'
        ];

        if (isset($data['Functions'])) {
            $list = $this->getImageList($data['Functions']);

            $list = array_map(function ($item) use ($functions) {
                if (isset($functions[$item])) {
                    return $functions[$item];
                }

                return $item;
            }, $list);

            return implode(',', $list);
        }

        return '';
    }
}