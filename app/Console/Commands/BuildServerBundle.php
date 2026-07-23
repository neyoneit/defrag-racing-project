<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

/**
 * Assembles the "core" server bundle that defrag server owners download.
 *
 * The bundle is split in two:
 *  - dfsv-baseq3.tar  : the ~485 MB id-Quake3 paks, static forever, built once
 *                       by hand and uploaded. This command never touches it.
 *  - dfsv-core.tar    : everything that actually changes - the oDFe engine and
 *                       the DeFRaG mod - plus the small static bits (recordsystem
 *                       modules, qagame, ip4db.dat, uglifix2.so). THIS command
 *                       rebuilds and publishes it.
 *
 * Only two inputs are dynamic:
 *  - oDFe.ded  : pulled from the latest Defrag-racing/oDFe GitHub release.
 *  - the mod   : latest non-beta defrag_*.zip from q3defrag.org.
 * Everything else lives in dfsv-core-static.tar (uploaded once, next to the
 * output on the dl_storage disk).
 *
 * A marker file records which engine build + mod filename produced the current
 * dfsv-core.tar, so a scheduled run is a cheap no-op until something changes.
 */
class BuildServerBundle extends Command
{
    protected $signature = 'bundle:build-server {--force : Rebuild even if the engine and mod are unchanged}';
    protected $description = 'Assemble dfsv-core.tar (oDFe engine + DeFRaG mod + static base) and publish it to the downloads disk';

    /** dl_storage disk root is /www/downloads on the storage VPS (local dir in dev). */
    private const DISK = 'dl_storage';

    private const STATIC_TAR = 'dfsv-core-static.tar';   // input, uploaded once (modules + qagame + ip4db + uglifix)
    private const OUTPUT_TAR = 'dfsv-core.tar';          // published output
    private const MARKER     = 'dfsv-core.version.json'; // records what produced the current output

    private const ENGINE_RELEASE = 'https://api.github.com/repos/Defrag-racing/oDFe/releases/latest';
    // The i386 Linux server binary lives in the multi-platform zip under
    // linux-x86/. It MUST be the 32-bit build: the MDD recordsystem is a
    // 32-bit qagamei386.so, and a 64-bit oDFe.ded silently falls back to
    // the qvm, which disables the recordsystem entirely.
    private const ENGINE_ASSET   = 'oDFe-other.zip';
    private const MOD_INDEX      = 'https://q3defrag.org/files/defrag/';

    public function handle(): int
    {
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

        // Cheap no-op when nothing changed since the last successful build.
        if (! $this->option('force') && $disk->exists(self::OUTPUT_TAR) && $disk->exists(self::MARKER)) {
            $marker = json_decode((string) $disk->get(self::MARKER), true) ?: [];
            if (($marker['engine'] ?? null) === $engine['version'] && ($marker['mod'] ?? null) === $mod['file']) {
                $this->info('Engine and mod unchanged since last build - nothing to do (use --force to rebuild).');
                return self::SUCCESS;
            }
        }

        if (! $disk->exists(self::STATIC_TAR)) {
            $this->error(self::STATIC_TAR . ' is missing on the ' . self::DISK . ' disk. Upload it once before building.');
            return self::FAILURE;
        }

        $work  = storage_path('app/bundle-build-' . uniqid());
        $stage = $work . '/stage';
        @mkdir($stage, 0775, true);

        try {
            // Static base -> stage/
            file_put_contents($work . '/static.tar', $disk->get(self::STATIC_TAR));
            $this->runProcess(['tar', '-xf', $work . '/static.tar', '-C', $stage]);

            // Fresh engine -> stage/oDFe.ded
            $this->info('Downloading engine...');
            $this->download($engine['url'], $work . '/engine.zip');
            $this->runProcess(['unzip', '-o', '-q', $work . '/engine.zip', '-d', $work . '/engine']);
            $ded = is_dir($work . '/engine/linux-x86')
                ? $this->findFile($work . '/engine/linux-x86', 'oDFe.ded')
                : null;
            if ($ded === null) {
                throw new \RuntimeException('linux-x86/oDFe.ded (the required i386 build) not found inside ' . self::ENGINE_ASSET);
            }
            copy($ded, $stage . '/oDFe.ded');
            chmod($stage . '/oDFe.ded', 0755);

            // Fresh mod -> stage/defrag/zz-*.pk3
            $this->info('Downloading mod...');
            $this->download($mod['url'], $work . '/mod.zip');
            $this->runProcess(['unzip', '-o', '-q', $work . '/mod.zip', '-d', $work . '/mod']);
            @mkdir($stage . '/defrag', 0775, true);
            $pk3s = $this->findAll($work . '/mod', 'zz-*.pk3');
            if (empty($pk3s)) {
                throw new \RuntimeException('no zz-*.pk3 found inside ' . $mod['file']);
            }
            foreach ($pk3s as $pk3) {
                copy($pk3, $stage . '/defrag/' . basename($pk3));
            }

            // Repack -> dfsv-core.tar
            $this->runProcess(['tar', '-cf', $work . '/' . self::OUTPUT_TAR, '-C', $stage, '.']);

            // Publish (stream so a ~10 MB body never sits in one buffer)
            $out = fopen($work . '/' . self::OUTPUT_TAR, 'rb');
            $disk->writeStream(self::OUTPUT_TAR, $out);
            if (is_resource($out)) {
                fclose($out);
            }

            $disk->put(self::MARKER, json_encode([
                'engine'   => $engine['version'],
                'mod'      => $mod['file'],
                'built_at' => now()->toIso8601String(),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            $mb = round(filesize($work . '/' . self::OUTPUT_TAR) / 1048576, 2);
            $this->info("Published " . self::OUTPUT_TAR . " ({$mb} MB) with " . count($pk3s) . ' mod pk3(s).');
            Log::info('bundle:build-server published', ['engine' => $engine['version'], 'mod' => $mod['file'], 'mb' => $mb]);

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
     * Latest oDFe Linux build. The release tag is the rolling "latest", so the
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
