<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MarketplaceWorkTypeResource\Pages;
use App\Models\MarketplaceWorkType;
use Filament\Forms;
use Filament\Forms\Form;
use Illuminate\Support\HtmlString;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * The kinds of work the marketplace knows about.
 *
 * Four of them ship with the site. The rest arrive from whoever needed one:
 * somebody picks "Something else" when posting, writes the name, and it lands
 * here waiting to be confirmed. Approving is where the wording gets tidied and
 * the missing languages get filled in - until then the type shows everywhere
 * in the author's English.
 */
class MarketplaceWorkTypeResource extends Resource
{
    protected static ?string $model = MarketplaceWorkType::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationGroup = 'Community';

    protected static ?string $navigationLabel = 'Marketplace Work Types';

    protected static ?string $recordTitleAttribute = 'label';

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    /** Suggestions nobody has looked at. */
    public static function getNavigationBadge(): ?string
    {
        return (string) MarketplaceWorkType::where('status', 'pending')->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    /** Every language the site is read in, except the one the labels are written in. */
    private static function translatableLocales(): array
    {
        return collect(config('locales.supported'))
            ->except('en')
            ->all();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('English')
                ->description('The fallback. Every language that has no translation shows this.')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('label')
                        ->label('Name')
                        ->required()
                        ->maxLength(60)
                        ->helperText('One listing: "Map", "Sound design".'),

                    Forms\Components\TextInput::make('label_plural')
                        ->label('Name in the plural')
                        ->maxLength(60)
                        ->helperText('A creator\'s specialty: "Maps", "Sound design". Empty falls back to the name.'),

                    Forms\Components\TextInput::make('description')
                        ->label('Short description')
                        ->maxLength(160)
                        ->columnSpanFull()
                        ->helperText('The line under the name in the picker.'),
                ]),

            Forms\Components\Section::make('Translations')
                ->description('Anything left empty shows the English above, which is the point of the fallback - a half-translated type is safe to approve.')
                ->collapsible()
                ->schema([
                    Forms\Components\Tabs::make('translations')->tabs(
                        collect(static::translatableLocales())
                            ->map(fn (string $language, string $locale) => Forms\Components\Tabs\Tab::make($language)
                                ->badge(fn (?MarketplaceWorkType $record) => empty($record?->translations[$locale]['label'] ?? null) ? null : '✓')
                                ->schema([
                                    Forms\Components\TextInput::make("translations.{$locale}.label")
                                        ->label('Name')
                                        ->maxLength(60),
                                    Forms\Components\TextInput::make("translations.{$locale}.label_plural")
                                        ->label('Name in the plural')
                                        ->maxLength(60),
                                    Forms\Components\TextInput::make("translations.{$locale}.description")
                                        ->label('Short description')
                                        ->maxLength(160),
                                ]))
                            ->values()
                            ->all()
                    ),
                ]),

            Forms\Components\Section::make('Settings')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('status')
                        ->options([
                            'pending' => 'Waiting for approval',
                            'approved' => 'Approved',
                            'rejected' => 'Rejected',
                        ])
                        ->required()
                        ->helperText('Only approved types can be picked when posting. A rejected one keeps working on the listings that already use it.'),

                    Forms\Components\Select::make('color')
                        ->options(array_combine(MarketplaceWorkType::COLORS, array_map('ucfirst', MarketplaceWorkType::COLORS)))
                        ->required()
                        ->helperText('The colour of the badge on the listing.'),

                    Forms\Components\TextInput::make('sort_order')
                        ->numeric()
                        ->required()
                        ->helperText('Low numbers come first in the picker.'),

