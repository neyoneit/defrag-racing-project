from __future__ import annotations

from dataclasses import dataclass
from typing import Dict, Tuple

from ext import Ext


@dataclass
class GameInfo:
    parameters: Dict[str, str]
    isDefrag: bool = False
    isFreeStyle: bool = False
    isOnline: bool = True
    gameName: str = ""
    gameNameShort: str = ""
    gameType: str = ""
    gameTypeShort: str = ""
    gameplayType: str = ""
    gameplayTypeShort: str = ""
    modType: str = ""
    modTypeName: str = ""

    def __init__(self, parameters: Dict[str, str], is_cpm_in_snapshots: bool | None) -> None:
        self.parameters = Ext.LowerKeys(parameters or {})
        key, value = self._get_game_name()
        self.gameNameShort = key
        self.gameName = value

        key, value = self._get_game_type()
        self.gameTypeShort = key
        self.gameType = value

        self.gameplayTypeShort = self._get_gameplay_type_short(is_cpm_in_snapshots)
        self.gameplayType = self._get_gameplay_type()

        key, value = self._get_mod_type()
        self.modType = key
        self.modTypeName = value

    def _get_mod_type(self) -> Tuple[str, str]:
        defrag_gametype = Ext.GetOrZero(self.parameters, 'defrag_gametype')
        if defrag_gametype > 1 and defrag_gametype != 5:
            df_mode = Ext.GetOrZero(self.parameters, 'defrag_mode')
            return str(df_mode), _get_df_mod_text(df_mode)
        if self.gameTypeShort == 'fc':
            all_weapons = Ext.ToInt(Ext.GetOrNull(self.parameters, 'all_weapons'), -1)
            match all_weapons:
                case 0:
                    df_mode = 7
                case 1:
                    df_mode = 2
                case 2:
                    df_mode = 8
                case 3:
                    df_mode = 3
                case _:
                    df_mode = 8
            return str(df_mode), _get_old_df_mod_text(all_weapons)
        return "", ""

    def _get_game_name(self) -> Tuple[str, str]:
        game = (Ext.GetOrNull(self.parameters, 'fs_game') or '').lower()
        g_name = (Ext.GetOrNull(self.parameters, 'gamename') or '').lower()
        game_version = (Ext.GetOrNull(self.parameters, 'gameversion') or '').lower()
        df_vers = (Ext.GetOrNull(self.parameters, 'defrag_vers') or '').lower()
        df_version = (Ext.GetOrNull(self.parameters, 'defrag_version') or '').lower()

        if game.startswith('defrag') or g_name == 'defrag' or df_vers or df_version:
            self.isDefrag = True
            return 'defrag', 'Defrag'

        mapping = {
            'cpma': ('cpma', 'Challenge ProMode Arena'),
            'osp': ('osp', 'Orange Smoothie Productions'),
            'arena': ('ra3', 'Rocket Arena'),
            'threewave': ('q3w', 'Threewave CTF'),
            'freeze': ('q3ft', 'Freeze Tag'),
            'ufreeze': ('q3uft', 'Ultra Freeze Tag'),
            'q3ut': ('q3ut', 'Urban Terror'),
            'excessiveplus': ('q3xp', 'Excessive Plus'),
            'excessive': ('q3ex', 'Excessive'),
            'reactance:iu': ('q3insta', 'InstaUnlagged'),
            'battle': ('battle', 'Battle'),
            'beryllium': ('beryllium', 'Beryllium'),
            'bma': ('bma', 'Black Metal Assault'),
            'the corkscrew mod': ('corkscrew', 'The CorkScrew Mod'),
            'f4a': ('f4a', 'Freeze For All'),
            'freezeplus': ('fp', 'Freeze Plus'),
            'generations': ('gen', 'Generations'),
            'nemesis': ('nemesis', 'Nemesis'),
            'noghost': ('noghost', 'NoGhost'),
            'q3f': ('q3f', 'Quake 3 Fortress'),
            'q3f2': ('q3f', 'Quake 3 Fortress'),
            'truecombat': ('truecombat', 'Quake 3 True Combat'),
            'q3tc': ('q3tc', 'Quake 3 True Combat'),
        }
        if game in mapping:
            return mapping[game]
        if game_version.startswith('osp'):
            return 'osp', 'Orange Smoothie Productions'
        xp_version = (Ext.GetOrNull(self.parameters, 'xp_version') or '').lower()
        if xp_version.startswith('xp'):
            return 'q3xp', 'Excessive Plus'
        if game.startswith('pkarena'):
            return game, 'Painkeep'
        if 'unlagged' in game:
            return 'unlagged', 'Unlagged'
        if 'westernq3' in game:
            return 'westernq3', 'Western Quake 3'
        return 'q3a', 'Quake 3 Arena'

    def _get_gameplay_type_short(self, is_cpm_in_snapshots: bool | None) -> str:
        if self.gameNameShort == 'defrag':
            if is_cpm_in_snapshots is not None:
                return 'cpm' if is_cpm_in_snapshots else 'vq3'
            promode = Ext.GetOrZero(self.parameters, 'df_promode')
            return 'cpm' if promode > 0 else 'vq3'
        if self.gameNameShort == 'cpma':
            server_gameplay = Ext.GetOrNull(self.parameters, 'server_gameplay') or ''
            mapping = {
                '0': 'vq3',
                'vq3': 'vq3',
                '1': 'pmc',
                'pmc': 'pmc',
                '2': 'cpm',
                'cpm': 'cpm',
                'cq3': 'cq3',
            }
            if server_gameplay in mapping:
                return mapping[server_gameplay]
            promode = Ext.GetOrZero(self.parameters, 'server_promode')
            return 'cpm' if promode > 0 else 'vq3'
        if self.gameNameShort == 'osp':
            promode = Ext.GetOrZero(self.parameters, 'server_promode')
            return 'cpm' if promode > 0 else 'vq3'
        return ''

    def _get_gameplay_type(self) -> str:
        mapping = {
            'vq3': 'Vanilla Quake3',
            'cpm': 'Challenge ProMode',
            'pmc': 'ProMode Classic',
            'cq3': 'Challenge Quake3',
        }
        return mapping.get(self.gameplayTypeShort, '')

    def _get_game_type(self) -> Tuple[str, str]:
        g_gametype = Ext.GetOrZero(self.parameters, 'g_gametype')
        if self.gameNameShort == 'defrag':
            df_gtype = Ext.GetOrZero(self.parameters, 'defrag_gametype')
            self.isFreeStyle = df_gtype in (2, 6)
            self.isOnline = df_gtype > 4
            mapping = {
                1: ('df', 'Offline Defrag'),
                2: ('fs', 'Offline Freestyle'),
                3: ('fc', 'Offline Fast Caps'),
                5: ('mdf', 'Multiplayer Defrag'),
                6: ('mfs', 'Multiplayer Freestyle'),
                7: ('mfc', 'Multiplayer Fast Caps'),
            }
            if df_gtype in mapping:
                return mapping[df_gtype]
            if g_gametype == 4:
                return 'fc', 'Offline Fast Caps'
            return 'df', 'Offline Defrag'
        if self.gameNameShort == 'cpma':
            mapping = {
                5: ('ca', 'Clan Arena'),
                6: ('ft', 'Freeze Tag'),
                7: ('ctfs', 'Capturestrike'),
                8: ('ntf', 'Not Team Fortress'),
                -1: ('hm', 'Hoonymode'),
            }
            if g_gametype in mapping:
                return mapping[g_gametype]
        if self.gameNameShort == 'osp' and g_gametype >= 5:
            if g_gametype == 5:
                return 'ca', 'Clan Arena'
            server_freezetag = Ext.GetOrZero(self.parameters, 'server_freezetag')
            if server_freezetag == 1:
                return 'fto', 'Freeze Tag (OSP)'
            if server_freezetag == 2:
                return 'ftv', 'Freeze Tag (Vanilla)'
        if self.gameNameShort == 'q3w':
            g_serverdata = (Ext.GetOrNull(self.parameters, 'g_serverdata') or '').upper()
            mapping = {
                'G00': ('ffa', 'Free for All'),
                'G01': ('1v1', 'Duel'),
                'G03': ('tdm', 'Team Deathmatch'),
                'G04': ('ctf', 'Capture the Flag'),
                'G05': ('ofc', 'One Flag CTF'),
                'G09': ('ctfs', 'Capturestrike'),
                'G10': ('cctf', 'Classic CTF'),
                'G010': ('cctf', 'Classic CTF'),
                'G11': ('ar', 'Arena'),
                'G011': ('ar', 'Arena'),
            }
            for key, value in mapping.items():
                if key in g_serverdata:
                    return value
        if self.gameNameShort == 'q3ut':
            mapping = {
                0: ('ffa', 'Free for All'),
                1: ('ffa', 'Free for All'),
                3: ('tdm', 'Team Deathmatch'),
                4: ('tsv', 'Team Survivor'),
                5: ('ftl', 'Follow the Leader'),
                6: ('ch', 'Capture & Hold'),
                7: ('ctf', 'Capture the Flag'),
                8: ('bd', 'Bomb & Defuse'),
            }
            if g_gametype in mapping:
                return mapping[g_gametype]
        if self.gameNameShort == 'q3xp':
            mapping = {
                5: ('rtf', 'Return The Flag'),
                6: ('ofc', 'One Flag CTF'),
                7: ('ca', 'Clan Arena'),
                8: ('ft', 'Freeze Tag'),
                9: ('ptl', 'Protect The Leader'),
            }
            if g_gametype in mapping:
                return mapping[g_gametype]
        base_mapping = {
            0: ('ffa', 'Free for All'),
            1: ('1v1', 'Duel'),
            2: ('ffa', 'Free for All'),
            3: ('tdm', 'Team Deathmatch'),
            4: ('ctf', 'Capture the Flag'),
        }
        return base_mapping.get(g_gametype, ('ffa', 'Free for All'))


def _get_df_mod_text(df_mode: int) -> str:
    return {
        0: 'Custom',
        1: 'No weapon / No map objects',
        2: 'Weapons & Map Objects',
        3: 'Map Objects Only',
        4: 'Weapons Only',
        5: 'Swinging Hook',
        6: 'Quake3 Hook',
        7: 'Original quake 3',
        8: 'Custom',
    }.get(df_mode, '')


def _get_old_df_mod_text(df_mode: int) -> str:
    return {
        0: 'Pickup',
        1: 'Give All, No BFG',
        2: 'Give All',
        3: 'No weapons',
    }.get(df_mode, 'Custom')
