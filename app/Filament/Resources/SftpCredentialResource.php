<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SftpCredentialResource\Pages;
use App\Models\SftpCredential;
use App\Services\SftpCredentialChecker;
use App\Services\StorageVpsProvisioner;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class SftpCredentialResource extends Resource
{
    protected static ?string $model = SftpCredential::class;
    protected static ?string $navigationIcon = 'heroicon-o-key';
    protected static ?string $navigationGroup = 'Content';
    protected static ?string $navigationLabel = 'SFTP credentials';
    protected static ?int $navigationSort = 31;
    protected static bool $shouldSkipAuthorization = true;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasModeratorPermission('sftp_credentials') ?? false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('provisioned_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Owner')
                    ->searchable()
                    ->formatStateUsing(fn (string $state): string => UserResource::q3tohtml($state))->html()
                    ->url(fn ($record) => "/profile/{$record->user_id}"),
                Tables\Columns\TextInputColumn::make('label')
                    ->label('Label')
                    ->placeholder('e.g. USA VPS')
                    ->rules(['nullable', 'string', 'max:40'])
                    ->searchable(),
                Tables\Columns\TextColumn::make('sftp_username')
                    ->label('SFTP user')
                    ->searchable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('host')
                    ->label('Host')
                    ->copyable(),
                Tables\Columns\TextColumn::make('port')
                    ->label('Port'),
                Tables\Columns\TextColumn::make('remote_path')
                    ->label('Remote dir'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active'  => 'success',
                        'revoked' => 'danger',
                        default   => 'gray',
                    }),
                Tables\Columns\TextColumn::make('geo_coverage')
                    ->label('Geo')
                    ->badge()
                    ->getStateUsing(function ($record) {
                        $servers = collect($record->servers ?? [])
                            ->filter(fn ($e) => ! empty($e['ip']) && ! empty($e['port']));
                        $total = $servers->count();
                        if ($total === 0) {
                            return 'no servers';
                        }
                        $located = $servers->filter(fn ($e) => self::serverLocated($e))->count();

                        return "{$located}/{$total} located";
                    })
                    ->color(function ($state) {
                        if (! str_contains($state, '/')) {
                            return 'gray';
                        }
                        [$located, $rest] = explode('/', $state);

                        return (int) $located === (int) $rest ? 'success' : 'warning';
                    })
                    ->tooltip(function ($record) {
                        $missing = collect($record->servers ?? [])
                            ->filter(fn ($e) => ! empty($e['ip']) && ! empty($e['port']))
                            ->reject(fn ($e) => self::serverLocated($e))
                            ->map(fn ($e) => $e['ip'].':'.$e['port'])
                            ->all();

                        return $missing ? 'Missing location: '.implode(', ', $missing) : 'All servers located';
                    }),
                Tables\Columns\TextColumn::make('last_upload_at')
                    ->label('Last demo')
                    ->badge()
                    ->sortable()
                    ->getStateUsing(fn ($record) => $record->last_upload_at
                        ? $record->last_upload_at->diffForHumans()
                        : 'never')
                    ->color(function ($record): string {
                        if (! $record->last_upload_at) {
                            return 'danger';
                        }
                        if ($record->last_upload_at->gt(now()->subDay())) {
                            return 'success';
                        }
                        if ($record->last_upload_at->gt(now()->subWeek())) {
                            return 'warning';
                        }

                        return 'danger';
                    })
                    ->tooltip(fn ($record) => sprintf(
                        '%s demo(s) ingested%s - synced hourly from the storage VPS',
                        number_format((int) ($record->demo_count ?? 0)),
                        $record->last_upload_at
                            ? ', newest ' . $record->last_upload_at->toDateTimeString() . ' UTC'
                            : ''
                    )),
                Tables\Columns\TextColumn::make('check_status')
                    ->label('Check')
                    ->badge()
                    ->placeholder('not checked')
                    ->color(fn (?string $state): string => match ($state) {
                        'ok'     => 'success',
                        'failed' => 'danger',
                        'error'  => 'warning',
                        default  => 'gray',
                    })
                    ->tooltip(fn ($record) => $record->last_checked_at
                        ? trim(
                            'Checked ' . $record->last_checked_at->diffForHumans()
                            . ($record->check_message ? "\n" . $record->check_message : '')
                        )
                        : 'Never checked')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('provisioned_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('revoked_at')
                    ->dateTime()
                    ->placeholder('-')
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active'  => 'Active',
                        'revoked' => 'Revoked',
                    ])
                    ->default('active'),
            ])
            ->actions([
                Tables\Actions\Action::make('manageServers')
                    ->label('Servers')
                    ->icon('heroicon-o-list-bullet')
                    ->color('info')
                    ->modalHeading('Manage declared servers + RS codes')
                    ->modalDescription("Each row is one of the user's defrag servers. Fill in the RS code issued for that server (matches the rs<PORT>=<id> entry in their sv.conf).")
                    ->modalWidth('4xl')
                    ->fillForm(function ($record) {
                        // Prefill the coordinate fields from the live Server row
                        // (auto-geolocated or previously set) so the admin sees
                        // the current value and only edits the ones that need it.
                        $servers = $record->servers ?? [];
                        foreach ($servers as &$entry) {
                            if (empty($entry['ip']) || empty($entry['port'])) {
                                continue;
                            }
                            $srv = \App\Models\Server::where('ip', $entry['ip'])
                                ->where('port', (int) $entry['port'])
                                ->first();
                            if ($srv) {
                                $entry['latitude'] = $entry['latitude'] ?? $srv->latitude;
                                $entry['longitude'] = $entry['longitude'] ?? $srv->longitude;
                            }
                        }

                        return ['servers' => $servers];
                    })
                    ->form([
                        \Filament\Forms\Components\Repeater::make('servers')
                            ->label(false)
                            ->schema([
                                \Filament\Forms\Components\Select::make('gametype')
                                    ->options([
                                        'mixed'     => 'Mixed',
                                        'cpm'       => 'CPM',
                                        'vq3'       => 'VQ3',
                                        'teamruns'  => 'Teamruns',
                                        'fastcaps'  => 'Fastcaps',
                                        'freestyle' => 'Freestyle',
                                    ])
                                    ->required()
                                    ->columnSpan(2),
                                \Filament\Forms\Components\TextInput::make('ip')
                                    ->required()
                                    ->columnSpan(3),
                                \Filament\Forms\Components\TextInput::make('port')
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(65535)
                                    ->required()
                                    ->columnSpan(2),
                                \Filament\Forms\Components\TextInput::make('rcon')
                                    ->required()
                                    ->columnSpan(2),
                                \Filament\Forms\Components\Select::make('location')
                                    ->label('Country')
                                    ->options(\App\Support\Countries::options())
                                    ->searchable()
                                    ->native(false)
                                    ->columnSpan(2),
                                \Filament\Forms\Components\TextInput::make('rs_code')
                                    ->label('RS code')
                                    ->placeholder('e.g. 4711')
                                    ->columnSpan(1),
                                \Filament\Forms\Components\TextInput::make('latitude')
                                    ->label('Latitude')
                                    ->numeric()
                                    ->minValue(-90)
                                    ->maxValue(90)
                                    ->columnSpan(3)
                                    ->helperText('Auto-filled from IP. Set by hand only when it couldn\'t resolve (e.g. a hostname).'),
                                \Filament\Forms\Components\TextInput::make('longitude')
                                    ->label('Longitude')
                                    ->numeric()
                                    ->minValue(-180)
                                    ->maxValue(180)
                                    ->columnSpan(3)
                                    ->helperText('Drives the visitor ping badge. Leave blank to keep it hidden for this server.'),
                                \Filament\Forms\Components\Textarea::make('admin_note')
                                    ->label('Admin note')
                                    ->placeholder('Engine, special config, things to remember - not shown to the user')
                                    ->rows(2)
                                    ->columnSpan(12),
                            ])
                            ->columns(12)
                            ->addable() // admin can add rows post-approval if user adds servers
                            ->reorderable(false)
                            ->itemLabel(fn (array $state) => sprintf(
                                '%s @ %s:%s',
                                strtoupper($state['gametype'] ?? '?'),
                                $state['ip']   ?? '?',
                                $state['port'] ?? '?',
                            )),
                    ])
                    ->action(function ($record, array $data) {
                        $record->update([
                            'servers' => $data['servers'] ?? [],
                        ]);

                        Notification::make()
                            ->success()
                            ->title('Servers updated')
                            ->send();
                    }),
                Tables\Actions\Action::make('checkSftp')
                    ->label('Check SFTP')
                    ->icon('heroicon-o-shield-check')
                    ->color('gray')
                    ->visible(fn ($record) => $record->status === 'active')
                    ->action(function ($record) {
                        $result = app(SftpCredentialChecker::class)->check($record);

                        if ($result['ok']) {
                            Notification::make()
                                ->success()
                                ->title('SFTP account healthy')
                                ->body("Account '{$record->sftp_username}' passed all checks (group, shell, chroot, test write, ingest).")
                                ->send();
                        } else {
                            Notification::make()
                                ->danger()
                                ->title("SFTP check failed for '{$record->sftp_username}'")
                                ->body(implode("\n", $result['problems']))
                                ->persistent()
                                ->send();
                        }
                    }),
                Tables\Actions\Action::make('resetPassword')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn ($record) => $record->status === 'active')
                    ->requiresConfirmation()
                    ->modalDescription('Generates a new random password on the VPS. The old one stops working immediately. The new password is shown ONCE.')
                    ->action(function ($record) {
                        try {
                            $response = app(StorageVpsProvisioner::class)
                                ->resetPassword($record->sftp_username);

                            $record->update([
                                'password_pending' => $response['password'],
                            ]);

                            Notification::make()
                                ->success()
                                ->title('Password rotated')
                                ->body("New password is now pending for the user to claim on their /server-hosting page. Notify them to copy it.")
                                ->send();
                        } catch (RuntimeException $e) {
                            Log::error('Password reset failed', [
                                'credential_id' => $record->id,
                                'error'         => $e->getMessage(),
                            ]);
                            Notification::make()
                                ->danger()
                                ->title('Reset failed')
                                ->body($e->getMessage())
                                ->persistent()
                                ->send();
                        }
                    }),
                Tables\Actions\Action::make('revoke')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->visible(fn ($record) => $record->status === 'active')
                    ->requiresConfirmation()
                    ->modalDescription('Removes the SFTP account from the storage VPS. Demos already uploaded are archived (not deleted). Cannot be undone - applicant would need to apply again.')
                    ->action(function ($record) {
                        try {
                            app(StorageVpsProvisioner::class)
                                ->revoke($record->sftp_username);

                            $record->update([
                                'status'     => 'revoked',
                                'revoked_at' => now(),
                            ]);

                            Notification::make()
                                ->success()
                                ->title('Credential revoked')
                                ->send();
                        } catch (RuntimeException $e) {
                            Log::error('Revoke failed', [
                                'credential_id' => $record->id,
                                'error'         => $e->getMessage(),
                            ]);
                            Notification::make()
                                ->danger()
                                ->title('Revoke failed')
                                ->body($e->getMessage())
                                ->persistent()
                                ->send();
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSftpCredentials::route('/'),
        ];
    }

    /** Does the live Server row for this declared entry have coordinates? */
    private static function serverLocated(array $entry): bool
    {
        $srv = \App\Models\Server::where('ip', $entry['ip'])
            ->where('port', (int) $entry['port'])
            ->first();

        return $srv && $srv->latitude !== null && $srv->longitude !== null;
    }
}
