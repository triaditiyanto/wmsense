<?php

namespace App\Providers;

use App\Models\User;
use App\Support\Menu;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Di luar production: lempar error untuk lazy loading (N+1), atribut yang
        // tidak ada, dan mass-assignment ke kolom yang tidak ada di $fillable.
        Model::shouldBeStrict(! $this->app->isProduction());

        // Gate "access": di Blade -> @can('access', ['INB_RECEIPT', 'create']) ... @endcan
        Gate::define('access', fn (User $user, string $code, string $action = 'view') => $user->hasAccess($code, $action));

        Paginator::useBootstrapFive();

        // View composer: variabel $menu otomatis tersedia di layout tiap kali layout dirender
        View::composer(['layouts.app', 'dashboard'], function ($view) {
            // once(): dihitung sekali per request walau composer dipanggil untuk 2 view
            $menu = once(fn () => auth()->check() ? Menu::for(auth()->user()) : collect());
            $view->with('menu', $menu)->with('activeModule', Menu::active($menu));
        });
    }
}
