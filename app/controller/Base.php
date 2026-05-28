<?php

declare(strict_types=1);

namespace App\Controller;

use App\Traits\DatabaseValueNormalizer;
use App\Traits\Response;
use App\Traits\Template;

abstract class Base
{
    use Template, Response, DatabaseValueNormalizer;
}


