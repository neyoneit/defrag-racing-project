<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DownloadResource\Pages;
use App\Models\Download;
use App\Models\DownloadCategory;
use App\Models\ModerationLog;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

class DownloadResource extends Resource
{
    protected static ?string $model = Download::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-down-tray';

    protected static ?string $navigationGroup = 'Moderation';

    protected static ?string $navigationLabel = 'Downloads';

    protected static ?int $navigationSort = 7;

    protected static ?string $modelLabel = 'Download';

    protected static ?string $pluralModelLabel = 'Downloads';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasModeratorPermission('downloads') ?? false;
    }

    /**
     * Auto-managed entries (the mod, server bundle, family-friendly set) are
     * kept out of the moderation list: they are rebuilt by sync commands, so
     * editing one by hand would just be overwritten on the next run.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('is_locked', false)
            ->withCount('files');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->whereNotNull('user_id')->count();

        return $count ? (string) $count : null;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->required()
                ->maxLength(150),
            Forms\Components\Select::make('category_id')
                ->label('Category')
                ->options(fn () => static::categoryOptions())
                ->searchable()
                ->required(),
            Forms\Components\Textarea::make('description')
                ->maxLength(5000)
                ->columnSpanFull(),
            Forms\Components\TextInput::make('youtube_url')
                ->label('YouTube URL')
                ->url()
                ->maxLength(255),
            Forms\Components\Select::make('status')
                ->options([
                    Download::STATUS_PUBLISHED => 'Published',
                    Download::STATUS_HIDDEN => 'Hidden',
                    Download::STATUS_REJECTED => 'Rejected',
                ])
                ->required(),
            Forms\Components\Toggle::make('defrag_only')
                ->label('DeFRaG only'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->striped()
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Download $r) => $r->files_count . ' file(s)'),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Uploader')
                    ->formatStateUsing(fn ($state) => $state ?? 'system')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('category.name')
                    ->label('Category')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        Download::STATUS_PUBLISHED => 'success',
                        Download::STATUS_HIDDEN => 'warning',
                        Download::STATUS_REJECTED => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('downloads_count')
                    ->label('DL')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        Download::STATUS_PUBLISHED => 'Published',
                        Download::STATUS_HIDDEN => 'Hidden',
                        Download::STATUS_REJECTED => 'Rejected',
                    ]),
                Tables\Filters\SelectFilter::make('category_id')
                    ->label('Category')
                    ->options(fn () => static::categoryOptions())
                    ->searchable(),
                Tables\Filters\TernaryFilter::make('user_id')
                    ->label('Source')
                    ->placeholder('All')
                    ->trueLabel('User uploads only')
                    ->falseLabel('System only')
                    ->queries(
                        true: fn (Builder $q) => $q->whereNotNull('user_id'),
                        false: fn (Builder $q) => $q->whereNull('user_id'),
                        blank: fn (Builder $q) => $q,
                    ),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(fn (Download $r) => "/downloads/entry/{$r->id}/{$r->slug}")
                    ->openUrlInNewTab(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('publish')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (Download $record) {
                        $record->update(['status' => Download::STATUS_PUBLISHED]);
                        ModerationLog::log('downloads', 'published', $record, ['name' => $record->name]);
                    })
                    ->visible(fn (Download $r) => $r->status !== Download::STATUS_PUBLISHED),
                Tables\Actions\Action::make('hide')
                    ->icon('heroicon-o-eye-slash')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(function (Download $record) {
                        $record->update(['status' => Download::STATUS_HIDDEN]);
                        ModerationLog::log('downloads', 'hidden', $record, ['name' => $record->name]);
                    })
                    ->visible(fn (Download $r) => $r->status === Download::STATUS_PUBLISHED),
                Tables\Actions\DeleteAction::make()
                    ->before(fn (Download $record) => static::purgeFiles($record)),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->before(fn ($records) => $records->each(fn ($r) => static::purgeFiles($r))),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    /**
     * Deletes an entry's stored objects before the row goes, so a removed
     * upload does not leave orphans sitting in the bucket. cascadeOnDelete
     * handles the download_files rows; this handles the bytes.
     */
    public static function purgeFiles(Download $record): void
    {
        foreach ($record->allFiles as $file) {
            try {
                Storage::disk($file->disk)->delete($file->path);
            } catch (\Throwable $e) {
                // A missing object should not block deleting the record.
            }
        }
    }

    private static function categoryOptions(): array
    {
        return DownloadCategory::where('is_locked', false)
            ->whereNull('auto_source')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDownloads::route('/'),
            'edit' => Pages\EditDownload::route('/{record}/edit'),
        ];
    }
}
