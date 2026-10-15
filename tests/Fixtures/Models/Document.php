<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use JustSteveKing\Toolbox\ConditionalRequests\Concerns\HasModelValidators;
use JustSteveKing\Toolbox\ConditionalRequests\Contracts\HasValidators;

/**
 * @property int $id
 * @property string $title
 */
final class Document extends Model implements HasValidators
{
    use HasModelValidators;

    protected $guarded = [];
}
