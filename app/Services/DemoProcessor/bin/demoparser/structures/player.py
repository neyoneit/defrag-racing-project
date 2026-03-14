from __future__ import annotations

from dataclasses import dataclass, field
from enum import IntEnum
from typing import List


class TrType(IntEnum):
    TR_STATIONARY = 0
    TR_INTERPOLATE = 1
    TR_LINEAR = 2
    TR_LINEAR_STOP = 3
    TR_SINE = 4
    TR_GRAVITY = 5


@dataclass
class Trajectory:
    trType: TrType = TrType.TR_STATIONARY
    trTime: int = 0
    trDuration: int = 0
    trBase: List[float] = field(default_factory=lambda: [0.0, 0.0, 0.0])
    trDelta: List[float] = field(default_factory=lambda: [0.0, 0.0, 0.0])

    def copy_from(self, other: "Trajectory") -> None:
        self.trType = other.trType
        self.trTime = other.trTime
        self.trDuration = other.trDuration
        self.trBase = other.trBase.copy()
        self.trDelta = other.trDelta.copy()


@dataclass
class EntityState:
    number: int = 0
    eType: int = 0
    eFlags: int = 0
    pos: Trajectory = field(default_factory=Trajectory)
    apos: Trajectory = field(default_factory=Trajectory)
    time: int = 0
    time2: int = 0
    origin: List[float] = field(default_factory=lambda: [0.0, 0.0, 0.0])
    origin2: List[float] = field(default_factory=lambda: [0.0, 0.0, 0.0])
    angles: List[float] = field(default_factory=lambda: [0.0, 0.0, 0.0])
    angles2: List[float] = field(default_factory=lambda: [0.0, 0.0, 0.0])
    otherEntityNum: int = 0
    otherEntityNum2: int = 0
    groundEntityNum: int = 0
    constantLight: int = 0
    loopSound: int = 0
    modelindex: int = 0
    modelindex2: int = 0
    clientNum: int = 0
    frame: int = 0
    solid: int = 0
    events: int = 0
    eventParm: int = 0
    powerups: int = 0
    weapon: int = 0
    legsAnim: int = 0
    torsoAnim: int = 0
    generic1: int = 0

    def copy(self, other: "EntityState") -> None:
        self.number = other.number
        self.eType = other.eType
        self.eFlags = other.eFlags
        self.pos.copy_from(other.pos)
        self.apos.copy_from(other.apos)
        self.time = other.time
        self.time2 = other.time2
        self.origin = other.origin.copy()
        self.origin2 = other.origin2.copy()
        self.angles = other.angles.copy()
        self.angles2 = other.angles2.copy()
        self.otherEntityNum = other.otherEntityNum
        self.otherEntityNum2 = other.otherEntityNum2
        self.groundEntityNum = other.groundEntityNum
        self.constantLight = other.constantLight
        self.loopSound = other.loopSound
        self.modelindex = other.modelindex
        self.modelindex2 = other.modelindex2
        self.clientNum = other.clientNum
        self.frame = other.frame
        self.solid = other.solid
        self.events = other.events
        self.eventParm = other.eventParm
        self.powerups = other.powerups
        self.weapon = other.weapon
        self.legsAnim = other.legsAnim
        self.torsoAnim = other.torsoAnim
        self.generic1 = other.generic1


@dataclass
class PlayerState:
    class StatIndex(IntEnum):
        STAT_HEALTH = 0
        STAT_ITEMS = 1
        STAT_WEAPONS = 2
        STAT_ARMOR = 3
        STAT_DEAD_YAW = 4
        STAT_CLIENTS_READY = 5
        STAT_MAX_HEALTH = 6
        STAT_TIMER_UPPER = 7
        STAT_TIMER_LOWER = 8

    commandTime: int = 0
    pm_type: int = 0
    bobCycle: int = 0
    pm_flags: int = 0
    pm_time: int = 0
    origin: List[float] = field(default_factory=lambda: [0.0, 0.0, 0.0])
    velocity: List[float] = field(default_factory=lambda: [0.0, 0.0, 0.0])
    weaponTime: int = 0
    gravity: int = 0
    speed: int = 0
    delta_angles: List[int] = field(default_factory=lambda: [0, 0, 0])
    groundEntityNum: int = 0
    legsTimer: int = 0
    legsAnim: int = 0
    torsoTimer: int = 0
    torsoAnim: int = 0
    movementDir: int = 0
    grapplePoint: List[float] = field(default_factory=lambda: [0.0, 0.0, 0.0])
    eFlags: int = 0
    eventSequence: int = 0
    events: List[int] = field(default_factory=lambda: [0, 0])
    eventParms: List[int] = field(default_factory=lambda: [0, 0])
    externalEvent: int = 0
    externalEventParm: int = 0
    externalEventTime: int = 0
    clientNum: int = 0
    weapon: int = 0
    weaponstate: int = 0
    viewangles: List[float] = field(default_factory=lambda: [0.0, 0.0, 0.0])
    viewheight: int = 0
    damageEvent: int = 0
    damageYaw: int = 0
    damagePitch: int = 0
    damageCount: int = 0
    stats: List[int] = field(default_factory=lambda: [0] * 16)
    persistant: List[int] = field(default_factory=lambda: [0] * 16)
    powerups: List[int] = field(default_factory=lambda: [0] * 16)
    ammo: List[int] = field(default_factory=lambda: [0] * 16)
    generic1: int = 0
    loopSound: int = 0
    jumppad_ent: int = 0
    ping: int = 0
    pmove_framecount: int = 0
    jumppad_frame: int = 0
    entityEventSequence: int = 0

    def copy(self, other: "PlayerState") -> None:
        self.commandTime = other.commandTime
        self.pm_type = other.pm_type
        self.bobCycle = other.bobCycle
        self.pm_flags = other.pm_flags
        self.pm_time = other.pm_time
        self.origin = other.origin.copy()
        self.velocity = other.velocity.copy()
        self.weaponTime = other.weaponTime
        self.gravity = other.gravity
        self.speed = other.speed
        self.delta_angles = other.delta_angles.copy()
        self.groundEntityNum = other.groundEntityNum
        self.legsTimer = other.legsTimer
        self.legsAnim = other.legsAnim
        self.torsoTimer = other.torsoTimer
        self.torsoAnim = other.torsoAnim
        self.movementDir = other.movementDir
        self.grapplePoint = other.grapplePoint.copy()
        self.eFlags = other.eFlags
        self.eventSequence = other.eventSequence
        self.events = other.events.copy()
        self.eventParms = other.eventParms.copy()
        self.externalEvent = other.externalEvent
        self.externalEventParm = other.externalEventParm
        self.externalEventTime = other.externalEventTime
        self.clientNum = other.clientNum
        self.weapon = other.weapon
        self.weaponstate = other.weaponstate
        self.viewangles = other.viewangles.copy()
        self.viewheight = other.viewheight
        self.damageEvent = other.damageEvent
        self.damageYaw = other.damageYaw
        self.damagePitch = other.damagePitch
        self.damageCount = other.damageCount
        self.stats = other.stats.copy()
        self.persistant = other.persistant.copy()
        self.powerups = other.powerups.copy()
        self.ammo = other.ammo.copy()
        self.generic1 = other.generic1
        self.loopSound = other.loopSound
        self.jumppad_ent = other.jumppad_ent
        self.ping = other.ping
        self.pmove_framecount = other.pmove_framecount
        self.jumppad_frame = other.jumppad_frame
        self.entityEventSequence = other.entityEventSequence
