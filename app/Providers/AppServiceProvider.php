<?php

namespace App\Providers;

use App\Contracts\ChangeRequest\ChangeRequestRepositoryInterface;
use App\Http\Repository\ChangeRequest\ChangeRequestRepository;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(ChangeRequestRepositoryInterface::class, ChangeRequestRepository::class);
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
        // URL::forceRootUrl('https://10.19.44.26/index.php'); // replace with your server IP
        Paginator::useBootstrap();
        Paginator::defaultView('pagination.modern');
        Schema::defaultstringLength(191);
    }
}
