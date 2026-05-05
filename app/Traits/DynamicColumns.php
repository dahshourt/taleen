<?php

namespace App\Traits;

use Illuminate\Support\Facades\Schema;

trait DynamicColumns
{
    public function getNameColumn(): ?string
    {
        $columns = $this->getModelColumns();

        $model_name = $this->getModelName();

        $possibilities = ['title', 'name'];

        foreach ($possibilities as $possibility) {
            $possibilities[] = "{$model_name}_$possibility";
        }

        return collect($possibilities)
            ->first(fn ($column) => in_array($column, $columns, true));
    }

    public function getNameAttribute(): mixed
    {
        return $this->attributes[$this->getNameColumn()] ?? null;
    }

    protected function getModelColumns(): array
    {
        return Schema::getColumnListing($this->getTable());
    }

    protected function getModelName(): string
    {
        return strtolower(class_basename(self::class));
    }
}
