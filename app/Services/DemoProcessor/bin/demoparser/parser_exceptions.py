class ParserEx(Exception):
    pass


class ErrorUnableToParseDeltaEntityState(ParserEx):
    def __init__(self) -> None:
        super().__init__("Unable to parse delta-entity state")


class ErrorBadCommandInParseGameState(ParserEx):
    def __init__(self) -> None:
        super().__init__("Bad command in parseGameState")


class ErrorDeltaFrameTooOld(ParserEx):
    def __init__(self) -> None:
        super().__init__("Delta frame too old.")


class ErrorDeltaParseEntitiesNumTooOld(ParserEx):
    def __init__(self) -> None:
        super().__init__("Delta parseEntitiesNum too old")


class ErrorParsePacketEntitiesEndOfMessage(ParserEx):
    def __init__(self) -> None:
        super().__init__("CL_ParsePacketEntities: end of message")


class ErrorBaselineNumberOutOfRange(ParserEx):
    def __init__(self) -> None:
        super().__init__("Baseline number out of range")


class ErrorParseSnapshotInvalidsize(ParserEx):
    def __init__(self) -> None:
        super().__init__("CL_ParseSnapshot: Invalid size for areamask")


class ErrorDeltaFromInvalidFrame(ParserEx):
    def __init__(self) -> None:
        super().__init__("Delta from invalid frame (not supposed to happen!)")


class ErrorBadChecksum(ParserEx):
    def __init__(self) -> None:
        super().__init__("Bad checksum at decoding demo time")


class ErrorWrongLength(ParserEx):
    def __init__(self) -> None:
        super().__init__("Demo file is corrupted, wrong message length")


class ErrorCantOpenFile(ParserEx):
    def __init__(self) -> None:
        super().__init__("Can't open demofile")


class ErrorInvalidFieldCount(ParserEx):
    def __init__(self) -> None:
        super().__init__("invalid entityState field count")


class ErrorMatchPhysics(ParserEx):
    def __init__(self) -> None:
        super().__init__("Cvar: physics do not match reported physics")
