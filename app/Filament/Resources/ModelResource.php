<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ModelResource\Pages;
use App\Models\PlayerModel;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ModelResource extends Resource
{
    protected static ?string $model = PlayerModel::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationLabel = 'Models';

    protected static ?string $modelLabel = 'Model';

    protected static ?string $pluralModelLabel = 'Models';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Textarea::make('description')
                    ->maxLength(65535)
                    ->columnSpanFull(),
                Forms\Components\Select::make('category')
                    ->options([
                        'player' => 'Player',
                        'weapon' => 'Weapon',
                        'shadow' => 'Shadow',
                        'item' => 'Item',
                    ])
                    ->required(),
                Forms\Components\TextInput::make('author')
                    ->maxLength(255),
                Forms\Components\TextInput::make('author_email')
                    ->email()
                    ->maxLength(255),
                Forms\Components\TextInput::make('downloads')
                    ->numeric()
                    ->default(0),
                Forms\Components\TextInput::make('poly_count')
                    ->numeric(),
                Forms\Components\TextInput::make('vert_count')
                    ->numeric(),
                Forms\Components\Toggle::make('has_sounds')
                    ->required(),
                Forms\Components\Toggle::make('has_ctf_skins')
                    ->required(),
                Forms\Components\Toggle::make('approved')
                    ->required()
                    ->label('Approved')
                    ->helperText('Approve this model to make it visible on the site'),
                Forms\Components\Toggle::make('hidden')
                    ->label('Hidden')
                    ->helperText('Hide this model from public listings')
                    ->default(false),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('head_icon')
                    ->label('Preview')
                    ->square()
                    ->size(60)
                    ->disk('public')
                    ->getStateUsing(function ($record) {
                        // Use head icon if available, otherwise use placeholder
                        if ($record->head_icon) {
                            return $record->head_icon;
                        }
                        return null;
                    })
                    ->defaultImageUrl('/images/no-image.png'),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.mdd_name')
                    ->label('Uploaded By')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('category')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'player' => 'success',
                        'weapon' => 'warning',
                        'shadow' => 'info',
                        'item' => 'primary',
                        default => 'gray',
                    }),
                Tables\Columns\IconColumn::make('approved')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\IconColumn::make('hidden')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('downloads')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->options([
                        'player' => 'Player',
                        'weapon' => 'Weapon',
                        'shadow' => 'Shadow',
                        'item' => 'Item',
                    ]),
                Tables\Filters\TernaryFilter::make('approved')
                    ->label('Approval Status')
                    ->placeholder('All models')
                    ->trueLabel('Approved only')
                    ->falseLabel('Pending approval only'),
                Tables\Filters\TernaryFilter::make('hidden')
                    ->label('Visibility')
                    ->placeholder('All models')
                    ->trueLabel('Hidden only')
                    ->falseLabel('Visible only'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(fn (PlayerModel $record) => $record->update(['approved' => true]))
                    ->visible(fn (PlayerModel $record) => !$record->approved),
                Tables\Actions\Action::make('unapprove')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn (PlayerModel $record) => $record->update(['approved' => false]))
                    ->visible(fn (PlayerModel $record) => $record->approved),
                Tables\Actions\Action::make('hide')
                    ->icon('heroicon-o-eye-slash')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(fn (PlayerModel $record) => $record->update(['hidden' => true]))
                    ->visible(fn (PlayerModel $record) => !$record->hidden),
                Tables\Actions\Action::make('show')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->requiresConfirmation()
                    ->action(fn (PlayerModel $record) => $record->update(['hidden' => false]))
                    ->visible(fn (PlayerModel $record) => $record->hidden),
                Tables\Actions\Action::make('generateThumbnail')
                    ->icon('heroicon-o-photo')
                    ->color('warning')
                    ->label('Generate GIF')
                    ->requiresConfirmation()
                    ->action(function (PlayerModel $record) {
                        try {
                            $gifPath = storage_path("app/public/thumbnails/model_{$record->id}.gif");
                            $thumbnailsDir = storage_path("app/public/thumbnails");

                            if (!file_exists($thumbnailsDir)) {
                                mkdir($thumbnailsDir, 0755, true);
                            }

                            // Run the Node.js thumbnail generation script
                            $result = \Illuminate\Support\Facades\Process::timeout(120)
                                ->env([
                                    'PUPPETEER_SKIP_CHROMIUM_DOWNLOAD' => 'true',
                                    'HOME' => '/tmp',
                                    'XDG_CONFIG_HOME' => '/tmp/.config'
                                ])
                                ->run([
                                    'node',
                                    base_path('renderModelThumbnail.cjs'),
                                    $record->id,
                                    $gifPath
                                ]);

                            if ($result->successful()) {
                                // Update model with thumbnail path
                                $record->update(['thumbnail' => "thumbnails/model_{$record->id}.gif"]);

                                \Filament\Notifications\Notification::make()
                                    ->title('Thumbnail generated successfully')
                                    ->success()
                                    ->send();
                            } else {
                                \Log::error("Failed to render thumbnail for model {$record->id}: " . $result->errorOutput());

                                \Filament\Notifications\Notification::make()
                                    ->title('Failed to generate thumbnail')
                                    ->body($result->errorOutput())
                                    ->danger()
                                    ->send();
                            }
                        } catch (\Exception $e) {
                            \Log::error("Error generating thumbnail for model {$record->id}: " . $e->getMessage());

                            \Filament\Notifications\Notification::make()
                                ->title('Error generating thumbnail')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('approve')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(fn ($records) => $records->each->update(['approved' => true])),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListModels::route('/'),
            'create' => Pages\CreateModel::route('/create'),
            'edit' => Pages\EditModel::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery();
    }
}
