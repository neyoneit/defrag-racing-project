<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

/**
 * Assembles the "core" server bundles that defrag server owners download.
 *
 * Per platform there are two pieces:
 *  - baseq3 data      : the ~485 MB id-Quake3 paks, static forever, built once
 *                       by hand and uploaded (dfsv-baseq3.tar). Never touched here.
 *  - the core bundle  : everything that actually changes - the oDFe engine and
 *                       the DeFRaG mod - plus the small static bits (recordsystem
 *                       modules, qagame, ip4db.dat, uglifix2.so / support DLLs).
 *                       THIS command rebuilds and publishes it.
 *
 * Only two inputs are dynamic and they are shared by both platforms:
 *  - oDFe      : pulled from the latest Defrag-racing/oDFe GitHub release.
 *  - the mod   : latest non-beta defrag_*.zip from q3defrag.org.
 * Everything else lives in the per-platform static archive (uploaded once, next
 * to the output on the dl_storage disk).
 *
 * A marker file per platform records which engine build + mod filename produced
 * the current output, so a scheduled run is a cheap no-op until something changes.
 *
 * The windows platform is skipped with a warning while its static archive has
 * not been uploaded yet - a linux-only box stays green.
 */
class BuildServerBundle extends Command
{
    protected $signature = 'bundle:build-server
        {--force : Rebuild even if the engine and mod are unchanged}
        {--platform=all : Which bundle to build: linux, windows or all}';

    protected $description = 'Assemble the core server bundles (oDFe engine + DeFRaG mod + static base) and publish them to the downloads disk';

    /** dl_storage disk root is /www/downloads on the storage VPS (local dir in dev). */
    private const DISK = 'dl_storage';

    private const ENGINE_RELEASE = 'https://api.github.com/repos/Defrag-racing/oDFe/releases/latest';
    // Both server binaries live in the multi-platform zip. They MUST be the
    // 32-bit builds: the MDD recordsystem is a 32-bit qagamei386.so /
    // qagamex86.dll, and a 64-bit oDFe.ded silently falls back to the qvm,
    // which disables the recordsystem entirely.
    private const ENGINE_ASSET   = 'oDFe-other.zip';
    private const MOD_INDEX      = 'https://q3defrag.org/files/defrag/';

    /**
     * Per-platform recipe.
     *
     * static / output / marker : file names on the dl_storage disk
     * engine_dir + engine_file : where the ded binary sits inside ENGINE_ASSET
     * engine_target            : its name inside the published bundle
     * format                   : tar (linux, preserves the exec bit) or zip (windows)
     * required                  : false = warn and skip when the static archive is missing
     */
    private const PLATFORMS = [
        'linux' => [
            'static'        => 'dfsv-core-static.tar',
            'output'        => 'dfsv-core.tar',
            'marker'        => 'dfsv-core.version.json',
            'engine_dir'    => 'linux-x86',
            'engine_file'   => 'oDFe.ded',
            'engine_target' => 'oDFe.ded',
            'format'        => 'tar',
            'required'      => true,
        ],
        'windows' => [
            'static'        => 'dfsv-core-win-static.zip',
            'output'        => 'dfsv-core-win.zip',
            'marker'        => 'dfsv-core-win.version.json',
            'engine_dir'    => 'windows-msvc-x86',
            'engine_file'   => 'oDFe.ded.exe',
            'engine_target' => 'oDFe.ded.exe',
            'format'        => 'zip',
            'required'      => false,
        ],
    ];

