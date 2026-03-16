<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FrontendErrorResource\Pages;
use App\Models\FrontendError;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FrontendErrorResource extends Resource
{
    protected static ?string $model = FrontendError::class;

    protected static ?string $navigationIcon = 'heroicon-o-exclamation-circle';

    protected static ?string $navigationLabel = 'Frontend Errors';

    protected static ?string $navigationGroup = 'Moderation';

    protected static ?int $navigationSort = 5;

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Copy for Claude')
                    ->description('Copy this text and paste it to Claude to debug the error')
                    ->schema([
                        Forms\Components\Textarea::make('claude_summary')
                            ->label('')
                            ->disabled()
                            ->rows(12)
                            ->columnSpanFull()
                            ->extraAttributes(['id' => 'claude-summary-text', 'style' => 'font-family: monospace; font-size: 12px;'])
                            ->formatStateUsing(fn ($record) => $record ? self::buildClaudeSummary($record) : ''),
                        Forms\Components\Placeholder::make('')
                            ->content(new \Illuminate\Support\HtmlString(
                                '<button type="button" onclick="const t=document.getElementById(\'claude-summary-text\');t.select();navigator.clipboard.writeText(t.value);this.textContent=\'Copied!\';setTimeout(()=>this.textContent=\'Copy to Clipboard\',2000)" class="fi-btn fi-btn-size-md px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white rounded-lg font-medium text-sm">Copy to Clipboard</button>'
                            )),
                    ])
                    ->collapsed(false),

                Forms\Components\Section::make('Error')
                    ->schema([
                        Forms\Components\TextInput::make('type')->disabled(),
                        Forms\Components\TextInput::make('message')->disabled()->columnSpanFull(),
                        Forms\Components\Textarea::make('stack')->disabled()->rows(8)->columnSpanFull(),
                    ]),
                Forms\Components\Section::make('Context')
                    ->schema([
                        Forms\Components\TextInput::make('url')->disabled(),
                        Forms\Components\TextInput::make('endpoint')->disabled(),
                        Forms\Components\TextInput::make('status_code')->disabled(),
                        Forms\Components\TextInput::make('component')->disabled(),
                        Forms\Components\Textarea::make('request_data')->disabled()->rows(4)->columnSpanFull(),
                        Forms\Components\Textarea::make('response_data')->disabled()->rows(4)->columnSpanFull(),
                    ])->columns(2),
                Forms\Components\Section::make('User')
                    ->schema([
                        Forms\Components\TextInput::make('user.name')->label('User')->disabled(),
                        Forms\Components\TextInput::make('ip')->disabled(),
                        Forms\Components\TextInput::make('user_agent')->disabled()->columnSpanFull(),
                        Forms\Components\DateTimePicker::make('created_at')->disabled(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->striped()
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('type')
                    ->colors([
                        'danger' => 'js_error',
                        'warning' => 'api_error',
                        'info' => 'vue_error',
                    ]),

                Tables\Columns\TextColumn::make('message')
                    ->limit(60)
                    ->tooltip(fn ($record) => $record->message)
                    ->searchable(),

                Tables\Columns\TextColumn::make('url')
                    ->limit(30)
                    ->tooltip(fn ($record) => $record->url),

                Tables\Columns\TextColumn::make('endpoint')
                    ->limit(25)
                    ->tooltip(fn ($record) => $record->endpoint)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('status_code')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($state) => match(true) {
                        $state >= 500 => 'danger',
                        $state >= 400 => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('User')
                    ->searchable()
                    ->html()
                    ->formatStateUsing(fn ($state) => $state ? UserResource::q3tohtml($state) : 'Anonymous'),

                Tables\Columns\IconColumn::make('is_bot')
                    ->label('Bot')
                    ->boolean()
                    ->trueIcon('heroicon-o-bug-ant')
                    ->falseIcon('heroicon-o-user')
                    ->trueColor('danger')
                    ->falseColor('success'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime()
                    ->sortable()
                    ->since(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'js_error' => 'JS Error',
                        'api_error' => 'API Error',
                        'vue_error' => 'Vue Error',
                    ]),
                Tables\Filters\TernaryFilter::make('is_bot')
                    ->label('Bot')
                    ->placeholder('All')
                    ->trueLabel('Bots only')
                    ->falseLabel('Real users only'),
            ])
            ->filtersLayout(Tables\Enums\FiltersLayout::AboveContent)
            ->actions([
                Tables\Actions\Action::make('copy_for_claude')
                    ->label('Copy')
                    ->icon('heroicon-o-clipboard-document')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Copy for Claude')
                    ->modalDescription(fn ($record) => new \Illuminate\Support\HtmlString(
                        '<textarea id="claude-copy-text" style="width:100%;height:16rem;font-family:monospace;font-size:12px;background:#111827;color:#e5e7eb;padding:12px;border-radius:8px;border:1px solid #374151;resize:none;" readonly>'
                        . e(self::buildClaudeSummary($record))
                        . '</textarea>'
                        . '<script>setTimeout(() => { const t = document.getElementById("claude-copy-text"); if(t) { t.select(); navigator.clipboard.writeText(t.value).catch(() => {}); } }, 100);</script>'
                    ))
                    ->modalSubmitActionLabel('Done')
                    ->action(fn () => null),
                Tables\Actions\ViewAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFrontendErrors::route('/'),
            'view' => Pages\ViewFrontendError::route('/{record}'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::where('created_at', '>=', now()->subDay())->count();
        return $count ?: null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'danger';
    }

    public static function buildClaudeSummary($record): string
    {
        $lines = [
            "Frontend error #{$record->id} reported by user " . ($record->user?->name ?? 'anonymous') . " at {$record->created_at}",
            "Type: {$record->type}",
            "Page: {$record->url}",
        ];
        if ($record->endpoint) $lines[] = "Endpoint: {$record->endpoint}";
        if ($record->status_code) $lines[] = "Status: {$record->status_code}";
        if ($record->component) $lines[] = "Component: {$record->component}";
        $lines[] = "Message: {$record->message}";
        if ($record->stack) $lines[] = "Stack:\n{$record->stack}";
        if ($record->request_data) $lines[] = "Request data: {$record->request_data}";
        if ($record->response_data) $lines[] = "Response: {$record->response_data}";
        $lines[] = "\nFind and fix this error. Show me how to reproduce it.";
        return implode("\n", $lines);
    }
}
