from __future__ import annotations

from dataclasses import dataclass, field
from pathlib import Path
from typing import Dict, List, Optional, Tuple

from console_commands_parser import ConsoleComandsParser
from console_string_utils import remove_colors, remove_non_ascii
from demoparser import const
from demoparser.structures.client import ClientConnection, ClientState
from demoparser.structures.client_event import ClientEvent
from demoparser.utils import split_config
from ext import Ext, ListMap
from game_info import GameInfo


@dataclass
class RawInfo:
    demoPath: str
    clc: ClientConnection
    client: ClientState
    rawConfig: Dict[int, str] = field(init=False)
    consoleComandsParser: ConsoleComandsParser = field(init=False)
    clientEvents: List[ClientEvent] = field(init=False)
    lastClientEvent: Optional[ClientEvent] = field(init=False)
    fin: Optional[Tuple[str, ClientEvent]] = field(init=False)
    maxSpeed: int = field(init=False)
    isCpmInSnapshots: Optional[bool] = field(init=False)
    gameInfo: GameInfo | None = field(init=False)
    cpData: List[int] = field(default_factory=list)

    # constants
    keyDemoName = "demoname"
    keyPlayer = "player"
    keyClient = "client"
    keyErrors = "errors"

    class FinishType:
        INCORRECT = 'INCORRECT'
        CORRECT_START = 'CORRECT_START'
        CORRECT_TR = 'CORRECT_TR'

    def __post_init__(self) -> None:
        self.rawConfig = self.clc.configs
        self.consoleComandsParser = ConsoleComandsParser(self.clc.console)
        self.clientEvents = list(self.client.clientEvents)
        self.lastClientEvent = self.client.lastClientEvent
        self.fin = self._get_correct_finish_event()
        self.maxSpeed = self.client.maxSpeed
        self.isCpmInSnapshots = self.client.isCpmInSnapshots
        self._friendly_info: Optional[Dict[str, Dict[str, str]]] = None
        self._player_configs: Dict[int, Dict[str, str]] = {}
        self.gameInfo = self._build_game_info()

    # ------------------------------------------------------------------
    def getFriendlyInfo(self) -> Dict[str, Dict[str, str]]:
        if self._friendly_info is not None:
            return self._friendly_info

        info: Dict[str, Dict[str, str]] = {}

        client_cfg = self.rawConfig.get(const.Q3_DEMO_CFG_FIELD_CLIENT)
        client_info = split_config(client_cfg) if client_cfg else {}
        if client_info:
            info[self.keyClient] = client_info

        player_info = self.getPlayerInfoByPlayerNum(self.clc.clientNum)
        if player_info:
            info[self.keyPlayer] = player_info

        if self.clc.errors:
            info[self.keyErrors] = {str(idx): message for idx, message in enumerate(self.clc.errors.keys(), start=1)}

        self._friendly_info = info
        return info

    # ------------------------------------------------------------------
    def getPlayerInfoByPlayerNum(self, client_num: int) -> Optional[Dict[str, str]]:
        key = const.Q3_DEMO_CFG_FIELD_PLAYER + int(client_num)
        if key not in self._player_configs:
            cfg = self.rawConfig.get(key)
            if cfg is None:
                return None
            self._player_configs[key] = self._split_config_player(cfg)
        return self._player_configs.get(key)

    def getPlayerInfoByPlayerName(self, player_name: Optional[str]) -> Optional[Dict[str, str]]:
        if not player_name:
            return None
        for idx in range(32):
            info = self.getPlayerInfoByPlayerNum(idx)
            if not info:
                continue
            user = info.get('name')
            if user and user == player_name:
                return info
        return None

    # ------------------------------------------------------------------
    def _build_game_info(self) -> GameInfo:
        client_cfg = split_config(self.rawConfig.get(const.Q3_DEMO_CFG_FIELD_CLIENT)) if self.rawConfig.get(const.Q3_DEMO_CFG_FIELD_CLIENT) else {}
        game_cfg = split_config(self.rawConfig.get(const.Q3_DEMO_CFG_FIELD_GAME)) if self.rawConfig.get(const.Q3_DEMO_CFG_FIELD_GAME) else {}
        additional = self.consoleComandsParser.additionalInfos[-1].toDictionary() if self.consoleComandsParser.additionalInfos else {}
        parameters = Ext.JoinLowercased(client_cfg, game_cfg, additional)
        return GameInfo(parameters, self.client.isCpmInSnapshots)

    # ------------------------------------------------------------------
    def _get_correct_finish_event(self) -> Optional[Tuple[str, ClientEvent]]:
        correct: List[Tuple[str, ClientEvent]] = []
        for idx in range(len(self.clientEvents) - 1, -1, -1):
            finish_type = self._is_finish_correct(idx)
            ev = self.clientEvents[idx]
            if finish_type != self.FinishType.INCORRECT and ev.timeNoError > 0:
                correct.append((finish_type, ev))
        if correct:
            return min(correct, key=lambda item: item[1].timeNoError)
        return None

    def _is_finish_correct(self, index: int) -> str:
        events = self.clientEvents
        current = events[index]
        if not current.eventFinish:
            return self.FinishType.INCORRECT
        for prev_index in range(index - 1, -1, -1):
            prev = events[prev_index]
            if prev.eventChangePmType or prev.eventFinish:
                return self.FinishType.INCORRECT
            current.timeByServerTime = current.serverTime - prev.serverTime
            if prev.eventTimeReset:
                return self.FinishType.CORRECT_TR
            if prev.eventStartTime:
                return self.FinishType.CORRECT_TR if self._has_start_before(prev_index) else self.FinishType.CORRECT_START
            if prev.eventStartFile or prev.eventChangeUser:
                return self.FinishType.INCORRECT
        return self.FinishType.INCORRECT

    def _has_start_before(self, index: int) -> bool:
        events = self.clientEvents
        for prev_index in range(index - 1, -1, -1):
            prev = events[prev_index]
            if prev.eventChangePmType or prev.eventChangeUser:
                return False
            if prev.eventStartTime or prev.eventTimeReset:
                return True
        return False

    # ------------------------------------------------------------------
    def _split_config_player(self, src: str) -> Dict[str, str]:
        split = ListMap(split_config(src))
        replaces = {
            'n': 'name',
            'dfn': 'df_name',
            't': 'team',
            'c1': 'color1',
            'c2': 'color2',
            'hc': 'maxHealth',
            'w': 'wins',
            'l': 'losses',
            'tt': 'teamTask',
            'tl': 'teamLeader',
        }
        Ext.replaceKeys(split, replaces)
        name_index = next((i for i, kv in enumerate(split) if kv[0].lower() == 'name'), -1)
        if name_index >= 0:
            name = split[name_index][1]
            uncolored = remove_colors(name) or name
            uncolored = remove_non_ascii(uncolored) or uncolored
            if uncolored != name:
                split.insert(name_index + 1, ('uncoloredName', uncolored))
        return dict(split)



