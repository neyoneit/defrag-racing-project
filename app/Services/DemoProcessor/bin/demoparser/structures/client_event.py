from __future__ import annotations

from dataclasses import dataclass

import sys
import os
sys.path.append(os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__)))))
from demoparser import const
from .client import CLSnapshot


@dataclass
class ClientEvent:
    eventStartFile: bool = False
    eventStartTime: bool = False
    eventTimeReset: bool = False
    eventFinish: bool = False
    eventCheckPoint: bool = False
    eventSomeTrigger: bool = False
    eventChangePmType: bool = False
    eventChangeUser: bool = False
    time: int = 0
    timeHasError: bool = False
    timeByServerTime: int = 0
    serverTime: int = 0
    playerNum: int = 0
    playerMode: int = 0
    userStat: int = 0
    speed: int = 0

    class PlayerMode:
        PM_NORMAL = 0
        PM_NOCLIP = 1
        PM_SPECTATOR = 2
        PM_DEAD = 3

    def __init__(self, time_value: int, time_has_error: bool, snapshot: CLSnapshot) -> None:
        if not time_has_error:
            self.time = time_value
        self.timeHasError = time_has_error
        self.serverTime = snapshot.serverTime
        self.playerNum = snapshot.ps.clientNum
        self.userStat = snapshot.ps.stats[12]
        self.playerMode = snapshot.ps.pm_type

    @property
    def hasAnyEvent(self) -> bool:
        return (
            self.eventStartFile or self.eventStartTime or self.eventTimeReset or self.eventFinish or
            self.eventCheckPoint or self.eventChangePmType or self.eventChangeUser or self.eventSomeTrigger
        )

    @property
    def timeNoError(self) -> int:
        return self.timeByServerTime if self.timeHasError else self.time


pmTypesStrings = ["normal", "noclip", "spectator", "death"]