                    Forms\Components\TextInput::make('slug')
                        ->required()
                        ->maxLength(60)
                        ->disabled()
                        ->dehydrated(false)
                        ->helperText('Stored on every listing of this type. It cannot be changed.'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->striped()
            ->defaultSort('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('label')
                    ->label('Name')
                    ->searchable()
                    ->sortable()
                    ->description(fn (MarketplaceWorkType $record): ?string => $record->description),

                Tables\Columns\TextColumn::make('slug')
                    ->searchable()
                    ->color('gray')
                    ->size('sm'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'Waiting',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),

                // How much of the type a reader in another language actually gets.
                Tables\Columns\TextColumn::make('translations')
                    ->label('Languages')
                    ->badge()
                    ->formatStateUsing(function (MarketplaceWorkType $record): string {
                        $total = count(static::translatableLocales());
                        $done = collect($record->translations ?? [])
                            ->filter(fn ($t) => !empty($t['label']))
                            ->count();

                        return $done . '/' . $total;
                    })
                    ->color(function (MarketplaceWorkType $record): string {
                        $total = count(static::translatableLocales());
                        $done = collect($record->translations ?? [])
                            ->filter(fn ($t) => !empty($t['label']))
                            ->count();

                        return $done === 0 ? 'danger' : ($done < $total ? 'warning' : 'success');
                    }),

                Tables\Columns\TextColumn::make('listings_count')
                    ->label('Listings')
                    ->counts('listings')
                    ->sortable(),

                Tables\Columns\TextColumn::make('suggestedBy.name')
                    ->label('Suggested by')
                    ->formatStateUsing(fn (?string $state): string => $state ? UserResource::q3tohtml($state) : '-')
                    ->html(),

                Tables\Columns\IconColumn::make('is_core')
                    ->label('Built in')
                    ->boolean(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Waiting for approval',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (MarketplaceWorkType $record): bool => $record->status !== 'approved')
                    ->requiresConfirmation()
                    ->modalDescription('It becomes pickable when posting. Fill in the languages first if you want to - you can also do it later.')
                    ->action(function (MarketplaceWorkType $record) {
                        $record->update(['status' => 'approved', 'approved_at' => now()]);

                        $record->suggestedBy?->systemNotify(
                            'marketplace',
                            '',
                            'Your work type is now on the marketplace',
                            '"' . $record->label . '" can now be picked by anyone posting a listing.',
                            route('marketplace.index', ['work_type' => $record->slug])
                        );

                        Notification::make()->success()->title('Approved')->send();
                    }),

                Tables\Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (MarketplaceWorkType $record): bool => !$record->is_core && $record->status !== 'rejected')
                    ->requiresConfirmation()
                    ->modalDescription('Nobody can pick it any more. Listings already on it keep showing it, so rename it first if the wording is the problem.')
                    ->action(function (MarketplaceWorkType $record) {
                        $record->update(['status' => 'rejected']);

                        Notification::make()->warning()->title('Rejected')->send();
                    }),

                // The two halves of getting a type translated without
                // clicking through nine language tabs: take the question out,
                // put the answer back in.
                Tables\Actions\Action::make('translationPrompt')
                    ->label('Copy prompt')
                    ->icon('heroicon-o-clipboard-document')
                    ->color('gray')
                    ->modalHeading(fn (MarketplaceWorkType $record): string => 'Translation prompt for "' . $record->label . '"')
                    ->modalContent(fn (MarketplaceWorkType $record) => view(
                        'filament.marketplace.translation-prompt',
                        ['prompt' => $record->translationPrompt()],
                    ))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),

                Tables\Actions\Action::make('pasteTranslations')
                    ->label('Paste translations')
                    ->icon('heroicon-o-language')
                    ->color('info')
                    ->modalHeading(fn (MarketplaceWorkType $record): string => 'Translations for "' . $record->label . '"')
                    ->modalDescription(new HtmlString('Paste the JSON you got back. Anything already filled in is kept unless the JSON gives that language a new value.'))
                    ->modalSubmitActionLabel('Save the translations')
                    ->form([
                        Forms\Components\Textarea::make('json')
                            ->label('JSON')
                            ->rows(12)
                            ->required()
                            ->placeholder('{"cs": {"label": "...", "label_plural": "...", "description": "..."}}')
                            ->rules(['json'])
                            ->helperText('Languages the site is not read in, and anything that is not label / label_plural / description, are ignored.'),
                    ])
                    ->action(function (MarketplaceWorkType $record, array $data) {
                        $result = $record->applyTranslations($data['json']);

                        if ($result === null) {
                            Notification::make()
                                ->danger()
                                ->title('Nothing usable in that JSON')
                                ->body('Check it is an object of locale => fields, and that the locales are ones the site is read in.')
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->success()
                            ->title('Saved')
                            ->body('"' . $record->label . '" now reads in ' . $result . ' language(s).')
                            ->send();
                    }),

                Tables\Actions\EditAction::make(),

                Tables\Actions\DeleteAction::make()
                    // A type still on a listing cannot go: the listing would
                    // be left showing a slug nobody can read.
                    ->visible(fn (MarketplaceWorkType $record): bool => !$record->is_core && $record->listings()->count() === 0),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMarketplaceWorkTypes::route('/'),
            'create' => Pages\CreateMarketplaceWorkType::route('/create'),
            'edit' => Pages\EditMarketplaceWorkType::route('/{record}/edit'),
        ];
    }
}
