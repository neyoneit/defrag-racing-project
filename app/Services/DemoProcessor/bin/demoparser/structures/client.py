from __future__ import annotations

from dataclasses import dataclass, field
from typing import Dict, List, Tuple

import sys
import os
sys.path.append(os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__)))))
from demoparser import const
from .player import PlayerState, EntityState


dataclass_client = dataclass


@dataclass_client
class ClientConnection:
    clientNum: int = 0
    connectPacketCount: int = 0
    checksumFeed: int = 0
    reliableSequence: int = 0
    reliableAcknowledge: int = 0
    serverMessageSequence: int = 0
    serverCommandSequence: int = 0
    lastExecutedServerCommand: int = 0
    console: Dict[int, Tuple[int, str]] = field(default_factory=dict)
    configs: Dict[int, str] = field(default_factory=dict)
    errors: Dict[str, str] = field(default_factory=dict)
    entityBaselines: Dict[int, EntityState] = field(default_factory=dict)
    demowaiting: bool = False


@dataclass_client
class CLSnapshot:
    valid: bool = False
    snapFlags: int = 0
    serverTime: int = 0
    messageNum: int = 0
    deltaNum: int = 0
    ping: int = 0
    areamask: bytearray = field(default_factory=lambda: bytearray(const.MAX_MAP_AREA_BYTES))
    cmdNum: int = 0
    ps: PlayerState = field(default_factory=PlayerState)
    numEntities: int = 0
    parseEntitiesNum: int = 0
    serverCommandNum: int = 0


@dataclass_client
class ClientState:
    snap: CLSnapshot = field(default_factory=CLSnapshot)
    newSnapshots: bool = False
    gameState: Dict[int, str] = field(default_factory=dict)
    parseEntitiesNum: int = 0
    snapshots: Dict[int, CLSnapshot] = field(default_factory=dict)
    entityBaselines: Dict[int, EntityState] = field(default_factory=dict)
    parseEntities: Dict[int, EntityState] = field(default_factory=dict)
    clientEvents: List["ClientEvent"] = field(default_factory=list)
    lastClientEvent: "ClientEvent" | None = None
    clientConfig: Dict[str, str] | None = None
    gameConfig: Dict[str, str] | None = None
    mapname: str = ""
    mapNameChecksum: int = 0
    dfvers: int = 0
    isOnline: bool = False
    isCheatsOn: bool = False
    maxSpeed: int = 0
    isCpmInParams: bool | None = None
    isCpmInSnapshots: bool | None = None


from .client_event import ClientEvent  # noqa: E402
