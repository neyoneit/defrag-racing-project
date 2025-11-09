from __future__ import annotations

import re
from dataclasses import dataclass, field
from datetime import datetime, timedelta
from typing import List, Optional


def remove_colors(text: str | None) -> str | None:
    return None if text is None else re.sub(r"\^.", "", text)


def remove_non_ascii(text: str | None) -> str | None:
    return None if text is None else re.sub(r"[^\u0020-\u007F]+", "", text)


def get_time_span(value: str) -> timedelta:
    parts = value.split(':')
    if len(parts) == 3:
        minutes, seconds, millis = parts
    elif len(parts) == 2:
        minutes = '0'
        seconds, millis = parts
    else:
        raise ValueError('Invalid time format')

    # Strip non-numeric characters from each component
    # This handles cases like "984!!!" or "984(" that appear in some demos
    minutes = re.sub(r'[^0-9]', '', minutes)
    seconds = re.sub(r'[^0-9]', '', seconds)
    millis = re.sub(r'[^0-9]', '', millis)

    return timedelta(minutes=int(minutes), seconds=int(seconds), milliseconds=int(millis))


@dataclass
class AdditionalTimeInfo:
    source: str = ''
    time: timedelta = timedelta(0)
    cpData: List[timedelta] = field(default_factory=list)
    offset: int = -1
    pmove_depends: int = -1
    pmove_fixed: int = -1
    sv_fps: int = -1
    com_maxfps: int = -1
    g_sync: int = -1
    pmove_msec: int = -1
    all_weapons: int = -1
    no_damage: int = -1
    enable_powerups: int = -1
    isTr: bool = False

    def toDictionary(self) -> dict[str, str]:
        result: dict[str, str] = {}
        if self.pmove_fixed >= 0:
            result['pmove_fixed'] = str(self.pmove_fixed)
        if self.sv_fps >= 0:
            result['sv_fps'] = str(self.sv_fps)
        if self.com_maxfps >= 0:
            result['com_maxfps'] = str(self.com_maxfps)
        if self.g_sync >= 0:
            result['g_sync'] = str(self.g_sync)
        if self.pmove_msec >= 0:
            result['pmove_msec'] = str(self.pmove_msec)
        if self.all_weapons >= 0:
            result['all_weapons'] = str(self.all_weapons)
        if self.no_damage >= 0:
            result['no_damage'] = str(self.no_damage)
        if self.enable_powerups >= 0:
            result['enable_powerups'] = str(self.enable_powerups)
        return result


def _clean(text: str) -> str:
    return re.sub(r"(\^[0-9]|\\\"|\\n|\")", "", text)


def get_name_online(demo_time_cmd: str) -> str:
    cleaned = _clean(demo_time_cmd)
    name = cleaned[6:cleaned.lower().rfind(' reached')]
    return normalize_name(name)


@dataclass
class Q3DFResult:
    name: str
    q3dfName: Optional[str]
    time: timedelta


def get_name_q3df(demo_time_cmd: str) -> Optional[Q3DFResult]:
    text = remove_non_ascii(demo_time_cmd) or ''
    text = remove_colors(text) or ''
    stripped = text.replace('chat "', '').rstrip('"')

    def parse_prefix(prefix: str) -> tuple[str, Optional[str]]:
        prefix = prefix.strip()
        if '(' in prefix and ')' in prefix:
            idx = prefix.rfind('(')
            name = prefix[:idx].strip()
            q3df = prefix[idx + 1:].strip(')')
        else:
            name = prefix
            q3df = None
        return normalize_name(name), normalize_name(q3df) if q3df else None

    def parse_time(segment: str) -> str:
        segment = segment.strip()
        segment = segment.split(' ', 1)[0]
        segment = segment.split('(', 1)[0]
        return segment.strip()

    if ' broke the server record with ' in stripped:
        prefix, rest = stripped.split(' broke the server record with ', 1)
        name, q3df = parse_prefix(prefix)
        time_part = parse_time(rest)
        return Q3DFResult(name=name, q3dfName=q3df, time=get_time_span(time_part))

    if ' equalled the server record with ' in stripped:
        prefix, rest = stripped.split(' equalled the server record with ', 1)
        name, q3df = parse_prefix(prefix)
        time_part = parse_time(rest)
        return Q3DFResult(name=name, q3dfName=q3df, time=get_time_span(time_part))

    if ', you are now rank' in stripped and ' with ' in stripped:
        prefix, rest = stripped.split(', you are now rank', 1)
        name, q3df = parse_prefix(prefix)
        if ' with ' in rest:
            time_part = parse_time(rest.split(' with ', 1)[1])
            return Q3DFResult(name=name, q3dfName=q3df, time=get_time_span(time_part))

    if stripped.startswith('console: ') and ' with ' in stripped:
        body = stripped[len('console: '):]
        name_part, rest = body.split(' is now rank', 1)
        name, q3df = parse_prefix(name_part)
        time_part = parse_time(rest.split(' with ', 1)[1])
        return Q3DFResult(name=name, q3dfName=q3df, time=get_time_span(time_part))

    return None


