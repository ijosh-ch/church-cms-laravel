<?php

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * This namespace is applied to your controller routes.
     *
     * In addition, it is set as the URL generator's root namespace.
     *
     * @var string
     */
    protected $namespace = 'App\Http\Controllers';
    protected $adminNamespace = 'App\Http\Controllers\Admin';

    /**
     * Define your route model bindings, pattern filters, etc.
     *
     * @return void
     */
    public function boot()
    {
        /*
         | 🔴 {member} binds on ifgf_members.public_ref, which is a real `uuid` column on
         | PostgreSQL. An implicit-binding lookup with a non-UUID segment is a TYPE ERROR
         | there, not a miss - it aborts the request's transaction (SQLSTATE 25P02) and
         | the page 500s on input a user controls. Constraining the segment turns that
         | into a clean 404 before any query runs. MySQL hid this by string-comparing.
        */
        Route::pattern('member', '[0-9a-fA-F-]{36}');

        parent::boot();
    }

    /**
     * Define the routes for the application.
     *
     * @return void
     */
    public function map()
    {
        // 🔴 FIRST, and it must stay first. routes/web.php registers paths such as '/'
        // with no domain constraint, so they match ANY hostname the app answers on —
        // attend.ifgf.site included. Registering the usher routes ahead of them is what
        // makes the usher site a distinct site rather than the member site with extra
        // pages bolted on.
        $this->mapUsherRoutes();

        $this->mapMemberAppRoutes();

        $this->mapIfgfSharedRoutes();

        $this->mapApiRoutes();

        $this->mapWebRoutes();

        $this->mapAdminRoutes();


        //
    }

    /**
     * Define the routes for the usher site (attend.ifgf.site).
     *
     * Falls back to a URL prefix when no domain is configured, so the flow is reachable in
     * local development without editing a hosts file.
     */
    protected function mapUsherRoutes()
    {
        $domain = config('ifgf-sites.usher_domain');

        $route = Route::middleware('ifgf')->namespace($this->namespace);

        $route = $domain
            ? $route->domain($domain)
            : $route->prefix(config('ifgf-sites.usher_prefix'));

        $route->group(base_path('routes/usher.php'));
    }

    /**
     * Define the routes for the member site (member.ifgf.site).
     *
     * Falls back to the /app prefix locally. Deliberately NOT /member — routes/web.php
     * already owns /member/* in the upstream fork, and colliding with it would make which
     * controller runs depend on registration order.
     */
    protected function mapMemberAppRoutes()
    {
        $domain = config('ifgf-sites.member_domain');

        $route = Route::middleware('ifgf')->namespace($this->namespace);

        $route = $domain
            ? $route->domain($domain)
            : $route->prefix('app');

        $route->group(base_path('routes/member-app.php'));
    }

    /**
     * Routes shared by both IFGF sites.
     *
     * Registered without a domain constraint on purpose: the language switcher lives in
     * the shared layout, so it must resolve on member.ifgf.site AND attend.ifgf.site.
     * Naming it once here avoids a duplicate route name, which Laravel resolves silently
     * and confusingly in favour of the last registration.
     */
    protected function mapIfgfSharedRoutes()
    {
        // The FQCN, not a namespace-relative string: an invokable controller given as a
        // bare string under ->namespace() is parsed as a "Controller@method" pair and
        // blows up in RouteAction::parse.
        Route::middleware('ifgf')
            ->get('/ifgf/locale', \App\Http\Controllers\Ifgf\LocaleController::class)
            ->name('ifgf.locale');
    }

    /**
     * Define the "web" routes for the application.
     *
     * These routes all receive session state, CSRF protection, etc.
     *
     * @return void
     */
    protected function mapWebRoutes()
    {
        Route::middleware('web')
             ->namespace($this->namespace)
             ->group(base_path('routes/web.php'));
    }

    /**
     * Define the "api" routes for the application.
     *
     * These routes are typically stateless.
     *
     * @return void
     */
    protected function mapApiRoutes()
    {
        Route::prefix('api')
             ->middleware('api')
             ->namespace($this->namespace)
             ->group(base_path('routes/api.php'));
    }



    protected function mapAdminRoutes()  
    {
        Route::prefix('admin')
            ->middleware(['web','auth','churchadmin'])
            ->namespace($this->adminNamespace)
            ->group(base_path('routes/admin.php'));
    }

}
