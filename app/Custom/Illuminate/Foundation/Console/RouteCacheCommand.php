<?php

declare(strict_types=1);

namespace App\Custom\Illuminate\Foundation\Console;

use App\Custom\Illuminate\Routing\RouteCollection as CustomRouteCollection;
use Illuminate\Routing\Route;
use Illuminate\Routing\RouteCollection;
use Illuminate\Foundation\Console\RouteCacheCommand as BaseCommand;

class RouteCacheCommand extends BaseCommand
{

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $this->call('route:clear');

        $routes = $this->getFreshApplicationRoutes();

        if (count($routes) === 0) {
            $this->error("Your application doesn't have any routes.");
            return;
        }

        $this->files->put(
            $this->laravel->getCachedRoutesPath(), $this->buildRouteCacheFile($routes)
        );

        $this->info('Routes cached successfully!');
        return;
    }

    protected function buildRouteCacheFile(RouteCollection $base)
    {
        $code = '<?php' . PHP_EOL . PHP_EOL;
        $code .= 'return [' . PHP_EOL;


        $stub = '    \'{{key}}\' => function() {return unserialize(base64_decode(\'{{routes}}\'));},';
        foreach (config('app.supported_locales') as $locale) {
            /** @var CustomRouteCollection|Route[] $routes */
            $routes = clone $base;
            $routes->localize($locale);
            foreach ($routes as $route) {
                $route->prepareForSerialization();
            }
            $line = str_replace('{{routes}}', base64_encode(serialize($routes)), $stub);
            $line = str_replace('{{key}}', $locale, $line);
            $code .= $line . PHP_EOL;
        }
        $code .= '];' . PHP_EOL;
        return $code;
    }
}
