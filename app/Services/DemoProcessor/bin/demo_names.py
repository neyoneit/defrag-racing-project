from __future__ import annotations

from dataclasses import dataclass
from typing import Dict, Optional

from console_string_utils import remove_colors


@dataclass
class DemoNames:
    dfName: Optional[str] = None
    uName: Optional[str] = None
    oName: Optional[str] = None
    lName: Optional[str] = None
    cName: Optional[str] = None
    fName: Optional[str] = None

    defaultName = "UnnamedPlayer"

    def setNamesByPlayerInfo(self, player_info: Optional[Dict[str, str]]) -> None:
        if player_info:
            self.dfName = player_info.get('df_name')
            raw_name = player_info.get('name')
            self.uName = normalize_name(remove_colors(raw_name) or '') if raw_name else None

    def setConsoleName(self, online_name: Optional[str], login_name: Optional[str], is_online: bool) -> None:
        if is_online:
            self.oName = normalize_name(remove_colors(online_name) or '') if online_name else None
            self.lName = normalize_name(remove_colors(login_name) or '') if login_name else None
        else:
            self.cName = normalize_name(remove_colors(online_name) or '') if online_name else None

    def setBracketsName(self, brackets_name: str) -> None:
        self.fName = brackets_name

    def chooseNormalName(self) -> str:
        return choose_name(self.dfName, self.cName, self.uName, self.oName, self.lName, self.fName)


def choose_name(*names: Optional[str]) -> str:
    for name in names:
        if name and name != DemoNames.defaultName:
            return name
    return DemoNames.defaultName


def normalize_name(name: str) -> str:
    import re

    return re.sub(r"[^a-zA-Z0-9!#$%&'()+,\-.;=\[\]^_{}]", "", name)
