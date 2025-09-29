from __future__ import annotations

from typing import Dict, List

from .bitstream import BitStreamReader
from .parser_exceptions import (
    ErrorBaselineNumberOutOfRange,
    ErrorParsePacketEntitiesEndOfMessage,
    ErrorParseSnapshotInvalidsize,
    ErrorUnableToParseDeltaEntityState,
)
from . import const
from .structures.mapper import MapperFactory
from .structures.player import EntityState, PlayerState, TrType
from .structures.client import CLSnapshot
from .structures.client_event import ClientEvent, pmTypesStrings
from .structures.client import ClientState
from .structures.client import ClientConnection
from .structures import mapper
from .utils import raw_bits_to_float, print_debug, print_exception
from .parser_exceptions import (
    ErrorBadCommandInParseGameState,
    ErrorDeltaFrameTooOld,
    ErrorDeltaParseEntitiesNumTooOld,
    ErrorDeltaFromInvalidFrame,
    ErrorBadChecksum,
)


class Q3HuffmanNode:
    __slots__ = ("left", "right", "symbol")

    def __init__(self) -> None:
        self.left: "Q3HuffmanNode | None" = None
        self.right: "Q3HuffmanNode | None" = None
        self.symbol: int = const.Q3_HUFFMAN_NYT_SYM


