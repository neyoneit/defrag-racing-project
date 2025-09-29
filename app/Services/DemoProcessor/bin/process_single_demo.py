#!/usr/bin/env python3
"""
Single demo processor - returns suggested filename for a single demo file
Compatible wrapper for the new Python-native DemoCleaner3 implementation
"""
import sys
import os
import warnings
from pathlib import Path

# Suppress all warnings
warnings.filterwarnings('ignore')

# Add current directory to path to import from the new implementation
current_dir = Path(__file__).parent
sys.path.insert(0, str(current_dir))

from renamer import suggest_name

def main():
    if len(sys.argv) != 2:
        print("Usage: process_single_demo.py <demo_file>", file=sys.stderr)
        sys.exit(1)

    demo_file = Path(sys.argv[1])

    if not demo_file.exists():
        print(f"Error: Demo file not found: {demo_file}", file=sys.stderr)
        sys.exit(1)

    # Get suggested name using the new Python implementation
    try:
        suggested = suggest_name(demo_file)

        if suggested:
            # Output the suggested filename
            print(suggested)
            sys.exit(0)
        else:
            print("Error: Could not parse demo file", file=sys.stderr)
            sys.exit(1)

    except Exception as e:
        print(f"Error: {e}", file=sys.stderr)
        sys.exit(1)

if __name__ == "__main__":
    main()