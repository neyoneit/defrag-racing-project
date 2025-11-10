from __future__ import annotations

import re
from dataclasses import dataclass, field
from datetime import datetime, timedelta
from pathlib import Path
from typing import Dict, Optional

from console_string_utils import get_time_span
from demo_names import DemoNames, normalize_name
from ext import Ext
from demoparser import const
from game_info import GameInfo
from raw_info import RawInfo


# Country name to ISO 2-letter code mapping
COUNTRY_CODE_MAP = {
    # Full names
    'RUSSIA': 'RU', 'GERMANY': 'DE', 'USA': 'US', 'POLAND': 'PL',
    'FRANCE': 'FR', 'SPAIN': 'ES', 'ITALY': 'IT', 'NETHERLANDS': 'NL',
    'BELGIUM': 'BE', 'SWEDEN': 'SE', 'NORWAY': 'NO', 'DENMARK': 'DK',
    'FINLAND': 'FI', 'AUSTRIA': 'AT', 'SWITZERLAND': 'CH', 'PORTUGAL': 'PT',
    'GREECE': 'GR', 'CZECHREPUBLIC': 'CZ', 'CZECH': 'CZ', 'SLOVAKIA': 'SK',
    'HUNGARY': 'HU', 'ROMANIA': 'RO', 'BULGARIA': 'BG', 'CROATIA': 'HR',
    'SERBIA': 'RS', 'SLOVENIA': 'SI', 'UKRAINE': 'UA', 'BELARUS': 'BY',
    'LITHUANIA': 'LT', 'LATVIA': 'LV', 'ESTONIA': 'EE', 'ICELAND': 'IS',
    'IRELAND': 'IE', 'UNITEDKINGDOM': 'GB', 'UK': 'GB', 'BRITAIN': 'GB',
    'GREATBRITAIN': 'GB', 'ENGLAND': 'GB', 'SCOTLAND': 'GB', 'WALES': 'GB',
    'CANADA': 'CA', 'MEXICO': 'MX', 'BRAZIL': 'BR', 'ARGENTINA': 'AR',
    'CHILE': 'CL', 'COLOMBIA': 'CO', 'PERU': 'PE', 'VENEZUELA': 'VE',
    'AUSTRALIA': 'AU', 'NEWZEALAND': 'NZ', 'JAPAN': 'JP', 'CHINA': 'CN',
    'SOUTHKOREA': 'KR', 'KOREA': 'KR', 'INDIA': 'IN', 'THAILAND': 'TH',
    'VIETNAM': 'VN', 'INDONESIA': 'ID', 'MALAYSIA': 'MY', 'SINGAPORE': 'SG',
    'PHILIPPINES': 'PH', 'TAIWAN': 'TW', 'HONGKONG': 'HK', 'ISRAEL': 'IL',
    'TURKEY': 'TR', 'SOUTHAFRICA': 'ZA', 'EGYPT': 'EG', 'MOROCCO': 'MA',
    # Already 2-letter codes (pass through)
    'RU': 'RU', 'DE': 'DE', 'US': 'US', 'PL': 'PL', 'FR': 'FR', 'ES': 'ES',
    'IT': 'IT', 'NL': 'NL', 'BE': 'BE', 'SE': 'SE', 'NO': 'NO', 'DK': 'DK',
    'FI': 'FI', 'AT': 'AT', 'CH': 'CH', 'PT': 'PT', 'GR': 'GR', 'CZ': 'CZ',
    'SK': 'SK', 'HU': 'HU', 'RO': 'RO', 'BG': 'BG', 'HR': 'HR', 'RS': 'RS',
    'SI': 'SI', 'UA': 'UA', 'BY': 'BY', 'LT': 'LT', 'LV': 'LV', 'EE': 'EE',
    'IS': 'IS', 'IE': 'IE', 'GB': 'GB', 'CA': 'CA', 'MX': 'MX', 'BR': 'BR',
    'AR': 'AR', 'CL': 'CL', 'CO': 'CO', 'PE': 'PE', 'VE': 'VE', 'AU': 'AU',
    'NZ': 'NZ', 'JP': 'JP', 'CN': 'CN', 'KR': 'KR', 'IN': 'IN', 'TH': 'TH',
    'VN': 'VN', 'ID': 'ID', 'MY': 'MY', 'SG': 'SG', 'PH': 'PH', 'TW': 'TW',
    'HK': 'HK', 'IL': 'IL', 'TR': 'TR', 'ZA': 'ZA', 'EG': 'EG', 'MA': 'MA',
}


