<?php

namespace App\Filament\Resources\ApiCallLogResource\Pages;

use App\Filament\Resources\ApiCallLogResource;
use App\Models\ApiCallLog;
use App\Models\User;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Drill-down for one user + endpoint combination. Lists the raw call
 * rows so the admin can see every individual query (?q=xyz etc) and
 * click through to a single-call detail.
 *
 * No GROUP BY here — rows have real primary keys, so standard Filament
 * row actions / URLs work without the headache.
 */
class ViewUserEndpointActivity extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = ApiCallLogResource::class;
    protected static string $view = 'filament.api-call-log.view-user-endpoint-activity';

    public int $user;
    public string $route;
    public string $method;
    public ?User $userModel = null;

    public function mount(int $user): void
    {
        $this->user      = $user;
        $this->route     = (string) request('route', '/');
        $this->method    = (string) request('method', 'GET');
        $this->userModel = User::find($user);
    }

    public function getTitle(): string
    {
        return "{$this->method} {$this->route}";
    }

    public function getBreadcrumbs(): array
    {
        return [
            ApiCallLogResource::getUrl()                                                   => 'API call log',
            ApiCallLogResource::getUrl('user-activity', ['user' => $this->user]) => $this->userModel?->plain_name ?? "user #{$this->user}",
            '#'                                                                            => "{$this->method} {$this->route}",
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => ApiCallLog::query()
                ->where('user_id', $this->user)
                ->where('route', $this->route)
                ->where('method', $this->method)
            )
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime('Y-m-d H:i:s')
                    ->since()
                    ->sortable(),
                Tables\Columns\TextColumn::make('query_string')
                    ->label('Query')
                    ->formatStateUsing(fn (?string $state) => $state ? '?' . $state : '—')
                    ->wrap()
                    ->fontFamily('mono'),
                Tables\Columns\TextColumn::make('response_status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (?int $state) => match (true) {
                        $state === null => 'gray',
                        $state >= 500   => 'danger',
                        $state >= 400   => 'warning',
                        $state >= 200   => 'success',
                        default         => 'gray',
                    }),
                Tables\Columns\TextColumn::make('response_ms')
                    ->label('ms')
                    ->numeric()
                    ->sortable()
                    ->color(fn (?int $state) => match (true) {
                        $state === null => 'gray',
                        $state > 2000   => 'danger',
                        $state > 500    => 'warning',
                        default         => 'success',
                    }),
                Tables\Columns\TextColumn::make('ip')
                    ->fontFamily('mono')
                    ->copyable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('token_id')
                    ->label('Auth')
                    ->state(fn ($record) => $record->token_id ? "token #{$record->token_id}" : 'session')
                    ->badge()
                    ->color(fn ($state) => str_starts_with((string) $state, 'token') ? 'info' : 'gray'),
            ])
            ->recordUrl(fn ($record) => ApiCallLogResource::getUrl('view-call', ['record' => $record->id]))
            ->paginated([25, 50, 100, 200]);
    }
}
