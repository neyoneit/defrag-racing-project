<?php

namespace App\External;

use Illuminate\Support\Str;

class DefragServer
{
    private $ip;
    private $port;
    private $socket;
    private $connected;

    private $previousData;

    public function __construct ($ip, $port) {
        // socket_connect/socket_sendto on AF_INET need a numeric IPv4 — they
        // don't resolve hostnames themselves, so credentials that declare a
        // server by FQDN (e.g. "deimos.baseq.fr") silently never connect.
        // Resolve once up front; on failure gethostbyname returns the input
        // unchanged, which lets the socket call fail loudly as it would.
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            $ip = gethostbyname($ip);
        }
        $this->ip = $ip;
        $this->port = $port;
        $this->socket = socket_create(AF_INET, SOCK_DGRAM, 0);
        $this->connected = false;

        $this->connect();
    }

    public function connect () {
        if ($this->connected) {
            return;
        }

        socket_set_option($this->socket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => 2, 'usec' => 0]);
        socket_connect($this->socket, $this->ip, $this->port);
        $this->connected = true;
    }

    public function getRconData($rconpass) {
        socket_sendto($this->socket, "\xff\xff\xff\xffrcon " . $rconpass . " score\x00", strlen("\xff\xff\xff\xffrcon " . $rconpass . " score\x00"), 0, $this->ip, $this->port);
        $data = "";

        $read = socket_read($this->socket, 8192);

        if(empty($read)){
            return 'Rcon not usable';
        }

        while($read) {
            $data .= $read;
            $read = socket_read($this->socket, 8192);
        }

        if (strpos($data, 'Bad rconpassword') !== false) {
            return 'Bad rconpassword';
        }
    
        $data = substr($data, 10);
        $data = explode("\n", $data);
    
        $scores = [];
        $players = [];
    
        foreach ($data as $line) {
            if (Str::startsWith($line, '<player>')) {
                $player = $this->parseScorePlayer($line);
                $players[$player['clientId']] = $player;

                // Associating player info with scores directly from $data array
                list($playerInfo, $scores) = $this->getPlayerInfo($player, $rconpass, $scores);

                $players[$player['clientId']]['name'] = isset($playerInfo['name']) ? $playerInfo['name'] : $player['name'];
    
                $players[$player['clientId']]['country'] = isset($playerInfo['tld']) ? $playerInfo['tld'] : '_404';
    
                if (isset($playerInfo['color1'])) {
                    $players[$player['clientId']]['nospec'] = ($playerInfo['color1'] == 'nospec' || $playerInfo['color1'] == 'nospecpm');
                } else {
                    $players[$player['clientId']]['nospec'] = false;
                }

                $players[$player['clientId']]['model'] = isset($playerInfo['model']) ? $playerInfo['model'] : 'sarge';
                $players[$player['clientId']]['headmodel'] = isset($playerInfo['headmodel']) ? $playerInfo['headmodel'] : 'sarge';
    
                usleep(200000); // sleep for 0.2 seconds
            } elseif (strpos($line, 'scores') === 0) {
                $scores = $this->parseScores($line);
            }
        }
    
        // Extract defrag_gametype - rcon score doesn't include it, so we need to get it via getstatus
        $defrag_gametype = '5'; // default

        // Send getstatus to get server CVARs including defrag_gametype
        socket_sendto($this->socket, "\xff\xff\xff\xffgetstatus\x00", strlen("\xff\xff\xff\xffgetstatus\x00"), 0, $this->ip, $this->port);
        $statusData = socket_read($this->socket, 4096);

        if ($statusData && preg_match('/defrag_gametype\\\\(\d+)/', $statusData, $matches)) {
            $defrag_gametype = $matches[1];
        }

        $result = [
            'players' => $players,
            'map' => explode(':', $data[0])[1],
            'hostname' => explode(':', $data[1])[1],
            'defrag' => explode(':', $data[2])[1],
            'defrag_gametype' => $defrag_gametype,
            'scores' => $scores,
            'rcon'   => true
        ];

        return $result;
    }    

    public function getData() {
        socket_sendto($this->socket, "\xff\xff\xff\xffgetstatus\x00", strlen("\xff\xff\xff\xffgetstatus\x00"), 0, $this->ip, $this->port);
        $data = socket_read($this->socket, 4096);

        list($serverData, $players) = $this->parseResponseBody(substr($data, 19));

        $serverData['players'] = $this->parsePlayers($players);

        $playerList = [];
        $i = 0;

        foreach ($serverData['players'] as $player) {
            $playerList[$i] = $player;
            $i++;
        }

        $result = [
            'players' => $playerList,
            'map' => $serverData['mapname'],
            'hostname' => $serverData['sv_hostname'],
            'defrag' => $this->getGameMode($serverData),
            'defrag_gametype' => $serverData['defrag_gametype'] ?? '5',
            'scores' => [
                'num_players' => count($serverData['players']),
                'speed' => 0,
                'speed_player_num' => 0,
                'speed_player_name' => "",
                'players' => $serverData['players'],
            ],
            'rcon'  =>  false
        ];

        return $result;
    }

    public function getDfStatusData() {
        socket_sendto($this->socket, "\xff\xff\xff\xffgetdfstatus\x00", strlen("\xff\xff\xff\xffgetdfstatus\x00"), 0, $this->ip, $this->port);
        $data = socket_read($this->socket, 8192);

        if (empty($data)) {
            return null;
        }

        // Find end of response header line
        $headerEnd = strpos($data, "\n");
        if ($headerEnd === false) {
            return null;
        }

        list($serverData, $playerLines) = $this->parseResponseBody(substr($data, $headerEnd + 1));

        // Reject responses that aren't real statusResponse packets (e.g. "print\nBad command")
        // so the caller can fall back to getstatus instead of saving empty data.
        if (empty($serverData['mapname']) || empty($serverData['sv_hostname'])) {
            return null;
        }

        // Parse player lines - three formats:
        // KG7X (new):       dfscore ping clientId "name" spec_clientId "tld" "color1" uid "model" "headmodel"
        // Extended (oDFe):  clientId dfscore ping "name" "spectating_name" "tld" "model" "headmodel" uid "color1"
        // Legacy (GTK):     dfscore ping "name" "spectating_name"
        $kg7xRegex = '/^(-?\d+)\s+(\d+)\s+(\d+)\s+"([^"]*)"\s+(-?\d+)\s+"([^"]*)"\s+"([^"]*)"\s+(\d+)\s+"([^"]*)"\s+"([^"]*)"$/';
        $extendedRegex = '/^(\d+)\s+(-?\d+)\s+(\d+)\s+"([^"]*)"\s+"([^"]*)"\s+"([^"]*)"\s+"([^"]*)"\s+"([^"]*)"\s+(\d+)\s+"([^"]*)"$/';
        $legacyRegex = '/^(-?\d+)\s+(\d+)\s+"([^"]*)"\s+"([^"]*)"$/';

        $parsedPlayers = [];

        foreach ($playerLines as $line) {
            if (empty(trim($line))) {
                continue;
            }

            if (preg_match($kg7xRegex, $line, $m)) {
                $clientId = (int) $m[3];
                $color1 = $m[7];
                $mddId = (int) $m[8];
                $parsedPlayers[$clientId] = [
                    'clientId' => $clientId,
                    'dfscore' => (int) $m[1],
                    'ping' => (int) $m[2],
                    'name' => $m[4],
                    'spectating_id' => (int) $m[5],
                    'spectating' => '',
                    'country' => strtoupper($m[6]),
                    'color1' => $color1,
                    'mddId' => $mddId,
                    'model' => $m[9] !== '' ? $m[9] : 'sarge',
                    'headmodel' => $m[10] !== '' ? $m[10] : 'sarge',
                    'nospec' => ($color1 == 'nospec' || $color1 == 'nospecpm'),
                ];
            } elseif (preg_match($extendedRegex, $line, $m)) {
                $clientId = (int) $m[1];
                $parsedPlayers[$clientId] = [
                    'clientId' => $clientId,
                    'dfscore' => (int) $m[2],
                    'ping' => (int) $m[3],
                    'name' => $m[4],
                    'spectating' => $m[5],
                    'spectating_id' => null,
                    'country' => strtoupper($m[6]),
                    'model' => $m[7],
                    'headmodel' => $m[8],
                    'mddId' => (int) $m[9],
                    'color1' => $m[10],
                    'nospec' => ($m[10] == 'nospec' || $m[10] == 'nospecpm'),
                ];
            } elseif (preg_match($legacyRegex, $line, $m)) {
                $idx = count($parsedPlayers);
                $parsedPlayers[$idx] = [
                    'clientId' => $idx,
                    'dfscore' => (int) $m[1],
                    'ping' => (int) $m[2],
                    'name' => $m[3],
                    'spectating' => $m[4],
                    'spectating_id' => null,
                    'country' => '_404',
                    'model' => 'sarge',
                    'headmodel' => 'sarge',
                    'mddId' => 0,
                    'color1' => '',
                    'nospec' => false,
                ];
            }
        }

        // Build name -> clientId lookup for spectating resolution (legacy/extended formats)
        $nameToClientId = [];
        foreach ($parsedPlayers as $p) {
            $nameToClientId[$p['name']] = $p['clientId'];
        }

        // Players array keyed by clientId with all info for updateServer()
        $players = [];
        foreach ($parsedPlayers as $clientId => $p) {
            $players[$clientId] = [
                'name' => $p['name'],
                'mddId' => $p['mddId'],
                'country' => $p['country'],
                'model' => $p['model'],
                'headmodel' => $p['headmodel'],
                'nospec' => $p['nospec'],
            ];
        }

        // scores.players with player_num, time, follow_num for getPlayerScore()
        $scorePlayers = [];
        foreach ($parsedPlayers as $clientId => $p) {
            if (isset($p['spectating_id']) && $p['spectating_id'] !== null) {
                $followNum = $p['spectating_id'];
            } else {
                $followNum = -1;
                if (!empty($p['spectating']) && isset($nameToClientId[$p['spectating']])) {
                    $followNum = $nameToClientId[$p['spectating']];
                }
            }

            $scorePlayers[] = [
                'player_num' => $clientId,
                'time' => $p['dfscore'],
                'ping' => $p['ping'],
                'follow_num' => $followNum,
            ];
        }

        return [
            'players' => $players,
            'map' => $serverData['mapname'] ?? '',
            'hostname' => $serverData['sv_hostname'] ?? '',
            'defrag' => $this->getGameMode($serverData),
            'defrag_gametype' => $serverData['defrag_gametype'] ?? '5',
            'scores' => [
                'num_players' => count($players),
                'speed' => 0,
                'speed_player_num' => 0,
                'speed_player_name' => "",
                'players' => $scorePlayers,
            ],
            'rcon' => true,
        ];
    }

    public function getGameMode ($serverData) {
        $physics = ($serverData['df_promode'] == '1') ? 'cpm' : 'vq3';
        $mode = ($serverData['defrag_mode'] == '2') ? '.2' : '';

        return $physics . $mode;
    }

    private function extractKeyValuePair($line) {
        if ($line === '') {
            return [null, null];
        }

        for ($i = 0; $i < strlen($line); $i++) {
            if ($line[$i] === ' ') {
                return [substr($line, 0, $i), trim(substr($line, $i + 1))];
            }
        }
    }

    private function parseScorePlayer ($data) {
        $clientId = explode('<num>', $data)[1];
        $name = explode('<nick>', $data)[1];
        $mddId = explode('<uid>', $data)[1];

        $player = [
            'clientId' => explode('</num>', $clientId)[0],
            'name' => explode('</nick>', $name)[0],
            'mddId' => explode('</uid>', $mddId)[0],
        ];

        $player['logged'] = ($player['mddId'] != '0');

        return $player;
    }

    public function getPlayerInfo($player, $rconpass, $scores = []) {
        socket_sendto($this->socket, "\xff\xff\xff\xffrcon " . $rconpass . " dumpuser " . $player['clientId'] . "\x00", strlen("\xff\xff\xff\xffrcon " . $rconpass . " dumpuser " . $player['clientId'] . "\x00"), 0, $this->ip, $this->port);
        $data = socket_read($this->socket, 4096);

        if (empty($scores) && strpos($data, 'scores ') !== false) {
            foreach (explode("\n", substr($data, 28)) as $line) {
                if (strpos($line, 'scores') === 0) {
                    $scores = $this->parseScores($line);
                }
            }
        }

        if (strpos($data, "print\nscores") !== false || strpos($data, "print\n<player>") !== false) {
            return $this->getPlayerInfo($player, $rconpass, $scores);
        }

        if ($data == $this->previousData) {
            return $this->getPlayerInfo($player, $rconpass, $scores);
        }

        $this->previousData = $data;

        $data = explode("\n", substr($data, 28));

        $result = [];

        foreach ($data as $line) {
            list($key, $value) = $this->extractKeyValuePair($line);
            $result[$key] = $value;
        }
        return [$result, $scores];
    }

    public function parseScores($data) {
        $scores = [];
        $parsed = explode('"', str_replace('scores ', '', $data));
        $data = explode(' ', trim($parsed[0]));

        $data[] = $parsed[1];

        $data = array_merge($data, explode(' ', trim($parsed[2])));

        $scores['num_players'] = (int) array_shift($data);
        $scores['speed'] = (int) array_shift($data);
        $scores['speed_player_num'] = (int) array_shift($data);
        $scores['speed_player_name'] = array_shift($data);

        $scores['players'] = [];

        if (count($data) <= 1) {
            return $scores;
        }

        while (count($data) > 0) {
            $player = [
                'player_num' => (int) array_shift($data),
                'time' => (int) array_shift($data),
                'ping' => (int) array_shift($data),
                'follow_num' => (int) array_shift($data),
            ];

            $scores['players'][] = $player;
        }

        return $scores;
    }

    public function parsePlayers($data) {
        $players = [];
        foreach ($data as $line) {
            if ($line == '') {
                continue;
            }

            $line = utf8_decode($line);

            $parts = explode(' ', $line, 3);

            if (count($parts) < 3) {
                continue;
            }

            $player = array_combine(['score', 'ping', 'name'], $parts);
            $players[] = ['name' => $player['name']];
        }

        return $players;
    }

    public function parseResponseBody($body) {
        $i = 0;
        $keys = [];
        $values = [];
        while (strpos($body, '\\') === 0) {
            if (strpos($body, '\\', 1) !== false) {
                $elementEnd = strpos($body, '\\', 1);
            } elseif (strpos($body, '\n', 1) !== false) {
                $elementEnd = strpos($body, '\n', 1);
            } else {
                break;
            }

            $element = substr($body, 1, $elementEnd - 1);
            if ($i % 2 == 0) {
                $keys[] = utf8_decode($element);
            } else {
                $values[] = utf8_decode($element);
            }

            $body = substr($body, $elementEnd);
            $i++;
        }

        $lines = explode("\n", $body);

        $diff = count($keys) - count($values);

        for($i = 0; $i < $diff; $i++) {
            if ($diff > 0) {
                array_pop($keys);
            } else {
                array_pop($values);
            }
        }

        return [array_combine($keys, $values), $lines];
    }
}