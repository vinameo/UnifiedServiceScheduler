<?php

namespace App\Domain\MasterData\Exceptions;

use App\Support\Problem\ProblemException;

class DealershipNotFound extends ProblemException
{
    public function __construct()
    {
        parent::__construct(
            status: 404,
            problemCode: 'DEALERSHIP_NOT_FOUND',
            title: 'Dealership not found.',
        );
    }
}
