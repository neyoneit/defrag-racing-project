from __future__ import annotations

from typing import Callable

import sys
import os
sys.path.append(os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__)))))
from demoparser import const

from .player import EntityState, PlayerState, TrType


class MapperFactory:
    EntityStateFieldNum = 51
    PlayerStateFieldNum = 48

    @staticmethod
    def update_entity_state(state: EntityState, number: int, reader, reset: bool) -> None:
        r_bool = lambda default=0: 0 if reset else default  # type: ignore
        if number == 0:
            state.pos.trTime = 0 if reset else reader.readLong()
        elif number == 1:
            state.pos.trBase[0] = 0 if reset else reader.readFloatIntegral()
        elif number == 2:
            state.pos.trBase[1] = 0 if reset else reader.readFloatIntegral()
        elif number == 3:
            state.pos.trDelta[0] = 0 if reset else reader.readFloatIntegral()
        elif number == 4:
            state.pos.trDelta[1] = 0 if reset else reader.readFloatIntegral()
        elif number == 5:
            state.pos.trBase[2] = 0 if reset else reader.readFloatIntegral()
        elif number == 6:
            state.apos.trBase[1] = 0 if reset else reader.readFloatIntegral()
        elif number == 7:
            state.pos.trDelta[2] = 0 if reset else reader.readFloatIntegral()
        elif number == 8:
            state.apos.trBase[0] = 0 if reset else reader.readFloatIntegral()
        elif number == 9:
            state.events = 0 if reset else int(reader.readNumBits(10))
        elif number == 10:
            state.angles2[1] = 0 if reset else reader.readFloatIntegral()
        elif number == 11:
            state.eType = 0 if reset else int(reader.readNumBits(8))
        elif number == 12:
            state.torsoAnim = 0 if reset else int(reader.readNumBits(8))
        elif number == 13:
            state.eventParm = 0 if reset else int(reader.readNumBits(8))
        elif number == 14:
            state.legsAnim = 0 if reset else int(reader.readNumBits(8))
        elif number == 15:
            state.groundEntityNum = 0 if reset else int(reader.readNumBits(10))
        elif number == 16:
            state.pos.trType = TrType.TR_STATIONARY if reset else TrType(reader.readByte())
        elif number == 17:
            state.eFlags = 0 if reset else int(reader.readNumBits(19))
        elif number == 18:
            state.otherEntityNum = 0 if reset else int(reader.readNumBits(10))
        elif number == 19:
            state.weapon = 0 if reset else int(reader.readNumBits(8))
        elif number == 20:
            state.clientNum = 0 if reset else int(reader.readNumBits(8))
        elif number == 21:
            state.angles[1] = 0 if reset else reader.readFloatIntegral()
        elif number == 22:
            state.pos.trDuration = 0 if reset else reader.readLong()
        elif number == 23:
            state.apos.trType = TrType.TR_STATIONARY if reset else TrType(reader.readByte())
        elif number == 24:
            state.origin[0] = 0 if reset else reader.readFloatIntegral()
        elif number == 25:
            state.origin[1] = 0 if reset else reader.readFloatIntegral()
        elif number == 26:
            state.origin[2] = 0 if reset else reader.readFloatIntegral()
        elif number == 27:
            state.solid = 0 if reset else int(reader.readNumBits(24))
        elif number == 28:
            state.powerups = 0 if reset else int(reader.readNumBits(16))
        elif number == 29:
            state.modelindex = 0 if reset else int(reader.readNumBits(8))
        elif number == 30:
            state.otherEntityNum2 = 0 if reset else int(reader.readNumBits(10))
        elif number == 31:
            state.loopSound = 0 if reset else int(reader.readNumBits(8))
        elif number == 32:
            state.generic1 = 0 if reset else int(reader.readNumBits(8))
        elif number == 33:
            state.origin2[2] = 0 if reset else reader.readFloatIntegral()
        elif number == 34:
            state.origin2[0] = 0 if reset else reader.readFloatIntegral()
        elif number == 35:
            state.origin2[1] = 0 if reset else reader.readFloatIntegral()
        elif number == 36:
            state.modelindex2 = 0 if reset else int(reader.readNumBits(8))
        elif number == 37:
            state.angles[0] = 0 if reset else reader.readFloatIntegral()
        elif number == 38:
            state.time = 0 if reset else reader.readLong()
        elif number == 39:
            state.apos.trTime = 0 if reset else reader.readLong()
        elif number == 40:
            state.apos.trDuration = 0 if reset else reader.readLong()
        elif number == 41:
            state.apos.trBase[2] = 0 if reset else reader.readFloatIntegral()
        elif number == 42:
            state.apos.trDelta[0] = 0 if reset else reader.readFloatIntegral()
        elif number == 43:
            state.apos.trDelta[1] = 0 if reset else reader.readFloatIntegral()
        elif number == 44:
            state.apos.trDelta[2] = 0 if reset else reader.readFloatIntegral()
        elif number == 45:
            state.time2 = 0 if reset else reader.readLong()
        elif number == 46:
            state.angles[2] = 0 if reset else reader.readFloatIntegral()
        elif number == 47:
            state.angles2[0] = 0 if reset else reader.readFloatIntegral()
        elif number == 48:
            state.angles2[2] = 0 if reset else reader.readFloatIntegral()
        elif number == 49:
            state.constantLight = 0 if reset else reader.readLong()
        elif number == 50:
            state.frame = 0 if reset else int(reader.readNumBits(16))

    @staticmethod
    def update_player_state(state: PlayerState, number: int, reader, reset: bool) -> None:
        if number == 0:
            state.commandTime = 0 if reset else reader.readLong()
        elif number == 1:
            state.origin[0] = 0 if reset else reader.readFloatIntegral()
        elif number == 2:
            state.origin[1] = 0 if reset else reader.readFloatIntegral()
        elif number == 3:
            state.bobCycle = 0 if reset else int(reader.readNumBits(8))
        elif number == 4:
            state.velocity[0] = 0 if reset else reader.readFloatIntegral()
        elif number == 5:
            state.velocity[1] = 0 if reset else reader.readFloatIntegral()
        elif number == 6:
            state.viewangles[1] = 0 if reset else reader.readFloatIntegral()
        elif number == 7:
            state.viewangles[0] = 0 if reset else reader.readFloatIntegral()
        elif number == 8:
            state.weaponTime = 0 if reset else int(reader.readNumBits(-16))
        elif number == 9:
            state.origin[2] = 0 if reset else reader.readFloatIntegral()
        elif number == 10:
            state.velocity[2] = 0 if reset else reader.readFloatIntegral()
        elif number == 11:
            state.legsTimer = 0 if reset else int(reader.readNumBits(8))
        elif number == 12:
            state.pm_time = 0 if reset else int(reader.readNumBits(-16))
        elif number == 13:
            state.eventSequence = 0 if reset else int(reader.readNumBits(16))
        elif number == 14:
            state.torsoAnim = 0 if reset else int(reader.readNumBits(8))
        elif number == 15:
            state.movementDir = 0 if reset else int(reader.readNumBits(4))
        elif number == 16:
            state.events[0] = 0 if reset else int(reader.readNumBits(8))
        elif number == 17:
            state.legsAnim = 0 if reset else int(reader.readNumBits(8))
        elif number == 18:
            state.events[1] = 0 if reset else int(reader.readNumBits(8))
        elif number == 19:
            state.pm_flags = 0 if reset else int(reader.readNumBits(16))
        elif number == 20:
            state.groundEntityNum = 0 if reset else int(reader.readNumBits(10))
        elif number == 21:
            state.weaponstate = 0 if reset else int(reader.readNumBits(4))
        elif number == 22:
            state.eFlags = 0 if reset else int(reader.readNumBits(16))
        elif number == 23:
            state.externalEvent = 0 if reset else int(reader.readNumBits(10))
        elif number == 24:
            state.gravity = 0 if reset else int(reader.readNumBits(16))
        elif number == 25:
            state.speed = 0 if reset else int(reader.readNumBits(16))
        elif number == 26:
            state.delta_angles[1] = 0 if reset else int(reader.readNumBits(16))
        elif number == 27:
            state.externalEventParm = 0 if reset else int(reader.readNumBits(8))
        elif number == 28:
            state.viewheight = 0 if reset else int(reader.readNumBits(-8))
        elif number == 29:
            state.damageEvent = 0 if reset else int(reader.readNumBits(8))
        elif number == 30:
            state.damageYaw = 0 if reset else int(reader.readNumBits(8))
        elif number == 31:
            state.damagePitch = 0 if reset else int(reader.readNumBits(8))
        elif number == 32:
            state.damageCount = 0 if reset else int(reader.readNumBits(8))
        elif number == 33:
            state.generic1 = 0 if reset else int(reader.readNumBits(8))
        elif number == 34:
            state.pm_type = 0 if reset else int(reader.readNumBits(8))
        elif number == 35:
            state.delta_angles[0] = 0 if reset else int(reader.readNumBits(16))
        elif number == 36:
            state.delta_angles[2] = 0 if reset else int(reader.readNumBits(16))
        elif number == 37:
            state.torsoTimer = 0 if reset else int(reader.readNumBits(12))
        elif number == 38:
            state.eventParms[0] = 0 if reset else int(reader.readNumBits(8))
        elif number == 39:
            state.eventParms[1] = 0 if reset else int(reader.readNumBits(8))
        elif number == 40:
            state.clientNum = 0 if reset else int(reader.readNumBits(8))
        elif number == 41:
            state.weapon = 0 if reset else int(reader.readNumBits(5))
        elif number == 42:
            state.viewangles[2] = 0 if reset else reader.readFloatIntegral()
        elif number == 43:
            state.grapplePoint[0] = 0 if reset else reader.readFloatIntegral()
        elif number == 44:
            state.grapplePoint[1] = 0 if reset else reader.readFloatIntegral()
        elif number == 45:
            state.grapplePoint[2] = 0 if reset else reader.readFloatIntegral()
        elif number == 46:
            state.jumppad_ent = 0 if reset else int(reader.readNumBits(10))
        elif number == 47:
            state.loopSound = 0 if reset else int(reader.readNumBits(16))

