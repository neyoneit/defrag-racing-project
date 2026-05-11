<?php

namespace App\Filament\Resources\UploadedDemoResource\Pages;

use App\Filament\Resources\UploadedDemoResource;
use App\Models\UploadedDemo;
use Filament\Actions;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

class ViewUploadedDemo extends ViewRecord
{
    protected static string $resource = UploadedDemoResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('File availability')
                    ->description('Live check across configured disks. Result cached 5 minutes per demo.')
                    ->schema([
                        Infolists\Components\TextEntry::make('file_existence_status')
                            ->label('Status')
                            ->state(fn (UploadedDemo $record) => $this->fileExistence($record)['summary'])
                            ->html(),
                        Infolists\Components\TextEntry::make('file_path')
                            ->label('file_path')
                            ->copyable()
                            ->placeholder('— empty (legacy demo, no longer downloadable)'),
                        Infolists\Components\TextEntry::make('processed_filename')
                            ->label('processed_filename')
                            ->copyable()
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('file_size')
                            ->label('Size')
                            ->formatStateUsing(fn ($state) => $state
                                ? number_format($state) . ' bytes (' . round($state / 1024, 1) . ' KB)'
                                : '—'),
                        Infolists\Components\TextEntry::make('file_hash')
                            ->label('MD5')
                            ->copyable()
                            ->placeholder('—'),
                    ])->columns(2),

