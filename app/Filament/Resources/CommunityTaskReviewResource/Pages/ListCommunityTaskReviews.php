<?php

namespace App\Filament\Resources\CommunityTaskReviewResource\Pages;

use App\Filament\Resources\CommunityTaskReviewResource;
use Filament\Resources\Pages\ListRecords;

class ListCommunityTaskReviews extends ListRecords
{
    protected static string $resource = CommunityTaskReviewResource::class;

    protected ?string $heading = 'Community Task Reviews';

    protected ?string $subheading = 'Review demos flagged by community consensus (3x not sure, 3x no match, 1x unassign)';
}
