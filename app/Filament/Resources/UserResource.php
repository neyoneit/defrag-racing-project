<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Community';

    public const OWNER_USER_ID = 8;

    public static function isOwner(): bool
    {
        return auth()->id() === self::OWNER_USER_ID;
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('username')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('plain_name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('email')
                    ->email()
                    ->required()
                    ->maxLength(255),
                Forms\Components\DateTimePicker::make('email_verified_at'),
                // Forms\Components\TextInput::make('password')
                //     ->password()
                //     ->required()
                //     ->maxLength(255),
                Forms\Components\TextInput::make('profile_photo_path')
                    ->maxLength(2048),
                // Forms\Components\TextInput::make('oldhash')
                //     ->maxLength(255),
                Forms\Components\TextInput::make('country')
                    ->required()
                    ->maxLength(255)
                    ->default('_404'),
                Forms\Components\TextInput::make('mdd_id')
                    ->maxLength(255),
                Forms\Components\Section::make('Roles')
                    ->schema([
                        Forms\Components\Toggle::make('admin')
                            ->label('Admin')
                            ->helperText('Full access: all admin panel sections, can delete, can manage moderators. Cannot grant admin role (only owner can).')
                            ->disabled(fn () => !self::isOwner()),
                        Forms\Components\Toggle::make('is_moderator')
                            ->label('Moderator')
                            ->helperText('Enable moderator role, then select which sections below.')
                            ->live(),
                        Forms\Components\CheckboxList::make('moderator_permissions')
                            ->label('Moderator Sections')
                            ->options([
                                'demo_reports' => 'Demo Reports',
                                'record_flags' => 'Record Flags',
                                'alias_reports' => 'Alias Reports',
                                'mapper_claims' => 'Mapper Claim Reports',
                                'models' => 'Models (approve/reject)',
                            ])
                            ->visible(fn (Forms\Get $get) => $get('is_moderator'))
                            ->columns(2),
                    ])->columns(1),
                Forms\Components\TextInput::make('twitter_name')
                    ->maxLength(255),
                Forms\Components\TextInput::make('twitch_name')
                    ->maxLength(255),
                Forms\Components\TextInput::make('discord_name')
                    ->maxLength(255),
                Forms\Components\TextInput::make('notification_settings')
                    ->required()
                    ->maxLength(255)
                    ->default('all'),
                Forms\Components\TextInput::make('model')
                    ->required()
                    ->maxLength(255)
                    ->default('sarge'),
            ]);
    }

    public static function q3tohtml($name) {
        $colored_name = '';
        $color = 'white';

        for ($i = 0; $i < strlen($name); $i++) {
            if ($name[$i] == '^') {
                if ($name[$i + 1] == '^') {
                    $colored_name .= '^';
                    $i++;
                } else {
                    $color = $name[$i + 1];
                    $i++;
                }
            } else {
                $colored_name .= '<span class="q3c-' . $color . '">' . $name[$i] . '</span>';
            }
        }

        return $colored_name;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->striped()
            ->columns([
                Tables\Columns\TextColumn::make('username')
                    ->searchable(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()->formatStateUsing(fn (string $state): string => UserResource::q3tohtml($state))->html(),
                Tables\Columns\TextColumn::make('plain_name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email_verified_at')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\ImageColumn::make('profile_photo_path')->defaultImageUrl(url('/images/null.jpg'))->circular(),
                Tables\Columns\TextColumn::make('country')
                    ->searchable(),
                Tables\Columns\TextColumn::make('mdd_id')
                    ->searchable(),
                Tables\Columns\IconColumn::make('admin')
                    ->boolean(),
                Tables\Columns\TextColumn::make('twitter_name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('twitch_name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('discord_name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('notification_settings')
                    ->searchable(),
                Tables\Columns\TextColumn::make('model')
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
