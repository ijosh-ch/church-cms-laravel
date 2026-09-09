<?php

namespace App\Http;

use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    /**
     * The application's global HTTP middleware stack.
     *
     * These middleware are run during every request to your application.
     *
     * @var array
     */
    protected $middleware = [
        \App\Http\Middleware\CheckForMaintenanceMode::class,
        \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,
        \App\Http\Middleware\TrimStrings::class,
        \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
        \App\Http\Middleware\TrustProxies::class,
        \Illuminate\Session\Middleware\StartSession::class,
        \Nckg\Impersonate\Impersonate::class,
        \App\Http\Middleware\SecureHeaders::class,
        \App\Http\Middleware\CheckMaintenanceMode::class

    ];

    /**
     * The application's route middleware groups.
     *
     * @var array
     */
    protected $middlewareGroups = [
        'web' => [
            \App\Http\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            //\Illuminate\Session\Middleware\StartSession::class,
            // \Illuminate\Session\Middleware\AuthenticateSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \App\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \App\Http\Middleware\WebCmsContext::class,
        ],

        /*
        | The IFGF sites (member.ifgf.site and attend.ifgf.site).
        |
        | Everything 'web' has EXCEPT WebCmsContext, which calls Church::first() to
        | bootstrap the upstream CMS. The ifgf_ schema is deliberately decoupled from the
        | fork, so on the PostgreSQL database that table does not exist — and in PostgreSQL
        | a failed statement aborts the whole request transaction (SQLSTATE 25P02), so
        | every later query fails too and the page 500s. MySQL hid it: the upstream tables
        | happened to be in the same database.
        |
        | Beyond the bug, these are separate sites and have no business booting the fork's
        | CMS context on every request.
        |
        | StartSession is not listed because it is in the GLOBAL stack above.
        */
        'ifgf' => [
            \App\Http\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \App\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \App\Http\Middleware\SetIfgfLocale::class,
        ],

        'api' => [
            EnsureFrontendRequestsAreStateful::class,
            'throttle:60,1',
            'bindings',
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],
    ];

    /**
     * The application's route middleware.
     *
     * These middleware may be assigned to groups or used individually.
     *
     * @var array
     */
    protected $routeMiddleware = [
        'auth' => \Illuminate\Auth\Middleware\Authenticate::class,
        'auth.basic' => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
        'bindings' => \Illuminate\Routing\Middleware\SubstituteBindings::class,
        'cache.headers' => \Illuminate\Http\Middleware\SetCacheHeaders::class,
        'can' => \Illuminate\Auth\Middleware\Authorize::class,
        'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
        'signed' => \Illuminate\Routing\Middleware\ValidateSignature::class,
        'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
        'churchadmin' => \App\Http\Middleware\MustBeChurchAdmin::class,
        'auth.usher' => \App\Http\Middleware\EnsureUsher::class,
        'ifgf.locale' => \App\Http\Middleware\SetIfgfLocale::class,
        'churchmember' => \App\Http\Middleware\MustBeChurchMember::class,
        'admingroup' => \App\Http\Middleware\AdminOnly::class,
        'permission' => \App\Http\Middleware\AdminOrPermission::class,
        'webguest'   => \App\Http\Middleware\WebGuestAuth::class,
    ];
}
