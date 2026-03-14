from __future__ import annotations

from dataclasses import dataclass
from datetime import datetime, timedelta
from typing import Dict, Iterable, List, Optional

from ext import Ext
from console_string_utils import (
    AdditionalTimeInfo,
    Q3DFResult,
    get_date_for_demo,
    get_name_offline,
    get_name_offline_old1,
    get_name_online,
    get_name_q3df,
    get_time_offline_normal,
    get_time_old1,
    get_time_old3,
    get_time_online,
    parse_additional_info,
)


@dataclass
class TimeStringInfo:
    source: str
    time: timedelta
    oName: Optional[str] = None
    lName: Optional[str] = None


@dataclass
class DateStringInfo:
    source: str
    recordDate: Optional[datetime]


class ConsoleComandsParser:
    def __init__(self, console_commands: Dict[int, tuple[int, str]]) -> None:
        self.timeStrings: List[TimeStringInfo] = []
        self.dateStrings: List[DateStringInfo] = []
        self.additionalInfos: List[AdditionalTimeInfo] = []

        timer_started_count = 0
        for _, (_, value) in console_commands.items():
            if value.startswith('print "Date:'):
                self.dateStrings.append(DateStringInfo(source=value, recordDate=get_date_for_demo(value)))
            elif 'reached the finish line in' in value:
                self.timeStrings.append(TimeStringInfo(
                    source=value,
                    time=get_time_online(value),
                    oName=get_name_online(value),
                ))
            elif any(token in value for token in (
                'broke the server record',
                'you are now rank',
                'equalled the server record with',
            )):
                result = get_name_q3df(value)
                if result is not None:
                    self.timeStrings.append(TimeStringInfo(
                        source=value,
                        time=result.time,
                        oName=result.name,
                        lName=result.q3dfName,
                    ))
            elif value.startswith('print "Time performed by'):
                self.timeStrings.append(TimeStringInfo(
                    source=value,
                    time=get_time_offline_normal(value),
                    oName=get_name_offline(value),
                ))
            elif value.startswith('NewTime'):
                self.timeStrings.append(TimeStringInfo(
                    source=value,
                    time=get_time_old1(value),
                    oName=get_name_offline_old1(value),
                ))
            elif value.startswith('print "^3Time Performed:'):
                self.timeStrings.append(TimeStringInfo(
                    source=value,
                    time=get_time_offline_normal(value),
                ))
            elif value.startswith('newTime'):
                self.timeStrings.append(TimeStringInfo(
                    source=value,
                    time=get_time_old3(value),
                ))
            elif value.startswith('TimerStarted'):
                timer_started_count += 1
            elif value.startswith('TimerStopped'):
                info = parse_additional_info(value)
                if timer_started_count > 1:
                    info.isTr = True
                timer_started_count = 0
                self.additionalInfos.append(info)

    def getFastestTimeStringInfo(self, names) -> Optional[TimeStringInfo]:
        if not self.timeStrings and self.additionalInfos:
            fastest_additional = Ext.MinOf(self.additionalInfos, lambda x: int(x.time.total_seconds() * 1000))
            if fastest_additional is not None:
                return TimeStringInfo(
                    source=fastest_additional.source,
                    time=fastest_additional.time,
                )
            return None
        if len(self.timeStrings) == 1:
            return self.timeStrings[0]
        if not self.timeStrings:
            return None

        candidates = [
            ts for ts in self.timeStrings
            if ts.oName and ts.oName in {names.dfName, names.uName}
        ]
        if not candidates:
            groups = {ts.oName for ts in self.timeStrings}
            if len(groups) == 1:
                candidates = list(self.timeStrings)
        if not candidates:
            return None

        fastest = Ext.MinOf(candidates, lambda x: int(x.time.total_seconds() * 1000))
        if fastest is None:
            return None
        ties = [ts for ts in candidates if ts.time == fastest.time]
        if len(ties) > 1:
            for item in ties:
                if item.lName:
                    return item
        return fastest

    def getGoodTimeStringInfo(self, names, time_ms: int) -> Optional[TimeStringInfo]:
        if time_ms > 0:
            for ts in self.timeStrings:
                if ts.time.total_seconds() * 1000 == time_ms:
                    if ts.oName:
                        if ts.oName in {names.uName, names.dfName}:
                            return ts
                    else:
                        return ts
        else:
            user_strings = [
                ts for ts in self.timeStrings
                if ts.oName and ts.oName in {names.uName, names.dfName}
            ]
            if user_strings:
                return Ext.MinOf(user_strings, lambda x: int(x.time.total_seconds() * 1000))
        return None

