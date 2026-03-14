<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$resource = \App\Filament\Resources\DonationResource::class;

echo "Should Register Navigation: " . ($resource::shouldRegisterNavigation() ? 'YES' : 'NO') . PHP_EOL;
echo "Navigation Label: " . $resource::getNavigationLabel() . PHP_EOL;
echo "Navigation Icon: " . $resource::getNavigationIcon() . PHP_EOL;
echo "Navigation Sort: " . $resource::getNavigationSort() . PHP_EOL;
echo "Navigation Group: " . ($resource::getNavigationGroup() ?? 'NULL') . PHP_EOL;
