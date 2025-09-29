from __future__ import annotations

from typing import List


class BitStreamReader:
    def __init__(self, data: bytes) -> None:
        self.bit_length = len(data) * 8
        add_bytes = (4 - (len(data) & 0x03)) & 0x03
        padded = data + b"\x00" * add_bytes
        self.data: List[int] = [int.from_bytes(padded[i:i + 4], "little", signed=False) for i in range(0, len(padded), 4)]
        self.reset()

    def reset(self) -> None:
        self.bit_idx = 0
        self.byte_idx = 0
        self.current_bits = self.data[self.byte_idx] if self.data else 0

    def is_eod(self) -> bool:
        return self.bit_idx >= self.bit_length

    def read_bits(self, bits: int) -> int:
        value = 0
        for shift in range(bits):
            bit = self.next_bit()
            if bit == -1:
                break
            value |= bit << shift
        return value

    def next_bit(self) -> int:
        if self.bit_idx >= self.bit_length:
            return -1
        result = self.current_bits & 1
        self.bit_idx += 1
        if (self.bit_idx & 31) >= 1:
            self.current_bits >>= 1
        else:
            self.byte_idx += 1
            if self.byte_idx < len(self.data):
                self.current_bits = self.data[self.byte_idx]
            else:
                self.current_bits = 0
        return result

    def skip_bits(self, skip: int) -> int:
        if skip < 0 or skip > 32 or self.bit_idx + skip > self.bit_length:
            return -1
        current_amount = 32 - (self.bit_idx & 31)
        self.bit_idx += skip
        if current_amount > skip:
            self.current_bits >>= skip
        else:
            self.byte_idx += 1
            if self.byte_idx < len(self.data):
                self.current_bits = self.data[self.byte_idx]
            else:
                self.current_bits = 0
            skip -= current_amount
            if skip > 0:
                self.current_bits >>= skip
        return self.bit_idx