def normalize_country_code(country: str) -> str:
    """Convert country name to ISO 2-letter code."""
    if not country:
        return ''
    # Remove spaces, hyphens, underscores and convert to uppercase
    normalized = country.upper().replace(' ', '').replace('-', '').replace('_', '')
    return COUNTRY_CODE_MAP.get(normalized, country.upper()[:2] if len(country) >= 2 else country.upper())


@dataclass
class Demo:
    mapName: str = ''
    modphysic: str = ''
    timeString: str = ''
    time: timedelta = timedelta(0)
    playerName: str = ''
    names: DemoNames | None = None
    country: str = ''
    file: Path | None = None
    isBroken: bool = False
    hasError: bool = False
    hasCorrectName: bool = False
    recordTime: Optional[datetime] = None
    hasTr: bool = False
    isNotFinished: bool = False
    isTas: bool = False
    validDict: Dict[str, str] = field(default_factory=dict)
    useValidation: bool = True
    rawTime: bool = False
    triggerTime: bool = False
    triggerTimeNoFinish: bool = False
    isSpectator: bool = False
    rawInfo: RawInfo | None = None
    userId: int = -1

    _demoNewName: str = ''
    _demoNewNameSimple: str = ''
    _normalizedFileName: str = ''

    tasTriggers = sorted(
        ["tas", "tasbot", "bot", "boted", "botland", "wiz", "wizland", "script", "scripted", "scriptland"],
        key=len,
        reverse=True,
    )

    @property
    def demoNewNameSimple(self) -> str:
        if not self._demoNewNameSimple:
            self.fillDemoNewName()
        return self._demoNewNameSimple

    @property
    def normalizedFileName(self) -> str:
        if not self._normalizedFileName and self.file is not None:
            self._normalizedFileName = self._get_normalized_file_name(self.file)
        return self._normalizedFileName

    @property
    def demoNewName(self) -> str:
        if self._demoNewName:
            return self._demoNewName
        if self.hasError:
            return self.normalizedFileName
        self.fillDemoNewName()
        return self._demoNewName

    def fillDemoNewName(self) -> None:
        if not self.file:
            return
        demoname = ''
        player_country = f"{self.playerName}.{self.country}" if self.country else self.playerName
        extension = self.file.suffix.lower()
        if self.time.total_seconds() > 0:
            minutes = int(self.time.total_seconds() // 60)
            seconds = int(self.time.total_seconds() % 60)
            # Extract milliseconds from the fractional part of seconds
            # Round to avoid floating point precision issues
            milliseconds = round((self.time.total_seconds() % 1) * 1000)
            demoname = f"{self.mapName}[{self.modphysic}]{minutes:02}.{seconds:02}.{milliseconds:03}({player_country})"
            self.hasCorrectName = True
        else:
            self.hasCorrectName = False
            old_name = self.normalizedFileName[:-len(extension)] if extension else self.normalizedFileName
            old_name = self._remove_substr(old_name, self.mapName)
            if self.country:
                if self.names and self.names.fName:
                    player_country = f"{self.names.fName}.{self.country}"
                old_name = self._remove_substr(old_name, player_country, from_start=False)
            old_name = old_name.replace('[dm]', '').replace('[spect]', '')
            normalized_name = normalize_name(self.playerName)
            patterns = [
                f"({normalized_name}.{self.country})",
                f"({normalized_name})",
            ]
            if self.names and self.names.fName:
                patterns.extend([
                    f"({self.names.fName}.{self.country})",
                    f"({self.names.fName})",
                ])
            for pattern in patterns:
                old_name = old_name.replace(pattern, '')
            old_name = self._remove_substr(old_name, normalized_name, from_start=False)
            if self.names and self.names.fName:
                old_name = self._remove_substr(old_name, self.names.fName, from_start=False)
            old_name = self._remove_substr(old_name, self.country, from_start=False)
            old_name = old_name.replace(f"[{self.modphysic}]", '')
            old_name = self._remove_substr(old_name, self.modphysic)
            if self.rawInfo and self.rawInfo.gameInfo:
                old_name = self._remove_substr(old_name, self.rawInfo.gameInfo.gameNameShort)
            old_name = self._remove_substr(old_name, self.validity)
            old_name = self._remove_double(old_name)
            old_name = old_name.replace('[]', '').replace('()', '')
            # Remove non-alphanumeric characters (except parentheses and brackets) from start/end
            old_name = re.sub(r"(^[^a-zA-Z0-9()\[\]]+|[^a-zA-Z0-9()\[\]]+$)", '', old_name)
            old_name = old_name.replace(' ', '_')
            demoname = f"{self.mapName}[{self.modphysic}]({player_country}){old_name}"
            demoname = demoname.replace(').)', ')').replace('.)', ')')
        self._demoNewNameSimple = demoname + extension
        final_name = demoname
        if self.useValidation and self.validity:
            final_name += f"{{{self.validity}}}"
        if self.userId >= 0:
            final_name += f"[{self.userId}]"
        elif self.isSpectator or (self.rawInfo and self.rawInfo.consoleComandsParser.additionalInfos and any(info.isTr for info in self.rawInfo.consoleComandsParser.additionalInfos)):
            final_name += '[spect]'
        self._demoNewName = final_name + extension

    @property
    def validity(self) -> str:
        if self.validDict:
            key, value = next(iter(self.validDict.items()))
            return f"{key}={value}"
        return ''

    @staticmethod
    def GetDemoFromRawInfo(raw: RawInfo) -> 'Demo':
        file_path = Path(raw.demoPath)
        friendly_info = raw.getFriendlyInfo()
        demo = Demo()
        demo.rawInfo = raw
        demo.file = file_path
        if not friendly_info or RawInfo.keyClient not in friendly_info or not friendly_info[RawInfo.keyClient]:
            demo.hasError = True
            demo.isBroken = True
            return demo
        names = DemoNames()
        player_info = friendly_info.get(RawInfo.keyPlayer)
        names.setNamesByPlayerInfo(player_info)
        fastest = raw.consoleComandsParser.getFastestTimeStringInfo(names)
        if raw.fin:
            finish_type, finish_event = raw.fin
            if not finish_event.timeHasError:
                demo.time = timedelta(milliseconds=finish_event.time)
            demo.hasTr = finish_type == RawInfo.FinishType.CORRECT_TR
            demo.triggerTime = True
        else:
            demo.hasTr = Demo._is_tr(raw, fastest)
        if demo.time.total_seconds() <= 0:
            if fastest:
                demo.time = fastest.time
                if raw.consoleComandsParser.dateStrings:
                    latest = next((d for d in reversed(raw.consoleComandsParser.dateStrings) if d.recordDate), None)
                    if latest:
                        demo.recordTime = latest.recordDate
                user = raw.getPlayerInfoByPlayerName(fastest.oName)
                if user:
                    names.setNamesByPlayerInfo(user)
            elif raw.fin:
                demo.time = timedelta(milliseconds=raw.fin[1].timeByServerTime)
        if raw.consoleComandsParser.dateStrings and not demo.recordTime:
            latest = next((d for d in reversed(raw.consoleComandsParser.dateStrings) if d.recordDate), None)
            if latest:
                demo.recordTime = latest.recordDate
        if fastest:
            names.setConsoleName(fastest.oName, fastest.lName, raw.gameInfo.isOnline if raw.gameInfo else True)
        filename = demo.normalizedFileName
        country_and_name = Demo._get_name_and_country(filename)
        country_name_parsed = Demo._try_get_name_and_country(country_and_name, names)
        normal_name = names.chooseNormalName()
        if not normal_name or normal_name == DemoNames.defaultName:
            names.setBracketsName(country_name_parsed[0])
        demo.playerName = names.chooseNormalName()
        demo.names = names
        demo.country = normalize_country_code(country_name_parsed[1])
        lower_filename = filename.lower()
        if 'tool_assisted=true' in lower_filename or Ext.ContainsAnySplitted(country_and_name, *Demo.tasTriggers) or Ext.ContainsAnySplitted(demo.playerName, *Demo.tasTriggers):
            demo.isTas = True
        if demo.time.total_seconds() > 0:
            demo.rawTime = True
        else:
            demo_name_time = Demo._try_get_time_from_file_name(filename)
            if demo_name_time is not None:
                demo.time = demo_name_time
        map_info = raw.rawConfig.get(const.Q3_DEMO_CFG_FIELD_MAP, '') if raw.rawConfig else ''
        client_info = friendly_info.get(RawInfo.keyClient, {})
        map_name = (client_info.get('mapname') or '').lower()
        demo.mapName = map_info if map_name and map_name.lower() == map_info.lower() else map_name
        if not map_name:
            demo.isBroken = True
        game_info = raw.gameInfo or GameInfo({}, None)
        if game_info.isDefrag:
            if game_info.modType:
                demo.modphysic = f"{game_info.gameTypeShort}.{game_info.gameplayTypeShort}.{game_info.modType}"
            else:
                demo.modphysic = f"{game_info.gameTypeShort}.{game_info.gameplayTypeShort}"
        else:
            demo.modphysic = f"{game_info.gameNameShort}.{game_info.gameTypeShort}"
        if demo.hasTr:
            demo.modphysic += '.tr'
        additional = raw.consoleComandsParser.additionalInfos[-1].toDictionary() if raw.consoleComandsParser.additionalInfos else None
        demo.validDict = Demo._check_validity(demo.time.total_seconds() > 0, demo.rawTime, game_info, demo.isTas, demo.triggerTimeNoFinish, additional)
        if not demo.validDict:
            filename_validity = Demo._get_validities(filename)
            if filename_validity:
                demo.validDict[filename_validity[0]] = filename_validity[1]
        if demo.triggerTime:
            demo.userId = Demo._try_get_user_id_from_file_name(file_path)
        if demo.validDict.get('client_finish') == 'false':
            demo.isNotFinished = True
        return demo

    # --- helper static methods ------------------------------------------------
    @staticmethod
    def _is_tr(raw: RawInfo, fastest) -> bool:
        if any(ev.eventTimeReset for ev in raw.clientEvents):
            return True
        if fastest and raw.consoleComandsParser.additionalInfos:
            for info in raw.consoleComandsParser.additionalInfos:
                if info.time == fastest.time:
                    return info.isTr
        return False

    @staticmethod
    def _remove_double(value: str) -> str:
        match = re.search(r"[^a-zA-Z0-9\\(\\)\\]\[](?=[^a-zA-Z0-9\\(\\)\\]\[])", value)
        if not match:
            return value
        idx = match.start()
        symbol = value[idx]
        new_value = value[:idx] + symbol + value[idx + 2:]
        return Demo._remove_double(new_value)

    @staticmethod
    def _remove_substr(source: str, include: Optional[str], from_start: bool = True) -> str:
        if not include or include not in source:
            return source
        pos = source.find(include) if from_start else source.rfind(include)
        if pos == -1:
            return source
        crop_start = 0
        crop_end = 0
        symbol = ''
        if pos > 0:
            prev = source[pos - 1]
            crop_start = 0 if prev.isalnum() else 1
            if crop_start:
                symbol = prev
        if pos + len(include) < len(source):
            nxt = source[pos + len(include)]
            crop_end = 0 if nxt.isalnum() else 1
            if crop_end:
                symbol = nxt
        if symbol in '([{)]}':
            symbol = '_'
        return source[:pos - crop_start] + symbol + source[pos + len(include) + crop_end:]

    @staticmethod
    def _get_name_and_country(filename: str) -> str:
        match = re.search(r"[^(]*\(([^)]*)\).*", filename)
        return match.group(1) if match else ''

    @staticmethod
    def _try_get_name_and_country(partname: str, names: DemoNames) -> Tuple[str, str]:
        # Always try to extract country from filename first
        sep = max(partname.rfind('.'), partname.rfind(','))
        if sep > 0 and sep + 1 < len(partname):
            country = partname[sep + 1:].strip()
            if not any(ch.isdigit() for ch in country):
                name_part = partname[:sep]
                # Check if the name part (without country) matches internal names
                if names and name_part in (names.dfName, names.uName, names.oName, names.cName):
                    return name_part, country
                return name_part, country
        # No country found - check if full partname matches internal names
        if names and partname in (names.dfName, names.uName, names.oName, names.cName):
            return partname, ''
        return partname, ''

    @staticmethod
    def _try_get_time_from_file_name(filename: str) -> Optional[timedelta]:
        parts = re.split(r"[\[\]()_]", filename)
        for part in parts:
            res = Demo._try_get_time_from_brackets(part)
            if res:
                return res
        return None

    @staticmethod
    def _try_get_time_from_brackets(part: str) -> Optional[timedelta]:
        tokens = re.split(r"[-.]", part)
        if not 2 <= len(tokens) <= 3:
            return None
        if any(not token or not token.isdigit() for token in tokens):
            return None
        return get_time_span(part)

    @staticmethod
    def _check_validity(has_time: bool, has_raw_time: bool, game_info: GameInfo, is_tas: bool, trigger_time_no_finish: bool, additional_info: Optional[Dict[str, str]]) -> Dict[str, str]:
        invalid: Dict[str, str] = {}
        params = Ext.LowerKeys(game_info.parameters) if game_info.parameters else {}
        if additional_info:
            params = Ext.JoinLowercased(additional_info, params)
        if not game_info.isFreeStyle:
            Demo._check_key(invalid, params, 'sv_cheats', 0)
        if game_info.isDefrag and ((has_time and not has_raw_time) or trigger_time_no_finish):
            invalid['client_finish'] = 'false'
        Demo._check_key(invalid, params, 'timescale', 1)
        Demo._check_key(invalid, params, 'g_speed', 320)
        Demo._check_key(invalid, params, 'g_gravity', 800)
        Demo._check_key(invalid, params, 'handicap', 100)
        Demo._check_key(invalid, params, 'g_knockback', 1000)
        if has_time and game_info.isOnline and not game_info.isFreeStyle:
            Demo._check_key(invalid, params, 'df_mp_interferenceoff', 3)
        if is_tas:
            invalid['tool_assisted'] = 'true'
        Demo._check_key(invalid, params, 'sv_fps', 125)
        Demo._check_key(invalid, params, 'com_maxfps', 125)
        g_sync = Demo._get_key(params, 'g_synchronousclients')
        if g_sync != 1:
            Demo._check_key(invalid, params, 'pmove_msec', 8)
            Demo._check_key(invalid, params, 'pmove_fixed', 1)
        Demo._check_key(invalid, params, 'g_killWallbug', 1)
        return invalid

    @staticmethod
    def _check_key(invalid: Dict[str, str], params: Dict[str, str], key: str, expected: int) -> None:
        if key in params and params[key]:
            value = Demo._get_key(params, key)
            if value < 0:
                invalid[key] = params[key]
            elif value != expected:
                invalid[key] = str(value)

    @staticmethod
    def _get_key(params: Dict[str, str], key: str) -> float:
        value = params.get(key)
        if value:
            try:
                return float(value)
            except ValueError:
                return -1
        return -1

    @staticmethod
    def _get_validities(filename: str) -> Optional[Tuple[str, str]]:
        match = re.match(r"^[^\\[]+\\[[^\\.\\]]+.[^\\]]+]\\d{2,3}\\.\\d{2}\\.\\d{3}\\(.+\\){(\\w+)=(\\w+)}(?:\\[\\d+\\])?\\.\\w+$", filename)
        if match:
            return match.group(1), match.group(2)
        return None

    @staticmethod
    def _try_get_user_id_from_file_name(file: Path) -> int:
        name_no_ext = file.stem
        match = re.match(r"^.+\\[(\\d+)\\]\\[(\\d+)\\]$", name_no_ext)
        if match:
            return int(match.group(2))
        match = re.match(r"^.+\\[.+\\].+\\(.+\\)(?:{.+})*\\[(\\d+)\\]$", name_no_ext)
        if match:
            return int(match.group(1))
        return -1

    @staticmethod
    def _get_normalized_file_name(file: Path) -> str:
        filename = file.name
        if '%' in filename:
            filename = Path(filename).name  # decode handled by OS when path created
        name_no_ext = filename[:-len(file.suffix)] if file.suffix else filename
        if '^' in name_no_ext:
            name_no_ext = remove_colors(name_no_ext)
        return name_no_ext + file.suffix.lower()



