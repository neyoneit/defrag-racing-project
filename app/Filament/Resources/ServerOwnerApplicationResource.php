<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ServerOwnerApplicationResource\Pages;
use App\Models\ServerOwnerApplication;
use App\Models\SftpCredential;
use App\Services\StorageVpsProvisioner;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ServerOwnerApplicationResource extends Resource
{
    protected static ?string $model = ServerOwnerApplication::class;
    protected static ?string $navigationIcon = 'heroicon-o-server-stack';
    protected static ?string $navigationGroup = 'Content';
    protected static ?string $navigationLabel = 'Server hosting applications';
    protected static ?int $navigationSort = 30;
    protected static bool $shouldSkipAuthorization = true;

    public static function canAccess(): bool
    {
        $user = auth()->user();
        return $user && ($user->admin || $user->is_moderator);
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) ServerOwnerApplication::where('status', 'pending')->count() ?: null;
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
                    ->label('Applicant')
                    ->searchable()
                    ->formatStateUsing(fn (string $state): string => UserResource::q3tohtml($state))->html()
                    ->url(fn ($record) => "/profile/{$record->user_id}"),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending'  => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default    => 'gray',
                    }),
                Tables\Columns\TextColumn::make('servers_count')
                    ->label('Servers')
                    ->state(fn ($record) => is_array($record->server_info) ? count($record->server_info) : 0)
                    ->alignCenter()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('credential.sftp_username')
                    ->label('SFTP user')
                    ->placeholder('—')
                    ->copyable(),
                Tables\Columns\TextColumn::make('reviewer.name')
                    ->label('Reviewed by')
                    ->formatStateUsing(fn (?string $state): string => $state ? UserResource::q3tohtml($state) : '')
                    ->html()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Submitted')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending'  => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('viewDetails')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->modalHeading(fn ($record) => 'Application from ' . ($record->user->plain_name ?? $record->user->username ?? 'user #' . $record->user_id))
                    ->modalContent(fn ($record) => view('filament.applications.detail-modal', ['record' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalWidth('3xl'),
                Tables\Actions\Action::make('approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record) => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->modalDescription('This will provision a fresh SFTP account on the storage VPS and return the password ONCE. Make sure you can hand it to the applicant.')
                    ->form([
                        \Filament\Forms\Components\TextInput::make('sftp_username')
                            ->label('SFTP username')
                            ->required()
                            ->rule('regex:/^[a-z][a-z0-9_-]{2,31}$/')
                            ->helperText('lowercase, starts with a letter, 3–32 chars, [a-z0-9_-]')
                            ->default(fn ($record) => StorageVpsProvisioner::suggestUsername($record->user->username ?? $record->user->name ?? 'user' . $record->user_id))
                            ->unique('sftp_credentials', 'sftp_username'),
                        \Filament\Forms\Components\Textarea::make('review_note')
                            ->label('Note to applicant (optional)')
                            ->rows(2),
                    ])
                    ->action(function ($record, array $data) {
                        try {
                            $result = DB::transaction(function () use ($record, $data) {
                                $provisioner = app(StorageVpsProvisioner::class);
                                $response = $provisioner->create($data['sftp_username']);

                                // Seed credential.servers from the
                                // application's declared servers, adding
                                // an empty rs_code per row. Admin fills
                                // these in via Manage servers action.
                                $declaredServers = collect($record->server_info ?? [])
                                    ->map(fn ($s) => [
                                        'gametype' => $s['gametype'] ?? null,
                                        'ip'       => $s['ip']       ?? null,
                                        'port'     => $s['port']     ?? null,
                                        'rcon'     => $s['rcon']     ?? null,
                                        'rs_code'  => null,
                                    ])
                                    ->values()
                                    ->all();

                                $credential = SftpCredential::create([
                                    'user_id'          => $record->user_id,
                                    'application_id'   => $record->id,
                                    'sftp_username'    => $response['username'],
                                    'host'             => $response['host'],
                                    'port'             => $response['port'],
                                    'remote_path'      => $response['remote_path'],
                                    'password_pending' => $response['password'],
                                    'servers'          => $declaredServers,
                                    'status'           => 'active',
                                    'provisioned_at'   => now(),
                                    'provisioned_by'   => auth()->id(),
                                ]);

                                $record->update([
                                    'status'      => 'approved',
                                    'reviewed_by' => auth()->id(),
                                    'reviewed_at' => now(),
                                    'review_note' => $data['review_note'] ?? null,
                                ]);

                                return [
                                    'credential' => $credential,
                                    'password'   => $response['password'],
                                ];
                            });

                            Notification::make()
                                ->success()
                                ->title('Application approved & credentials provisioned')
                                ->body(sprintf(
                                    "Username: %s\nPassword (shown once): %s\nHost: %s:%d\nRemote path: %s",
                                    $result['credential']->sftp_username,
                                    $result['password'],
                                    $result['credential']->host,
                                    $result['credential']->port,
                                    $result['credential']->remote_path,
                                ))
                                ->persistent()
                                ->send();
                        } catch (RuntimeException $e) {
                            Log::error('Approve failed', [
                                'application_id' => $record->id,
                                'error'          => $e->getMessage(),
                            ]);
                            Notification::make()
                                ->danger()
                                ->title('Provisioning failed')
                                ->body($e->getMessage())
                                ->persistent()
                                ->send();
                        }
                    }),
                Tables\Actions\Action::make('reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn ($record) => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->form([
                        \Filament\Forms\Components\Textarea::make('review_note')
                            ->label('Reason for rejection (visible to applicant)')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function ($record, array $data) {
                        $record->update([
                            'status'      => 'rejected',
                            'reviewed_by' => auth()->id(),
                            'reviewed_at' => now(),
                            'review_note' => $data['review_note'],
                        ]);
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListServerOwnerApplications::route('/'),
        ];
    }
}
