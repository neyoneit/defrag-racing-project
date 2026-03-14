#!/usr/bin/env python3
"""
Test script: compares Python vs C Huffman reader output on demo files.
Runs parse_demo_metadata() with both implementations and verifies identical results.

Usage: python3 test_c_extension.py [--count N] [--verbose]
"""
import sys
import os
import json
import time
import random
import warnings
import argparse
from pathlib import Path

warnings.filterwarnings('ignore')

# Add paths
current_dir = Path(__file__).parent
sys.path.insert(0, str(current_dir))

from demoparser.huffman import _Q3HuffmanReaderPython, _HAS_C_EXTENSION
if _HAS_C_EXTENSION:
    from demoparser.huffman import _Q3HuffmanReaderC


def parse_with_reader(demo_path: str, reader_class):
    """Parse a demo file using a specific reader class, return metadata dict."""
    # Temporarily monkey-patch Q3HuffmanReader in the parser module
    import demoparser.huffman as huff_mod
    import demoparser.parser as parser_mod
    original = huff_mod.Q3HuffmanReader
    huff_mod.Q3HuffmanReader = reader_class
    # parser.py imports Q3HuffmanReader at module level, so we need to patch there too
    parser_mod.Q3HuffmanReader = reader_class
    try:
        from renamer import parse_demo_metadata
        result = parse_demo_metadata(Path(demo_path))
        return result
    finally:
        huff_mod.Q3HuffmanReader = original
        parser_mod.Q3HuffmanReader = original


def compare_metadata(py_result, c_result, demo_path):
    """Compare two metadata dicts, return list of differences."""
    diffs = []

    if py_result is None and c_result is None:
        return diffs
    if py_result is None and c_result is not None:
        diffs.append(f"Python returned None, C returned data")
        return diffs
    if py_result is not None and c_result is None:
        diffs.append(f"Python returned data, C returned None")
        return diffs

    # Compare all keys
    all_keys = set(list(py_result.keys()) + list(c_result.keys()))
    for key in sorted(all_keys):
        py_val = py_result.get(key)
        c_val = c_result.get(key)
        if py_val != c_val:
            # For floats, allow small tolerance
            if isinstance(py_val, float) and isinstance(c_val, float):
                if abs(py_val - c_val) < 1e-6:
                    continue
            diffs.append(f"  {key}: Python={py_val!r}, C={c_val!r}")

    return diffs


def main():
    parser = argparse.ArgumentParser(description='Test C extension vs Python Huffman reader')
    parser.add_argument('--count', type=int, default=100, help='Number of demos to test (default: 100)')
    parser.add_argument('--verbose', action='store_true', help='Show details for each demo')
    parser.add_argument('--demo', type=str, help='Test a single specific demo file')
    args = parser.parse_args()

    if not _HAS_C_EXTENSION:
        print("ERROR: C extension not available! Build it first:")
        print("  cd demoparser && python3 setup.py build_ext --inplace")
        sys.exit(1)

    print(f"C extension loaded successfully")

    # Find demo files
    if args.demo:
        demo_files = [args.demo]
    else:
        demos_dir = current_dir.parent.parent.parent.parent / 'storage' / 'app' / 'demos'
        demo_files = []
        for ext in ['*.dm_68', '*.dm_91']:
            demo_files.extend(str(p) for p in demos_dir.rglob(ext))

        if not demo_files:
            print(f"No demo files found in {demos_dir}")
            sys.exit(1)

        random.shuffle(demo_files)
        demo_files = demo_files[:args.count]

    print(f"Testing {len(demo_files)} demo files...\n")

    passed = 0
    failed = 0
    errors = 0
    py_total_time = 0
    c_total_time = 0

    for i, demo_path in enumerate(demo_files, 1):
        demo_name = os.path.basename(demo_path)
        try:
            # Parse with Python
            t0 = time.perf_counter()
            py_result = parse_with_reader(demo_path, _Q3HuffmanReaderPython)
            py_time = time.perf_counter() - t0

            # Parse with C
            t0 = time.perf_counter()
            c_result = parse_with_reader(demo_path, _Q3HuffmanReaderC)
            c_time = time.perf_counter() - t0

            py_total_time += py_time
            c_total_time += c_time

            diffs = compare_metadata(py_result, c_result, demo_path)

            if diffs:
                failed += 1
                speedup = py_time / c_time if c_time > 0 else float('inf')
                print(f"[{i:3d}] FAIL {demo_name} (Py: {py_time:.2f}s, C: {c_time:.2f}s, {speedup:.1f}x)")
                for d in diffs:
                    print(f"       {d}")
            else:
                passed += 1
                speedup = py_time / c_time if c_time > 0 else float('inf')
                if args.verbose:
                    print(f"[{i:3d}] OK   {demo_name} (Py: {py_time:.2f}s, C: {c_time:.2f}s, {speedup:.1f}x)")

        except Exception as e:
            errors += 1
            print(f"[{i:3d}] ERR  {demo_name}: {e}")

    # Summary
    print(f"\n{'='*60}")
    print(f"Results: {passed} passed, {failed} failed, {errors} errors")
    print(f"Total Python time: {py_total_time:.2f}s")
    print(f"Total C time:      {c_total_time:.2f}s")
    if c_total_time > 0:
        print(f"Overall speedup:   {py_total_time / c_total_time:.1f}x")
    print(f"{'='*60}")

    if failed > 0:
        sys.exit(1)


if __name__ == "__main__":
    main()
