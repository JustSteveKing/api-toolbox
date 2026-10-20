<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use JustSteveKing\Toolbox\ConditionalRequests\Concerns\HasModelValidators;
use JustSteveKing\Toolbox\ConditionalRequests\Contracts\HasValidators;

/**
 * @property int $id
 * @property string $name
 */
final class Folder extends Model implements HasValidators
{
    use HasModelValidators;

    protected $guarded = [];
}
