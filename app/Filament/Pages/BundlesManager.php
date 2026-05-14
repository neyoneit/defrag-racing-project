<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\ValidatesStoragePath;
use App\Models\Bundle;
use App\Models\BundleCategory;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\WithFileUploads;

class BundlesManager extends Page
{
    use ValidatesStoragePath;
    use WithFileUploads;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationLabel = 'Bundles Manager';
    protected static ?string $navigationGroup = 'Bundles';
    protected static ?int $navigationSort = 1;
    protected static ?string $title = 'Bundles Manager';
    protected static string $view = 'filament.pages.bundles-manager';

    protected const DISK = 'dl_storage';
    protected const PUBLIC_URL_BASE = 'https://dl.defrag.racing/downloads/';

    public ?int $selectedCategoryId = null;

    public bool $bundleFormOpen = false;
    public ?int $editingBundleId = null;
    public array $bundleForm = ['name' => '', 'description' => '', 'url' => ''];

    public bool $categoryFormOpen = false;
    public ?int $editingCategoryId = null;
    public array $categoryForm = ['name' => ''];

    public bool $pickerOpen = false;
    public string $pickerPath = '';
    public $pickerUploadFile = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function mount(): void
    {
        $first = BundleCategory::orderBy('name')->first();
        $this->selectedCategoryId = $first?->id;
    }

    protected function disk(): Filesystem
    {
        return Storage::disk(self::DISK);
    }

    #[Computed]
    public function categories()
    {
        return BundleCategory::withCount('bundles')->orderBy('position')->orderBy('name')->get();
    }

    #[Computed]
    public function selectedCategory()
    {
        if (! $this->selectedCategoryId) {
            return null;
        }

        return BundleCategory::with('bundles')->find($this->selectedCategoryId);
    }

    public function selectCategory(int $id): void
    {
        $this->selectedCategoryId = $id;
    }

    // ---------- Category form ----------

    public function openCategoryForm(?int $id = null): void
    {
        $this->editingCategoryId = $id;
        $this->categoryForm = ['name' => $id ? (BundleCategory::find($id)?->name ?? '') : ''];
        $this->categoryFormOpen = true;
    }

    public function closeCategoryForm(): void
    {
        $this->categoryFormOpen = false;
        $this->editingCategoryId = null;
    }

    public function saveCategory(): void
    {
        $name = trim((string) ($this->categoryForm['name'] ?? ''));

        if ($name === '') {
            Notification::make()->title('Name is required')->danger()->send();

            return;
        }

        if ($this->editingCategoryId) {
            BundleCategory::where('id', $this->editingCategoryId)->update(['name' => $name]);
        } else {
            $cat = BundleCategory::create(['name' => $name]);
            $this->selectedCategoryId = $cat->id;
        }

        unset($this->categories);
        $this->closeCategoryForm();

        Notification::make()->title('Category saved')->success()->send();
    }

    public function reorderCategories(array $orderedIds): void
    {
        $orderedIds = array_values(array_filter(array_map('intval', $orderedIds)));
        $valid = BundleCategory::whereIn('id', $orderedIds)->pluck('id')->all();

        foreach ($orderedIds as $position => $id) {
            if (! in_array($id, $valid, true)) {
                continue;
            }

            BundleCategory::where('id', $id)->update(['position' => $position]);
        }

        unset($this->categories);
    }

    public function reorderBundles(array $orderedIds): void
    {
        if (! $this->selectedCategoryId) {
            return;
        }

        $orderedIds = array_values(array_filter(array_map('intval', $orderedIds)));
        $valid = Bundle::where('category_id', $this->selectedCategoryId)
            ->whereIn('id', $orderedIds)
            ->pluck('id')
            ->all();

        foreach ($orderedIds as $position => $id) {
            if (! in_array($id, $valid, true)) {
                continue;
            }

            Bundle::where('id', $id)->update(['position' => $position]);
        }

        unset($this->selectedCategory);
    }

    public function deleteCategory(int $id): void
    {
        $cat = BundleCategory::withCount('bundles')->find($id);

        if (! $cat) {
            return;
        }

        if ($cat->bundles_count > 0) {
            Notification::make()
                ->title('Cannot delete')
                ->body("Category has {$cat->bundles_count} bundle(s). Move or delete them first.")
                ->danger()
                ->send();

            return;
        }

        $cat->delete();

        if ($this->selectedCategoryId === $id) {
            $this->selectedCategoryId = BundleCategory::orderBy('name')->value('id');
        }

        unset($this->categories);

        Notification::make()->title('Category deleted')->success()->send();
    }

    // ---------- Bundle form ----------

    public function openBundleForm(?int $id = null): void
    {
        $this->editingBundleId = $id;

        if ($id) {
            $b = Bundle::find($id);

            $this->bundleForm = [
                'name' => $b->name ?? '',
                'description' => $b->description ?? '',
                'url' => $b->url ?? '',
            ];
        } else {
            $this->bundleForm = ['name' => '', 'description' => '', 'url' => ''];
        }

        $this->bundleFormOpen = true;
        $this->pickerOpen = false;
    }

