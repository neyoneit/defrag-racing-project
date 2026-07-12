<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DefragliveWatchExclusionResource\Pages;
use App\Models\DefragliveWatchExclusion;
use App\Models\DefragliveWatchSession;
use App\Services\DefragliveWatchService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Contest bans for the DefragLive watch contest: exclude a player's watch time
 * accrued BEFORE the given moment (AFK-farming on endless maps etc.). Time
 * after the cutoff counts again, so a legit player whose nick was abused can
 * still compete from the ban onward.
 */
class DefragliveWatchExclusionResource extends Resource
{
    protected static ?string $model = DefragliveWatchExclusion::class;

    protected static ?string $navigationIcon = 'heroicon-o-no-symbol';

    protected static ?string $navigationGroup = 'DefragLive';

    protected static ?string $navigationLabel = 'Contest Bans';

    protected static ?int $navigationSort = 25;

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Player')->schema([
                Forms\Components\Select::make('player_pick')
                    ->label('Pick a watched player')
                    ->options(fn () => self::playerOptions())
                    ->searchable()
                    ->dehydrated(false)
                    ->live()
                    ->afterStateUpdated(function (Forms\Set $set, ?string $state) {
                        if (! $state) {
                            return;
                        }
                        $p = json_decode($state, true);
                        $set('player_name', $p['name']);
                        $set('name_clean', $p['clean']);
                        $set('mdd_id', $p['mdd']);
                    })
                    ->helperText('Everyone the stream has ever watched. Picking one fills the fields below - or skip it and type manually.'),
                Forms\Components\TextInput::make('player_name')
                    ->label('Player name')
                    ->helperText('Name as seen on stream / leaderboard. Color codes are fine - matching uses the cleaned form below.')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (Forms\Set $set, ?string $state) {
                        $set('name_clean', app(DefragliveWatchService::class)->cleanName((string) $state));
                    }),
                Forms\Components\TextInput::make('name_clean')
                    ->label('Cleaned name (matching key)')
                    ->helperText('Lowercase, color codes stripped. Auto-filled from the name; adjust only if needed.')
                    ->maxLength(255),
                Forms\Components\TextInput::make('mdd_id')
                    ->label('mDd id (optional)')
                    ->helperText('Set when the player is a resolved defrag account - the ban then also matches their sessions credited by account.')
                    ->numeric(),
            ])->columns(1),
            Forms\Components\Section::make('Ban')->schema([
                Forms\Components\DateTimePicker::make('excluded_before')
                    ->label('Exclude watch time before')
                    ->seconds(false)
                    ->default(now())
                    ->required()
                    ->helperText('Everything watched BEFORE this moment is discarded from tickets and stats. Time after it counts normally, so a legit player behind an abused nick can still compete.'),
                Forms\Components\Textarea::make('reason')
                    ->required()
                    ->rows(3)
                    ->helperText('Why the time was excluded, e.g. "AFK-farming watch time on an endless map".'),
            ])->columns(1),
        ]);
    }

    /**
     * Every identity the stream has ever watched, one option per leaderboard
     * key (mdd account, else cleaned name), newest sighting's spelling wins.
     * Option value carries the full identity as JSON for afterStateUpdated.
     */
    protected static function playerOptions(): array
    {
        $options = [];
        $seen = [];
        $rows = DefragliveWatchSession::query()
            ->orderByDesc('id')
            ->get(['mdd_id', 'player_name', 'player_name_clean']);
        foreach ($rows as $s) {
            $key = $s->mdd_id ? 'mdd:'.$s->mdd_id : 'name:'.$s->player_name_clean;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $value = json_encode([
                'name' => $s->player_name,
                'clean' => $s->player_name_clean,
                'mdd' => $s->mdd_id ? (int) $s->mdd_id : null,
            ]);
            $label = trim(preg_replace('/\^[0-9A-Za-z]/', '', (string) $s->player_name)) ?: $s->player_name_clean;
            if ($s->mdd_id) {
                $label .= ' (mdd '.$s->mdd_id.')';
            }
            $options[$value] = $label;
        }
        natcasesort($options);

        return $options;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('player_name')
                    ->label('Player')
                    ->searchable(),
                Tables\Columns\TextColumn::make('name_clean')
                    ->label('Match key')
                    ->searchable(),
                Tables\Columns\TextColumn::make('mdd_id')
                    ->label('mDd id')
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('excluded_before')
                    ->label('Time excluded before')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('reason')
                    ->limit(60)
                    ->wrap(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Banned at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDefragliveWatchExclusions::route('/'),
            'create' => Pages\CreateDefragliveWatchExclusion::route('/create'),
            'edit' => Pages\EditDefragliveWatchExclusion::route('/{record}/edit'),
        ];
    }
}
