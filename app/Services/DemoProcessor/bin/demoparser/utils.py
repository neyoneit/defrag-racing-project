from __future__ import annotations

import math
from typing import Dict

debug = False


def angle2short(value: float) -> int:
    return int(value * 65536.0 / 360.0) & 0xFFFF


def short2angle(value: int) -> float:
    return float(value * (360.0 / 65536.0))


def raw_bits_to_float(bits: int) -> float:
    sign = -1 if bits & 0x80000000 else 1
    exponent = (bits >> 23) & 0xFF
    mantissa = ((bits & 0x7FFFFF) | 0x800000) if exponent > 0 else (bits & 0x7FFFFF) << 1
    return sign * mantissa * math.pow(2.0, exponent - 150)


def split_config(src: str) -> Dict[str, str]:
    if not src:
        return {}
    begin_index = 1 if src.startswith('\\') else 0
    pieces = src.split('\\')
    result: Dict[str, str] = {}
    for index in range(begin_index, len(pieces) - 1, 2):
        key = pieces[index]
        value = pieces[index + 1]
        if value:
            result[key] = value
    return result


def print_debug(message: str, *args: object) -> None:
    if not debug:
        return
    if args:
        print(message.format(*args))
    else:
        print(message)


def print_exception(errors: Dict[str, str], exc: Exception) -> None:
    errors[str(exc)] = ""
    print_debug(str(exc))
