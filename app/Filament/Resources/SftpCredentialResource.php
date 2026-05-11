<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SftpCredentialResource\Pages;
use App\Models\SftpCredential;
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
        $user = auth()->user();
        return $user && ($user->admin || $user->is_moderator);
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
                Tables\Columns\TextColumn::make('provisioned_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('revoked_at')
                    ->dateTime()
                    ->placeholder('—')
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
                    ->modalDescription("Each row is one of the user's defrag servers. Fill in the RS code we issued for that server (matches the rs<PORT>=<id> entry in their sv.conf).")
                    ->modalWidth('4xl')
                    ->fillForm(fn ($record) => [
                        'servers' => $record->servers ?? [],
                    ])
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
                                    ->columnSpan(3),
                                \Filament\Forms\Components\TextInput::make('rs_code')
                                    ->label('RS code')
                                    ->placeholder('e.g. 4711')
                                    ->columnSpan(2),
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
                    ->modalDescription('Removes the SFTP account from the storage VPS. Demos already uploaded are archived (not deleted). Cannot be undone — applicant would need to apply again.')
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
}
