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
use Illuminate\Support\Collection;

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
                Forms\Components\Section::make('Other occurrences')
                    ->schema([
                        Forms\Components\Placeholder::make('duplicates')
                            ->label('')
                            ->content(function ($record) {
                                if (!$record) return 'N/A';
                                $dupes = FrontendError::where('message', $record->message)
                                    ->where('id', '!=', $record->id)
                                    ->orderBy('created_at', 'desc')
                                    ->limit(20)
                                    ->get();
                                if ($dupes->isEmpty()) return new \Illuminate\Support\HtmlString('<span style="color:#6b7280;">No other occurrences.</span>');
                                $html = '<div style="overflow-x:auto;"><table style="width:100%;font-size:13px;border-collapse:collapse;">'
                                    . '<tr style="border-bottom:1px solid #374151;color:#9ca3af;"><th style="padding:6px 8px;text-align:left;">#</th><th style="padding:6px 8px;text-align:left;">User</th><th style="padding:6px 8px;text-align:left;">IP</th><th style="padding:6px 8px;text-align:left;">URL</th><th style="padding:6px 8px;text-align:left;">UA</th><th style="padding:6px 8px;text-align:left;">When</th></tr>';
                                foreach ($dupes as $d) {
                                    $user = e($d->user?->name ?? 'anonymous');
                                    $ip = e($d->ip);
                                    $url = e(\Illuminate\Support\Str::limit($d->url, 40));
                                    $ua = e(\Illuminate\Support\Str::limit($d->user_agent, 30));
                                    $when = $d->created_at->diffForHumans();
                                    $html .= "<tr style=\"border-bottom:1px solid #1f2937;\">"
                                        . "<td style=\"padding:6px 8px;color:#e5e7eb;\">{$d->id}</td>"
                                        . "<td style=\"padding:6px 8px;color:#e5e7eb;\">{$user}</td>"
                                        . "<td style=\"padding:6px 8px;color:#9ca3af;font-family:monospace;font-size:12px;\">{$ip}</td>"
                                        . "<td style=\"padding:6px 8px;color:#9ca3af;\" title=\"" . e($d->url) . "\">{$url}</td>"
                                        . "<td style=\"padding:6px 8px;color:#6b7280;font-size:11px;\" title=\"" . e($d->user_agent) . "\">{$ua}</td>"
                                        . "<td style=\"padding:6px 8px;color:#9ca3af;\">{$when}</td>"
                                        . "</tr>";
                                }
                                $total = FrontendError::where('message', $record->message)->count();
                                $html .= '</table></div>';
                                if ($total > 21) $html .= '<div style="color:#6b7280;font-size:12px;margin-top:8px;">Showing 20 of ' . $total . ' total occurrences.</div>';
                                return new \Illuminate\Support\HtmlString($html);
                            }),
                    ])
                    ->collapsed(false),
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

                Tables\Columns\TextColumn::make('duplicate_count')
                    ->label('Count')
                    ->getStateUsing(fn ($record) => FrontendError::where('message', $record->message)->count())
                    ->badge()
                    ->color(fn (int $state) => match (true) {
                        $state >= 10 => 'danger',
                        $state >= 3 => 'warning',
                        default => 'gray',
                    }),

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
                Tables\Actions\Action::make('copy_and_delete')
                    ->label('Copy & Delete')
                    ->icon('heroicon-o-clipboard-document')
                    ->color('info')
                    ->modalHeading('Copy for Claude')
                    ->modalWidth('4xl')
                    ->fillForm(fn ($record) => ['copy_text' => self::buildClaudeSummary($record)])
                    ->form([
                        Forms\Components\Textarea::make('copy_text')
                            ->label('Click text to copy, or use button below')
                            ->rows(14)
                            ->columnSpanFull()
                            ->extraAttributes(['id' => 'claude-copy-text', 'onclick' => "this.select();navigator.clipboard.writeText(this.value).then(()=>{this.style.borderColor='#22c55e';setTimeout(()=>this.style.borderColor='',1000)})", 'style' => 'font-family:monospace;font-size:12px;cursor:pointer;']),
                        Forms\Components\Placeholder::make('')
                            ->content(new \Illuminate\Support\HtmlString(
                                '<button type="button" onclick="const t=document.getElementById(\'claude-copy-text\');t.select();navigator.clipboard.writeText(t.value);this.textContent=\'Copied!\';this.style.background=\'#22c55e\';setTimeout(()=>{this.textContent=\'Copy to Clipboard\';this.style.background=\'#2563eb\'},1500)" style="padding:8px 16px;background:#2563eb;color:white;border:none;border-radius:8px;font-weight:600;font-size:13px;cursor:pointer;">Copy to Clipboard</button>'
                            )),
                    ])
                    ->modalSubmitActionLabel('Delete error')
                    ->modalCancelActionLabel('Keep')
                    ->action(fn ($record) => $record->delete()),
                Tables\Actions\Action::make('copy_group')
                    ->label('Copy group')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('warning')
                    ->visible(fn ($record) => FrontendError::where('message', $record->message)->count() > 1)
                    ->modalHeading('Copy grouped errors for Claude')
                    ->modalWidth('4xl')
                    ->fillForm(fn ($record) => ['copy_text' => self::buildGroupSummary($record)])
                    ->form([
                        Forms\Components\Textarea::make('copy_text')
                            ->label('Click text to copy, or use button below')
                            ->rows(18)
                            ->columnSpanFull()
                            ->extraAttributes(['id' => 'claude-group-text', 'onclick' => "this.select();navigator.clipboard.writeText(this.value).then(()=>{this.style.borderColor='#22c55e';setTimeout(()=>this.style.borderColor='',1000)})", 'style' => 'font-family:monospace;font-size:12px;cursor:pointer;']),
                        Forms\Components\Placeholder::make('')
                            ->content(new \Illuminate\Support\HtmlString(
                                '<button type="button" onclick="const t=document.getElementById(\'claude-group-text\');t.select();navigator.clipboard.writeText(t.value);this.textContent=\'Copied!\';this.style.background=\'#22c55e\';setTimeout(()=>{this.textContent=\'Copy to Clipboard\';this.style.background=\'#2563eb\'},1500)" style="padding:8px 16px;background:#2563eb;color:white;border:none;border-radius:8px;font-weight:600;font-size:13px;cursor:pointer;">Copy to Clipboard</button>'
                            )),
                    ])
                    ->modalSubmitActionLabel('Delete all duplicates')
                    ->modalCancelActionLabel('Keep')
                    ->action(fn ($record) => FrontendError::where('message', $record->message)->delete()),
                Tables\Actions\ViewAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('copy_selected')
                        ->label('Copy for Claude')
                        ->icon('heroicon-o-clipboard-document')
                        ->modalHeading('Copy selected errors for Claude')
                        ->modalWidth('4xl')
                        ->form([
                            Forms\Components\Textarea::make('copy_text')
                                ->label('Click text to copy, or use button below')
                                ->rows(18)
                                ->columnSpanFull()
                                ->extraAttributes(['id' => 'claude-bulk-text', 'onclick' => "this.select();navigator.clipboard.writeText(this.value).then(()=>{this.style.borderColor='#22c55e';setTimeout(()=>this.style.borderColor='',1000)})", 'style' => 'font-family:monospace;font-size:12px;cursor:pointer;']),
                            Forms\Components\Placeholder::make('')
                                ->content(new \Illuminate\Support\HtmlString(
                                    '<button type="button" onclick="const t=document.getElementById(\'claude-bulk-text\');t.select();navigator.clipboard.writeText(t.value);this.textContent=\'Copied!\';this.style.background=\'#22c55e\';setTimeout(()=>{this.textContent=\'Copy to Clipboard\';this.style.background=\'#2563eb\'},1500)" style="padding:8px 16px;background:#2563eb;color:white;border:none;border-radius:8px;font-weight:600;font-size:13px;cursor:pointer;">Copy to Clipboard</button>'
                                )),
                        ])
                        ->fillForm(fn (Collection $records) => ['copy_text' => self::buildBulkSummary($records)])
                        ->modalSubmitActionLabel('Delete selected')
                        ->modalCancelActionLabel('Keep')
                        ->action(fn (Collection $records) => $records->each->delete())
                        ->deselectRecordsAfterCompletion(),
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

    public static function buildGroupSummary($record): string
    {
        $duplicates = FrontendError::where('message', $record->message)
            ->orderBy('created_at', 'desc')
            ->get();

        $lines = [
            "Grouped frontend error - {$duplicates->count()} occurrences",
            "Message: {$record->message}",
            "Type: {$record->type}",
            "",
            "Occurrences:",
        ];

        foreach ($duplicates as $dup) {
            $user = $dup->user?->name ?? 'anonymous';
            $lines[] = "  - #{$dup->id} by {$user} at {$dup->created_at} on {$dup->url}";
        }

        if ($record->stack) $lines[] = "\nStack (from latest):\n{$record->stack}";
        $lines[] = "\nFind and fix this error. Show me how to reproduce it.";
        return implode("\n", $lines);
    }

    public static function buildBulkSummary(Collection $records): string
    {
        $lines = ["Selected {$records->count()} frontend errors:", ""];

        foreach ($records as $record) {
            $lines[] = "--- Error #{$record->id} ---";
            $lines[] = self::buildClaudeSummary($record);
            $lines[] = "";
        }

        return implode("\n", $lines);
    }
}
