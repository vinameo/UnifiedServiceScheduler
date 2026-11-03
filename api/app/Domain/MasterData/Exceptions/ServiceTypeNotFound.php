<?php

namespace App\Domain\MasterData\Exceptions;

use App\Support\Problem\ProblemException;

class ServiceTypeNotFound extends ProblemException
{
    public function __construct()
    {
        parent::__construct(
            status: 404,
            problemCode: 'SERVICE_TYPE_NOT_FOUND',
            title: 'Service type not found.',
        );
    }
}

