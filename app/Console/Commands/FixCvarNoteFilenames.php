<?php

namespace App\Console\Commands;

use App\Models\UploadedDemo;
use Illuminate\Console\Command;

/**
 * Repairs the doubled cvar note left in processed_filename.
 *
 * The renamer appends a note like {sv_cheats=1} when a demo was recorded with
 * a cvar it should not have been. It also checks whether the name already
 * carries one, so it does not add a second - except PHP's str-cast of a float
 * gives "1.0" where the original names say "1", so the check never matched its
 * own note and appended anyway. The result is
 * {sv_cheats=1{sv_cheats=1.0}: two notes, one of them never closed.
 *
 * The cause is fixed in the parser; this only cleans up names already stored.
 *
 * Nothing on storage is touched, and nothing needs to be. A demo is found by
 * file_path - processed_filename is only the name offered to the browser on
 * download, what the lists show, and what search matches against. Half of the
 * affected rows are failed-validity and keep no file at all; the other half do
 * have one in the bucket, still under its old key, which stays exactly where
 * it is.
 *
 * Reports without writing unless --apply is passed. Applying writes a journal
 * of every old name next to the log, so it can be put back.
 */
class FixCvarNoteFilenames extends Command
{
    protected $signature = 'demos:fix-cvar-note
        {--apply : Actually write the corrected names. Without this nothing is written}
        {--limit=0 : Stop after this many rows (0 = all)}';

    protected $description = 'Fix filenames left with a doubled or unclosed cvar note';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $apply = (bool) $this->option('apply');

        $rows = UploadedDemo::whereNotNull('processed_filename')
            ->where('processed_filename', 'like', '%{%')
            ->select('id', 'processed_filename')
            ->get();

        $fixes = [];

        foreach ($rows as $row) {
            $fixed = $this->repair($row->processed_filename);

            if ($fixed !== null && $fixed !== $row->processed_filename) {
                $fixes[] = [$row->id, $row->processed_filename, $fixed];
            }
        }

        if ($limit > 0) {
            $fixes = array_slice($fixes, 0, $limit);
        }

        if (! $fixes) {
            $this->info('Nothing to fix.');

            return self::SUCCESS;
        }

        $this->info(count($fixes) . ' name(s) to correct'
            . ' out of ' . $rows->count() . ' carrying a brace at all.');
        $this->newLine();

        foreach (array_slice($fixes, 0, $apply ? 0 : 10) as [$id, $was, $now]) {
            $this->line('  ' . $id);
            $this->line('    was: ' . $was);
            $this->line('    now: ' . $now);
        }

        if (! $apply) {
            if (count($fixes) > 10) {
                $this->line('  ... and ' . (count($fixes) - 10) . ' more');
            }

            $this->newLine();
            $this->comment('Nothing was written. Add --apply to correct these.');

            return self::SUCCESS;
        }

        // Written before the first update, not after the last, so an
        // interrupted run still leaves every old name recoverable.
        $journal = storage_path('logs/cvar-note-fix-' . now()->format('Ymd-His') . '.json');
        file_put_contents($journal, json_encode(
            array_map(fn ($f) => ['id' => $f[0], 'was' => $f[1], 'now' => $f[2]], $fixes),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ));
        $this->line('Old names written to ' . $journal);

        $written = 0;

        foreach ($fixes as [$id, $was, $now]) {
            UploadedDemo::where('id', $id)->update(['processed_filename' => $now]);
            $written++;
        }

        $this->info('Corrected ' . $written . ' name(s).');

        return self::SUCCESS;
    }

    /**
     * The name with its trailing cvar notes rebuilt, or null when it carries
     * none. A brace anywhere else - players do call themselves things like
     * "{oss" or "DOSIA{Andre" - is left alone, because the notes only ever sit
     * at the very end, after the extension is stripped.
     */
    private function repair(string $name): ?string
    {
        $ext = pathinfo($name, PATHINFO_EXTENSION);
        $base = $ext === '' ? $name : substr($name, 0, -(strlen($ext) + 1));

        if (! preg_match('/^(.*?)((?:\{\w+=[^{}]*\}?)+)$/', $base, $m)) {
            return null;
        }

        if (! preg_match_all('/\{(\w+)=([^{}]*)\}?/', $m[2], $notes, PREG_SET_ORDER)) {
            return null;
        }

        // Only touch a name the renamer actually damaged: one where a note was
        // left open, or where the same note is there twice. A single, properly
        // closed {sv_cheats=1.0} is not damage - it is simply how the value was
        // written at the time, and thousands of those name a file that exists.
        // Rewriting them would leave the row pointing at a name storage has
        // never heard of.
        $unclosed = substr_count($m[2], '{') !== substr_count($m[2], '}');
        $duplicated = count(array_unique(array_column($notes, 1))) < count($notes);

        if (! $unclosed && ! $duplicated) {
            return null;
        }

        $pairs = [];

        foreach ($notes as $note) {
            // The parser reads cvars as floats, so a flag comes back as "1.0"
            // and a whole number as "120.0", while the names written before it
            // say "1" and "120". Same value, so drop the tail.
            $pairs[$note[1]] = preg_replace('/\.0+$/', '', $note[2]);
        }

        // A truncated key next to the full one - {sv_chea=1{sv_cheats=1.0} -
        // is the same note twice, so keep the one that is spelled out.
        foreach (array_keys($pairs) as $key) {
            foreach (array_keys($pairs) as $other) {
                if ($key !== $other && str_starts_with($other, $key) && $pairs[$key] === $pairs[$other]) {
                    unset($pairs[$key]);
                    break;
                }
            }
        }

        $rebuilt = '';

        foreach ($pairs as $key => $value) {
            $rebuilt .= '{' . $key . '=' . $value . '}';
        }

        return $m[1] . $rebuilt . ($ext === '' ? '' : '.' . $ext);
    }
}
