<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\ValidatesStoragePath;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class StorageBrowser extends Page
{
    use ValidatesStoragePath;

    protected static ?string $navigationIcon = 'heroicon-o-folder-open';
    protected static ?string $navigationLabel = 'Storage Browser';
    protected static ?string $navigationGroup = 'Storage';
    protected static ?string $title = 'Storage Browser (dl.defrag.racing)';
    protected static string $view = 'filament.pages.storage-browser';

    protected const DISK = 'dl_storage';

    public const PER_PAGE = 100;

    public string $currentPath = '';
    public int $page = 1;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasModeratorPermission('storage_browser') ?? false;
    }

    public function mount(): void
    {
        $this->currentPath = '';
    }

    protected function disk(): Filesystem
    {
        return Storage::disk(self::DISK);
    }

    /**
     * One cached listing per directory. The old code listed the directory
     * twice (directories() + files()) and then stat'ed EVERY file for size
     * and mtime - one SFTP round trip each, so a folder with thousands of
     * pk3s took forever. Flysystem's listContents() carries size + mtime in
     * the listing itself: single traversal, cached 30s (mutations below
     * invalidate it). Files are paginated in getListingProperty().
     *
     * Symlinks (e.g. bsp/, maps/ on the storage VPS) arrive typed as FILES
     * in the SFTP listing, which made them unbrowsable. Entries that look
     * like that (no extension or unknown size) get one follow-the-link stat
     * (directoryExists) and are reclassified as folders when they resolve to
     * a directory.
     */
    private function rawListing(): array
    {
        $path = $this->currentPath;

        return Cache::remember('dlstorage:list:' . $path, 30, function () use ($path) {
            $disk = $this->disk();
            $dirs = [];
            $files = [];

            foreach ($disk->getDriver()->listContents($path, false) as $item) {
                $name = basename($item->path());
                if ($item->isDir()) {
                    $dirs[] = ['name' => $name, 'path' => $item->path(), 'type' => 'dir'];
                } else {
                    $files[] = [
                        'name' => $name,
                        'path' => $item->path(),
                        'type' => 'file',
                        'size' => $item->fileSize(),
                        'mtime' => $item->lastModified(),
                    ];
                }
            }

            // Symlink-to-directory fixup (see docblock).
            foreach ($files as $i => $f) {
                $suspicious = $f['size'] === null || ! str_contains($f['name'], '.');
                if (! $suspicious) {
                    continue;
                }
                try {
                    if ($disk->directoryExists($f['path'])) {
                        $dirs[] = ['name' => $f['name'], 'path' => $f['path'], 'type' => 'dir'];
                        unset($files[$i]);
                    }
                } catch (\Throwable) {
                }
            }

            $byName = fn ($a, $b) => strnatcasecmp($a['name'], $b['name']);
            usort($dirs, $byName);
            $files = array_values($files);
            usort($files, $byName);

            return ['dirs' => $dirs, 'files' => $files];
        });
    }

    public function getListingProperty(): array
    {
        try {
            $all = $this->rawListing();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Cannot list directory')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return ['dirs' => [], 'files' => [], 'totalFiles' => 0];
        }

        return [
            'dirs' => $all['dirs'],
            'files' => array_slice($all['files'], ($this->page - 1) * self::PER_PAGE, self::PER_PAGE),
            'totalFiles' => count($all['files']),
        ];
    }

    public function getTotalPagesProperty(): int
    {
        try {
            $total = count($this->rawListing()['files']);
        } catch (\Throwable) {
            return 1;
        }

        return max(1, (int) ceil($total / self::PER_PAGE));
    }

    public function nextPage(): void
    {
        $this->page = min($this->totalPages, $this->page + 1);
    }

    public function prevPage(): void
    {
        $this->page = max(1, $this->page - 1);
    }

    private function forgetListing(): void
    {
        Cache::forget('dlstorage:list:' . $this->currentPath);
    }

    public function getBreadcrumbsProperty(): array
    {
        if ($this->currentPath === '') {
            return [];
        }

        $segments = explode('/', $this->currentPath);
        $crumbs = [];
        $accum = '';

        foreach ($segments as $segment) {
            $accum = $accum === '' ? $segment : $accum . '/' . $segment;
            $crumbs[] = ['name' => $segment, 'path' => $accum];
        }

        return $crumbs;
    }

    public function enterDir(string $name): void
    {
        $this->validateName($name);
        $this->currentPath = $this->joinPath($this->currentPath, $name);
        $this->page = 1;
    }

    public function goTo(string $path): void
    {
        $path = trim($path, '/');
        $this->validatePath($path);
        $this->currentPath = $path;
        $this->page = 1;
    }

    public function goUp(): void
    {
        if ($this->currentPath === '') {
            return;
        }

        $parts = explode('/', $this->currentPath);
        array_pop($parts);
        $this->currentPath = implode('/', $parts);
        $this->page = 1;
    }

    public function goRoot(): void
    {
        $this->currentPath = '';
        $this->page = 1;
    }

    // Same signed-url handoff as the Serverdemos Browser: Livewire can't
    // reliably carry a streamed download (and dl_storage holds multi-hundred
    // MB files that must never ride through a Livewire payload). The
    // controller re-checks auth + permission and streams with no temp file.
    public function downloadFile(string $name)
    {
        $this->validateName($name);
        $path = $this->joinPath($this->currentPath, $name);

        return redirect()->to(\Illuminate\Support\Facades\URL::temporarySignedRoute(
            'defraghq.storage-download',
            now()->addMinutes(5),
            ['disk' => self::DISK, 'path' => $path],
        ));
    }

    public function mkdirAction(): Action
    {
        return Action::make('mkdir')
            ->label('New folder')
            ->icon('heroicon-o-folder-plus')
            ->color('primary')
            ->form([
                TextInput::make('name')
                    ->label('Folder name')
                    ->required()
                    ->maxLength(255)
                    ->regex('/^[A-Za-z0-9._\-][A-Za-z0-9._\- ]*$/')
                    ->helperText('Letters, digits, dot, underscore, hyphen, space.'),
            ])
            ->action(function (array $data) {
                $name = $data['name'];
                $this->validateName($name);

                $disk = $this->disk();
                $path = $this->joinPath($this->currentPath, $name);

                if ($disk->exists($path)) {
                    Notification::make()->title('Already exists')->danger()->send();

                    return;
                }

                $disk->makeDirectory($path);
                $this->forgetListing();

                Notification::make()->title('Folder created')->success()->send();
            });
    }

    public function uploadAction(): Action
    {
        return Action::make('upload')
            ->label('Upload files')
            ->icon('heroicon-o-arrow-up-tray')
            ->color('primary')
            ->form([
                FileUpload::make('files')
                    ->label('Drop files here')
                    ->multiple()
                    ->preserveFilenames()
                    ->required()
                    ->disk('local')
                    ->directory('storage-browser-uploads')
                    ->maxSize(1024 * 1024)
                    ->helperText('Max 1 GB per file. Existing files will be overwritten.'),
            ])
            ->action(function (array $data) {
                $disk = $this->disk();
                $local = Storage::disk('local');
                $uploaded = 0;
                $overwritten = 0;

                foreach ((array) $data['files'] as $tempPath) {
                    $name = basename($tempPath);
                    $this->validateName($name);

                    $targetPath = $this->joinPath($this->currentPath, $name);
                    $existed = $disk->exists($targetPath);

                    $stream = $local->readStream($tempPath);

                    if (! is_resource($stream)) {
                        continue;
                    }

                    $disk->writeStream($targetPath, $stream);

                    if (is_resource($stream)) {
                        fclose($stream);
                    }

                    $local->delete($tempPath);

                    $uploaded++;

                    if ($existed) {
                        $overwritten++;
                    }
                }

                $this->forgetListing();

                Notification::make()
                    ->title("Uploaded {$uploaded} file(s)")
                    ->body($overwritten > 0 ? "{$overwritten} overwritten." : null)
                    ->success()
                    ->send();
            });
    }

    public function renameAction(): Action
    {
        return Action::make('rename')
            ->icon('heroicon-o-pencil-square')
            ->modalHeading(fn (array $arguments) => "Rename \"{$arguments['oldName']}\"")
            ->form(fn (array $arguments) => [
                TextInput::make('newName')
                    ->label('New name')
                    ->default($arguments['oldName'] ?? '')
                    ->required()
                    ->maxLength(255)
                    ->regex('/^[A-Za-z0-9._\-][A-Za-z0-9._\- ]*$/'),
            ])
            ->action(function (array $data, array $arguments) {
                $oldName = $arguments['oldName'];
                $newName = $data['newName'];

                $this->validateName($oldName);
                $this->validateName($newName);

                if ($oldName === $newName) {
                    return;
                }

                $disk = $this->disk();
                $oldPath = $this->joinPath($this->currentPath, $oldName);
                $newPath = $this->joinPath($this->currentPath, $newName);

                if ($disk->exists($newPath)) {
                    Notification::make()->title('Target already exists')->danger()->send();

                    return;
                }

                $disk->move($oldPath, $newPath);
                $this->forgetListing();

                Notification::make()->title('Renamed')->success()->send();
            });
    }

    public function deleteAction(): Action
    {
        return Action::make('delete')
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(fn (array $arguments) => "Delete \"{$arguments['name']}\"?")
            ->modalDescription('This permanently removes the file/folder from the storage VPS. Cannot be undone.')
            ->modalSubmitActionLabel('Delete')
            ->action(function (array $arguments) {
                $name = $arguments['name'];
                $type = $arguments['type'];

                $this->validateName($name);

                $disk = $this->disk();
                $path = $this->joinPath($this->currentPath, $name);

                if ($type === 'dir') {
                    $disk->deleteDirectory($path);
                } else {
                    $disk->delete($path);
                }

                $this->forgetListing();

                Notification::make()->title('Deleted')->success()->send();
            });
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->uploadAction(),
            $this->mkdirAction(),
        ];
    }

    public function formatBytes(?int $bytes): string
    {
        if ($bytes === null) {
            return '?';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        $value = (float) $bytes;

        while ($value >= 1024 && $i < count($units) - 1) {
            $value /= 1024;
            $i++;
        }

        return round($value, $i === 0 ? 0 : 1) . ' ' . $units[$i];
    }
}
