<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Inertia\Inertia;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        // Handle HTTP errors (403, 404, 500, etc.) with Inertia error page
        $this->renderable(function (HttpException $e, $request) {
            $status = $e->getStatusCode();

            // Only handle specific error codes
            if (in_array($status, [403, 404, 500, 503])) {
                return Inertia::render('Errors/Error', [
                    'status' => $status,
                ])
                ->toResponse($request)
                ->setStatusCode($status);
            }
        });
    }
}
