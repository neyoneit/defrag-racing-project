#!/usr/bin/env python3
"""
Batch Demo Renamer - Compatible wrapper for the new Python-native DemoCleaner3 implementation
"""
import os
import sys
import subprocess
from pathlib import Path
import hashlib
from typing import List, Optional, Dict
from collections import defaultdict

# Add current directory to path to import from the new implementation
current_dir = Path(__file__).parent
sys.path.insert(0, str(current_dir))

from renamer import suggest_name, FileRenamer, RenameStatus

class BatchDemoRenamer:
    def __init__(self):
        self.renamer = FileRenamer()

    def calculate_md5(self, file_path: Path) -> str:
        """Calculate MD5 hash of a file"""
        hash_md5 = hashlib.md5()
        with open(file_path, "rb") as f:
            for chunk in iter(lambda: f.read(4096), b""):
                hash_md5.update(chunk)
        return hash_md5.hexdigest()

    def deduplicate_by_md5(self, demo_files: List[Path]) -> List[Path]:
        """Remove identical files (same MD5), keep only one copy"""
        print("Deduplicating identical files by MD5...")

        md5_groups = defaultdict(list)

        # Group files by MD5 hash
        for demo_file in demo_files:
            try:
                md5_hash = self.calculate_md5(demo_file)
                md5_groups[md5_hash].append(demo_file)
            except Exception as e:
                print(f"    Error calculating MD5 for {demo_file.name}: {e}")
                continue

        remaining_files = []
        deleted_count = 0

        for md5_hash, identical_files in md5_groups.items():
            if len(identical_files) > 1:
                # Keep the oldest file (first created/modified)
                keep_file = min(identical_files, key=lambda x: x.stat().st_mtime)
                remaining_files.append(keep_file)

                # Delete the duplicates
                for duplicate in identical_files:
                    if duplicate != keep_file:
                        try:
                            duplicate.unlink()
                            deleted_count += 1
                            print(f"    Deleted duplicate: {duplicate.name}")
                        except Exception as e:
                            print(f"    Error deleting {duplicate.name}: {e}")
            else:
                # No duplicates, keep the file
                remaining_files.append(identical_files[0])

        print(f"MD5 deduplication complete: Deleted {deleted_count} identical files")
        return remaining_files

    def get_suggested_name(self, demo_file: Path) -> Optional[str]:
        """Get suggested filename using the new Python implementation"""
        try:
            return suggest_name(demo_file)
        except Exception as e:
            print(f"    Error parsing {demo_file}: {e}")
            return None

    def rename_demo(self, demo_file: Path, suggested_name: str, create_conflicts_dir: bool = True) -> str:
        """Rename a demo file, handling conflicts (same name, different content)"""
        new_path = demo_file.parent / suggested_name

        # If already correctly named
        if demo_file.name.lower() == suggested_name.lower():
            return "already_named"

        # Use the new renamer with delete_identical option
        try:
            outcome = self.renamer.rename_file(demo_file, suggested_name, delete_identical=True)

            if outcome.status == RenameStatus.RENAMED:
                return "renamed"
            elif outcome.status == RenameStatus.ALREADY_MATCHES:
                return "already_named"
            elif outcome.status == RenameStatus.DELETED_DUPLICATE:
                return "identical_deleted"
            elif outcome.status == RenameStatus.SKIPPED_EXISTING:
                if create_conflicts_dir:
                    # Move to conflicts directory
                    conflicts_dir = demo_file.parent / "_conflicts"
                    conflicts_dir.mkdir(exist_ok=True)

                    import time
                    timestamp = int(time.time())
                    conflict_name = f"{demo_file.stem}_{timestamp}{demo_file.suffix}"
                    conflict_path = conflicts_dir / conflict_name

                    demo_file.rename(conflict_path)
                    return f"conflict_moved_to_{conflict_path.name}"
                else:
                    return "name_conflict_skipped"

        except Exception as e:
            print(f"    Error renaming {demo_file}: {e}")
            return "error_renaming"

    def process_directory(self, directory: str, create_conflicts_dir: bool = True) -> dict:
        """Process all demo files in a directory"""
        demo_dir = Path(directory)
        if not demo_dir.exists():
            raise FileNotFoundError(f"Directory not found: {directory}")

        # Find all demo files
        demo_files = []
        for pattern in ["*.dm_68", "*.dm_67", "*.dm_66"]:
            demo_files.extend(demo_dir.glob(pattern))

        print(f"Found {len(demo_files)} demo files in '{directory}'")

        # Step 1: Deduplicate by MD5 first
        demo_files = self.deduplicate_by_md5(demo_files)
        print(f"After MD5 deduplication: {len(demo_files)} unique files remaining")

        stats = {
            "processed": 0,
            "renamed": 0,
            "already_named": 0,
            "identical_deleted": 0,
            "conflicts": 0,
            "errors": 0
        }

        for i, demo_file in enumerate(demo_files, 1):
            stats["processed"] += 1
            print(f"[{i}/{len(demo_files)}] Processing {demo_file.name}...", end="")

            try:
                # Get suggested name from new Python implementation
                suggested_name = self.get_suggested_name(demo_file)
                if not suggested_name:
                    print(" ERROR: Could not parse demo")
                    stats["errors"] += 1
                    continue

                # Rename the file
                result = self.rename_demo(demo_file, suggested_name, create_conflicts_dir)

                if result == "renamed":
                    stats["renamed"] += 1
                    print(f" -> {suggested_name}")
                elif result == "already_named":
                    stats["already_named"] += 1
                    print(" (already correctly named)")
                elif result == "identical_deleted":
                    stats["identical_deleted"] += 1
                    print(" (identical file deleted)")
                elif result.startswith("conflict_moved"):
                    stats["conflicts"] += 1
                    print(f" (name conflict: moved to _conflicts)")
                elif result == "name_conflict_skipped":
                    stats["conflicts"] += 1
                    print(" (name conflict, skipped)")
                elif result == "error_renaming":
                    stats["errors"] += 1
                    print(" (error renaming file)")

            except Exception as e:
                print(f" ERROR: {e}")
                stats["errors"] += 1

        return stats

def main():
    if len(sys.argv) < 2:
        print("Usage: python BatchDemoRenamer.py <demo_directory> [--no-conflicts-dir]")
        print("Renames all demo files in the specified directory based on their content.")
        print("Options:")
        print("  --no-conflicts-dir    Don't create _conflicts directory, just skip duplicates")
        sys.exit(1)

    demo_directory = sys.argv[1]
    create_conflicts_dir = "--no-conflicts-dir" not in sys.argv

    try:
        renamer = BatchDemoRenamer()
        stats = renamer.process_directory(demo_directory, create_conflicts_dir)

        print(f"\nSummary:")
        print(f"  Processed: {stats['processed']}")
        print(f"  Renamed: {stats['renamed']}")
        print(f"  Already named: {stats['already_named']}")
        print(f"  Identical deleted: {stats['identical_deleted']}")
        print(f"  Name conflicts: {stats['conflicts']}")
        print(f"  Errors: {stats['errors']}")

    except Exception as e:
        print(f"Error: {e}")
        sys.exit(1)

if __name__ == "__main__":
    main()