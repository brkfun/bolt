<?php

namespace LaraZeus\Bolt\Filament\Resources;

use Closure;
use Filament\Forms\Form;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ForceDeleteAction;
use Filament\Tables\Actions\ForceDeleteBulkAction;
use Filament\Tables\Actions\RestoreAction;
use Filament\Tables\Actions\RestoreBulkAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use LaraZeus\Bolt\BoltPlugin;
use LaraZeus\Bolt\Concerns\HasOptions;
use LaraZeus\Bolt\Concerns\Schemata;
use LaraZeus\Bolt\Filament\Actions\ReplicateFormAction;
use LaraZeus\Bolt\Filament\Resources\FormResource\Pages;
use LaraZeus\Bolt\Filament\Resources\ResponseResource\Pages\ListResponses;
use LaraZeus\Bolt\Models\Form as ZeusForm;

class FormResource extends BoltResource
{
    use HasOptions;
    use Schemata;

    protected static ?string $navigationIcon = 'clarity-form-line';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    protected static Closure | array | null $boltFormSchema = null;

    public static function getModel(): string
    {
        return BoltPlugin::getModel('Form');
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) BoltPlugin::getModel('Form')::query()->count();
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'slug'];
    }

    public static function getModelLabel(): string
    {
        return __('Form');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Forms');
    }

    public static function getNavigationLabel(): string
    {
        return __('Forms');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make()->schema([
                    TextEntry::make('name'),
                    TextEntry::make('slug')
                        ->url(fn (ZeusForm $record) => route('bolt.form.show', ['slug' => $record->slug]))
                        ->openUrlInNewTab(),
                    TextEntry::make('description'),
                    IconEntry::make('is_active')
                        ->icon(fn (string $state): string => match ($state) {
                            '1' => 'clarity-check-circle-line',
                            '0' => 'clarity-times-circle-solid',
                            default => 'clarity-check-circle-line',
                        })
                        ->color(fn (string $state): string => match ($state) {
                            '0' => 'warning',
                            '1' => 'success',
                            default => 'gray',
                        }),

                    TextEntry::make('start_date')->dateTime(),
                    TextEntry::make('end_date')->dateTime(),
                ])
                    ->columns(2),
            ]);
    }

    public static function form(Form $form): Form
    {
        return $form->schema(static::$boltFormSchema ?? static::getMainFormSchema());
    }

    public function getBoltFormSchema(): array | Closure | null
    {
        return static::$boltFormSchema;
    }

    public static function getBoltFormSchemaUsing(array | Closure | null $form): void
    {
        static::$boltFormSchema = $form;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('ordering')
            ->columns([
                TextColumn::make('name')->searchable()->sortable()->label(__('Form Name'))->toggleable(),
                TextColumn::make('category.name')->searchable()->label(__('Category'))->sortable()->toggleable(),
                IconColumn::make('is_active')->boolean()->label(__('Is Active'))->sortable()->toggleable(),
                TextColumn::make('start_date')->dateTime()->searchable()->sortable()->label(__('Start Date'))->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('end_date')->dateTime()->searchable()->sortable()->label(__('End Date'))->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('responses_exists')->boolean()->exists('responses')->label(__('Responses Exists'))->sortable()->toggleable(),
                TextColumn::make('responses_count')->counts('responses')->label(__('Responses Count'))->sortable()->toggleable(),
            ])
            ->actions(static::getActions())
            ->filters([
                TrashedFilter::make(),
                Filter::make('is_active')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->where('is_active', true))
                    ->label(__('Is Active')),

                Filter::make('not_active')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->where('is_active', false))
                    ->label(__('Inactive')),

                SelectFilter::make('category_id')
                    ->options(BoltPlugin::getModel('Category')::pluck('name', 'id'))
                    ->label(__('Category')),
            ])
            ->bulkActions([
                DeleteBulkAction::make(),
                ForceDeleteBulkAction::make(),
                RestoreBulkAction::make(),
            ]);
    }

    /** @phpstan-return Builder<ZeusForm> */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getPages(): array
    {
        $pages = [
            'index' => Pages\ListForms::route('/'),
            'create' => Pages\CreateForm::route('/create'),
            'edit' => Pages\EditForm::route('/{record}/edit'),
            'view' => Pages\ViewForm::route('/{record}'),
        ];

        if (class_exists(\LaraZeus\BoltPro\BoltProServiceProvider::class)) {
            //@phpstan-ignore-next-line
            $pages['prefilled'] = \LaraZeus\BoltPro\Livewire\PrefilledForm::route('/{record}/prefilled');
        }

        return $pages;
    }

    public static function getWidgets(): array
    {
        $widgets = [
            FormResource\Widgets\FormOverview::class,
            FormResource\Widgets\ResponsesPerMonth::class,
            FormResource\Widgets\ResponsesPerStatus::class,
            FormResource\Widgets\ResponsesPerFields::class,
        ];

        if (class_exists(\LaraZeus\BoltPro\BoltProServiceProvider::class)) {
            //@phpstan-ignore-next-line
            $widgets[] = \LaraZeus\BoltPro\Widgets\ResponsesPerCollection::class;
        }

        return $widgets;
    }

    public static function getActions(): array
    {
        $action = [
            ViewAction::make(),
            EditAction::make('edit'),
            Action::make('entries')
                ->color('info')
                ->label(__('Entries'))
                ->icon('clarity-data-cluster-line')
                ->tooltip(__('view all entries'))
                ->url(fn (ZeusForm $record): string => ListResponses::getUrl(['form_id' => $record->id])),
            Action::make('prefilledLink')
                ->label(__('Prefilled Link'))
                ->icon('heroicon-o-link')
                ->tooltip(__('Get Prefilled Link'))
                ->visible(class_exists(\LaraZeus\BoltPro\BoltProServiceProvider::class))
                ->url(fn (ZeusForm $record): string => FormResource::getUrl('prefilled', ['record' => $record])),
            ReplicateFormAction::make(),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];

        if (class_exists(\LaraZeus\Helen\HelenServiceProvider::class)) {
            //@phpstan-ignore-next-line
            $action[] = \LaraZeus\Helen\Actions\ShortUrlAction::make('get-link')
                ->distUrl(fn (ZeusForm $record) => route('bolt.form.show', $record));
        }

        return [ActionGroup::make($action)];
    }
}
