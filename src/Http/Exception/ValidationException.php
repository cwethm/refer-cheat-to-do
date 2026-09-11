<?php
declare(strict_types=1);

namespace App\Http\Exception;

use Cake\Http\Exception\HttpException;

class ValidationException extends HttpException
{
    /**
     * Default HTTP status code for validation errors.
     *
     * @var int
     */
    protected int $_defaultCode = 422;
}
