<?php

namespace App\Filament\Resources\ApiCallLogResource\Pages;

use App\Filament\Resources\ApiCallLogResource;
use App\Filament\Resources\UserResource;
use App\Models\ApiCallLog;
use App\Models\User;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Drill-down page for one user. Lists their API calls grouped by route
 * with counts + average response time. Clicking a route opens a modal
 * with the raw entries (timestamp + query string + status + ms).
 */
class ViewUserActivity extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = ApiCallLogResource::class;
    protected static string $view = 'filament.api-call-log.view-user-activity';

    public int $user;
    public ?User $userModel = null;

    public function mount(int $user): void
    {
        $this->user = $user;
        $this->userModel = User::find($user);
    }

    public function getTitle(): string
    {
        $name = $this->userModel?->plain_name ?? $this->userModel?->username ?? "user #{$this->user}";
        return "API activity — {$name}";
    }

    public function getBreadcrumbs(): array
    {
        return [
            ApiCallLogResource::getUrl() => 'API call log',
            '#' => $this->userModel?->plain_name ?? "user #{$this->user}",
        ];
    }

    public function getTableRecordKey($record): string
    {
        return (string) ($record->route ?? '0');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => ApiCallLog::query()
                ->selectRaw('
                    route,
                    method,
                    COUNT(*) as call_count,
                    MAX(created_at) as last_call,
                    AVG(response_ms) as avg_ms,
                    SUM(CASE WHEN response_status >= 400 THEN 1 ELSE 0 END) as error_count
                ')
                ->where('user_id', $this->user)
                ->groupBy('route', 'method')
            )
            ->defaultSort('call_count', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('method')
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('route')
                    ->wrap(),
                Tables\Columns\TextColumn::make('call_count')
                    ->label('Calls')
                    ->numeric()
                    ->sortable()
                    ->color(fn ($state) => $state > 1000 ? 'danger' : ($state > 200 ? 'warning' : 'gray')),
                Tables\Columns\TextColumn::make('error_count')
                    ->label('Errors')
                    ->numeric()
                    ->color(fn ($state) => $state > 0 ? 'warning' : 'gray'),
                Tables\Columns\TextColumn::make('avg_ms')
                    ->label('Avg ms')
                    ->formatStateUsing(fn ($state) => $state !== null ? round((float) $state) : '—')
                    ->color(fn ($state) => $state !== null && (float) $state > 1000 ? 'warning' : 'gray'),
                Tables\Columns\TextColumn::make('last_call')
                    ->label('Last call')
                    ->dateTime('Y-m-d H:i:s')
                    ->since()
                    ->sortable(),
            ])
            ->recordUrl(fn ($record) => ApiCallLogResource::getUrl('endpoint-activity', [
                'user'   => $this->user,
                'route'  => $record->route,
                'method' => $record->method,
            ]))
            ->filters([
                Tables\Filters\Filter::make('last_24h')
                    ->label('Last 24h')
                    ->query(fn ($query) => $query->where('created_at', '>=', now()->subDay())),
                Tables\Filters\Filter::make('last_7d')
                    ->label('Last 7 days')
                    ->default()
                    ->query(fn ($query) => $query->where('created_at', '>=', now()->subDays(7))),
            ]);
    }
}