class Q3HuffmanMapper:
    rootNode: "Q3HuffmanNode | None" = None

    @classmethod
    def decode_symbol(cls, reader: BitStreamReader) -> int:
        cls.init()
        node = cls.rootNode
        while node is not None and node.symbol == const.Q3_HUFFMAN_NYT_SYM:
            bit = reader.next_bit()
            if bit < 0:
                return -1
            node = node.left if bit == 0 else node.right
        return const.Q3_HUFFMAN_NYT_SYM if node is None else node.symbol

    @classmethod
    def init(cls) -> None:
        if cls.rootNode is not None:
            return
        symtab = [
            0x0006, 0x003B, 0x00C8, 0x00EC, 0x01A1, 0x0111, 0x0090, 0x007F, 0x0035, 0x00B4, 0x00E9, 0x008B, 0x0093,
            0x006D, 0x0139, 0x02AC, 0x00A5, 0x0258, 0x03F0, 0x03F8, 0x05DD, 0x07F3, 0x062B, 0x0723, 0x02F4, 0x058D,
            0x04AB, 0x0763, 0x05EB, 0x0143, 0x024F, 0x01D4, 0x0077, 0x04D3, 0x0244, 0x06CD, 0x07C5, 0x07F9, 0x070D,
            0x07CD, 0x0294, 0x05AC, 0x0433, 0x0414, 0x0671, 0x06F0, 0x03F4, 0x0178, 0x00A7, 0x01C3, 0x01EF, 0x0397,
            0x0153, 0x01B1, 0x020D, 0x0361, 0x0207, 0x02F1, 0x0399, 0x0591, 0x0523, 0x02BC, 0x0344, 0x05F3, 0x01CF,
            0x00D0, 0x00FC, 0x0084, 0x0121, 0x0151, 0x0280, 0x0270, 0x033D, 0x0463, 0x06D7, 0x0771, 0x039D, 0x06AB,
            0x05C7, 0x0733, 0x032C, 0x049D, 0x056B, 0x076B, 0x05D3, 0x0571, 0x05E3, 0x0633, 0x04D7, 0x06CB, 0x0370,
            0x02A8, 0x02C7, 0x0305, 0x02EB, 0x01D8, 0x02F3, 0x013C, 0x03AB, 0x038F, 0x0297, 0x00B0, 0x0141, 0x034F,
            0x005C, 0x0128, 0x02BD, 0x02C4, 0x0198, 0x028F, 0x010C, 0x01B3, 0x0185, 0x018C, 0x0147, 0x0179, 0x00D9,
            0x00C0, 0x0117, 0x0119, 0x014B, 0x01E1, 0x01A3, 0x0173, 0x016F, 0x00E8, 0x0088, 0x00E5, 0x005F, 0x00A9,
            0x00CC, 0x00FD, 0x010F, 0x0183, 0x0101, 0x0187, 0x0167, 0x01E7, 0x0157, 0x0174, 0x03CB, 0x03C4, 0x0281,
            0x024D, 0x0331, 0x0563, 0x0380, 0x07D7, 0x042B, 0x0545, 0x046B, 0x043D, 0x072B, 0x04F9, 0x04E3, 0x0645,
            0x052B, 0x0431, 0x07EB, 0x05B9, 0x0314, 0x05F9, 0x0533, 0x042C, 0x06DD, 0x05C1, 0x071D, 0x05D1, 0x0338,
            0x0461, 0x06E3, 0x0745, 0x066B, 0x04CD, 0x04CB, 0x054D, 0x0238, 0x07C1, 0x063D, 0x07BC, 0x04C5, 0x07AC,
            0x07E3, 0x0699, 0x07D3, 0x0614, 0x0603, 0x05BC, 0x069D, 0x0781, 0x0663, 0x048D, 0x0154, 0x0303, 0x015D,
            0x0060, 0x0089, 0x07C7, 0x0707, 0x01B8, 0x03F1, 0x062C, 0x0445, 0x0403, 0x051D, 0x05C5, 0x074D, 0x041D,
            0x0200, 0x07B9, 0x04DD, 0x0581, 0x050D, 0x04B9, 0x05CD, 0x0794, 0x05BD, 0x0594, 0x078D, 0x0558, 0x07BD,
            0x04C1, 0x07DD, 0x04F8, 0x02D1, 0x0291, 0x0499, 0x06F8, 0x0423, 0x0471, 0x06D3, 0x0791, 0x00C9, 0x0631,
            0x0507, 0x0661, 0x0623, 0x0118, 0x0605, 0x06C1, 0x05D7, 0x04F0, 0x06C5, 0x0700, 0x07D1, 0x07A8, 0x061D,
            0x0D00, 0x0405, 0x0758, 0x06F9, 0x05A8, 0x06B9, 0x068D, 0x00AF, 0x0064
        ]
        cls.rootNode = Q3HuffmanNode()
        for symbol, path in enumerate(symtab):
            cls._put_sym(symbol, path)

    @classmethod
    def _put_sym(cls, symbol: int, path: int) -> None:
        node = cls.rootNode
        while path > 1:
            if path & 0x1:
                if node.right is None:
                    node.right = Q3HuffmanNode()
                node = node.right
            else:
                if node.left is None:
                    node.left = Q3HuffmanNode()
                node = node.left
            path >>= 1
        node.symbol = symbol


