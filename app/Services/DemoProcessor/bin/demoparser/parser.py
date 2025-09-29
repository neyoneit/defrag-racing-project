from __future__ import annotations

import struct
from dataclasses import dataclass
from typing import Optional

from . import const, q3_svc
from .huffman import Q3HuffmanReader
from .parser_exceptions import (
    ErrorBadCommandInParseGameState,
    ErrorBaselineNumberOutOfRange,
    ErrorCantOpenFile,
    ErrorDeltaFrameTooOld,
    ErrorDeltaParseEntitiesNumTooOld,
    ErrorDeltaFromInvalidFrame,
    ErrorParsePacketEntitiesEndOfMessage,
    ErrorParseSnapshotInvalidsize,
    ErrorUnableToParseDeltaEntityState,
)
from .structures.client import CLSnapshot, ClientConnection, ClientState
from .structures.client_event import ClientEvent
from .structures.mapper import MapperFactory
from .structures.player import EntityState
from .utils import split_config
import sys
import os
sys.path.append(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
from ext import Ext


def _get_or_create(dictionary, key, factory):
    value = dictionary.get(key)
    if value is None:
        value = factory()
        dictionary[key] = value
    return value


@dataclass
class Q3DemoMessage:
    sequence: int
    size: int
    data: bytes


class Q3MessageStream:
    def __init__(self, file_name: str) -> None:
        self._handle = open(file_name, 'rb')

    def next_message(self) -> Optional[Q3DemoMessage]:
        header = self._handle.read(8)
        if len(header) != 8:
            return None
        sequence, msg_length = struct.unpack('<ii', header)
        if sequence == -1 and msg_length == -1:
            return None
        if msg_length < 0 or msg_length > const.Q3_MESSAGE_MAX_SIZE:
            raise ErrorCantOpenFile()
        data = self._handle.read(msg_length)
        if len(data) != msg_length:
            return None
        return Q3DemoMessage(sequence=sequence, size=msg_length, data=data)

    def close(self) -> None:
        self._handle.close()


class Q3DemoConfigParser:
    def __init__(self) -> None:
        self.clc = ClientConnection()
        self.client = ClientState()
        self.serverTime = 0

    def parse(self, message: Q3DemoMessage) -> bool:
        self.serverTime = 0
        self.clc.serverMessageSequence = message.sequence
        reader = Q3HuffmanReader(message.data)
        reader.readLong()
        while not reader.isEOD():
            command = reader.readByte()
            if command in (q3_svc.BAD, q3_svc.NOP):
                return True
            if command == q3_svc.EOF:
                return True
            if command == q3_svc.SERVERCOMMAND:
                self._parse_server_command(reader)
            elif command == q3_svc.GAMESTATE:
                self._parse_game_state(reader)
            elif command == q3_svc.SNAPSHOT:
                self._parse_snapshot(reader)
            else:
                return True
        return True

    def _parse_server_command(self, reader: Q3HuffmanReader) -> None:
        key = reader.readLong()
        value = reader.readString()
        self.clc.console[key] = (self.serverTime, value)

    def _parse_game_state(self, reader: Q3HuffmanReader) -> None:
        reader.readLong()
        while True:
            command = reader.readByte()
            if command == q3_svc.EOF:
                break
            if command == q3_svc.CONFIGSTRING:
                key = reader.readShort()
                if key < 0 or key > const.MAX_CONFIGSTRINGS:
                    return
                self.clc.configs[key] = reader.readBigString()
            elif command == q3_svc.BASELINE:
                newnum = reader.readNumBits(const.GENTITYNUM_BITS)
                if newnum < 0 or newnum >= const.MAX_GENTITIES:
                    self._log_error(ErrorBaselineNumberOutOfRange())
                    return
                entity = _get_or_create(self.clc.entityBaselines, newnum, EntityState)
                if not reader.readDeltaEntity(entity, int(newnum)):
                    self._log_error(ErrorUnableToParseDeltaEntityState())
                    return
            else:
                self._log_error(ErrorBadCommandInParseGameState())
                return
        self.clc.clientNum = reader.readLong()
        self.clc.checksumFeed = reader.readLong()

    def _parse_snapshot(self, decoder: Q3HuffmanReader) -> None:
        if self.client.clientConfig is None:
            self.client.clientConfig = {}
            game_cfg = self.clc.configs.get(const.Q3_DEMO_CFG_FIELD_GAME)
            if game_cfg is not None:
                game_config = split_config(game_cfg)
                self.client.isCheatsOn = Ext.GetOrZero(game_config, 'sv_cheats') > 0
            client_cfg = self.clc.configs.get(const.Q3_DEMO_CFG_FIELD_CLIENT)
            if client_cfg is not None:
                client_config = split_config(client_cfg)
                self.client.clientConfig = client_config
                self.client.dfvers = Ext.GetOrZero(client_config, 'defrag_vers')
                mapname = Ext.GetOrNull(client_config, 'mapname')
                self.client.mapname = mapname or ''
                self.client.mapNameChecksum = self._map_checksum(self.client.mapname)
                self.client.isOnline = Ext.GetOrZero(client_config, 'defrag_gametype') > 4
        new_snap = CLSnapshot()
        new_snap.serverCommandNum = self.clc.serverCommandSequence
        new_snap.serverTime = decoder.readLong()
        new_snap.messageNum = self.clc.serverMessageSequence
        self.serverTime = new_snap.serverTime
        delta_num = decoder.readByte()
        new_snap.deltaNum = -1 if delta_num == 0 else new_snap.messageNum - delta_num
        new_snap.snapFlags = decoder.readByte()
        old_snapshot: Optional[CLSnapshot] = None
        if new_snap.deltaNum <= 0:
            new_snap.valid = True
            self.clc.demowaiting = False
        else:
            old_snapshot = _get_or_create(self.client.snapshots, new_snap.deltaNum & const.PACKET_MASK, CLSnapshot)
            if not old_snapshot.valid:
                self._log_error(ErrorDeltaFromInvalidFrame())
            elif old_snapshot.messageNum != new_snap.deltaNum:
                self._log_error(ErrorDeltaFrameTooOld())
            elif (self.client.parseEntitiesNum - old_snapshot.parseEntitiesNum) > (const.MAX_PARSE_ENTITIES - 128):
                self._log_error(ErrorDeltaParseEntitiesNumTooOld())
            else:
                new_snap.valid = True
        length = decoder.readByte()
        if length > len(new_snap.areamask):
            self._log_error(ErrorParseSnapshotInvalidsize())
            return
        decoder.readData(new_snap.areamask, length)
        if old_snapshot is not None:
            new_snap.ps.copy(old_snapshot.ps)
        decoder.readDeltaPlayerState(new_snap.ps)
        self._parse_packet_entities(decoder, old_snapshot, new_snap)
        if not new_snap.valid:
            return
        old_message = self.client.snap.messageNum + 1
        if new_snap.messageNum - old_message >= const.PACKET_BACKUP:
            old_message = new_snap.messageNum - (const.PACKET_BACKUP - 1)
        for message_num in range(old_message, new_snap.messageNum):
            stored = self.client.snapshots.get(message_num & const.PACKET_MASK)
            if stored is not None:
                stored.valid = False
        self.client.snap = new_snap
        self.client.snap.ping = 0
        self.client.snapshots[self.client.snap.messageNum & const.PACKET_MASK] = self.client.snap
        self.client.newSnapshots = True
        self._update_client_events(new_snap)

    def _update_client_events(self, snapshot: CLSnapshot) -> None:
        if self.client.dfvers <= 0 or not self.client.mapname:
            return
        result = self._get_time(snapshot.ps, int(snapshot.serverTime), self.client.dfvers, self.client.mapNameChecksum)
        events = self.client.clientEvents
        event = ClientEvent(result.Time, result.HasError, snapshot)
        prev_stat = 0
        new_stat = snapshot.ps.stats[12]
        if events:
            previous = events[-1]
            if previous.playerNum != snapshot.ps.clientNum:
                event.eventChangeUser = True
            if previous.playerMode != snapshot.ps.pm_type:
                event.eventChangePmType = True
            prev_stat = previous.userStat
            is_normal = snapshot.ps.pm_type == ClientEvent.PlayerMode.PM_NORMAL
            prev_normal = previous.playerMode == ClientEvent.PlayerMode.PM_NORMAL
            if prev_stat != new_stat:
                if (prev_stat & 4) != (new_stat & 4):
                    if is_normal:
                        if (prev_stat & 2) == 0:
                            event.eventStartTime = True
                        else:
                            event.eventTimeReset = True
                elif (prev_stat & 8) != (new_stat & 8):
                    if (is_normal or prev_normal) and not event.eventChangeUser:
                        event.eventFinish = True
                elif (prev_stat & 16) != (new_stat & 16):
                    if is_normal:
                        event.eventCheckPoint = True
                elif previous.eventFinish and (prev_stat & 2) != 0 and (new_stat & 2) == 0:
                    if (is_normal or prev_normal) and not event.eventChangeUser:
                        previous.eventFinish = False
                        if not previous.hasAnyEvent:
                            events.pop()
                        event.eventFinish = True
                elif previous.eventStartTime and (prev_stat & 2) == 0 and (new_stat & 2) != 0:
                    if is_normal:
                        previous.eventStartTime = False
                        if not previous.hasAnyEvent:
                            events.pop()
                        event.eventStartTime = True
                elif previous.eventTimeReset and (prev_stat & 4) == 0 and (new_stat & 2) != 0:
                    if is_normal:
                        previous.eventTimeReset = False
                        if not previous.hasAnyEvent:
                            events.pop()
                        event.eventTimeReset = True
                else:
                    event.eventSomeTrigger = True
        else:
            event.eventStartFile = True
            if snapshot.ps.pm_type == ClientEvent.PlayerMode.PM_NORMAL:
                if (prev_stat & 4) != (new_stat & 4) and (prev_stat & 2) == 0:
                    event.eventStartTime = True
        x_vel = abs(snapshot.ps.velocity[0])
        y_vel = abs(snapshot.ps.velocity[1])
        speed = int((x_vel ** 2 + y_vel ** 2) ** 0.5)
        event.speed = speed
        if speed > self.client.maxSpeed:
            self.client.maxSpeed = speed
        if event.hasAnyEvent:
            events.append(event)
        self.client.lastClientEvent = event

    def _parse_packet_entities(self, decoder: Q3HuffmanReader, oldframe: Optional[CLSnapshot], newframe: CLSnapshot) -> None:
        newframe.parseEntitiesNum = self.client.parseEntitiesNum
        newframe.numEntities = 0
        oldindex = 0
        if oldframe is None or oldframe.numEntities == 0:
            oldnum = 99999
            oldstate = None
        else:
            oldstate = _get_or_create(self.client.parseEntities, (oldframe.parseEntitiesNum + oldindex) & (const.MAX_PARSE_ENTITIES - 1), EntityState)
            oldnum = oldstate.number
        while True:
            newnum = decoder.readNumBits(const.GENTITYNUM_BITS)
            if newnum == (const.MAX_GENTITIES - 1):
                break
            if decoder.isEOD():
                self._log_error(ErrorParsePacketEntitiesEndOfMessage())
                return
            while oldframe is not None and oldnum < newnum:
                self._cl_delta_entity(decoder, newframe, oldnum, oldstate, True)
                oldindex += 1
                if oldindex >= oldframe.numEntities:
                    oldnum = 99999
                    oldstate = None
                else:
                    oldstate = _get_or_create(self.client.parseEntities, (oldframe.parseEntitiesNum + oldindex) & (const.MAX_PARSE_ENTITIES - 1), EntityState)
                    oldnum = oldstate.number
            if oldframe is not None and oldnum == newnum:
                self._cl_delta_entity(decoder, newframe, newnum, oldstate, False)
                oldindex += 1
                if oldindex >= oldframe.numEntities:
                    oldnum = 99999
                    oldstate = None
                else:
                    oldstate = _get_or_create(self.client.parseEntities, (oldframe.parseEntitiesNum + oldindex) & (const.MAX_PARSE_ENTITIES - 1), EntityState)
                    oldnum = oldstate.number
                continue
            if oldnum > newnum or oldframe is None:
                baseline = _get_or_create(self.client.entityBaselines, newnum, EntityState)
                self._cl_delta_entity(decoder, newframe, newnum, baseline, False)
                continue
        while oldframe is not None and oldnum != 99999:
            self._cl_delta_entity(decoder, newframe, oldnum, oldstate, True)
            oldindex += 1
            if oldindex >= oldframe.numEntities:
                break
            oldstate = _get_or_create(self.client.parseEntities, (oldframe.parseEntitiesNum + oldindex) & (const.MAX_PARSE_ENTITIES - 1), EntityState)
            oldnum = oldstate.number

    def _cl_delta_entity(self, decoder: Q3HuffmanReader, frame: CLSnapshot, newnum: int, old: Optional[EntityState], unchanged: bool) -> None:
        state = _get_or_create(self.client.parseEntities, self.client.parseEntitiesNum & (const.MAX_PARSE_ENTITIES - 1), EntityState)
        if unchanged and old is not None:
            state.copy(old)
        else:
            decoder.readDeltaEntity(state, newnum)
        if state.number == (const.MAX_GENTITIES - 1):
            return
        self.client.parseEntitiesNum += 1
        frame.numEntities += 1

    def _map_checksum(self, mapname: str) -> int:
        if not mapname:
            return 0
        return sum(map(ord, mapname.lower())) & 0xFF

    class TimeResult:
        def __init__(self, time: int, has_error: bool) -> None:
            self.Time = time
            self.HasError = has_error

    def _get_time(self, ps, server_time: int, df_ver: int, checksum: int) -> 'Q3DemoConfigParser.TimeResult':
        value = (ps.stats[7] << 16) | (ps.stats[8] & 0xFFFF)
        if value == 0:
            return self.TimeResult(0, False)
        if (self.client.isOnline and df_ver != 190) or (df_ver >= 19112 and self.client.isCheatsOn):
            return self.TimeResult(value, False)
        value ^= abs(int(ps.origin[0])) & 0xFFFF
        value ^= abs(int(ps.velocity[0])) << 16
        value ^= ps.stats[0] & 0xFF if ps.stats[0] > 0 else 150
        value ^= (ps.movementDir & 0xF) << 28
        for shift in range(24, 0, -8):
            temp = ((value >> shift) ^ (value >> (shift - 8))) & 0xFF
            value = (value & ~(0xFF << shift)) | (temp << shift)
        local = (server_time << 2) & 0xFFFFFFFF
        local = (local + ((df_ver + checksum) << 8)) & 0xFFFFFFFF
        local ^= (server_time << 24) & 0xFFFFFFFF
        value ^= local
        local = (value >> 28) & 0xF
        local |= ((~local) & 0xF) << 4
        local |= local << 8
        local |= local << 16
        value ^= local
        local = (value >> 22) & 0x3F
        value &= 0x3FFFFF
        local_sum = sum((value >> (6 * idx)) & 0x3F for idx in range(3))
        local_sum += (value >> 18) & 0xF
        has_error = local != (local_sum & 0x3F)
        return self.TimeResult(value, has_error)

    def _log_error(self, exc: Exception) -> None:
        self.clc.errors[str(exc)] = ''


class Q3DemoParser:
    def __init__(self, file_name: str) -> None:
        self.file_name = file_name

    def parse_config(self):
        sys.path.append(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
        from raw_info import RawInfo
        parser = Q3DemoConfigParser()
        stream = Q3MessageStream(self.file_name)
        try:
            while True:
                message = stream.next_message()
                if message is None:
                    break
                if not parser.parse(message):
                    break
        finally:
            stream.close()
        return RawInfo(self.file_name, parser.clc, parser.client)

    @staticmethod
    def get_raw_config_strings(file_name: str):
        return Q3DemoParser(file_name).parse_config()