    public function closeBundleForm(): void
    {
        $this->bundleFormOpen = false;
        $this->editingBundleId = null;
    }

    public function saveBundle(): void
    {
        $name = trim((string) ($this->bundleForm['name'] ?? ''));
        $description = trim((string) ($this->bundleForm['description'] ?? ''));
        $url = trim((string) ($this->bundleForm['url'] ?? ''));

        if ($name === '' || $description === '' || $url === '') {
            Notification::make()->title('Name, description and URL are required')->danger()->send();

            return;
        }

        if (! $this->selectedCategoryId) {
            Notification::make()->title('Select a category first')->danger()->send();

            return;
        }

        $data = [
            'name' => $name,
            'description' => $description,
            'url' => $url,
            'category_id' => $this->selectedCategoryId,
        ];

        if ($this->editingBundleId) {
            Bundle::where('id', $this->editingBundleId)->update($data);
        } else {
            Bundle::create($data);
        }

        unset($this->categories, $this->selectedCategory);
        $this->closeBundleForm();

        Notification::make()->title('Bundle saved')->success()->send();
    }

    public function deleteBundle(int $id): void
    {
        Bundle::where('id', $id)->delete();
        unset($this->categories, $this->selectedCategory);

        Notification::make()->title('Bundle deleted')->success()->send();
    }

    // ---------- Storage picker ----------

    public function openPicker(): void
    {
        $this->pickerPath = '';
        $this->pickerOpen = true;
    }

    public function closePicker(): void
    {
        $this->pickerOpen = false;
        $this->pickerUploadFile = null;
    }

    #[Computed]
    public function pickerListing(): array
    {
        if (! $this->pickerOpen) {
            return ['dirs' => [], 'files' => []];
        }

        try {
            $disk = $this->disk();
            $path = $this->pickerPath;

            $dirs = collect($disk->directories($path))
                ->map(fn ($p) => ['name' => basename($p), 'path' => $p])
                ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
                ->values()
                ->all();

            $files = collect($disk->files($path))
                ->map(function ($p) use ($disk) {
                    $size = null;

                    try {
                        $size = $disk->size($p);
                    } catch (\Throwable) {
                    }

                    return ['name' => basename($p), 'path' => $p, 'size' => $size];
                })
                ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
                ->values()
                ->all();

            return ['dirs' => $dirs, 'files' => $files];
        } catch (\Throwable $e) {
            Notification::make()->title('Cannot list directory')->body($e->getMessage())->danger()->send();

            return ['dirs' => [], 'files' => []];
        }
    }

    #[Computed]
    public function pickerBreadcrumbs(): array
    {
        if ($this->pickerPath === '') {
            return [];
        }

        $segments = explode('/', $this->pickerPath);
        $crumbs = [];
        $accum = '';

        foreach ($segments as $segment) {
            $accum = $accum === '' ? $segment : $accum . '/' . $segment;
            $crumbs[] = ['name' => $segment, 'path' => $accum];
        }

        return $crumbs;
    }

    public function pickerEnterDir(string $name): void
    {
        $this->validateName($name);
        $this->pickerPath = $this->joinPath($this->pickerPath, $name);
    }

    public function pickerGoTo(string $path): void
    {
        $path = trim($path, '/');
        $this->validatePath($path);
        $this->pickerPath = $path;
    }

    public function pickerGoUp(): void
    {
        if ($this->pickerPath === '') {
            return;
        }

        $parts = explode('/', $this->pickerPath);
        array_pop($parts);
        $this->pickerPath = implode('/', $parts);
    }

    public function pickerGoRoot(): void
    {
        $this->pickerPath = '';
    }

    public function pickerSelectFile(string $relativePath): void
    {
        $this->validatePath($relativePath);

        $this->bundleForm['url'] = self::PUBLIC_URL_BASE . $relativePath;
        $this->pickerOpen = false;

        Notification::make()->title('File selected')->body($relativePath)->success()->send();
    }

    public function pickerCreateFolder(string $name): void
    {
        $this->validateName($name);

        $disk = $this->disk();
        $path = $this->joinPath($this->pickerPath, $name);

        if ($disk->exists($path)) {
            Notification::make()->title('Already exists')->danger()->send();

            return;
        }

        $disk->makeDirectory($path);
        unset($this->pickerListing);

        Notification::make()->title('Folder created')->success()->send();
    }

    public function pickerUploadHere(): void
    {
        if (! $this->pickerUploadFile) {
            Notification::make()->title('No file selected')->danger()->send();

            return;
        }

        $name = $this->pickerUploadFile->getClientOriginalName();
        $this->validateName($name);

        $disk = $this->disk();
        $targetPath = $this->joinPath($this->pickerPath, $name);

        $stream = fopen($this->pickerUploadFile->getRealPath(), 'rb');

        if (! is_resource($stream)) {
            Notification::make()->title('Cannot read upload')->danger()->send();

            return;
        }

        $disk->writeStream($targetPath, $stream);

        if (is_resource($stream)) {
            fclose($stream);
        }

        $this->pickerUploadFile = null;
        unset($this->pickerListing);

        Notification::make()->title('Uploaded')->body($name)->success()->send();
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