                Infolists\Components\Section::make('Status & source')
                    ->schema([
                        Infolists\Components\TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'assigned'             => 'success',
                                'fallback-assigned'    => 'info',
                                'processed', 'uploaded' => 'gray',
                                'processing'           => 'warning',
                                'failed', 'failed-validity', 'unsupported-version' => 'danger',
                                default                => 'gray',
                            }),
                        Infolists\Components\TextEntry::make('source')->placeholder('—'),
                        Infolists\Components\TextEntry::make('download_count')
                            ->label('Downloads')
                            ->formatStateUsing(fn ($state) => (int) ($state ?? 0)),
                        Infolists\Components\TextEntry::make('manually_assigned')
                            ->label('Manually assigned')
                            ->badge()
                            ->color(fn ($state) => $state ? 'warning' : 'gray')
                            ->formatStateUsing(fn ($state) => $state ? 'yes' : 'no'),
                        Infolists\Components\TextEntry::make('processing_output')
                            ->label('Processing output / log')
                            ->columnSpanFull()
                            ->placeholder('—')
                            ->extraAttributes(['style' => 'white-space: pre-wrap; font-family: monospace; font-size: 12px;']),
                    ])->columns(4),

                Infolists\Components\Section::make('Run details')
                    ->schema([
                        Infolists\Components\TextEntry::make('map_name')->label('Map')->placeholder('—'),
                        Infolists\Components\TextEntry::make('physics')->placeholder('—'),
                        Infolists\Components\TextEntry::make('gametype')->placeholder('—'),
                        Infolists\Components\TextEntry::make('time_ms')
                            ->label('Time')
                            ->formatStateUsing(function ($state) {
                                if (! $state) return '—';
                                $m = floor($state / 60000);
                                $s = floor(($state % 60000) / 1000);
                                $ms = $state % 1000;
                                return sprintf('%d:%02d.%03d (%s ms)', $m, $s, $ms, number_format($state));
                            }),
                        Infolists\Components\TextEntry::make('record_date')
                            ->label('Record date (from demo)')
                            ->dateTime('Y-m-d H:i:s')
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('country')->placeholder('—'),
                        Infolists\Components\TextEntry::make('validity')
                            ->label('Validity flags')
                            ->columnSpanFull()
                            ->formatStateUsing(function ($state) {
                                if (empty($state)) return '— none';
                                $arr = is_array($state) ? $state : json_decode($state, true);
                                if (! $arr) return '—';
                                return new HtmlString('<pre style="font-size:12px; margin:0;">'
                                    . e(json_encode($arr, JSON_PRETTY_PRINT))
                                    . '</pre>');
                            }),
                    ])->columns(3),

                Infolists\Components\Section::make('Player as recorded in demo')
                    ->schema([
                        Infolists\Components\TextEntry::make('player_name')
                            ->label('player_name (raw)')
                            ->copyable()
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('q3df_login_name')
                            ->label('q3df_login_name')
                            ->copyable()
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('q3df_login_name_colored')
                            ->label('q3df_login_name_colored')
                            ->copyable()
                            ->placeholder('—'),
                    ])->columns(3),

                Infolists\Components\Section::make('Matching / auto-assignment')
                    ->schema([
                        Infolists\Components\TextEntry::make('match_method')
                            ->badge()
                            ->color('info')
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('name_confidence')
                            ->label('Name confidence')
                            ->formatStateUsing(fn ($state) => $state === null ? '—' : $state . ' %'),
                        Infolists\Components\TextEntry::make('matched_alias')->placeholder('—'),
                        Infolists\Components\TextEntry::make('suggested_user_id')
                            ->label('Suggested user id')
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('suggestedUser.name')
                            ->label('Suggested user')
                            ->formatStateUsing(fn ($state) => $state
                                ? strip_tags(preg_replace('/\^[0-9]/', '', $state))
                                : '—')
                            ->url(fn ($record) => $record->suggested_user_id
                                ? "/profile/" . $record->suggested_user_id
                                : null)
                            ->openUrlInNewTab(),
                    ])->columns(3),

                Infolists\Components\Section::make('Uploader')
                    ->schema([
                        Infolists\Components\TextEntry::make('user_id')
                            ->label('User ID'),
                        Infolists\Components\TextEntry::make('user.name')
                            ->label('Name')
                            ->formatStateUsing(fn ($state) => $state
                                ? strip_tags(preg_replace('/\^[0-9]/', '', $state))
                                : '—')
                            ->url(fn ($record) => $record->user_id
                                ? "/profile/" . $record->user_id
                                : null)
                            ->openUrlInNewTab(),
                        Infolists\Components\TextEntry::make('user.email')->label('Email')->placeholder('—'),
                        Infolists\Components\TextEntry::make('user.mdd_id')->label('MDD ID')->placeholder('—'),
                    ])->columns(4),

                Infolists\Components\Section::make('Linked online record')
                    ->visible(fn ($record) => $record->record_id !== null)
                    ->schema([
                        Infolists\Components\TextEntry::make('record_id')->label('Record ID'),
                        Infolists\Components\TextEntry::make('record.name')
                            ->label('Record player')
                            ->formatStateUsing(fn ($state) => $state
                                ? strip_tags(preg_replace('/\^[0-9]/', '', $state))
                                : '—'),
                        Infolists\Components\TextEntry::make('record.time')
                            ->label('Record time')
                            ->formatStateUsing(function ($state) {
                                if (! $state) return '—';
                                $m = floor($state / 60000);
                                $s = floor(($state % 60000) / 1000);
                                $ms = $state % 1000;
                                return sprintf('%d:%02d.%03d', $m, $s, $ms);
                            }),
                        Infolists\Components\TextEntry::make('record.rank')->label('Rank')->placeholder('—'),
                        Infolists\Components\TextEntry::make('record.mapname')->label('Record map')->placeholder('—'),
                        Infolists\Components\TextEntry::make('record.physics')->label('Record physics')->placeholder('—'),
                        Infolists\Components\TextEntry::make('record.gametype')->label('Record gametype')->placeholder('—'),
                        Infolists\Components\TextEntry::make('record.date_set')
                            ->label('Record date_set')
                            ->dateTime('Y-m-d H:i')
                            ->placeholder('—'),
                    ])->columns(4),

                Infolists\Components\Section::make('Linked offline record')
                    ->visible(fn ($record) => $record->offlineRecord !== null)
                    ->schema([
                        Infolists\Components\TextEntry::make('offlineRecord.id')->label('OfflineRecord ID'),
                        Infolists\Components\TextEntry::make('offlineRecord.mapname')->label('Map')->placeholder('—'),
                        Infolists\Components\TextEntry::make('offlineRecord.physics')->placeholder('—'),
                        Infolists\Components\TextEntry::make('offlineRecord.gametype')->placeholder('—'),
                        Infolists\Components\TextEntry::make('offlineRecord.time')
                            ->label('Time')
                            ->formatStateUsing(function ($state) {
                                if (! $state) return '—';
                                $m = floor($state / 60000);
                                $s = floor(($state % 60000) / 1000);
                                $ms = $state % 1000;
                                return sprintf('%d:%02d.%03d', $m, $s, $ms);
                            }),
                        Infolists\Components\TextEntry::make('offlineRecord.rank')->label('Rank')->placeholder('—'),
                    ])->columns(5),

                Infolists\Components\Section::make('Rendered video')
                    ->visible(fn ($record) => $record->renderedVideo !== null)
                    ->schema([
                        Infolists\Components\TextEntry::make('renderedVideo.id')->label('Render ID'),
                        Infolists\Components\TextEntry::make('renderedVideo.status')->badge(),
                        Infolists\Components\TextEntry::make('renderedVideo.quality_tier')->placeholder('—'),
                        Infolists\Components\TextEntry::make('renderedVideo.created_at')
                            ->label('Render created')
                            ->dateTime('Y-m-d H:i'),
                    ])->columns(4),

                Infolists\Components\Section::make('Assignment reports for this demo')
                    ->visible(fn ($record) => $record->assignmentReports()->count() > 0)
                    ->schema([
                        Infolists\Components\TextEntry::make('assignment_reports_summary')
                            ->label('')
                            ->columnSpanFull()
                            ->state(function ($record) {
                                $rows = $record->assignmentReports()
                                    ->orderByDesc('created_at')
                                    ->get(['id', 'report_type', 'status', 'created_at']);
                                if ($rows->isEmpty()) return '—';
                                $html = '<table style="width:100%; font-size:12px;"><thead><tr><th align="left">ID</th><th align="left">Type</th><th align="left">Status</th><th align="left">Created</th></tr></thead><tbody>';
                                foreach ($rows as $r) {
                                    $url = url('/defraghq/demo-assignment-reports/' . $r->id);
                                    $html .= '<tr>'
                                        . '<td><a href="' . e($url) . '" style="color:#60a5fa;">#' . (int) $r->id . '</a></td>'
                                        . '<td>' . e($r->report_type) . '</td>'
                                        . '<td>' . e($r->status) . '</td>'
                                        . '<td>' . e((string) $r->created_at) . '</td>'
                                        . '</tr>';
                                }
                                $html .= '</tbody></table>';
                                return new HtmlString($html);
                            }),
                    ]),

                Infolists\Components\Section::make('Audit')
                    ->schema([
                        Infolists\Components\TextEntry::make('created_at')->dateTime('Y-m-d H:i:s'),
                        Infolists\Components\TextEntry::make('updated_at')->dateTime('Y-m-d H:i:s'),
                    ])->columns(2),
            ]);
    }

    private function fileExistence(UploadedDemo $record): array
    {
        $cacheKey = "filament:demo_file_check:{$record->id}";

        return Cache::remember($cacheKey, 300, function () use ($record) {
            $local = false;
            $remote = false;
            $remoteError = null;

            if (! empty($record->file_path)) {
                try {
                    $local = Storage::disk('local')->exists($record->file_path);
                } catch (\Throwable $e) {
                    // ignore — keep $local = false
                }

                try {
                    $remote = Storage::disk('s3')->exists($record->file_path);
                } catch (\Throwable $e) {
                    $remoteError = $e->getMessage();
                }
            }

            $rows = [];
            if (empty($record->file_path)) {
                $rows[] = '<span style="color:#fbbf24;">⚠ file_path is empty — legacy demo, never had a stored file</span>';
            } else {
                $rows[] = ($local ? '<span style="color:#34d399;">✓</span>' : '<span style="color:#f87171;">✗</span>')
                    . ' <strong>Local disk</strong> <code style="font-size:12px;">storage/app/' . e($record->file_path) . '</code>';
                if ($remoteError) {
                    $rows[] = '<span style="color:#f87171;">✗</span> <strong>Backblaze (s3)</strong> — error: <code style="font-size:12px;">' . e($remoteError) . '</code>';
                } else {
                    $rows[] = ($remote ? '<span style="color:#34d399;">✓</span>' : '<span style="color:#f87171;">✗</span>')
                        . ' <strong>Backblaze (s3)</strong> <code style="font-size:12px;">' . e($record->file_path) . '</code>';
                }
                if (! $local && ! $remote) {
                    $rows[] = '<div style="margin-top:6px; color:#f87171;"><strong>Download will return 404</strong> — file is missing from both disks.</div>';
                } elseif ($remote && ! $local) {
                    $rows[] = '<div style="margin-top:6px; color:#9ca3af;">Will be served from Backblaze.</div>';
                } elseif ($local && ! $remote) {
                    $rows[] = '<div style="margin-top:6px; color:#9ca3af;">Will be served from local storage (not yet uploaded to Backblaze).</div>';
                } else {
                    $rows[] = '<div style="margin-top:6px; color:#34d399;">File present on both disks.</div>';
                }
            }

            return [
                'local'   => $local,
                'remote'  => $remote,
                'summary' => new HtmlString(implode('<br>', $rows)),
            ];
        });
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('open_on_site')
                ->label('Open on site')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->url(fn ($record) => "/demos/{$record->id}")
                ->openUrlInNewTab(),

            Actions\Action::make('download')
                ->label('Download')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('primary')
                ->url(fn ($record) => route('demos.download', $record))
                ->openUrlInNewTab(),

            Actions\Action::make('refresh_file_check')
                ->label('Re-check files')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(function ($record) {
                    Cache::forget("filament:demo_file_check:{$record->id}");
                    Notification::make()->title('Cache cleared, reloading…')->success()->send();
                    return redirect(request()->header('Referer') ?: url()->current());
                }),

            Actions\Action::make('detach_from_record')
                ->label('Detach from record')
                ->icon('heroicon-o-link-slash')
                ->color('warning')
                ->visible(fn ($record) => $record->record_id !== null)
                ->requiresConfirmation()
                ->modalHeading('Detach demo from record')
                ->modalDescription('Sets record_id = null and status = "processed". Demo will be re-considered by demos:rematch-all on the next run.')
                ->action(function ($record) {
                    $record->update([
                        'record_id'          => null,
                        'status'             => 'processed',
                        'manually_assigned'  => false,
                    ]);
                    Notification::make()->title('Detached from record')->success()->send();
                }),

            Actions\DeleteAction::make()
                ->label('Delete demo row')
                ->modalDescription('Removes the uploaded_demos row. The file on Backblaze/local is NOT deleted.'),
        ];
    }
}
