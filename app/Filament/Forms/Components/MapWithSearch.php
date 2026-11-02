<?php

namespace App\Filament\Forms\Components;

use Filament\Forms\Components\Field;

class MapWithSearch extends Field
{
    protected string $view = 'filament.forms.components.map-with-search';

    protected function setUp(): void
    {
        parent::setUp();

        $this->default([
            'lat' => -6.894561,
            'lng' => 110.637492,
        ]);
    }
}