    public function handle(): int
    {
        $platform = strtolower((string) $this->option('platform'));
        if (! in_array($platform, ['all', 'linux', 'windows'], true)) {
            $this->error("Unknown --platform '{$platform}' (use linux, windows or all).");
            return self::FAILURE;
        }
        $targets = $platform === 'all' ? array_keys(self::PLATFORMS) : [$platform];

        $disk = Storage::disk(self::DISK);

        $engine = $this->latestEngine();
        if ($engine === null) {
            $this->error('Could not resolve the latest oDFe engine release.');
            return self::FAILURE;
        }

        $mod = $this->latestMod();
        if ($mod === null) {
            $this->error('Could not resolve the latest DeFRaG mod on q3defrag.org.');
            return self::FAILURE;
        }

        $this->info("Latest engine build: {$engine['version']}");
        $this->info("Latest mod:          {$mod['file']}");

        // Which targets actually need work? Resolving this first means the
        // engine and mod are downloaded only when at least one build runs.
        $todo = [];
        foreach ($targets as $name) {
            $recipe = self::PLATFORMS[$name];

            if (! $this->option('force') && $disk->exists($recipe['output']) && $disk->exists($recipe['marker'])) {
                $marker = json_decode((string) $disk->get($recipe['marker']), true) ?: [];
                if (($marker['engine'] ?? null) === $engine['version'] && ($marker['mod'] ?? null) === $mod['file']) {
                    $this->info("[{$name}] engine and mod unchanged since last build - nothing to do (use --force to rebuild).");
                    continue;
                }
            }

            if (! $disk->exists($recipe['static'])) {
                $message = $recipe['static'] . ' is missing on the ' . self::DISK . ' disk. Upload it once before building.';
                if ($recipe['required']) {
                    $this->error("[{$name}] {$message}");
                    return self::FAILURE;
                }
                $this->warn("[{$name}] skipped: {$message}");
                continue;
            }

            $todo[] = $name;
        }

        if (empty($todo)) {
            return self::SUCCESS;
        }

        $work = storage_path('app/bundle-build-' . uniqid());
        @mkdir($work, 0775, true);

        try {
            // Shared inputs - fetched once, reused by every platform.
            $this->info('Downloading engine...');
            $this->download($engine['url'], $work . '/engine.zip');
            $this->runProcess(['unzip', '-o', '-q', $work . '/engine.zip', '-d', $work . '/engine']);

            $this->info('Downloading mod...');
            $this->download($mod['url'], $work . '/mod.zip');
            $this->runProcess(['unzip', '-o', '-q', $work . '/mod.zip', '-d', $work . '/mod']);

            $pk3s = $this->findAll($work . '/mod', 'zz-*.pk3');
            if (empty($pk3s)) {
                throw new \RuntimeException('no zz-*.pk3 found inside ' . $mod['file']);
            }

            foreach ($todo as $name) {
                $this->buildPlatform($name, $work, $pk3s, $engine, $mod, $disk);
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Build failed: ' . $e->getMessage());
            Log::error('bundle:build-server failed', ['error' => $e->getMessage()]);
            return self::FAILURE;
        } finally {
            $this->runProcess(['rm', '-rf', $work], false);
        }
    }

    /**
     * Stage one platform's bundle (static base + fresh engine + fresh mod pk3s)
     * and publish it with its marker.
     *
     * @param  string[]                     $pk3s
     * @param  array{version:string,url:string} $engine
     * @param  array{file:string,url:string}    $mod
     */
    private function buildPlatform(
        string $name,
        string $work,
        array $pk3s,
        array $engine,
        array $mod,
        \Illuminate\Contracts\Filesystem\Filesystem $disk
    ): void {
        $recipe = self::PLATFORMS[$name];
        $stage  = $work . '/stage-' . $name;
        @mkdir($stage, 0775, true);

        // Static base -> stage/
        $staticLocal = $work . '/static-' . $name;
        file_put_contents($staticLocal, $disk->get($recipe['static']));
        if (str_ends_with(strtolower($recipe['static']), '.zip')) {
            $this->extractZip($staticLocal, $stage);
        } else {
            $this->runProcess(['tar', '-xf', $staticLocal, '-C', $stage]);
        }

        // Fresh engine -> stage/<engine_target>
        $engineDir = $work . '/engine/' . $recipe['engine_dir'];
        $ded       = is_dir($engineDir) ? $this->findFile($engineDir, $recipe['engine_file']) : null;
        if ($ded === null) {
            throw new \RuntimeException(
                $recipe['engine_dir'] . '/' . $recipe['engine_file']
                . ' (the required 32-bit build) not found inside ' . self::ENGINE_ASSET
            );
        }
        copy($ded, $stage . '/' . $recipe['engine_target']);
        chmod($stage . '/' . $recipe['engine_target'], 0755);

        // Fresh mod -> stage/defrag/zz-*.pk3
        @mkdir($stage . '/defrag', 0775, true);
        foreach ($pk3s as $pk3) {
            copy($pk3, $stage . '/defrag/' . basename($pk3));
        }

        // Repack
        $outLocal = $work . '/' . $recipe['output'];
        if ($recipe['format'] === 'zip') {
            $this->packZip($stage, $outLocal);
        } else {
            $this->runProcess(['tar', '-cf', $outLocal, '-C', $stage, '.']);
        }

        // Publish (stream so a ~10 MB body never sits in one buffer)
        $out = fopen($outLocal, 'rb');
        $disk->writeStream($recipe['output'], $out);
        if (is_resource($out)) {
            fclose($out);
        }

        $disk->put($recipe['marker'], json_encode([
            'engine'   => $engine['version'],
            'mod'      => $mod['file'],
            'built_at' => now()->toIso8601String(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $mb = round(filesize($outLocal) / 1048576, 2);
        $this->info("[{$name}] published " . $recipe['output'] . " ({$mb} MB) with " . count($pk3s) . ' mod pk3(s).');
        Log::info('bundle:build-server published', [
            'platform' => $name,
            'output'   => $recipe['output'],
            'engine'   => $engine['version'],
            'mod'      => $mod['file'],
            'mb'       => $mb,
        ]);
    }

    /**
     * Latest oDFe build. The release tag is the rolling "latest", so the
     * asset's updated_at is what actually identifies a given build.
     *
     * @return array{version:string,url:string}|null
     */
    private function latestEngine(): ?array
    {
        $res = Http::withHeaders(['User-Agent' => 'defrag-racing-bundle'])
            ->acceptJson()->timeout(20)->get(self::ENGINE_RELEASE);
        if (! $res->successful()) {
            return null;
        }
        $data = $res->json();
        foreach ($data['assets'] ?? [] as $asset) {
            if (($asset['name'] ?? '') === self::ENGINE_ASSET) {
                return [
                    'version' => (string) ($asset['updated_at'] ?? $data['published_at'] ?? ''),
                    'url'     => (string) $asset['browser_download_url'],
                ];
            }
        }
        return null;
    }

    /**
     * Newest non-beta defrag_*.zip from the q3defrag.org directory listing.
     *
     * @return array{file:string,url:string}|null
     */
    private function latestMod(): ?array
    {
        $res = Http::withOptions(['verify' => false])->timeout(20)->get(self::MOD_INDEX);
        if (! $res->successful()) {
            return null;
        }
        preg_match_all('/href="(defrag_[\d.]+\.zip)"/i', $res->body(), $m);
        $files = array_values(array_filter($m[1] ?? [], static fn ($f) => stripos($f, 'beta') === false));
        if (empty($files)) {
            return null;
        }
        usort($files, 'strnatcasecmp');
        $file = end($files);
        return ['file' => $file, 'url' => self::MOD_INDEX . $file];
    }

    private function download(string $url, string $dest): void
    {
        $res = Http::withOptions(['verify' => false, 'sink' => $dest])
            ->withHeaders(['User-Agent' => 'defrag-racing-bundle'])
            ->timeout(180)->get($url);
        if (! $res->successful()) {
            throw new \RuntimeException("download failed ({$url}): HTTP " . $res->status());
        }
    }

    private function extractZip(string $zipPath, string $dest): void
    {
        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new \RuntimeException("could not open zip {$zipPath}");
        }
        if (! $zip->extractTo($dest)) {
            $zip->close();
            throw new \RuntimeException("could not extract {$zipPath} into {$dest}");
        }
        $zip->close();
    }

    /** Zip the whole staging tree with paths relative to its root. */
    private function packZip(string $stage, string $dest): void
    {
        $zip = new \ZipArchive();
        if ($zip->open($dest, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException("could not create zip {$dest}");
        }
        // walk() yields files only; zip creates the parent entries implicitly.
        $prefix = rtrim($stage, '/') . '/';
        foreach ($this->walk($stage) as $file) {
            $zip->addFile($file->getPathname(), substr($file->getPathname(), strlen($prefix)));
        }
        if (! $zip->close()) {
            throw new \RuntimeException("could not finalize zip {$dest}");
        }
    }

    private function runProcess(array $cmd, bool $check = true): void
    {
        $p = new Process($cmd);
        $p->setTimeout(300);
        $p->run();
        if ($check && ! $p->isSuccessful()) {
            throw new \RuntimeException('command failed: ' . implode(' ', $cmd) . ' :: ' . $p->getErrorOutput());
        }
    }

    private function findFile(string $dir, string $name): ?string
    {
        foreach ($this->walk($dir) as $file) {
            if ($file->getFilename() === $name) {
                return $file->getPathname();
            }
        }
        return null;
    }

    /** @return string[] */
    private function findAll(string $dir, string $pattern): array
    {
        $out = [];
        foreach ($this->walk($dir) as $file) {
            if (fnmatch($pattern, $file->getFilename())) {
                $out[] = $file->getPathname();
            }
        }
        return $out;
    }

    private function walk(string $dir): \RecursiveIteratorIterator
    {
        return new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );
    }
}
