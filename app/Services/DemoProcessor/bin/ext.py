from __future__ import annotations

from dataclasses import dataclass
from typing import Callable, Dict, Iterable, List, Sequence, Tuple, TypeVar

T = TypeVar("T")
K = TypeVar("K")
V = TypeVar("V")


class ListMap(List[Tuple[K, V]]):
    def __init__(self, source: Dict[K, V] | None = None) -> None:
        super().__init__()
        if source:
            for key, value in source.items():
                self.add(key, value)

    def add(self, key: K, value: V) -> None:
        self.append((key, value))

    def Add(self, key: K, value: V) -> None:  # C# compatibility
        self.add(key, value)

    def Get(self, key: K) -> List[V]:
        return [value for current_key, value in self if current_key == key]

    def ToDictionary(self) -> Dict[K, V]:
        mapping: Dict[K, V] = {}
        for key, value in self:
            mapping[key] = value
        return mapping


class Ext:
    @staticmethod
    def GetOrNull(dictionary: Dict[K, V] | None, key: K) -> V | None:
        if dictionary is None:
            return None
        return dictionary.get(key)

    @staticmethod
    def GetOrZero(dictionary: Dict[K, str] | None, key: K) -> int:
        if not dictionary or key not in dictionary:
            return 0
        value = dictionary[key]
        try:
            return int(value)
        except (TypeError, ValueError):
            return 0

    @staticmethod
    def Join(*dicts: Dict[K, V] | None) -> Dict[K, V]:
        result: Dict[K, V] = {}
        for dictionary in dicts:
            if dictionary:
                result.update(dictionary)
        return result

    @staticmethod
    def JoinLowercased(*dicts: Dict[str, str] | None) -> Dict[str, str]:
        result: Dict[str, str] = {}
        for dictionary in dicts:
            if dictionary:
                for key, value in dictionary.items():
                    result[key.lower()] = value
        return result

    @staticmethod
    def ContainsAny(data: str, *values: str) -> bool:
        return any(value in data for value in values)

    @staticmethod
    def ContainsAnySplitted(data: str, *values: str) -> bool:
        lowered = {value.lower() for value in values}
        for part in Ext._split_non_alnum(data):
            if part.lower() in lowered:
                return True
        return False

    @staticmethod
    def MinOf(collection: Iterable[T], selector: Callable[[T], int | float]) -> T | None:
        minimum: T | None = None
        minimum_value: float = float("inf")
        for item in collection:
            value = selector(item)
            if value < minimum_value:
                minimum_value = value
                minimum = item
        return minimum

    @staticmethod
    def CountOf(collection: Iterable[T], to_compare: T) -> int:
        return sum(1 for item in collection if item == to_compare)

    @staticmethod
    def IndexOf(collection: Sequence[T], predicate: Callable[[T], bool]) -> int:
        for index, item in enumerate(collection):
            if predicate(item):
                return index
        return -1

    @staticmethod
    def Split(collection: Sequence[T], chunk_size: int) -> List[List[T]]:
        if chunk_size <= 0:
            raise ValueError("chunk_size must be positive")
        return [list(collection[i:i + chunk_size]) for i in range(0, len(collection), chunk_size)]

    @staticmethod
    def LowerKeys(dictionary: Dict[str, V]) -> Dict[str, V]:
        return {key.lower(): value for key, value in dictionary.items()}

    @staticmethod
    def replaceKeys(src: ListMap[str, str], replaces: Dict[str, str]) -> None:
        lowered = {key.lower(): value for key, value in replaces.items()}
        for index, (key, value) in enumerate(src):
            replacement = lowered.get(key.lower())
            if replacement is not None:
                src[index] = (replacement, value)

    @staticmethod
    def ToInt(input_string: str | None, default_value: int) -> int:
        if input_string is None:
            return default_value
        try:
            return int(input_string)
        except ValueError:
            return default_value

    @staticmethod
    def getExMessage(exception: Exception) -> str:
        return f"{exception}"

    @staticmethod
    def _split_non_alnum(data: str) -> List[str]:
        parts: List[str] = []
        current: List[str] = []
        for char in data:
            if char.isalnum():
                current.append(char)
            else:
                if current:
                    parts.append("".join(current))
                    current = []
        if current:
            parts.append("".join(current))
        return parts


class Ext2:
    @staticmethod
    def GetOrCreate(dictionary: Dict[K, V], key: K, factory: Callable[[], V]) -> V:
        if key not in dictionary:
            dictionary[key] = factory()
        return dictionary[key]


@dataclass(frozen=True)
class Pair:
    Key: str
    Value: str
