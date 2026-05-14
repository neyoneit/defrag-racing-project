<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\ValidatesStoragePath;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StorageBrowser extends Page
{
    use ValidatesStoragePath;

    protected static ?string $navigationIcon = 'heroicon-o-folder-open';
    protected static ?string $navigationLabel = 'Storage Browser';
    protected static ?string $navigationGroup = 'Storage';
    protected static ?string $title = 'Storage Browser (dl.defrag.racing)';
    protected static string $view = 'filament.pages.storage-browser';

    protected const DISK = 'dl_storage';

    public string $currentPath = '';

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function mount(): void
    {
        $this->currentPath = '';
    }

    protected function disk(): Filesystem
    {
        return Storage::disk(self::DISK);
    }

    public function getListingProperty(): array
    {
        $disk = $this->disk();
        $path = $this->currentPath;

        try {
            $directories = collect($disk->directories($path))
                ->map(fn ($p) => [
                    'name' => basename($p),
                    'path' => $p,
                    'type' => 'dir',
                ])
                ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
                ->values()
                ->all();

            $files = collect($disk->files($path))
                ->map(function ($p) use ($disk) {
                    $size = null;
                    $mtime = null;

                    try {
                        $size = $disk->size($p);
                    } catch (\Throwable) {
                    }

                    try {
                        $mtime = $disk->lastModified($p);
                    } catch (\Throwable) {
                    }

                    return [
                        'name' => basename($p),
                        'path' => $p,
                        'type' => 'file',
                        'size' => $size,
                        'mtime' => $mtime,
                    ];
                })
                ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
                ->values()
                ->all();

            return ['dirs' => $directories, 'files' => $files];
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Cannot list directory')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return ['dirs' => [], 'files' => []];
        }
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
    }

    public function goTo(string $path): void
    {
        $path = trim($path, '/');
        $this->validatePath($path);
        $this->currentPath = $path;
    }

    public function goUp(): void
    {
        if ($this->currentPath === '') {
            return;
        }

        $parts = explode('/', $this->currentPath);
        array_pop($parts);
        $this->currentPath = implode('/', $parts);
    }

    public function goRoot(): void
    {
        $this->currentPath = '';
    }

    public function downloadFile(string $name)
    {
        $this->validateName($name);
        $disk = $this->disk();
        $path = $this->joinPath($this->currentPath, $name);

        if (! $disk->exists($path)) {
            Notification::make()->title('File not found')->danger()->send();

            return null;
        }

        $size = $disk->size($path);

        return new StreamedResponse(function () use ($disk, $path) {
            $stream = $disk->readStream($path);

            if (is_resource($stream)) {
                fpassthru($stream);
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="' . addslashes(basename($name)) . '"',
            'Content-Length' => (string) $size,
            'X-Accel-Buffering' => 'no',
        ]);
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
