<?php

namespace App\Domain\MasterData\Exceptions;

use App\Support\Problem\ProblemException;

class TechnicianNotFound extends ProblemException
{
    public function __construct()
    {
        parent::__construct(
            status: 404,
            problemCode: 'TECHNICIAN_NOT_FOUND',
            title: 'Technician not found.',
        );
    }
}
