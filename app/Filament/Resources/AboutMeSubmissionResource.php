<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AboutMeSubmissionResource\Pages;
use App\Models\AboutMeSubmission;
use App\Models\User;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AboutMeSubmissionResource extends Resource
{
    protected static ?string $model = AboutMeSubmission::class;
    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    protected static ?string $navigationGroup = 'Moderation';
    protected static ?string $navigationLabel = 'About Me';
    protected static ?int $navigationSort = 1;
    protected static bool $shouldSkipAuthorization = true;

    public static function canAccess(): bool
    {
        $user = auth()->user();
        return $user && ($user->admin || $user->is_moderator);
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) AboutMeSubmission::where('status', 'pending')->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Profile')
                    ->searchable()
                    ->formatStateUsing(fn (string $state): string => UserResource::q3tohtml($state))->html()
                    ->url(fn ($record) => "/profile/{$record->user_id}"),
                Tables\Columns\TextColumn::make('submitter.name')
                    ->label('Submitted By')
                    ->searchable()
                    ->formatStateUsing(fn (string $state): string => UserResource::q3tohtml($state))->html(),
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'create' => 'success',
                        'edit' => 'info',
                        'delete' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('content')
                    ->limit(80)
                    ->tooltip(fn ($record) => $record->content)
                    ->wrap(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ])
                    ,
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record) => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->update([
                            'status' => 'approved',
                            'reviewed_by' => auth()->id(),
                            'reviewed_at' => now(),
                        ]);

                        $user = User::find($record->user_id);
                        if ($record->type === 'delete') {
                            $user->update(['about_me' => null]);
                        } else {
                            $user->update(['about_me' => $record->content]);
                        }
                    }),
                Tables\Actions\Action::make('reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn ($record) => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->update([
                            'status' => 'rejected',
                            'reviewed_by' => auth()->id(),
                            'reviewed_at' => now(),
                        ]);
                    }),
                Tables\Actions\Action::make('editContent')
                    ->icon('heroicon-o-pencil')
                    ->color('warning')
                    ->visible(fn ($record) => $record->status === 'pending' && $record->type !== 'delete')
                    ->form([
                        \Filament\Forms\Components\Textarea::make('content')
                            ->required()
                            ->maxLength(500)
                            ->default(fn ($record) => $record->content),
                    ])
                    ->action(function ($record, array $data) {
                        $record->update(['content' => $data['content']]);
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAboutMeSubmissions::route('/'),
        ];
    }
}
