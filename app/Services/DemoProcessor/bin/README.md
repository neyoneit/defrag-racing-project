# DemoCleaner3 Linux Renamer

Python port of DemoCleaner3's demo parser and rename logic. It runs entirely on Linux (or any system with Python 3.10+) and reproduces the behaviour of:

- filename suggestion (`Demo.GetDemoFromRawInfo` / `fillDemoNewName`)
- duplicate handling / deletion rules from `FileHelper.renameFile`
- optional logging, progress counters, and case-only renames

## Requirements

- Python 3.10 or newer
- `DemoCleaner3_LinuxRenamer` directory kept intact (modules use relative imports)

## Quick start

```bash
# from the project root
py -3 DemoCleaner3_LinuxRenamer/renamer.py /path/to/demo.dm_68
```

The CLI will parse the demo, print the suggested filename, and rename the file in place:

```
Suggested name: map[physic]mm.ss.mmm(player).dm_68
renamed
/path/to/map[physic]mm.ss.mmm(player).dm_68
```

### CLI options

```
usage: renamer.py [-h] [--delete-identical] [--log-file LOG_FILE] file [new_name]
```

- `file` – path to the demo to rename
- `new_name` (optional) – override the suggested filename. If omitted the tool auto-generates the name from demo contents
- `--delete-identical` – when the target filename already exists, delete the source if both files are identical
- `--log-file` – write move/rename/delete operations to a DemoCleaner-style log file

### Library usage

```python
from DemoCleaner3_LinuxRenamer.renamer import FileRenamer, RenameStatus
from DemoCleaner3_LinuxRenamer.renamer import suggest_name

suggested = suggest_name(Path("/path/demo.dm_68"))
renamer = FileRenamer()
outcome = renamer.rename_file(Path("/path/demo.dm_68"), suggested, delete_identical=True)
assert outcome.status is RenameStatus.RENAMED
```

`suggest_name` returns `None` when the parser cannot determine a valid filename (malformed demo, missing data, etc.).

## Notes & parity gaps

- Parser is a direct port of DemoCleaner3's C# demo reader. If the original tool fails on a demo, this port will likely fail as well.
- No batching UI; you can script over the CLI to process folders.
- Logging, permission fixes, and duplicate detection mirror the Windows version.
- Known warnings about "Possible nested set" come from regexes copied verbatim from the C# project and are benign.
## Future improvements

- Add automated regression tests around `demo.py`, `raw_info.py`, `console_commands_parser.py`, and `renamer.py` to lock the parsing/rename behaviour down.
- Replace the legacy regexes in `demo.py` and `console_string_utils.py` (the ones that emit the "Possible nested set" warning) with clearer expressions once covered by tests.
