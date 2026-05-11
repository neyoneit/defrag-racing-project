<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UploadedDemoResource\Pages;
use App\Models\UploadedDemo;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UploadedDemoResource extends Resource
{
    protected static ?string $model = UploadedDemo::class;

    protected static ?string $navigationIcon = 'heroicon-o-film';

    protected static ?string $navigationLabel = 'Uploaded Demos';

    protected static ?string $navigationGroup = 'Content';

    protected static ?int $navigationSort = 6;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasModeratorPermission('demo_reports') ?? false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['user', 'record', 'record.user', 'suggestedUser']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('original_filename')
                    ->label('Filename')
                    ->searchable()
                    ->limit(35)
                    ->tooltip(fn ($record) => $record->original_filename),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Uploader')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('user', fn ($q) => $q->where('name', 'like', "%{$search}%"));
                    })
                    ->formatStateUsing(fn ($state) => strip_tags(preg_replace('/\^[0-9]/', '', $state ?? '')))
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->sortable()
                    ->color(fn (string $state): string => match ($state) {
                        'assigned'             => 'success',
                        'fallback-assigned'    => 'info',
                        'processed'            => 'gray',
                        'uploaded'             => 'gray',
                        'processing'           => 'warning',
                        'failed'               => 'danger',
                        'failed-validity'      => 'danger',
                        'unsupported-version'  => 'danger',
                        default                => 'gray',
                    }),

                Tables\Columns\TextColumn::make('map_name')
                    ->label('Map')
                    ->searchable()
                    ->limit(25)
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('physics')
                    ->badge()
                    ->color(fn (?string $state) => str_contains($state ?? '', 'cpm') ? 'purple' : 'info')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('time_ms')
                    ->label('Time')
                    ->formatStateUsing(function ($state) {
                        if (! $state) return '—';
                        $m = floor($state / 60000);
                        $s = floor(($state % 60000) / 1000);
                        $ms = $state % 1000;
                        return sprintf('%d:%02d.%03d', $m, $s, $ms);
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('record_id')
                    ->label('Record')
                    ->placeholder('—')
                    ->url(fn ($record) => $record->record_id
                        ? url("/profile/mdd/" . ($record->record?->mdd_id ?? 0))
                        : null)
                    ->openUrlInNewTab(),

                Tables\Columns\TextColumn::make('file_size')
                    ->label('Size')
                    ->formatStateUsing(fn ($state) => $state ? round($state / 1024, 1) . ' KB' : '—')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('manually_assigned')
                    ->label('Manual')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Uploaded')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->multiple()
                    ->options([
                        'uploaded'             => 'Uploaded',
                        'processing'           => 'Processing',
                        'processed'            => 'Processed',
                        'assigned'             => 'Assigned',
                        'fallback-assigned'    => 'Fallback Assigned',
                        'failed'               => 'Failed',
                        'failed-validity'      => 'Failed Validity',
                        'unsupported-version'  => 'Unsupported Version',
                    ]),

                Tables\Filters\SelectFilter::make('physics')
                    ->options([
                        'cpm' => 'CPM',
                        'vq3' => 'VQ3',
                    ]),

                Tables\Filters\SelectFilter::make('gametype')
                    ->options([
                        'mdf' => 'mdf (online df)',
                        'mfs' => 'mfs (online fs)',
                        'mfc' => 'mfc (online fc)',
                        'df'  => 'df (offline)',
                        'fs'  => 'fs (offline)',
                        'fc'  => 'fc (offline)',
                    ]),

                Tables\Filters\TernaryFilter::make('record_id')
                    ->label('Has record')
                    ->placeholder('All')
                    ->trueLabel('Linked to record')
                    ->falseLabel('Unlinked')
                    ->queries(
                        true: fn (Builder $q) => $q->whereNotNull('record_id'),
                        false: fn (Builder $q) => $q->whereNull('record_id'),
                    ),

                Tables\Filters\TernaryFilter::make('manually_assigned'),

                Tables\Filters\Filter::make('needs_attention')
                    ->label('Needs attention (failed / unsupported)')
                    ->query(fn (Builder $q) => $q->whereIn('status', ['failed', 'failed-validity', 'unsupported-version'])),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->paginated([25, 50, 100, 250]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUploadedDemos::route('/'),
            'view'  => Pages\ViewUploadedDemo::route('/{record}'),
        ];
    }
}