class Q3HuffmanReader:
    BIT_POS: List[int] = [0] * 32

    def __init__(self, buffer: bytes) -> None:
        self.stream = BitStreamReader(buffer)
        mask = 1
        for index in range(32):
            self.BIT_POS[index] = mask
            mask <<= 1

    def isEOD(self) -> bool:
        return self.stream.is_eod()

    def readNumBits(self, bits: int) -> int:
        neg = bits < 0
        if neg:
            bits = -bits
        fragment_bits = bits & 7
        value = 0
        if fragment_bits:
            value = self.stream.read_bits(fragment_bits)
            bits -= fragment_bits
        if bits:
            decoded = 0
            for offset in range(0, bits, 8):
                sym = Q3HuffmanMapper.decode_symbol(self.stream)
                if sym == const.Q3_HUFFMAN_NYT_SYM:
                    return -1
                decoded |= sym << offset
            if fragment_bits:
                decoded <<= fragment_bits
            value |= decoded
        if neg and bits > 0 and (value & (1 << (bits - 1))):
            value |= -1 ^ ((1 << bits) - 1)
        return value

    def readNumber(self, bits: int) -> int:
        return Q3HuffmanMapper.decode_symbol(self.stream) if bits == 8 else self.readNumBits(bits)

    def readByte(self) -> int:
        return Q3HuffmanMapper.decode_symbol(self.stream)

    def readShort(self) -> int:
        return self.readNumBits(16)

    def readInt(self) -> int:
        return self.readNumBits(32)

    def readLong(self) -> int:
        return self.readNumBits(32)

    def readFloat(self) -> float:
        value = self.readNumBits(32)
        if self.isEOD():
            return -1.0
        return float(raw_bits_to_float(value))

    def readAngle16(self) -> float:
        return float((self.readNumBits(16) * 360.0) / 65536.0)

    def readFloatIntegral(self) -> float:
        if self.readNumBits(1) == 0:
            trunc = self.readNumBits(const.FLOAT_INT_BITS)
            trunc -= const.FLOAT_INT_BIAS
            return float(trunc)
        return self.readFloat()

    def readData(self, data: bytearray, length: int) -> None:
        for index in range(min(length, len(data))):
            data[index] = self.readByte()

    def readStringBase(self, limit: int, stop_at_newline: bool) -> str:
        chars: List[str] = []
        for _ in range(limit):
            byte = Q3HuffmanMapper.decode_symbol(self.stream)
            if byte <= 0:
                break
            if stop_at_newline and byte == 0x0A:
                break
            if byte > 127 or byte == const.Q3_PERCENT_CHAR_BYTE:
                byte = const.Q3_DOT_CHAR_BYTE
            chars.append(chr(byte))
        return "".join(chars)

    def readString(self) -> str:
        return self.readStringBase(const.Q3_MAX_STRING_CHARS, False)

    def readBigString(self) -> str:
        return self.readStringBase(const.Q3_BIG_INFO_STRING, False)

    def readStringLine(self) -> str:
        return self.readStringBase(const.Q3_MAX_STRING_CHARS, True)

    def readServerCommand(self) -> Dict[str, str]:
        return {"sequence": str(self.readLong()), "command": self.readString()}

    def readDeltaEntity(self, state: EntityState, number: int) -> bool:
        if self.readNumBits(1) == 1:
            state.number = const.MAX_GENTITIES - 1
            return True
        if self.readNumBits(1) == 0:
            state.number = number
            return True
        count = self.readByte()
        if count < 0 or count > MapperFactory.EntityStateFieldNum:
            print_debug("invalid entityState field count: {0}", count)
            return False
        state.number = number
        for index in range(count):
            if self.readNumBits(1) == 0:
                continue
            reset = self.readNumBits(1) == 0
            MapperFactory.update_entity_state(state, index, self, reset)
        return True

    def readDeltaPlayerState(self, state: PlayerState) -> bool:
        count = self.readByte()
        if count < 0 or count > MapperFactory.PlayerStateFieldNum:
            print_debug("invalid entityState field count: {0}", count)
            return False
        for index in range(count):
            if self.readNumBits(1) == 0:
                continue
            MapperFactory.update_player_state(state, index, self, False)
        if self.readNumBits(1) != 0:
            if self.readNumBits(1) != 0:
                self._read_ps_array(state.stats, const.MAX_STATS)
            if self.readNumBits(1) != 0:
                self._read_ps_array(state.persistant, const.MAX_PERSISTANT)
            if self.readNumBits(1) != 0:
                self._read_ps_array(state.ammo, const.MAX_WEAPONS)
            if self.readNumBits(1) != 0:
                self._read_ps_long_array(state.powerups, const.MAX_POWERUPS)
        return True

    def _read_ps_array(self, array: List[int], length: int) -> None:
        bits = self.readNumBits(length)
        for idx in range(length):
            if bits & self.BIT_POS[idx]:
                array[idx] = self.readShort()

    def _read_ps_long_array(self, array: List[int], length: int) -> None:
        bits = self.readNumBits(length)
        for idx in range(length):
            if bits & self.BIT_POS[idx]:
                array[idx] = self.readLong()
