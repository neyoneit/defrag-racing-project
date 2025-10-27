#!/usr/bin/env python3
"""
Linux-compatible reimplementation of DemoCleaner3's FileHelper.renameFile logic.
"""
from __future__ import annotations

import argparse
import sys
import os
import stat
from dataclasses import dataclass
from enum import Enum
from pathlib import Path
from typing import Callable, Optional
if __package__ in (None, ""):
    from demoparser.parser import Q3DemoParser
    from demo import Demo
else:
    from .demoparser.parser import Q3DemoParser
    from .demo import Demo




class RenameStatus(Enum):
    """Outcome of a rename attempt."""
    RENAMED = "renamed"
    ALREADY_MATCHES = "already_matches"
    SKIPPED_EXISTING = "skipped_existing"
    DELETED_DUPLICATE = "deleted_duplicate"


@dataclass(frozen=True)
class RenameOutcome:
    """Detailed outcome for a rename request."""
    status: RenameStatus
    source: Path
    target: Path


class Logger:
    """Minimal logger interface used by the renamer."""

    def log(self, operation: str, *messages: str) -> None:  # pragma: no cover - interface
        pass


class NullLogger(Logger):
    """Logger that discards all log messages."""

    def log(self, operation: str, *messages: str) -> None:
        return None


class FileLogger(Logger):
    """Append-only text logger that mirrors DemoCleaner3's log structure."""

    def __init__(self, log_path: Path) -> None:
        self._path = Path(log_path)
        self._path.parent.mkdir(parents=True, exist_ok=True)

    def log(self, operation: str, *messages: str) -> None:
        with self._path.open("a", encoding="utf-8") as handle:
            handle.write(f"{operation}\n")
            for label, message in enumerate(messages, start=1):
                handle.write(f"  arg{label}: {message}\n")
            handle.write("-------------------------------\n")


class FileRenamer:
    """Port of DemoCleaner3.ExtClasses.FileHelper.renameFile for Linux environments."""

    def __init__(
        self,
        on_progress: Optional[Callable[[int], None]] = None,
        on_percent: Optional[Callable[[int], None]] = None,
        logger: Optional[Logger] = None,
    ) -> None:
        self._on_progress = on_progress
        self._on_percent = on_percent
        self.logger = logger or NullLogger()
        self.count_move_files = 0
        self.count_delete_files = 0
        self.count_progress_demos = 0
        self.count_demos_amount = 0

    def set_total(self, total: int) -> None:
        """Set total demos to allow percent callbacks."""
        self.count_demos_amount = max(0, total)
        if self.count_demos_amount == 0:
            self.count_progress_demos = 0

    def increase_progress(self, by_value: int) -> None:
        """Public hook mirroring the original helper API."""
        if by_value > 0:
            self._update_progress(by_value)

    def rename_file(self, file_path: Path | str, new_name: str, delete_identical: bool = False) -> RenameOutcome:
        """Rename a single file following DemoCleaner3 rules."""
        source = Path(file_path)
        if not source.exists():
            raise FileNotFoundError(f"File not found: {source}")

        if Path(new_name).name != new_name:
            raise ValueError("new_name must be a file name, not a path.")

        target = source.with_name(new_name)
        source_lower = str(source).lower()
        target_lower = str(target).lower()

        if source_lower != target_lower:
            if target.exists():
                if delete_identical:
                    self._delete_file(source)
                    return RenameOutcome(RenameStatus.DELETED_DUPLICATE, source, target)
                self._update_progress()
                return RenameOutcome(RenameStatus.SKIPPED_EXISTING, source, target)
            self._move_file(source, target)
            return RenameOutcome(RenameStatus.RENAMED, source, target)

        if str(source) != str(target):
            self._move_file(source, target)
            return RenameOutcome(RenameStatus.RENAMED, source, target)

        self._update_progress()
        return RenameOutcome(RenameStatus.ALREADY_MATCHES, source, target)

    # Internal helpers -------------------------------------------------

    def _update_progress(self, increment: int = 1) -> None:
        if increment <= 0:
            return
        self.count_progress_demos += increment
        if self._on_progress:
            self._on_progress(self.count_progress_demos)
        if self.count_demos_amount > 0 and self._on_percent:
            percent = int((self.count_progress_demos / self.count_demos_amount) * 100)
            if percent < 0:
                percent = 0
            self._on_percent(percent)

    def _delete_file(self, path: Path) -> None:
        self._try_operate(path, path.unlink)
        self.count_delete_files += 1
        self._update_progress()
        self.logger.log("DeleteFile", str(path))

    def _move_file(self, source: Path, target: Path) -> None:
        def _rename() -> None:
            os.replace(source, target)

        self._try_operate(source, _rename)
        self.count_move_files += 1
        self._update_progress()
        self.logger.log("RenameFile", str(source), str(target))

    def _try_operate(self, path: Path, operation: Callable[[], None]) -> None:
        try:
            operation()
        except PermissionError:
            self._ensure_writable(path)
            operation()

    def _ensure_writable(self, path: Path) -> None:
        try:
            current_mode = path.stat().st_mode
            path.chmod(current_mode | stat.S_IWUSR | stat.S_IWGRP | stat.S_IWOTH)
        except FileNotFoundError:
            pass


def suggest_name(file_path: Path) -> Optional[str]:
    try:
        parser = Q3DemoParser(str(file_path))
        raw = parser.parse_config()
        demo = Demo.GetDemoFromRawInfo(raw)
    except Exception:
        return None
    if demo.hasError:
        return None
    return Path(demo.demoNewName).name


def parse_demo_metadata(file_path: Path) -> Optional[dict]:
    """
    Parse demo file and return metadata including record date.
    Returns dict with: suggested_filename, record_date (ISO format)
    """
    try:
        parser = Q3DemoParser(str(file_path))
        raw = parser.parse_config()
        demo = Demo.GetDemoFromRawInfo(raw)
    except Exception:
        return None

    if demo.hasError:
        return None

    metadata = {
        "suggested_filename": Path(demo.demoNewName).name,
        "record_date": demo.recordTime.isoformat() if demo.recordTime else None,
        "map_name": demo.mapName,
        "player_name": demo.playerName,
        "physics": demo.modphysic,
        "time_seconds": demo.time.total_seconds() if demo.time else None,
    }

    return metadata


def _build_cli() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(description="Rename a file using DemoCleaner3 rules.")
    parser.add_argument("file", type=Path, help="Path to the demo file to rename")
    parser.add_argument("new_name", nargs="?", help="Optional explicit filename within the same directory")
    parser.add_argument("--delete-identical", action="store_true", help="Delete the original file when the target name already exists")
    parser.add_argument("--log-file", type=Path, help="Optional log file path mirroring DemoCleaner3 log format")
    return parser


def main() -> None:
    parser = _build_cli()
    args = parser.parse_args()

    logger: Optional[Logger] = None
    if args.log_file:
        logger = FileLogger(args.log_file)

    renamer = FileRenamer(logger=logger)
    new_name = args.new_name
    if new_name is None:
        suggested = suggest_name(args.file)
        if not suggested:
            print("Unable to determine suggested name", file=sys.stderr)
            sys.exit(1)
        print(f"Suggested name: {suggested}")
        new_name = suggested
    outcome = renamer.rename_file(args.file, new_name, args.delete_identical)
    print(outcome.status.value)
    print(outcome.target)


if __name__ == "__main__":
    main()