def get_time_online(demo_time_cmd: str) -> timedelta:
    cleaned = _clean(demo_time_cmd)
    demo_time = cleaned[cleaned.rfind('in') + 3:]
    est_index = demo_time.find(' (est')
    if est_index > 0:
        demo_time = demo_time[:est_index]
    return get_time_span(demo_time)


def get_time_offline_normal(demo_time_cmd: str) -> timedelta:
    cleaned = re.sub(r"(\^.|\\\"|\\n|\")", "", demo_time_cmd)
    cleaned = cleaned[cleaned.find(':') + 2:]
    space = cleaned.find(' ')
    if space > 0:
        cleaned = cleaned[:space].strip()
    return get_time_span(cleaned)


def get_name_offline(demo_time_cmd: str) -> str:
    cleaned = re.sub(r"(\^.|\\\"|\\n|\")", "", demo_time_cmd)
    cleaned = cleaned[24:]
    space = cleaned.find(' :')
    if space >= 0:
        cleaned = cleaned[:space]
    return normalize_name(cleaned)


def get_time_old1(demo_time_cmd: str) -> timedelta:
    parts = demo_time_cmd.split(' ')
    return get_time_span(parts[2])


def get_name_offline_old1(demo_time_cmd: str) -> str:
    parts = demo_time_cmd.split(' ')
    return normalize_name(remove_colors(parts[3]) or '')


def get_time_old3(demo_time_cmd: str) -> timedelta:
    milliseconds = int(demo_time_cmd.split(' ')[1])
    return timedelta(milliseconds=milliseconds)


def get_date_for_demo(text: str) -> Optional[datetime]:
    date_string = text[13:].replace('\n', '').replace('"', '').strip()
    for pattern in ("%m-%d-%y %H:%M", "%m-%d-%y %H:%M"):
        try:
            return datetime.strptime(date_string, pattern)
        except ValueError:
            continue
    return None


def normalize_name(name: str) -> str:
    return re.sub(r"[^a-zA-Z0-9!#$%&'()+,\-.;=\[\]^_{}]", "", name)


def parse_additional_info(text: str) -> AdditionalTimeInfo:
    parts = text.split(' ')
    info = AdditionalTimeInfo(source=text)
    millis = _to_int(parts, 1, -1)
    if millis < 0:
        return info
    info.time = timedelta(milliseconds=millis)
    offset = _to_int(parts, 2, -1)
    if offset < 0:
        return info
    info.offset = offset
    if offset > 0:
        for idx in range(offset):
            cp_millis = _to_int(parts, 3 + idx, -1)
            info.cpData.append(timedelta(milliseconds=cp_millis))
    if len(parts) <= offset + 3:
        return info
    stats_string = parts[offset + 3]
    if stats_string != 'Stats':
        return info
    info.pmove_depends = _to_int(parts, offset + 4, -1)
    info.pmove_fixed = _to_int(parts, offset + 5, -1)
    info.sv_fps = _to_int(parts, offset + 6, -1)
    info.com_maxfps = _to_int(parts, offset + 7, -1)
    info.g_sync = _to_int(parts, offset + 8, -1)
    if info.pmove_depends <= 4:
        info.pmove_msec = _to_int(parts, offset + 9, -1)
    info.all_weapons = _to_int(parts, offset + 10, -1)
    info.no_damage = _to_int(parts, offset + 11, -1)
    info.enable_powerups = _to_int(parts, offset + 12, -1)
    return info


def _to_int(parts: List[str], index: int, default: int) -> int:
    if index < len(parts):
        try:
            return int(parts[index])
        except ValueError:
            return default
    return default
